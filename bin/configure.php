<?php
declare(strict_types=1);

/**
 * Writes config/config.php by asking for the few values that vary.
 *
 *   php bin/configure.php
 *
 * Exists because hand-editing the file in a terminal editor is where this
 * setup goes wrong: a paste lands in the wrong place, a line gets clipped, and
 * the result is a parse error or a silently missing setting. Nothing here is
 * typed into a file by hand, the app key is generated rather than remembered,
 * and the password is never echoed to the screen or written to shell history.
 */

$root = dirname(__DIR__);
$target = $root . '/config/config.php';
$failed = false;

require_once $root . '/app/Core/Config.php';
require_once $root . '/app/Core/Smtp.php';
require_once $root . '/app/Core/MailApi.php';
require_once $root . '/app/Core/Stripe.php';
require_once __DIR__ . '/configure_input.php';

function ask(string $label, string $default = '', bool $hidden = false): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, $label . $suffix . ': ');

    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        // Keep the password off the screen and out of any scrollback someone
        // might screenshot.
        shell_exec('stty -echo 2>/dev/null');
        $value = cleanInput((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    } else {
        $value = cleanInput((string) fgets(STDIN));
    }

    return $value !== '' ? $value : $default;
}

// Read before anything is asked, so a re-run can keep what a first run
// generated and what launch steps have since changed.
$existing = is_file($target) ? (array) require $target : [];

echo "\nFix Listed — configuration\n";
echo "Press Enter to accept the value in brackets.\n\n";

if (is_file($target)) {
    $backup = $target . '.bak-' . date('Ymd-His');
    copy($target, $backup);
    // The backup holds the same credentials as the original, so it gets the
    // same permissions — copy() would otherwise leave it at the umask default.
    chmod($backup, 0600);
    echo "Existing config backed up to " . basename($backup) . "\n";
    echo "Delete old backups once you are happy: rm config/config.php.bak-*\n\n";
}

/** Writes the config file at mode 600, the one way, from both paths. */
function writeConfig(string $target, array $config): void
{
    $php = "<?php\n"
         . "/**\n"
         . " * Fix Listed — configuration.\n"
         . " *\n"
         . " * Written by bin/configure.php on " . date('j M Y') . ".\n"
         . " * Gitignored. Never commit this file. Re-run bin/configure.php to rebuild it.\n"
         . " */\n\n"
         . "return " . var_export($config, true) . ";\n";

    file_put_contents($target, $php);
    chmod($target, 0600);
}

/*
 * --launch — flip the two settings that make a preview a live site.
 *
 * app.noindex and app.demo_data are the last things in this file anyone had a
 * reason to hand-edit, and they get edited on launch day — the worst possible
 * moment to put a parse error into a file holding live credentials.
 *
 * It refuses if the gates in `php bin/check.php --live` are not met, because
 * the failure it prevents is real: turning off noindex with sample listings
 * still showing invites search engines to index ten invented businesses, and
 * that is not a mistake a robots file undoes quickly.
 */
if (in_array('--launch', $argv, true)) {
    if (!is_file($target)) {
        fwrite(STDERR, "\nThere is no config/config.php yet.\n\n");
        exit(1);
    }

    $config = require $target;

    echo "Going live\n\n";
    echo "  app.noindex    " . (($config['app']['noindex'] ?? true) ? 'true — search engines told to stay away' : 'already false') . "\n";
    echo "  app.demo_data  '" . ($config['app']['demo_data'] ?? 'label') . "'" . (($config['app']['demo_data'] ?? '') === 'hide' ? ' — already hidden' : '') . "\n\n";

    // The gates that make this safe, checked here rather than trusted to have
    // been read. Sample rows plus an indexable site is the combination that
    // cannot be quietly undone.
    $blockers = [];
    try {
        $db = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['db']['host'], $config['db']['port'], $config['db']['name']),
            $config['db']['user'], $config['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $demoRows = (int) $db->query('SELECT COUNT(*) FROM pro_profiles WHERE is_demo = 1')->fetchColumn()
                  + (int) $db->query('SELECT COUNT(*) FROM jobs WHERE is_demo = 1')->fetchColumn();
        if ($demoRows > 0) {
            $blockers[] = $demoRows . ' sample rows are still in the database — run: php bin/demo.php purge';
        }
        $realPros = (int) $db->query("SELECT COUNT(*) FROM pro_profiles WHERE is_demo = 0 AND status = 'active'")->fetchColumn();
        if ($realPros === 0) {
            $blockers[] = 'there are no real tradespeople listed — an empty directory is worse than none';
        }
        $demoAdmins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role IN ('superadmin','market_admin','moderator') AND is_demo = 1 AND status = 'active'")->fetchColumn();
        if ($demoAdmins > 0) {
            $blockers[] = 'a sample admin account can still sign in — its password is published';
        }
    } catch (Throwable $e) {
        $blockers[] = 'could not read the database to check: ' . $e->getMessage();
    }

    if ($blockers !== []) {
        fwrite(STDERR, "STOPPED. Not ready to go live:\n\n");
        foreach ($blockers as $blocker) {
            fwrite(STDERR, "  - " . $blocker . "\n");
        }
        fwrite(STDERR, "\nNothing was changed. See php bin/check.php --live.\n\n");
        exit(1);
    }

    echo "This makes the site public: search engines may index it, and no listing\n";
    echo "is labelled Sample any more.\n\n";
    if (strtolower(substr(ask('Go live? (y/n)', 'n'), 0, 1)) !== 'y') {
        echo "Cancelled. Nothing was changed.\n\n";
        exit(0);
    }

    $config['app']['noindex']   = false;
    $config['app']['demo_data'] = 'hide';
    writeConfig($target, $config);

    $lint = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lint, $code);
    if ($code !== 0) {
        fwrite(STDERR, "\nThe file was written but does not parse:\n" . implode("\n", $lint) . "\n");
        exit(1);
    }

    echo "\nLive. noindex off, sample data hidden.\n\n";
    echo "Two things that are now urgent rather than pending:\n";
    echo "  - the webhook URL in Stripe must match where the app actually is\n";
    echo "  - bin/sweep.php must be on cron, or the refund promise is not kept\n\n";
    exit(0);
}

/*
 * --analytics — set or clear the Google Tag Manager container.
 *
 * Here for the same reason as --stripe: this file holds live credentials, and
 * hand-editing it in a terminal editor to change one line is how a stray
 * comma takes the whole site down. Nothing else in the file is touched.
 */
if (in_array('--analytics', $argv, true)) {
    if (!is_file($target)) {
        fwrite(STDERR, "\nThere is no config/config.php yet. Run php bin/configure.php first.\n\n");
        exit(1);
    }

    $config  = require $target;
    $current = (string) ($config['analytics']['gtm_id'] ?? '');

    echo "Google Tag Manager\n\n";
    echo "  Currently: " . ($current !== '' ? $current : 'not set — no tag is rendered') . "\n\n";
    echo "The container id from tagmanager.google.com, e.g. GTM-XXXXXXX.\n";
    echo "Enter 'none' to switch it off. Press Enter to leave it as it is.\n\n";

    $id = ask('Container id', $current);

    if (strtolower($id) === 'none') {
        $id = '';
    }

    // The same rule the template enforces before printing it into a <script>.
    // Refusing here too means a bad value is never written, rather than
    // written and then silently ignored on every page.
    if ($id !== '' && preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) !== 1) {
        fwrite(STDERR, "\nThat is not a container id. Expected GTM- followed by letters\n");
        fwrite(STDERR, "and digits, e.g. GTM-TX649KRG. Received: " . describeInput($id, $id) . ".\n");
        fwrite(STDERR, queuedInputHint($id));
        fwrite(STDERR, "Nothing was written.\n\n");
        exit(1);
    }

    $config['analytics'] = ['gtm_id' => $id];
    writeConfig($target, $config);

    $lint = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lint, $code);
    if ($code !== 0) {
        fwrite(STDERR, "\nThe file was written but does not parse:\n" . implode("\n", $lint) . "\n");
        exit(1);
    }

    if ($id === '') {
        echo "\nSwitched off. No tag is rendered on any page.\n\n";
        exit(0);
    }

    echo "\nSet to {$id}. It renders on public pages only —\n";
    echo "not /admin, not /my, and never for a signed-in staff account.\n\n";
    echo "Check /privacy still describes what your tags actually do.\n\n";
    exit(0);
}

/*
 * --stripe — change the payment keys and nothing else.
 *
 * The full run asks for everything and rebuilds the file from the answers,
 * which is right the first time and wrong every time after. Swapping test
 * keys for live ones would otherwise mean retyping a database password and a
 * mail provider key that nobody should have to have to hand — and would
 * quietly reset app.key, app.noindex and app.demo_data back to their
 * first-run defaults, undoing launch steps that were taken on purpose.
 */
if (in_array('--stripe', $argv, true)) {
    if (!is_file($target)) {
        fwrite(STDERR, "\nThere is no config/config.php yet. Run php bin/configure.php first.\n\n");
        exit(1);
    }

    $config = require $target;
    $current = $config['stripe'] ?? [];

    // What is there now, without printing any of it. A key on screen is a key
    // in the scrollback, and this is the one command somebody runs over SSH
    // while sharing a window.
    $describe = static function (string $value, bool $isSecret = false): string {
        if ($value === '') {
            return 'not set';
        }
        if (!$isSecret) {
            return 'set';
        }
        return 'set — ' . (\FixListed\Core\Stripe::isLiveKey($value) ? 'LIVE' : 'test')
             . ', ' . \FixListed\Core\Stripe::keyKind($value);
    };

    echo "Changing the Stripe keys only. Everything else in the file is left alone.\n\n";
    echo "  Publishable key:  " . $describe((string) ($current['publishable_key'] ?? '')) . "\n";
    echo "  Secret key:       " . $describe((string) ($current['secret_key'] ?? ''), true) . "\n";
    echo "  Webhook secret:   " . $describe((string) ($current['webhook_secret'] ?? '')) . "\n\n";
    echo "Press Enter at any prompt to keep what is already there.\n\n";

    $secret = trim(ask('Stripe secret key (not shown as you type)', '', true));
    if ($secret === '') {
        $secret = (string) ($current['secret_key'] ?? '');
        echo "  keeping the existing secret key\n";
    } elseif (!\FixListed\Core\Stripe::looksLikeSecretKey($secret)) {
        fwrite(STDERR, "\nThat does not look like a Stripe secret key. Expected one starting\n");
        fwrite(STDERR, "sk_test_, sk_live_, rk_test_ or rk_live_.\n");
        fwrite(STDERR, "Received: " . describeInput($secret, $secret) . ".\n");
        fwrite(STDERR, queuedInputHint($secret));
        fwrite(STDERR, "Nothing was written.\n\n");
        exit(1);
    }

    $publishable = ask('Stripe publishable key (pk_...)', (string) ($current['publishable_key'] ?? ''));
    if ($publishable !== '' && !str_starts_with($publishable, 'pk_')) {
        fwrite(STDERR, "\nA publishable key starts with pk_.\n");
        fwrite(STDERR, "Received: " . describeInput($publishable, $publishable) . ".\n");
        fwrite(STDERR, "Nothing was written.\n\n");
        exit(1);
    }

    // Live and test have separate endpoints and separate signing secrets.
    // Pasting the test one against live keys makes every webhook fail
    // signature verification, which looks exactly like the endpoint being
    // down — so it is worth saying before the prompt rather than after.
    if (\FixListed\Core\Stripe::isLiveKey($secret)) {
        echo "\nThese are LIVE keys, so the webhook secret must be the LIVE one —\n";
        echo "from the live-mode endpoint, not the test one. They are different,\n";
        echo "and the wrong one makes every webhook fail its signature check.\n";
    }
    $webhook = trim(ask('Stripe webhook signing secret (whsec_...)', '', true));
    if ($webhook === '') {
        $webhook = (string) ($current['webhook_secret'] ?? '');
        echo "  keeping the existing webhook secret\n";
    } elseif (!str_starts_with($webhook, 'whsec_')) {
        fwrite(STDERR, "\nA webhook signing secret starts with whsec_.\n");
        fwrite(STDERR, "Received: " . describeInput($webhook, $webhook) . ".\n");
        fwrite(STDERR, queuedInputHint($webhook));
        fwrite(STDERR, "Nothing was written.\n\n");
        exit(1);
    }

    $config['stripe'] = [
        'publishable_key' => $publishable,
        'secret_key'      => $secret,
        'webhook_secret'  => $webhook,
        // Left as it was, or empty. See config.example.php for why pinning a
        // version that disagrees with the event destination goes wrong.
        'api_version'     => (string) ($current['api_version'] ?? ''),
    ];

    // A stub override alongside live keys sends real checkouts nowhere.
    // Removed here rather than merely reported, because there is no situation
    // in which both are wanted.
    if (\FixListed\Core\Stripe::isLiveKey($secret) && !empty($current['api_base'])) {
        unset($config['stripe']['api_base']);
        echo "\n  removed stripe.api_base — live keys must talk to Stripe, not a stub\n";
    } elseif (!empty($current['api_base'])) {
        $config['stripe']['api_base'] = (string) $current['api_base'];
    }

    writeConfig($target, $config);

    $lint = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lint, $code);
    if ($code !== 0) {
        fwrite(STDERR, "\nThe file was written but does not parse:\n" . implode("\n", $lint) . "\n");
        exit(1);
    }

    echo "\nWritten. Stripe keys updated, everything else untouched.\n";
    if (\FixListed\Core\Stripe::isLiveKey($secret)) {
        echo "\n*** LIVE KEYS ARE NOW IN PLACE. Real cards will be charged. ***\n";
    }
    echo "\nNext:  php bin/check.php\n\n";
    exit(0);
}

$dbName = ask('Database name', 'leedixon_fixlisted');
$dbUser = ask('Database user', 'leedixon_fixapp');
$dbPass = ask('Database password (not shown as you type)', '', true);
$url    = ask('Site URL', 'https://fixlisted.com');
$mail   = ask('Send email FROM (the brand address)', 'hello@fixlisted.com');
$reply  = ask('Replies should go TO', 'lee@leedixon.com');
$alert  = ask('Send signup alerts to', $reply);

echo "\nMail sending\n";
echo "Mail for fixlisted.com is not hosted on this server, so anything sent\n";
echo "by the web server itself fails SPF and DKIM and is dropped silently.\n";
echo "It has to go through the provider that holds the domain.\n\n";
echo "  1. Resend, over its HTTPS API   (recommended — port 443)\n";
echo "  2. SMTP                          (a provider and password)\n";
echo "  3. This server's mail()          (only if the from-address is a mailbox here)\n\n";
echo "Choose 1 unless you have a reason not to. This host intercepts outbound\n";
echo "SMTP with its own mail filter, which breaks the TLS handshake before any\n";
echo "password is even sent. Port 443 is not intercepted.\n\n";

$transport = ask('Which one', '1');
$transport = match ($transport) {
    '2'     => 'smtp',
    '3'     => 'mail',
    default => 'api',
};

$smtp = ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''];
$api  = ['key' => ''];

// Hosts for the providers worth using. Picking from the list avoids a typo in
// a hostname producing a timeout that looks like a firewall problem.
$providers = [
    '1' => ['Resend',     'smtp.resend.com',     587, 'resend'],
    '2' => ['Brevo',      'smtp-relay.brevo.com', 587, null],
    '3' => ['MailerSend', 'smtp.mailersend.net', 587, null],
    '4' => ['Postmark',   'smtp.postmarkapp.com', 587, null],
    '5' => ['Gmail / Google Workspace', 'smtp.gmail.com', 587, null],
    '6' => ['Something else', '', 587, null],
];

if ($transport === 'api') {
    echo "\nThe API key from resend.com/api-keys — it starts with re_ and needs\n";
    echo "Sending access. This is not your Resend login password.\n";
    $api['key'] = trim(ask('Resend API key (not shown as you type)', '', true));

    if ($api['key'] === '') {
        fwrite(STDERR, "\nThe API transport was chosen but no key given. Nothing was written.\n");
        exit(1);
    }
}

if ($transport === 'smtp') {
    echo "\n";
    foreach ($providers as $k => $p) {
        printf("  %s. %s%s\n", $k, $p[0], $p[1] !== '' ? '  (' . $p[1] . ')' : '');
    }
    echo "\n";
    $choice = ask('Which provider', '1');
    $picked = $providers[$choice] ?? $providers['6'];

    $smtp['host']     = $picked[1] !== '' ? $picked[1] : ask('SMTP host', '');
    $smtp['port']     = (int) ask('SMTP port', (string) $picked[2]);
    // 465 is implicit TLS from the first byte; 587 upgrades with STARTTLS.
    // Getting this backwards produces a timeout rather than a useful error.
    $smtp['encryption'] = $smtp['port'] === 465 ? 'ssl' : 'tls';
    // Resend's username is the literal word "resend" for every account; the
    // API key is the password. Getting this wrong is the usual first failure.
    $smtp['username'] = ask('SMTP username', $picked[3] ?? $mail);
    echo "\nThis is the API key or SMTP password from the provider's dashboard,\n";
    echo "not your login password for that provider.\n";
    // Google shows App Passwords in groups of four; the spaces are cosmetic.
    $smtp['password'] = str_replace(' ', '', ask('SMTP password (not shown as you type)', '', true));

    if ($smtp['password'] === '') {
        fwrite(STDERR, "\nSMTP was chosen but no password given. Nothing was written.\n");
        exit(1);
    }
}

echo "\nPayments\n";
echo "Keys come from the Stripe dashboard. Test keys charge nobody; live keys\n";
echo "take real money. A restricted key (rk_...) is preferred over a standard\n";
echo "secret key (sk_...) — it does everything this site needs and nothing else,\n";
echo "so a leaked one is worth far less. Leave blank to skip for now — job\n";
echo "posting will say so rather than half-working.\n\n";

$stripe = ['publishable_key' => '', 'secret_key' => '', 'webhook_secret' => ''];
$stripe['secret_key'] = trim(ask('Stripe secret key (not shown as you type)', '', true));

if ($stripe['secret_key'] !== '') {
    if (!\FixListed\Core\Stripe::looksLikeSecretKey($stripe['secret_key'])) {
        fwrite(STDERR, "\nThat does not look like a Stripe secret key. Expected one starting\n");
        fwrite(STDERR, "sk_test_, sk_live_, rk_test_ or rk_live_. Nothing was written.\n");
        exit(1);
    }
    $stripe['publishable_key'] = trim(ask('Stripe publishable key (pk_...)', ''));
    echo "\nThe webhook secret is shown when you add the endpoint in Stripe:\n";
    echo "  Developers > Webhooks > Add endpoint\n";
    echo "  URL:    " . rtrim($url, '/') . "/webhooks/stripe\n";
    echo "  Events: checkout.session.completed, charge.refunded, invoice.paid,\n";
    echo "          invoice.payment_failed, customer.subscription.updated,\n";
    echo "          customer.subscription.deleted\n";
    echo "Without it, no payment can ever be confirmed and no job goes live.\n";
    $stripe['webhook_secret'] = trim(ask('Stripe webhook signing secret (whsec_...)', '', true));

    if (\FixListed\Core\Stripe::isLiveKey($stripe['secret_key'])) {
        echo "\n*** These are LIVE keys. Real cards will be charged. ***\n";
    }
    echo "\nKey type: " . \FixListed\Core\Stripe::keyKind($stripe['secret_key']) . "\n";
}

if ($dbPass === '') {
    fwrite(STDERR, "\nA database password is required. Nothing was written.\n");
    exit(1);
}

$config = [
    'app' => [
        'name'     => 'Fix Listed',
        'url'      => $url,
        'env'      => 'production',
        'timezone' => 'America/Chicago',
        // Kept if there is one. Re-running this for an unrelated reason must
        // not silently rotate the key that ad-impression dedupe hashes with.
        'key'      => $existing['app']['key'] ?? bin2hex(random_bytes(32)),

        // Every public page carries <meta name="robots" content="noindex">
        // while this is true. Turning it off is a deliberate step in
        // docs/launch.md, not something a template should decide.
        'noindex'   => $existing['app']['noindex'] ?? true,

        // 'label' shows the seeded listings with a Sample badge on every card
        // and a banner on every page. 'hide' filters them out of every query.
        // There is no mode that shows them unlabelled — see app/Core/Demo.php.
        'demo_data' => $existing['app']['demo_data'] ?? 'label',
    ],
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => $dbName,
        'user'    => $dbUser,
        'pass'    => $dbPass,
        // Never change: without this the client negotiates latin1 and mangles
        // every em-dash and accented name on the way out.
        'charset' => 'utf8mb4',
    ],
    'stripe' => $stripe,
    'mail' => [
        'from_address' => $mail,
        'from_name'    => 'Fix Listed',
        'reply_to'     => $reply,
        'alert_to'     => $alert,
        'transport'    => $transport,
        'smtp'         => $smtp,
        'api'          => $api,
    ],
];

writeConfig($target, $config);

// Prove the file parses and the credentials work, rather than leaving that to
// be discovered by a blank page in a browser.
$lint = [];
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $lint, $code);
if ($code !== 0) {
    fwrite(STDERR, "\nThe file was written but does not parse:\n" . implode("\n", $lint) . "\n");
    exit(1);
}
echo "\nWritten to config/config.php (mode 600), app key generated.\n";

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['port'], $dbName),
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($dbName)
    )->fetchColumn();
    echo "Database connects. {$tables} tables.\n\n";
    echo $tables >= 30
        ? "Next:  php bin/check.php\n\n"
        : "The schema is behind. Next:\n"
        . "  mysql -u {$dbUser} -p {$dbName} < database/migrations/002_geography.sql\n"
        . "  mysql -u {$dbUser} -p {$dbName} < database/seed.sql\n"
        . "  php bin/check.php\n\n";
} catch (Throwable $e) {
    echo "\nThe database refused those credentials:\n";
    echo '  ' . $e->getMessage() . "\n";
    echo "Check the password, and that the user is added to the database with ALL PRIVILEGES.\n\n";
    $failed = true;
}

// Checked separately from the database on purpose: a database problem must not
// hide a mail problem, and either one alone is worth knowing about.
if ($transport !== 'mail') {
    // Smtp reads app.url for its HELO name, so the config has to be live
    // before either probe runs.
    \FixListed\Core\Config::load($config);
}

if ($transport === 'api') {
    echo "Checking the Resend API…\n";
    try {
        $client = new \FixListed\Core\MailApi($api['key']);
        $probe  = $client->send(
            'Fix Listed <' . $mail . '>',
            [$mail],
            'Fix Listed API check',
            '<p>The HTTPS API transport is configured correctly.</p>',
            "The HTTPS API transport is configured correctly.\n",
            $reply,
        );
        if ($probe) {
            echo "The API works. A test message is on its way to {$mail}.\n\n";
        } else {
            echo "The API REFUSED the message:\n  " . $client->lastError() . "\n\n";
            $failed = true;
        }
    } catch (Throwable $e) {
        echo '  ' . $e->getMessage() . "\n\n";
        $failed = true;
    }
}

if ($transport === 'smtp') {
    echo "Checking SMTP…\n";
    try {
        $client = new \FixListed\Core\Smtp(
            $smtp['host'], $smtp['port'], $smtp['username'], $smtp['password'], $smtp['encryption']
        );
        $probe = $client->send(
            $mail,
            $mail,
            "To: {$mail}\r\nSubject: Fix Listed SMTP check\r\n\r\nSMTP is configured correctly.\r\n",
        );
        if ($probe) {
            echo "SMTP works. A test message is on its way to {$mail}.\n\n";
        } else {
            echo "SMTP FAILED. The reason is printed above and in the error log:\n"
               . "  535 ............... the password was refused. For Resend the\n"
               . "                      username is the literal word 'resend' and the\n"
               . "                      password is the API key.\n"
               . "  STARTTLS failed ... a TLS problem, not a password problem. If it\n"
               . "                      names a certificate belonging to a2hosting.com,\n"
               . "                      this host is proxying outbound SMTP: re-run this\n"
               . "                      script and choose option 1, the HTTPS API.\n"
               . "  cannot reach ...... outbound port blocked, or wrong host.\n\n";
            $failed = true;
        }
    } catch (Throwable $e) {
        echo '  ' . $e->getMessage() . "\n\n";
        $failed = true;
    }
}

exit($failed ? 1 : 0);
