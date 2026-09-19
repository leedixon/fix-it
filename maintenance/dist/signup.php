<?php
declare(strict_types=1);

/**
 * Waitlist capture for the pre-launch page.
 *
 * Lives in the document root because the page posts to it, but holds no
 * credentials of its own — it boots the application from outside the web root
 * and borrows its database connection.
 */

// The repo sits beside the document root: /home/leedixon/fixlisted.com is the
// web root, /home/leedixon/fixlisted is the repo. Override with an env var if
// your layout differs.
$appRoot = getenv('FIXLISTED_APP') ?: dirname(__DIR__) . '/fixlisted';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

$bootstrap = $appRoot . '/app/bootstrap.php';
if (!is_file($bootstrap)) {
    error_log("waitlist: cannot find bootstrap at {$bootstrap}");
    reply(500, ['ok' => false, 'error' => 'We could not save that just now.']);
}
require $bootstrap;

use FixListed\Core\Database;

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) {
    reply(400, ['ok' => false, 'error' => 'That did not come through properly.']);
}

$str = static fn(string $k, int $max): string => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);

// Two quiet spam checks. A bot fills every field it finds, including the one
// hidden off-screen, and it does it faster than a person can read the form.
if ($str('website', 50) !== '') {
    reply(200, ['ok' => true]);                       // succeed silently
}
if ((int) ($in['elapsed'] ?? 0) < 2) {
    reply(200, ['ok' => true]);
}

$role = $str('role', 12);
if (!in_array($role, ['pro', 'homeowner'], true)) {
    reply(400, ['ok' => false, 'error' => 'Please choose whether you are a tradesperson or a homeowner.']);
}

$name  = $str('name', 120);
$email = mb_strtolower($str('email', 191));

if (mb_strlen($name) < 2) {
    reply(422, ['ok' => false, 'error' => 'Please tell us your name.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reply(422, ['ok' => false, 'error' => 'That email address does not look right.']);
}

$counties = $str('counties', 255);
if ($role === 'pro' && $counties === '') {
    reply(422, ['ok' => false, 'error' => 'Pick at least one county you will drive to.']);
}

try {
    $db = Database::fromConfig();

    // Rate limit by IP: a person signs up once, maybe twice for a friend.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip !== '' && ($packed = @inet_pton($ip)) !== false) {
        $recent = (int) $db->value(
            'SELECT COUNT(*) FROM waitlist WHERE ip = :ip AND created_at > NOW() - INTERVAL 1 HOUR',
            ['ip' => $packed],
        );
        if ($recent >= 5) {
            reply(429, ['ok' => false, 'error' => 'That is a lot of signups from one place.']);
        }
    } else {
        $packed = null;
    }

    // A repeat signup updates the entry instead of leaving you a duplicate to
    // clean up by hand later.
    $db->run(
        'INSERT INTO waitlist (role, name, email, phone, trade, counties, town, note, ip, user_agent)
              VALUES (:role, :name, :email, :phone, :trade, :counties, :town, :note, :ip, :ua)
         ON DUPLICATE KEY UPDATE
              name = VALUES(name), phone = VALUES(phone), trade = VALUES(trade),
              counties = VALUES(counties), town = VALUES(town), note = VALUES(note)',
        [
            'role'     => $role,
            'name'     => $name,
            'email'    => $email,
            'phone'    => $str('phone', 32),
            'trade'    => $str('trade', 80),
            'counties' => $counties,
            'town'     => $str('town', 80),
            'note'     => $str('note', 2000),
            'ip'       => $packed,
            'ua'       => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ],
    );
} catch (Throwable $e) {
    // Never leak the database error to the page — but do not lose the lead
    // either. The page falls back to a mailto when this happens.
    error_log('waitlist insert failed: ' . $e->getMessage());
    reply(500, ['ok' => false, 'error' => 'We could not save that just now.']);
}

reply(200, ['ok' => true]);
