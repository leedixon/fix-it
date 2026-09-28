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

/*
 * Every table the seed writes transactional rows into — not just the four a
 * visitor sees.
 *
 * This listed users, pro_profiles, jobs and reviews only, so on a database
 * holding 104 rows of seeded payments, subscriptions, placements and stats it
 * printed "0 sample" and purge answered "Nothing to purge". The report was
 * true about the four tables it looked at and wrong about the database.
 */
$counts = static function (Database $db): array {
    $out = [];
    foreach ([
        'users', 'pro_profiles', 'jobs', 'reviews',
        'payments', 'subscriptions', 'quotes',
        'ad_placements', 'ad_creatives', 'ad_stats_daily', 'moderation_items',
        'pro_photos',
    ] as $table) {
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

    printf("  %-17s %8s %8s\n", '', 'sample', 'real');
    foreach ($counts($db) as $table => $n) {
        printf("  %-17s %8d %8d\n", $table, $n['demo'], $n['real']);
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

// The seeded superadmin is flagged as sample data, because it is: its password
// is published in seed.sql. Deleting it is correct — but only once a real one
// exists, or this command locks you out of your own back end.
$realAdmins = (int) $db->value(
    "SELECT COUNT(*) FROM users
      WHERE role IN ('superadmin','market_admin') AND is_demo = 0 AND status = 'active'"
);
$demoAdmins = (int) $db->value(
    "SELECT COUNT(*) FROM users WHERE role IN ('superadmin','market_admin') AND is_demo = 1"
);

if ($demoAdmins > 0 && $realAdmins === 0) {
    fwrite(STDERR, "\nSTOPPED. The only administrator accounts are sample ones, and purging\n");
    fwrite(STDERR, "would delete them — locking you out of /admin with no way back in.\n\n");
    fwrite(STDERR, "Make a real one first:\n\n  php bin/admin.php\n\nThen run this again.\n\n");
    exit(1);
}

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

/*
 * Order matters even with cascades: deleting the users last means a row whose
 * owner is gone can never be left behind pointing at nothing.
 *
 * The money tables come first, and they were missing entirely until the admin
 * dashboard was caught reporting $496 of revenue on a site that had sold
 * nothing. This command deleted the demo pros and jobs and left their
 * payments, subscriptions, placements, creatives, stats and quotes behind —
 * so "purged" left a hundred rows of invented activity feeding every figure
 * an owner reads. Migration 009 gave those tables the flag; this deletes on
 * it.
 *
 * ad_stats_daily before ad_placements before subscriptions, because each
 * points at the one after it.
 */
$db->transaction(static function (Database $db): void {
    $db->affected('DELETE FROM pro_photos WHERE is_demo = 1');
    $db->affected('DELETE FROM ad_stats_daily WHERE is_demo = 1');
    $db->affected('DELETE FROM ad_placements WHERE is_demo = 1');
    $db->affected('DELETE FROM ad_creatives WHERE is_demo = 1');
    $db->affected('DELETE FROM payments WHERE is_demo = 1');
    $db->affected('DELETE FROM subscriptions WHERE is_demo = 1');
    $db->affected('DELETE FROM quotes WHERE is_demo = 1');
    $db->affected('DELETE FROM moderation_items WHERE is_demo = 1');

    $db->affected('DELETE r FROM reviews r JOIN pro_profiles p ON p.id = r.pro_id WHERE p.is_demo = 1');
    $db->affected('DELETE FROM reviews WHERE is_demo = 1');
    $db->affected('DELETE FROM jobs WHERE is_demo = 1');
    $db->affected('DELETE FROM pro_profiles WHERE is_demo = 1');
    $db->affected('DELETE FROM users WHERE is_demo = 1');
});

echo "\nPurged.\n\n";
foreach ($counts($db) as $table => $n) {
    printf("  %-17s %d sample, %d real\n", $table, $n['demo'], $n['real']);
}
echo "\nNext: set app.demo_data to 'hide' in config/config.php so the sample\n";
echo "banner stops appearing, then  php bin/check.php\n\n";
