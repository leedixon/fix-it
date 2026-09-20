<?php
declare(strict_types=1);

/**
 * Applies any migration the database has not seen yet.
 *
 *   php bin/migrate.php          apply everything pending
 *   php bin/migrate.php --list   show what would run, change nothing
 *
 * Exists so nobody has to type `mysql -u ... -p ... < file.sql` and pick the
 * right file. That is how 002 got applied twice, and how a password ends up in
 * shell history. The credentials come from config/config.php, the order comes
 * from the filenames, and the migrations table records what ran.
 *
 * Every migration is written to be safe to re-run. This script is the belt;
 * the guards inside each file are the braces.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use FixListed\Core\Database;

$listOnly = in_array('--list', $argv, true);
$dir      = BASE_PATH . '/database/migrations';
$db       = Database::fromConfig();

$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

if ($files === []) {
    exit("No migrations found in database/migrations.\n");
}

// A fresh install created by schema.sql has the table; a very old one may not.
$hasTable = $db->value(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'migrations'"
);
if ((int) $hasTable === 0) {
    exit("The migrations table is missing. Load database/schema.sql first.\n");
}

$applied = array_column($db->all('SELECT filename FROM migrations'), 'filename');
$pending = array_values(array_filter(
    $files,
    static fn (string $f): bool => !in_array(basename($f), $applied, true),
));

if ($pending === []) {
    echo "Up to date. " . count($applied) . " migration(s) already applied.\n";
    exit(0);
}

echo "Pending:\n";
foreach ($pending as $file) {
    echo '  ' . basename($file) . "\n";
}

if ($listOnly) {
    echo "\nNothing was applied (--list).\n";
    exit(0);
}

echo "\n";

foreach ($pending as $file) {
    $name = basename($file);
    echo "Applying {$name} … ";

    try {
        foreach (statements((string) file_get_contents($file)) as $sql) {
            $db->run($sql);
        }
    } catch (Throwable $e) {
        echo "FAILED\n\n" . $e->getMessage() . "\n\n";
        echo "Nothing after this point was applied. The migrations are written to be\n";
        echo "safe to re-run, so fix the cause and run this again.\n";
        exit(1);
    }

    // The file records this itself, but a migration that forgot to would
    // otherwise run forever.
    $db->run('INSERT IGNORE INTO migrations (filename) VALUES (:f)', ['f' => $name]);
    echo "ok\n";
}

echo "\nDone. Next:  php bin/check.php\n";

/**
 * Splits a file into statements on semicolons that are not inside a string or
 * a comment.
 *
 * Needed because PDO prepares one statement at a time, and these files use
 * PREPARE/EXECUTE blocks to stay idempotent. Naively exploding on ';' breaks
 * the moment a value or a comment contains one.
 *
 * @return array<int,string>
 */
function statements(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = null;       // ' or " while inside a string literal
    $comment = null;     // 'line' or 'block' while inside a comment

    for ($i = 0; $i < $len; $i++) {
        $c    = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($comment === 'line') {
            if ($c === "\n") { $comment = null; $buf .= $c; }
            continue;
        }
        if ($comment === 'block') {
            if ($c === '*' && $next === '/') { $comment = null; $i++; }
            continue;
        }
        if ($quote !== null) {
            $buf .= $c;
            // Backslash escapes the next character; '' is an escaped quote.
            if ($c === '\\' && $next !== '') { $buf .= $next; $i++; continue; }
            if ($c === $quote) {
                if ($next === $quote) { $buf .= $next; $i++; continue; }
                $quote = null;
            }
            continue;
        }

        if ($c === '-' && $next === '-' && ($sql[$i + 2] ?? ' ') === ' ') { $comment = 'line'; $i++; continue; }
        if ($c === '#') { $comment = 'line'; continue; }
        if ($c === '/' && $next === '*') { $comment = 'block'; $i++; continue; }
        if ($c === "'" || $c === '"') { $quote = $c; $buf .= $c; continue; }

        if ($c === ';') {
            if (trim($buf) !== '') { $out[] = trim($buf); }
            $buf = '';
            continue;
        }

        $buf .= $c;
    }

    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }
    return $out;
}
