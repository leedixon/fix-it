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

use FixListed\Core\Config;
use FixListed\Core\Database;
use FixListed\Core\Mailer;
use FixListed\Core\View;

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
    // The signup is saved, which is the part that must not fail. Answer the
// visitor now, then send the mail — a slow local MTA should never be the
// reason a form feels broken.
http_response_code(200);
echo json_encode(['ok' => true]);

// Under PHP-FPM this hands the response back immediately and the mail is sent
// after the visitor already has their confirmation. Without FPM the response
// is still correct; it just flushes when the script ends.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

try {
    $view   = new View(BASE_PATH . '/app/Views');
    $mailer = Mailer::fromConfig();
    $alertTo = (string) Config::get('mail.alert_to', '');
    $from    = (string) Config::get('mail.from_address', '');

    if ($role === 'pro') {
        $html = $view->render('emails.waitlist_pro', [
            'title'     => "You're on the founding list",
            'preheader' => 'Your Fix Listed profile will be live the day we open. Nothing to pay.',
            'name'      => $name,
            'trade'     => $str('trade', 80) ?: 'General handyman',
            'counties'  => $counties,
            'replyTo'   => $from,
        ], 'emails.layout');

        $text = "You're on the founding list, " . explode(' ', $name)[0] . ".\n\n"
              . "Fix Listed opens in Northwest Illinois shortly, and your listing will be live on day one. "
              . "Nothing to pay - now or later - to be on it.\n\n"
              . "What we have for you:\n"
              . "  " . ($str('trade', 80) ?: 'General handyman') . "\n"
              . "  Covering " . $counties . "\n\n"
              . "Wrong, or missing something? Reply to this email and I'll fix it. If you send a couple of "
              . "photos of recent work, I'll put them on your profile before we open.\n\n"
              . "A reminder of the deal: listing is free, quoting is free, we take no commission on anything "
              . "you agree with a homeowner, and we don't sell your details on.\n\n"
              . "-- Fix Listed, a trades directory for Northwest Illinois";

        $mailer->send($email, "You're on the Fix Listed founding list", $html, $text, $from);
    } else {
        $town = $str('town', 80) ?: 'your area';
        $html = $view->render('emails.waitlist_homeowner', [
            'title'     => "You're on the list",
            'preheader' => 'One email, the day Fix Listed opens near you. Nothing else.',
            'name'      => $name,
            'town'      => $town,
        ], 'emails.layout');

        $text = "You're on the list, " . explode(' ', $name)[0] . ".\n\n"
              . "We'll email you once - the day Fix Listed opens in " . $town . ". "
              . "No newsletter, no drip campaign.\n\n"
              . "How it will work:\n"
              . "  $10 flat to post a job. That's the only thing you ever pay us.\n"
              . "  No commission. Whatever you agree with your pro is what you pay them.\n"
              . "  Your details stay here - your job goes to local tradespeople, not four call centres.\n\n"
              . "Know a good handyman, plumber or electrician around here? Reply and tell me who.\n\n"
              . "-- Fix Listed, a trades directory for Northwest Illinois";

        $mailer->send($email, 'You are on the Fix Listed list', $html, $text, $from);
    }

    if ($alertTo !== '') {
        $alertHtml = $view->render('emails.waitlist_alert', [
            'title'     => 'New Fix Listed signup',
            'preheader' => $name . ' - ' . ($role === 'pro' ? ($str('trade', 80) ?: 'handyman') : 'homeowner'),
            'role'      => $role,
            'name'      => $name,
            'email'     => $email,
            'phone'     => $str('phone', 32),
            'trade'     => $str('trade', 80),
            'counties'  => $counties,
            'town'      => $str('town', 80),
            'note'      => $str('note', 2000),
        ], 'emails.layout');

        $alertText = ($role === 'pro' ? 'NEW FOUNDING PRO' : 'NEW HOMEOWNER') . "\n\n"
                   . $name . "\n" . $email . "\n" . $str('phone', 32) . "\n"
                   . ($role === 'pro' ? $str('trade', 80) . "\nCovering " . $counties : $str('town', 80)) . "\n"
                   . ($str('note', 2000) !== '' ? "\n" . $str('note', 2000) . "\n" : '');

        $mailer->send(
            $alertTo,
            ($role === 'pro' ? 'New pro: ' : 'New signup: ') . $name,
            $alertHtml,
            $alertText,
            $email,   // reply goes straight back to the person who signed up
        );
    }
} catch (Throwable $e) {
    // The signup is already saved. A mail failure is worth knowing about but
    // must never turn a successful capture into an error for the visitor.
    error_log('waitlist mail failed: ' . $e->getMessage());
}

exit;
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

// The signup is saved, which is the part that must not fail. Answer the
// visitor now, then send the mail — a slow local MTA should never be the
// reason a form feels broken.
http_response_code(200);
echo json_encode(['ok' => true]);

// Under PHP-FPM this hands the response back immediately and the mail is sent
// after the visitor already has their confirmation. Without FPM the response
// is still correct; it just flushes when the script ends.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

try {
    $view   = new View(BASE_PATH . '/app/Views');
    $mailer = Mailer::fromConfig();
    $alertTo = (string) Config::get('mail.alert_to', '');
    $from    = (string) Config::get('mail.from_address', '');

    if ($role === 'pro') {
        $html = $view->render('emails.waitlist_pro', [
            'title'     => "You're on the founding list",
            'preheader' => 'Your Fix Listed profile will be live the day we open. Nothing to pay.',
            'name'      => $name,
            'trade'     => $str('trade', 80) ?: 'General handyman',
            'counties'  => $counties,
            'replyTo'   => $from,
        ], 'emails.layout');

        $text = "You're on the founding list, " . explode(' ', $name)[0] . ".\n\n"
              . "Fix Listed opens in Northwest Illinois shortly, and your listing will be live on day one. "
              . "Nothing to pay - now or later - to be on it.\n\n"
              . "What we have for you:\n"
              . "  " . ($str('trade', 80) ?: 'General handyman') . "\n"
              . "  Covering " . $counties . "\n\n"
              . "Wrong, or missing something? Reply to this email and I'll fix it. If you send a couple of "
              . "photos of recent work, I'll put them on your profile before we open.\n\n"
              . "A reminder of the deal: listing is free, quoting is free, we take no commission on anything "
              . "you agree with a homeowner, and we don't sell your details on.\n\n"
              . "-- Fix Listed, a trades directory for Northwest Illinois";

        $mailer->send($email, "You're on the Fix Listed founding list", $html, $text, $from);
    } else {
        $town = $str('town', 80) ?: 'your area';
        $html = $view->render('emails.waitlist_homeowner', [
            'title'     => "You're on the list",
            'preheader' => 'One email, the day Fix Listed opens near you. Nothing else.',
            'name'      => $name,
            'town'      => $town,
        ], 'emails.layout');

        $text = "You're on the list, " . explode(' ', $name)[0] . ".\n\n"
              . "We'll email you once - the day Fix Listed opens in " . $town . ". "
              . "No newsletter, no drip campaign.\n\n"
              . "How it will work:\n"
              . "  $10 flat to post a job. That's the only thing you ever pay us.\n"
              . "  No commission. Whatever you agree with your pro is what you pay them.\n"
              . "  Your details stay here - your job goes to local tradespeople, not four call centres.\n\n"
              . "Know a good handyman, plumber or electrician around here? Reply and tell me who.\n\n"
              . "-- Fix Listed, a trades directory for Northwest Illinois";

        $mailer->send($email, 'You are on the Fix Listed list', $html, $text, $from);
    }

    if ($alertTo !== '') {
        $alertHtml = $view->render('emails.waitlist_alert', [
            'title'     => 'New Fix Listed signup',
            'preheader' => $name . ' - ' . ($role === 'pro' ? ($str('trade', 80) ?: 'handyman') : 'homeowner'),
            'role'      => $role,
            'name'      => $name,
            'email'     => $email,
            'phone'     => $str('phone', 32),
            'trade'     => $str('trade', 80),
            'counties'  => $counties,
            'town'      => $str('town', 80),
            'note'      => $str('note', 2000),
        ], 'emails.layout');

        $alertText = ($role === 'pro' ? 'NEW FOUNDING PRO' : 'NEW HOMEOWNER') . "\n\n"
                   . $name . "\n" . $email . "\n" . $str('phone', 32) . "\n"
                   . ($role === 'pro' ? $str('trade', 80) . "\nCovering " . $counties : $str('town', 80)) . "\n"
                   . ($str('note', 2000) !== '' ? "\n" . $str('note', 2000) . "\n" : '');

        $mailer->send(
            $alertTo,
            ($role === 'pro' ? 'New pro: ' : 'New signup: ') . $name,
            $alertHtml,
            $alertText,
            $email,   // reply goes straight back to the person who signed up
        );
    }
} catch (Throwable $e) {
    // The signup is already saved. A mail failure is worth knowing about but
    // must never turn a successful capture into an error for the visitor.
    error_log('waitlist mail failed: ' . $e->getMessage());
}

exit;
