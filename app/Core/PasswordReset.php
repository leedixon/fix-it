<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * One-time links for setting and resetting a password.
 *
 * The token is emailed in the clear and stored only as a SHA-256 hash, so a
 * copy of the database does not hand over working links to every account. It
 * is checked in constant time, single-use, and expires.
 *
 * Two lifetimes, because the two uses are not the same thing. An approval
 * email sits unread in a tradesperson's inbox over a weekend, so the invite it
 * carries lasts a fortnight. A reset the person asked for thirty seconds ago
 * lasts an hour.
 */
final class PasswordReset
{
    public const INVITE_DAYS  = 14;
    public const RESET_HOURS  = 1;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return string the raw token to put in the emailed link */
    public function issue(int $userId, bool $invite = false): string
    {
        // Not password_hash(): that is deliberately slow and salted, so it
        // cannot be looked up. A token is high-entropy already, and a plain
        // SHA-256 is what lets a unique index find the row.
        $token = bin2hex(random_bytes(32));

        // Computed by the database, not by PHP. resolve() checks it against
        // NOW(), so the same clock has to set it — otherwise a timezone
        // difference between the two silently expires every link the moment
        // it is created.
        $interval = $invite
            ? self::INVITE_DAYS . ' DAY'
            : self::RESET_HOURS . ' HOUR';

        // Outstanding links for this account stop working. Asking for a second
        // reset must invalidate the first, or an intercepted older email stays
        // usable.
        $this->db->affected(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL',
            ['id' => $userId],
        );

        $this->db->insert(
            "INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:id, :hash, NOW() + INTERVAL {$interval})",
            ['id' => $userId, 'hash' => hash('sha256', $token)],
        );

        return $token;
    }

    /**
     * The user this token belongs to, or null if it is unknown, used or stale.
     *
     * @return array<string,mixed>|null
     */
    public function resolve(string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }

        return $this->db->one(
            "SELECT r.id AS reset_id, u.id, u.email, u.first_name, u.role
               FROM password_resets r
               JOIN users u ON u.id = r.user_id
              WHERE r.token_hash = :hash
                AND r.used_at IS NULL
                AND r.expires_at > NOW()
                AND u.status = 'active'
              LIMIT 1",
            ['hash' => hash('sha256', $token)],
        );
    }

    /** Sets the password and spends the token, or does neither. */
    public function complete(int $resetId, int $userId, string $password): void
    {
        $this->db->transaction(function (Database $db) use ($resetId, $userId, $password): void {
            $db->affected(
                'UPDATE users
                    SET password_hash = :hash, failed_logins = 0, locked_until = NULL,
                        email_verified_at = COALESCE(email_verified_at, NOW())
                  WHERE id = :id',
                ['hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $userId],
            );
            // Marked used inside the same transaction: a token that set a
            // password but stayed valid would be reusable by anyone holding
            // the email.
            $db->affected(
                'UPDATE password_resets SET used_at = NOW() WHERE id = :id',
                ['id' => $resetId],
            );
        });
    }
}
