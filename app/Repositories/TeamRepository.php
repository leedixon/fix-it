<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Auth;
use FixListed\Core\Database;

/**
 * The people who run the site.
 *
 * Deliberately **not** a Repository subclass, and so not market-scoped. A
 * superadmin belongs to no market — that is what makes them a superadmin —
 * so a query that insisted on market_id would return a team with its owner
 * missing. The scoping that does apply is by role, and it is in every query
 * here rather than left to the caller.
 *
 * Staff accounts are never given a password by whoever creates them. They are
 * created with password_hash NULL and reached through an emailed link they
 * set themselves, so nobody but the person ever knows their password and
 * there is no "temporary password" to be reused somewhere else or left in a
 * chat thread.
 */
final class TeamRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Everyone with a staff role, owner first. @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->all(
            "SELECT u.id, u.email, u.role, u.status, u.first_name, u.last_name,
                    u.created_at, u.last_login_at, u.invited_at, u.accepted_at,
                    u.password_hash IS NOT NULL AS has_password,
                    m.name AS market_name,
                    inv.email AS invited_by_email,
                    (SELECT COUNT(*) FROM password_resets r
                      WHERE r.user_id = u.id AND r.used_at IS NULL AND r.expires_at > NOW())
                      AS live_invites
               FROM users u
               LEFT JOIN markets m ON m.id = u.market_id
               LEFT JOIN users inv ON inv.id = u.invited_by
              WHERE u.role IN ('superadmin','market_admin','moderator')
                AND u.status <> 'deleted'
              ORDER BY FIELD(u.role, 'superadmin','market_admin','moderator'), u.id"
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one(
            "SELECT * FROM users
              WHERE id = :id AND role IN ('superadmin','market_admin','moderator')
              LIMIT 1",
            ['id' => $id],
        );
    }

    /** An account with this email, whatever its role. @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->one(
            'SELECT id, email, role, status, first_name, last_name FROM users WHERE email = :e LIMIT 1',
            ['e' => mb_strtolower(trim($email))],
        );
    }

    /**
     * Creates a staff account with no password.
     *
     * market_id is NULL for a superadmin and set for everybody else, which is
     * the one structural difference between the roles: a superadmin is not
     * *in* a market, they own all of them.
     */
    public function invite(
        string $email,
        string $firstName,
        string $lastName,
        string $role,
        int $marketId,
        int $invitedBy,
    ): int {
        return $this->db->insert(
            "INSERT INTO users (market_id, role, email, password_hash, first_name, last_name,
                                status, invited_by, invited_at, email_verified_at)
             VALUES (:market, :role, :email, NULL, :first, :last, 'active', :by, NOW(), NULL)",
            [
                'market' => $role === Auth::ROLE_SUPER ? null : $marketId,
                'role'   => $role,
                'email'  => mb_strtolower(trim($email)),
                'first'  => trim($firstName),
                'last'   => trim($lastName),
                'by'     => $invitedBy,
            ],
        );
    }

    /**
     * Promotes an account that already exists — a tradesperson who is also
     * going to help run the site, say.
     *
     * Their password is left alone. They already have one and it already
     * works; wiping it to force a "set your password" link would lock them
     * out of the account they have been using.
     */
    public function promote(int $userId, string $role, int $marketId, int $invitedBy): void
    {
        $this->db->affected(
            'UPDATE users
                SET role = :role,
                    market_id = IF(:role2 = \'superadmin\', NULL, COALESCE(market_id, :market)),
                    invited_by = COALESCE(invited_by, :by),
                    invited_at = COALESCE(invited_at, NOW())
              WHERE id = :id',
            ['role' => $role, 'role2' => $role, 'market' => $marketId, 'by' => $invitedBy, 'id' => $userId],
        );
    }

    public function setRole(int $userId, string $role, int $marketId): void
    {
        $this->db->affected(
            'UPDATE users
                SET role = :role,
                    market_id = IF(:role2 = \'superadmin\', NULL, COALESCE(market_id, :market))
              WHERE id = :id',
            ['role' => $role, 'role2' => $role, 'market' => $marketId, 'id' => $userId],
        );
    }

    /**
     * Suspends or restores. Any outstanding invite dies with the suspension.
     *
     * Leaving a live invite on a suspended account means the link in their
     * inbox still works — the account is switched off and the way back in is
     * not.
     */
    public function setStatus(int $userId, string $status): void
    {
        $this->db->affected(
            'UPDATE users SET status = :status WHERE id = :id',
            ['status' => $status, 'id' => $userId],
        );

        if ($status !== 'active') {
            $this->db->affected(
                'UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL',
                ['id' => $userId],
            );
        }
    }

    /**
     * Removes a person. Marked deleted, not deleted.
     *
     * Their audit trail points at this row: who approved which application,
     * who removed which job. A real DELETE would either cascade that history
     * away or leave it pointing at nothing, and "who let this listing
     * through" is a question that outlives the person who answered it.
     *
     * The email is released so the address can be used again, and the
     * password is cleared so the old one cannot sign in.
     */
    public function remove(int $userId): void
    {
        $this->db->affected(
            "UPDATE users
                SET status = 'deleted',
                    password_hash = NULL,
                    role = 'homeowner',
                    email = CONCAT('deleted+', id, '@fixlisted.invalid'),
                    first_name = '', last_name = '', phone = ''
              WHERE id = :id",
            ['id' => $userId],
        );

        $this->db->affected(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL',
            ['id' => $userId],
        );
    }

    /**
     * How many superadmins can still sign in.
     *
     * Every destructive path checks this first. Locking the last owner out of
     * the platform is a mistake with no way back through the interface — it
     * needs SSH and the command line — so the interface refuses to make it.
     */
    public function activeSuperadmins(int $excludingUserId = 0): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM users
              WHERE role = 'superadmin' AND status = 'active'
                AND password_hash IS NOT NULL
                AND id <> :exclude",
            ['exclude' => $excludingUserId],
        );
    }

    /** Records that an invited person has set their password and arrived. */
    public function markAccepted(int $userId): void
    {
        $this->db->affected(
            'UPDATE users SET accepted_at = COALESCE(accepted_at, NOW()) WHERE id = :id',
            ['id' => $userId],
        );
    }
}
