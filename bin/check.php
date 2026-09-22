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

/*
 * app.url is quietly the most load-bearing setting here.
 *
 * Every canonical link, every og:url, every @id in the structured data, every
 * <loc> in the sitemap and the Sitemap: line in robots.txt is built from it.
 * Wrong, and the whole site tells search engines it lives somewhere else —
 * which fails silently, looks fine in a browser, and is only noticed weeks
 * later when nothing has been indexed.
 */
$appUrl   = (string) Config::get('app.url', '');
$appHost  = (string) (parse_url($appUrl, PHP_URL_HOST) ?: '');
$isLocal  = in_array($appHost, ['localhost', '127.0.0.1', '::1'], true);

// http is correct on a development box and wrong on a public one, so the
// check asks which this is rather than insisting on https everywhere. A
// permanent failure that everyone learns to read past protects nothing.
line(str_starts_with($appUrl, 'https://') || $isLocal, 'app.url scheme suits the host',
    str_starts_with($appUrl, 'https://')
        ? 'canonicals, og:url, JSON-LD and the sitemap are built from it'
        : ($isLocal
            ? "'{$appUrl}' — local, so http is fine here"
            : "'{$appUrl}' is not https — every canonical and sitemap URL would say that"));

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


/*
 * --live — the launch gate.
 *
 * Everything above passes on a site no visitor should be allowed near. These
 * are the settings that are correct for a preview and wrong for a live site,
 * and they are informational the rest of the time precisely so they can be
 * read past during development. Here they fail.
 */
if (in_array('--live', $argv, true)) {
    echo "\n--- live readiness " . str_repeat('-', 52) . "\n\n";

    // The one that is not a launch item at all. database/seed.sql publishes
    // this password, and the repository may well be public — so a seeded
    // admin that can still sign in is an open door, today, not at launch.
    $demoAdmins = $db->all(
        "SELECT email FROM users
          WHERE role IN ('superadmin','market_admin','moderator')
            AND is_demo = 1 AND status = 'active'"
    );
    line($demoAdmins === [], 'no sample admin account can sign in',
        $demoAdmins === []
            ? ''
            : implode(', ', array_column($demoAdmins, 'email'))
              . ' — password is published in database/seed.sql. Run: php bin/admin.php');

    $realAdmins = (int) $db->value(
        "SELECT COUNT(*) FROM users
          WHERE role = 'superadmin' AND status = 'active'
            AND is_demo = 0 AND password_hash IS NOT NULL"
    );
    line($realAdmins > 0, 'a real superadmin exists',
        $realAdmins > 0 ? $realAdmins . ' who can sign in' : 'run: php bin/admin.php');
    line($realAdmins > 1, 'more than one superadmin can sign in',
        $realAdmins > 1 ? '' : 'one lost password means SSH to get back in — not fatal, but know it');

    $demoRows = (int) $db->value('SELECT COUNT(*) FROM pro_profiles WHERE is_demo = 1')
              + (int) $db->value('SELECT COUNT(*) FROM jobs WHERE is_demo = 1');
    line($demoRows === 0, 'no sample listings or jobs remain',
        $demoRows === 0 ? '' : $demoRows . ' invented rows — run: php bin/demo.php purge');

    line(Config::get('app.demo_data') === 'hide', "app.demo_data is 'hide'",
        Config::get('app.demo_data') === 'hide' ? '' : "currently '" . Config::get('app.demo_data') . "'");

    line(Config::get('app.noindex') === false, 'app.noindex is off',
        Config::get('app.noindex') === false ? 'search engines may index the site' : 'every page still says noindex');

    $realPros = (int) $db->value("SELECT COUNT(*) FROM pro_profiles WHERE is_demo = 0 AND status = 'active'");
    line($realPros > 0, 'there are real tradespeople to show',
        $realPros > 0 ? $realPros . ' live' : 'a directory with nobody in it is worse than no directory');

    // Live keys are the point of going live; a test key here means the site
    // takes no money at all.
    line($secretKey !== '' && \FixListed\Core\Stripe::isLiveKey($secretKey),
        'Stripe is in live mode',
        $secretKey === '' ? 'no key configured' : '');

    /*
     * The auto-refund is a written promise on /pricing. A promise that only
     * happens when somebody remembers to run something is not a promise.
     *
     * Counting distinct days in the log, not just its age. A log touched once
     * by hand satisfies "modified recently" for as long as the window lasts,
     * so that alone cannot tell a working cron from a single manual run — it
     * would have reported green on a schedule that never fired. Two different
     * days in the log means something is running it without being asked.
     */
    $sweepLog  = BASE_PATH . '/storage/logs/sweep.log';
    $sweepDays = [];
    $sweepAge  = null;

    if (is_file($sweepLog)) {
        $sweepAge = time() - filemtime($sweepLog);
        preg_match_all('/sweep (\d{4}-\d{2}-\d{2}) /', (string) file_get_contents($sweepLog), $found);
        $sweepDays = array_unique($found[1] ?? []);
    }

    // A daily job leaves 24 hours between runs; 26 allows for drift and a
    // slow night without reporting a healthy schedule as broken.
    $recent = $sweepAge !== null && $sweepAge < 93600;

    line($recent && count($sweepDays) > 1, 'the refund sweep is running on a schedule',
        match (true) {
            $sweepAge === null      => 'no storage/logs/sweep.log at all — is bin/sweep.php on cron?',
            count($sweepDays) <= 1  => 'only one day in the log, so this may be your manual run — '
                                     . 'check again tomorrow, after cron has had a turn',
            !$recent                => 'last run was ' . (int) round($sweepAge / 3600) . ' hours ago — '
                                     . 'a daily job should be under 24',
            default                 => count($sweepDays) . ' days recorded, last run '
                                     . (int) round($sweepAge / 3600) . 'h ago',
        });

    /*
     * The SEO layer, which is one setting deep and fails silently.
     *
     * app.url with a path on it is the preview mount. Left in place at
     * launch, every canonical on the live site points at /preview/... —
     * pages that are correct, indexed under an address nobody should reach.
     */
    $parsedPath = (string) (parse_url($appUrl, PHP_URL_PATH) ?: '');
    line(str_starts_with($appUrl, 'https://') && trim($parsedPath, '/') === '',
        'app.url is the live root, not the preview mount',
        trim($parsedPath, '/') === ''
            ? $appUrl
            : "'{$appUrl}' still has '{$parsedPath}' on it — every canonical would point there");

    /*
     * robots.txt and the sitemap both key off app.noindex, checked above.
     * What cannot be inferred from a setting is whether the sitemap has
     * anything in it: real profiles and jobs are excluded when they are
     * seeded, so a site that has purged its sample data and signed nobody up
     * submits a sitemap of landing pages and no listings.
     */
    $scope1     = \FixListed\Core\TenantScope::market((int) $db->value(
        "SELECT id FROM markets WHERE status = 'live' ORDER BY launched_at, id LIMIT 1"
    ));
    $mapPros    = count((new \FixListed\Repositories\ProRepository($db, $scope1))->sitemap());
    $mapCities  = count((new \FixListed\Repositories\GeographyRepository($db, $scope1))->pageCities());
    line($mapPros > 0, 'the sitemap has real listings in it, not just landing pages',
        $mapPros > 0
            ? $mapPros . ' profiles, ' . $mapCities . ' town pages'
            : $mapCities . ' town pages but no real profiles — the pages are fine, they are just empty');

    $gtm = (string) Config::get('analytics.gtm_id', '');
    line($gtm !== '', 'Google Tag Manager is configured',
        $gtm !== '' ? $gtm : 'no container — nothing about the launch will be measured');

    line(!\FixListed\Core\Maintenance::isOn(), 'the site is not in maintenance mode',
        \FixListed\Core\Maintenance::isOn() ? 'visitors are seeing the holding page' : '');

    echo "\nThese are launch gates. Failures here are settings that are correct\n";
    echo "for a preview and wrong for a site taking money from strangers.\n";
}

printf("\n%d passed, %d failed\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
