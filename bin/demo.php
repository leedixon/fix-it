<?php
declare(strict_types=1);

/**
 * Controls the invented seed listings.
 *
 *   php bin/demo.php status     what is in the database and which mode is on
 *   php bin/demo.php purge      delete every seeded row, permanently
 *
 * The label/hide switch itself lives in config/config.php as app.demo_data,
 * because it has to be readable by the web request. This script is for the
 * part a config value cannot do: telling you what is actually in there, and
 * removing it when the real listings arrive.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use FixListed\Core\Config;
use FixListed\Core\Database;
use FixListed\Core\Demo;

$command = $argv[1] ?? 'status';
$db      = Database::fromConfig();

$counts = static function (Database $db): array {
    $out = [];
    foreach (['users', 'pro_profiles', 'jobs', 'reviews'] as $table) {
        $out[$table] = [
            'demo' => (int) $db->value("SELECT COUNT(*) FROM {$table} WHERE is_demo = 1"),
            'real' => (int) $db->value("SELECT COUNT(*) FROM {$table} WHERE is_demo = 0"),
        ];
    }
    return $out;
};

if ($command === 'status') {
    echo "\nMode: " . Demo::mode() . "\n";
    echo Demo::isVisible()
        ? "  Seeded listings are SHOWN, each one labelled 'Sample'.\n"
        : "  Seeded listings are HIDDEN from every page.\n";
    echo "  Change it with app.demo_data in config/config.php ('label' or 'hide').\n\n";

    printf("  %-14s %8s %8s\n", '', 'sample', 'real');
    foreach ($counts($db) as $table => $n) {
        printf("  %-14s %8d %8d\n", $table, $n['demo'], $n['real']);
    }
    echo "\n";

    $realPros = $counts($db)['pro_profiles']['real'];
    if ($realPros > 0 && Demo::isVisible()) {
        echo "You have {$realPros} real profile(s). Once there are enough to fill a page,\n";
        echo "switch app.demo_data to 'hide', confirm the site still reads well, then:\n";
        echo "  php bin/demo.php purge\n\n";
    }
    exit(0);
}

if ($command !== 'purge') {
    fwrite(STDERR, "Unknown command '{$command}'. Use: status | purge\n");
    exit(1);
}

// --- purge -----------------------------------------------------------------

$before = $counts($db);
$total  = array_sum(array_column($before, 'demo'));

if ($total === 0) {
    echo "Nothing to purge — no rows are flagged as sample data.\n";
    exit(0);
}

echo "\nThis will permanently delete:\n";
foreach ($before as $table => $n) {
    if ($n['demo'] > 0) {
        printf("  %-14s %d row(s)\n", $table, $n['demo']);
    }
}
echo "\nRows not flagged as sample data are left alone:\n";
foreach ($before as $table => $n) {
    printf("  %-14s %d real row(s) kept\n", $table, $n['real']);
}

echo "\nThis cannot be undone. Type the word 'purge' to confirm: ";
if (trim((string) fgets(STDIN)) !== 'purge') {
    echo "Cancelled. Nothing was deleted.\n";
    exit(0);
}

// Order matters even with cascades: deleting the users last means a row whose
// owner is gone can never be left behind pointing at nothing.
$db->transaction(static function (Database $db): void {
    $db->affected('DELETE r FROM reviews r JOIN pro_profiles p ON p.id = r.pro_id WHERE p.is_demo = 1');
    $db->affected('DELETE FROM reviews WHERE is_demo = 1');
    $db->affected('DELETE FROM jobs WHERE is_demo = 1');
    $db->affected('DELETE FROM pro_profiles WHERE is_demo = 1');
    $db->affected('DELETE FROM users WHERE is_demo = 1');
});

echo "\nPurged.\n\n";
foreach ($counts($db) as $table => $n) {
    printf("  %-14s %d sample, %d real\n", $table, $n['demo'], $n['real']);
}
echo "\nNext: set app.demo_data to 'hide' in config/config.php so the sample\n";
echo "banner stops appearing, then  php bin/check.php\n\n";
