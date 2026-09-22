<?php
declare(strict_types=1);

/**
 * Pre-flight check. Run on the server after filling in config/config.php:
 *
 *   php bin/check.php
 *
 * Answers "did I set this up correctly?" in one command, instead of finding
 * out from a white page in a browser.
 */

$root = dirname(__DIR__);
$ok = 0;
$bad = 0;

function line(bool $pass, string $label, string $detail = ''): void
{
    global $ok, $bad;
    $pass ? $ok++ : $bad++;
    printf("%s  %s%s\n", $pass ? ' OK  ' : 'FAIL ', $label, $detail !== '' ? "  — {$detail}" : '');
}

// --- config file ------------------------------------------------------------
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    echo "FAIL  config/config.php is missing.\n\n";
    echo "      cp config/config.example.php config/config.php\n";
    echo "      nano config/config.php\n";
    echo "      chmod 600 config/config.php\n";
    exit(1);
}
line(true, 'config/config.php exists');

$perms = substr(sprintf('%o', fileperms($configFile)), -3);
line($perms === '600', 'config/config.php is not world-readable', "mode {$perms}" . ($perms === '600' ? '' : ' — run: chmod 600 config/config.php'));

require $root . '/app/bootstrap.php';

use FixListed\Core\Config;
use FixListed\Core\Database;
use FixListed\Core\Demo;

// --- values -----------------------------------------------------------------
foreach (['db.name', 'db.user', 'app.url'] as $key) {
    $value = Config::get($key);
    line(is_string($value) && $value !== '', "{$key} is set", is_string($value) ? $value : '(empty)');
}

$charset = Config::get('db.charset');
line($charset === 'utf8mb4', 'db.charset is utf8mb4',
    $charset === 'utf8mb4' ? '' : "found '{$charset}' — em-dashes and accents will be mangled");

$key = (string) Config::get('app.key', '');
line(strlen($key) >= 32, 'app.key is set',
    strlen($key) >= 32 ? strlen($key) . ' chars' : "generate one: php -r 'echo bin2hex(random_bytes(32));'");

line(Config::get('app.env') === 'production', "app.env is 'production'",
    Config::get('app.env') === 'production' ? '' : "currently '" . Config::get('app.env') . "' — visitors would see error detail");

$from = (string) Config::get('mail.from_address', '');
line($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false, 'mail.from_address is a valid address', $from);
line((string) Config::get('mail.alert_to', '') !== '', 'mail.alert_to is set (signup alerts go here)', (string) Config::get('mail.alert_to', ''));

$transport = (string) Config::get('mail.transport', 'mail');
if ($transport === 'api') {
    $key = (string) Config::get('mail.api.key', '');
    line($key !== '', 'the HTTPS API transport has a key', $key !== '' ? 'api.resend.com' : 'no key set');
} elseif ($transport === 'smtp') {
    $host = (string) Config::get('mail.smtp.host', '');
    $pass = (string) Config::get('mail.smtp.password', '');
    line($host !== '' && $pass !== '', 'SMTP is configured', $host !== '' ? $host : 'no host set');
} else {
    // Sending as a domain hosted elsewhere is the failure that looks like
    // nothing happening at all: no bounce, nothing in spam.
    line(true, "mail.transport is 'mail'",
        'fine only if ' . $from . ' is a mailbox on THIS server — otherwise use api');
}

// Seeded listings are invented businesses. Which mode is on decides whether a
// visitor sees them, so it belongs in the pre-flight rather than only in
// bin/demo.php.
$demoMode = Demo::mode();
line(true, "sample data is '" . $demoMode . "'",
    $demoMode === 'label'
        ? 'seed listings are shown, each labelled Sample — switch to hide at launch'
        : 'seed listings are hidden from every page');

// --- payments ---------------------------------------------------------------
$secretKey  = (string) Config::get('stripe.secret_key', '');
$webhookKey = (string) Config::get('stripe.webhook_secret', '');
$apiBase    = (string) Config::get('stripe.api_base', '');

if ($secretKey === '') {
    line(true, 'Stripe is not configured',
        'job posting will say payments are off rather than half-working');
} else {
    $live = \FixListed\Core\Stripe::isLiveKey($secretKey);
    $kind = \FixListed\Core\Stripe::keyKind($secretKey);
    line(\FixListed\Core\Stripe::looksLikeSecretKey($secretKey), 'Stripe secret key looks like a key',
        ($live ? 'LIVE — real cards will be charged' : 'test mode') . ', ' . $kind . ' key');

    // Not a failure — a standard key works — but worth saying every time.
    // A restricted key scoped to what this site does is worth far less to
    // whoever ends up with it.
    line(true, $kind === 'restricted'
        ? 'the key is restricted, so a leak is limited to what this site does'
        : 'the key is a standard secret key',
        $kind === 'restricted' ? '' : 'consider a restricted key — see docs/payments.md');

    // Without this, a payment can never be confirmed and no job ever goes
    // live. Everything else about Stripe can be right and nothing will work.
    line($webhookKey !== '', 'Stripe webhook secret is set',
        $webhookKey !== '' ? '' : 'no payment can be confirmed without it — see docs/payments.md');

    // The list Stripe must be told to send. Printed rather than merely
    // checked, because nothing here can see the dashboard — the person
    // reading this is the only one who can compare the two.
    $events = \FixListed\Controllers\WebhookController::HANDLED_EVENTS;
    line(true, 'webhook events this endpoint handles', count($events) . ' — listed below');
    foreach ($events as $event) {
        echo '        ' . $event . "\n";
    }
    echo "        (Stripe > Webhooks > your endpoint must send exactly these)\n";

    // A test override left in production would send real checkouts to a stub,
    // which is why it is a failure with live keys rather than a note.
    if ($apiBase === '') {
        line(true, 'Stripe requests go to api.stripe.com');
    } else {
        line(!$live, 'Stripe API base is overridden',
            $live
                ? 'LIVE KEYS pointed at ' . $apiBase . ' — remove stripe.api_base from config'
                : 'pointed at ' . $apiBase . ' for testing; remove it before going live');
    }
}

// --- database ---------------------------------------------------------------
try {
    $db = Database::fromConfig();
    $version = (string) $db->value('SELECT VERSION()');
    line(true, 'database connects', $version);

    $tables = (int) $db->value(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema',
        ['schema' => Config::get('db.name')],
    );
    if ($tables >= 29) {
        line(true, 'schema loaded', "{$tables} tables");

        /*
         * Counting tables is not the same as being up to date.
         *
         * A migration that adds a *column* leaves the table count unchanged,
         * so this said "schema is up to date" on a database missing the
         * columns three admin screens select — and those screens answered 500
         * while every check here passed. The only honest measure is which
         * migration files have actually run.
         */
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        $ran   = array_column($db->all('SELECT filename FROM migrations'), 'filename');
        $pending = array_values(array_diff(array_map('basename', $files), $ran));

        line($pending === [], 'every migration has been applied',
            $pending === []
                ? count($files) . ' in database/migrations, all run'
                : count($pending) . ' pending: ' . implode(', ', $pending) . ' — run: php bin/migrate.php');
    } elseif ($tables > 0) {
        // An existing install cannot be fixed by re-running schema.sql — it has
        // no IF NOT EXISTS and stops at the first table that already exists.
        line(false, 'schema is behind', "{$tables} tables, expected 29+ — apply the migrations in database/migrations/");
    } else {
        line(false, 'schema is not imported', 'run database/schema.sql');
    }

    $hasWaitlist = (int) $db->value(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = 'waitlist'",
        ['schema' => Config::get('db.name')],
    );
    line($hasWaitlist === 1, 'waitlist table exists',
        $hasWaitlist === 1 ? (string) $db->value('SELECT COUNT(*) FROM waitlist') . ' signups so far'
                           : 'run database/migrations/001_waitlist.sql');

    // Proves the connection charset, not just the column charset — this is the
    // failure that looks like corrupted data but is really a client setting.
    $round = (string) $db->value("SELECT 'em—dash'");
    line($round === 'em—dash', 'utf8mb4 survives a round trip', $round);
} catch (Throwable $e) {
    line(false, 'database connects', $e->getMessage());
}

// --- environment ------------------------------------------------------------
line(PHP_VERSION_ID >= 80100, 'PHP is 8.1 or newer', PHP_VERSION);
foreach (['pdo_mysql', 'mbstring', 'curl', 'openssl'] as $ext) {
    line(extension_loaded($ext), "extension {$ext}");
}
line(function_exists('mail'), 'mail() is available', function_exists('mail') ? '' : 'signup emails will not send');

foreach (['storage/logs', 'storage/uploads'] as $dir) {
    line(is_writable($root . '/' . $dir), "{$dir} is writable");
}

// The maintenance switch is written into storage/ itself, so the directory
// has to be writable or the admin screen's button silently cannot work.
line(is_writable($root . '/storage'), 'storage is writable',
    is_writable($root . '/storage') ? 'the maintenance switch can be set from the admin screen'
        : 'maintenance mode could only be toggled over SSH');

// --- is the site currently down? -------------------------------------------
//
// Last, and loudly. Everything above can pass on a site no visitor can reach,
// and leaving maintenance on is the way this feature goes wrong.
if (\FixListed\Core\Maintenance::isOn()) {
    $state = \FixListed\Core\Maintenance::state();
    echo "\n";
    line(false, 'THE SITE IS DOWN FOR MAINTENANCE',
        'on for ' . \FixListed\Core\Maintenance::runningFor()
        . ($state['by'] !== '' ? ', by ' . $state['by'] : '')
        . ' — run: php bin/maintenance.php off');
} else {
    line(true, 'the site is not in maintenance mode');
}

printf("\n%d passed, %d failed\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
