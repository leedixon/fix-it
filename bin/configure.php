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

function ask(string $label, string $default = '', bool $hidden = false): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, $label . $suffix . ': ');

    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        // Keep the password off the screen and out of any scrollback someone
        // might screenshot.
        shell_exec('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    } else {
        $value = trim((string) fgets(STDIN));
    }

    return $value !== '' ? $value : $default;
}

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

$dbName = ask('Database name', 'leedixon_fixlisted');
$dbUser = ask('Database user', 'leedixon_fixapp');
$dbPass = ask('Database password (not shown as you type)', '', true);
$url    = ask('Site URL', 'https://fixlisted.com');
$mail   = ask('Send email from', 'lee@leedixon.com');
$alert  = ask('Send signup alerts to', $mail);

echo "\nMail sending\n";
echo "If the from-address is on Google Workspace or Microsoft 365, answer yes.\n";
echo "Sending such mail from this web server fails authentication and the\n";
echo "recipient's provider drops it silently.\n\n";

$useSmtp = strtolower(substr(ask('Send through SMTP? (y/n)', 'y'), 0, 1)) === 'y';
$smtp = ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'username' => $mail, 'password' => ''];

if ($useSmtp) {
    $smtp['host']     = ask('SMTP host', 'smtp.gmail.com');
    $smtp['port']     = (int) ask('SMTP port', '587');
    // 465 is implicit TLS from the first byte; 587 upgrades with STARTTLS.
    // Getting this backwards produces a timeout rather than a useful error.
    $smtp['encryption'] = $smtp['port'] === 465 ? 'ssl' : 'tls';
    $smtp['username'] = ask('SMTP username', $mail);
    echo "\nFor Google this must be an App Password, not your normal password:\n";
    echo "Google Account > Security > 2-Step Verification > App passwords.\n";
    // Google shows App Passwords in groups of four; the spaces are cosmetic.
    $smtp['password'] = str_replace(' ', '', ask('SMTP password (not shown as you type)', '', true));

    if ($smtp['password'] === '') {
        fwrite(STDERR, "\nSMTP was chosen but no password given. Nothing was written.\n");
        exit(1);
    }
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
        'key'      => bin2hex(random_bytes(32)),
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
    'stripe' => [
        'publishable_key' => '',
        'secret_key'      => '',
        'webhook_secret'  => '',
    ],
    'mail' => [
        'from_address' => $mail,
        'from_name'    => 'Fix Listed',
        'alert_to'     => $alert,
        'transport'    => $useSmtp ? 'smtp' : 'mail',
        'smtp'         => $smtp,
    ],
];

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
if ($useSmtp) {
    echo "Checking SMTP…\n";
    try {
        \FixListed\Core\Config::load($config);
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
            echo "SMTP FAILED. The reason is in the error log. A 535 means the\n"
               . "password was refused — with Google that means an App Password\n"
               . "is required rather than your normal one.\n\n";
            $failed = true;
        }
    } catch (Throwable $e) {
        echo '  ' . $e->getMessage() . "\n\n";
        $failed = true;
    }
}

exit($failed ? 1 : 0);
