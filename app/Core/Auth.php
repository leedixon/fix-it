<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Authentication, and the capability checks the whole app hangs off.
 *
 * Roles, narrowest to widest:
 *   homeowner     — posts jobs, reads quotes on their own jobs
 *   pro           — quotes jobs, manages one profile and its advertising
 *   moderator     — the queue: applications, listings, jobs. Staff.
 *   market_admin  — everything inside one market, bar money. Staff.
 *   superadmin    — every market, plus money, the team, and deletion
 *
 * **Ask what somebody can do, not what they are.** can('finance.view') says
 * what the code actually depends on; is(ROLE_SUPER) says what happens to be
 * true today, and is the line that gets missed when a fourth role arrives.
 * CAPABILITIES below is the whole permission model, in one readable table —
 * the point being that you can audit it by reading it.
 *
 * The line that matters most is between superadmin and everyone else. Money,
 * the team, and deleting an account are the three things a staff member
 * cannot do, and they are grouped at the bottom of the table for that reason.
 */
final class Auth
{
    public const ROLE_HOMEOWNER = 'homeowner';
    public const ROLE_PRO       = 'pro';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_ADMIN     = 'market_admin';
    public const ROLE_SUPER     = 'superadmin';

    private const SESSION_KEY = 'user_id';
    private const LOCK_ATTEMPTS = 5;
    private const LOCK_MINUTES  = 15;

    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $id = Session::get(self::SESSION_KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return $this->user = null;
        }

        $row = $this->db->one(
            'SELECT id, market_id, role, email, first_name, last_name, status
               FROM users WHERE id = :id AND status = :status LIMIT 1',
            ['id' => (int) $id, 'status' => 'active'],
        );

        // A user suspended mid-session stops being signed in on their next
        // request, rather than at their next login.
        return $this->user = $row;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    public function role(): ?string
    {
        $user = $this->user();
        return $user['role'] ?? null;
    }

    /** The market this user belongs to. Null for superadmins, who belong to none. */
    public function marketId(): ?int
    {
        $user = $this->user();
        return isset($user['market_id']) ? (int) $user['market_id'] : null;
    }

    public function is(string ...$roles): bool
    {
        $role = $this->role();
        return $role !== null && in_array($role, $roles, true);
    }

    public function isSuperadmin(): bool
    {
        return $this->is(self::ROLE_SUPER);
    }

    /** Everyone who gets through the /admin door at all. */
    public const STAFF_ROLES = [self::ROLE_SUPER, self::ROLE_ADMIN, self::ROLE_MODERATOR];

    /**
     * Who can do what. The whole permission model.
     *
     * @var array<string,array<int,string>>
     */
    private const CAPABILITIES = [
        // --- the day job. All staff. ---------------------------------------
        'admin.access'        => self::STAFF_ROLES,
        'applications.review' => self::STAFF_ROLES,
        'listings.moderate'   => self::STAFF_ROLES,   // suspend and restore
        'jobs.moderate'       => self::STAFF_ROLES,   // take off the board
        'activity.view'       => self::STAFF_ROLES,

        // --- running the market. Managers and up. --------------------------
        // A moderator works the queue. Somebody's phone number and sign-in
        // history is not part of the queue.
        'people.view'         => [self::ROLE_SUPER, self::ROLE_ADMIN],
        'licensing.manage'    => [self::ROLE_SUPER, self::ROLE_ADMIN],
        'placements.view'     => [self::ROLE_SUPER, self::ROLE_ADMIN],

        // --- the owner's own. Superadmin only. -----------------------------
        // Three things no staff member does, however senior: see or move
        // money, change who is on the team, or erase a person.
        'finance.view'        => [self::ROLE_SUPER],  // revenue, what a plan earns
        'finance.manage'      => [self::ROLE_SUPER],  // prices and inventory caps
        'team.manage'         => [self::ROLE_SUPER],
        'users.delete'        => [self::ROLE_SUPER],
        'markets.manage'      => [self::ROLE_SUPER],
        'maintenance.manage'  => [self::ROLE_SUPER],
    ];

    /**
     * Whether the signed-in person may do this.
     *
     * An unknown capability is false, never true. A typo in a template should
     * hide a button, not expose one — the failure has to land on the safe
     * side, because the unsafe side is silent.
     */
    public function can(string $capability): bool
    {
        $role = $this->role();
        if ($role === null) {
            return false;
        }
        return in_array($role, self::CAPABILITIES[$capability] ?? [], true);
    }

    /** Staff, of any rank. */
    public function isStaff(): bool
    {
        return $this->is(...self::STAFF_ROLES);
    }

    /** What to call a role in front of a person. */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            self::ROLE_SUPER     => 'Superadmin',
            self::ROLE_ADMIN     => 'Manager',
            self::ROLE_MODERATOR => 'Moderator',
            self::ROLE_PRO       => 'Tradesperson',
            self::ROLE_HOMEOWNER => 'Homeowner',
            default              => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    /** One line on what a staff role is for, shown wherever one is chosen. */
    public static function roleBlurb(string $role): string
    {
        return match ($role) {
            self::ROLE_SUPER     => 'Everything, including money, the team and deleting accounts.',
            self::ROLE_ADMIN     => 'Runs the site day to day: applications, listings, jobs, people, '
                                  . 'licensing. Cannot see money or manage the team.',
            self::ROLE_MODERATOR => 'Works the queue: approves applications, suspends listings, '
                                  . 'removes jobs. Nothing else.',
            default              => '',
        };
    }

    /** Superadmins pass for any market; everyone else only for their own. */
    public function canAdminister(int $marketId): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }
        return $this->is(self::ROLE_ADMIN, self::ROLE_MODERATOR) && $this->marketId() === $marketId;
    }

    /**
     * @return array{ok:bool,reason:string} ok=true on success. The reason is
     *         for logs; never show it to the visitor, because "no such email"
     *         versus "wrong password" tells an attacker which accounts exist.
     */
    public function attempt(string $email, string $password, string $ip = ''): array
    {
        $user = $this->db->one(
            'SELECT id, password_hash, status, failed_logins, locked_until
               FROM users WHERE email = :email LIMIT 1',
            ['email' => strtolower(trim($email))],
        );

        if ($user === null) {
            // Hash anyway, so a missing account does not answer faster than a
            // wrong password and reveal itself by timing.
            password_verify($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1MlVkd.');
            return ['ok' => false, 'reason' => 'no_such_user'];
        }

        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            return ['ok' => false, 'reason' => 'locked'];
        }

        if ($user['status'] !== 'active') {
            return ['ok' => false, 'reason' => 'inactive'];
        }

        if (!is_string($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            $this->recordFailure((int) $user['id'], (int) $user['failed_logins']);
            return ['ok' => false, 'reason' => 'bad_password'];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $this->db->affected(
                'UPDATE users SET password_hash = :hash WHERE id = :id',
                ['hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => (int) $user['id']],
            );
        }

        $this->db->affected(
            'UPDATE users
                SET failed_logins = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = :ip
              WHERE id = :id',
            ['ip' => $ip !== '' ? inet_pton($ip) : null, 'id' => (int) $user['id']],
        );

        Session::regenerate();
        Session::put(self::SESSION_KEY, (int) $user['id']);
        $this->loaded = false;

        return ['ok' => true, 'reason' => ''];
    }

    private function recordFailure(int $userId, int $current): void
    {
        $attempts = $current + 1;
        $lockUntil = $attempts >= self::LOCK_ATTEMPTS
            ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)
            : null;

        $this->db->affected(
            'UPDATE users SET failed_logins = :attempts, locked_until = :lock WHERE id = :id',
            ['attempts' => $attempts, 'lock' => $lockUntil, 'id' => $userId],
        );
    }

    public function logout(): void
    {
        Session::destroy();
        $this->user = null;
        $this->loaded = true;
    }

    /**
     * Signs the user out but keeps the session.
     *
     * For the case where a real account authenticates and then turns out not
     * to be allowed here: they must not stay signed in, but something has to
     * survive to tell them why. Session::destroy() throws away the flash
     * message along with the login, so the person is bounced back to a blank
     * form with no idea what happened.
     */
    public function forgetUser(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();
        $this->user = null;
        $this->loaded = true;
    }
}
