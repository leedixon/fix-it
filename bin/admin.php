<?php
declare(strict_types=1);

/**
 * Creates or resets an administrator account.
 *
 *   php bin/admin.php                  create or reset one, prompting
 *   php bin/admin.php --list           show the administrators that exist
 *
 * Exists because the seeded admin is demonstration data — it is flagged
 * is_demo and its password is printed in database/seed.sql, so it must not be
 * the account that runs the live site. This makes a real one without anybody
 * typing a password hash into SQL, and without the password reaching shell
 * history or the screen.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use FixListed\Core\Auth;
use FixListed\Core\Database;

$db = Database::fromConfig();

function ask(string $label, string $default = '', bool $hidden = false): string
{
    fwrite(STDOUT, $label . ($default !== '' ? " [{$default}]" : '') . ': ');
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        shell_exec('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    } else {
        $value = trim((string) fgets(STDIN));
    }
    return $value !== '' ? $value : $default;
}

$admins = $db->all(
    "SELECT id, email, role, status, is_demo, last_login_at
       FROM users WHERE role IN ('superadmin','market_admin') ORDER BY role, id"
);

echo "\nFix Listed — administrators\n\n";
if ($admins === []) {
    echo "  none yet\n\n";
} else {
    foreach ($admins as $a) {
        printf("  %-34s %-13s %s%s\n", $a['email'], $a['role'],
            $a['status'], $a['is_demo'] ? '  ** SAMPLE ACCOUNT — replace this **' : '');
    }
    echo "\n";
}

if (in_array('--list', $argv, true)) {
    exit(0);
}

$demoAdmins = array_filter($admins, static fn (array $a): bool => (bool) $a['is_demo']);
if ($demoAdmins !== []) {
    echo "A sample admin account is still in place. Its password is published in\n";
    echo "database/seed.sql, so anyone who has read this repository can sign in as it.\n";
    echo "Make a real one now, then delete the sample with bin/demo.php purge.\n\n";
}

$email = mb_strtolower(ask('Email for the administrator'));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "\nThat is not an email address. Nothing was changed.\n");
    exit(1);
}

$existing = $db->one('SELECT id, role, is_demo FROM users WHERE email = :e LIMIT 1', ['e' => $email]);
if ($existing !== null) {
    echo "\nThat account exists. This will reset its password and make it a superadmin.\n";
    if (strtolower(substr(ask('Continue? (y/n)', 'n'), 0, 1)) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
}

$first = ask('First name', 'Lee');
$last  = ask('Last name', 'Dixon');

echo "\nPick a password you do not use anywhere else. It is not shown as you type\n";
echo "and it is never written to disk in plain form.\n";
$pass  = ask('Password', '', true);
$again = ask('Again', '', true);

if ($pass !== $again) {
    fwrite(STDERR, "\nThose did not match. Nothing was changed.\n");
    exit(1);
}
// Long beats complicated: three unrelated words outlast a short password with
// a symbol in it, and people actually remember them.
if (mb_strlen($pass) < 12) {
    fwrite(STDERR, "\nUse at least 12 characters. Nothing was changed.\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

if ($existing !== null) {
    $db->affected(
        "UPDATE users
            SET password_hash = :hash, role = 'superadmin', status = 'active',
                is_demo = 0, failed_logins = 0, locked_until = NULL,
                first_name = :first, last_name = :last, email_verified_at = COALESCE(email_verified_at, NOW())
          WHERE id = :id",
        ['hash' => $hash, 'first' => $first, 'last' => $last, 'id' => (int) $existing['id']],
    );
    $id = (int) $existing['id'];
    echo "\nUpdated. That account is now a superadmin with the new password.\n";
} else {
    $id = $db->insert(
        "INSERT INTO users (market_id, role, email, password_hash, first_name, last_name, status, email_verified_at)
         VALUES (NULL, 'superadmin', :email, :hash, :first, :last, 'active', NOW())",
        ['email' => $email, 'hash' => $hash, 'first' => $first, 'last' => $last],
    );
    echo "\nCreated.\n";
}

(new \FixListed\Core\AuditLog($db))->record('admin.account_set', $id, 'user', $id, ['via' => 'bin/admin.php']);

echo "Sign in at " . rtrim((string) \FixListed\Core\Config::get('app.url', ''), '/') . "/admin\n";
echo "Role: " . Auth::ROLE_SUPER . "\n\n";
