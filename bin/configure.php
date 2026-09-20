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
    echo "Existing config backed up to " . basename($backup) . "\n\n";
}

$dbName = ask('Database name', 'leedixon_fixlisted');
$dbUser = ask('Database user', 'leedixon_fixapp');
$dbPass = ask('Database password (not shown as you type)', '', true);
$url    = ask('Site URL', 'https://fixlisted.com');
$mail   = ask('Send email from', 'lee@leedixon.com');
$alert  = ask('Send signup alerts to', $mail);

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
    echo "\nThe file is valid, but the database refused those credentials:\n";
    echo '  ' . $e->getMessage() . "\n";
    echo "Check the password, and that the user is added to the database with ALL PRIVILEGES.\n\n";
    exit(1);
}
