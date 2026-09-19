<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Authentication and the role checks the whole app hangs off.
 *
 * Roles, narrowest to widest:
 *   homeowner     — posts jobs, reads quotes on their own jobs
 *   pro           — quotes jobs, manages one profile and its advertising
 *   market_admin   — everything inside one market
 *   superadmin    — every market, plus market creation and billing
 */
final class Auth
{
    public const ROLE_HOMEOWNER = 'homeowner';
    public const ROLE_PRO       = 'pro';
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

    /** Superadmins pass for any market; everyone else only for their own. */
    public function canAdminister(int $marketId): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }
        return $this->is(self::ROLE_ADMIN) && $this->marketId() === $marketId;
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
}
