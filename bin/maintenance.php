<?php
declare(strict_types=1);

/**
 * Takes the site down and puts it back up.
 *
 *   php bin/maintenance.php status
 *   php bin/maintenance.php on
 *   php bin/maintenance.php on --message="Back by 3pm — upgrading the jobs board."
 *   php bin/maintenance.php off
 *
 * This is the one that works when nothing else does. It needs no database, no
 * session and no running web application — which is the state you will be in
 * on the day you reach for it. The admin screen at /admin/maintenance does the
 * same thing when the site is healthy enough to load it.
 *
 * While it is on, visitors get a 503 with Retry-After and a short page saying
 * the site is back shortly. Signed-in administrators see the site as normal,
 * so you can check a deploy before letting the public back in.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use FixListed\Core\Maintenance;

$command = $argv[1] ?? 'status';

$message = '';
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--message=')) {
        $message = substr($arg, 10);
    }
}

$who = trim((string) (getenv('USER') ?: getenv('LOGNAME') ?: '')) ?: 'cli';

echo "\n";

switch ($command) {
    case 'status':
        report();
        break;

    case 'on':
        if (Maintenance::isOn()) {
            echo "Already down for maintenance.\n";
            report();
            break;
        }
        if (!Maintenance::on($message, $who)) {
            fwrite(STDERR, "Could not write " . Maintenance::file() . "\n");
            fwrite(STDERR, "Check that storage/ is writable by this user.\n\n");
            exit(1);
        }
        echo "The site is now DOWN for maintenance.\n";
        echo "  Visitors get a 503 and the 'back shortly' page.\n";
        echo "  Signed-in administrators still see the whole site.\n";
        report();
        break;

    case 'off':
        if (!Maintenance::isOn()) {
            echo "The site is already up. Nothing to do.\n\n";
            break;
        }
        if (!Maintenance::off()) {
            fwrite(STDERR, "Could not remove " . Maintenance::file() . "\n");
            fwrite(STDERR, "Delete that file by hand and the site comes straight back up.\n\n");
            exit(1);
        }
        echo "The site is back UP. Visitors are being served normally again.\n\n";
        break;

    default:
        fwrite(STDERR, "Unknown command '{$command}'.\n\n");
        fwrite(STDERR, "  php bin/maintenance.php status\n");
        fwrite(STDERR, "  php bin/maintenance.php on [--message=\"...\"]\n");
        fwrite(STDERR, "  php bin/maintenance.php off\n\n");
        exit(1);
}

function report(): void
{
    $state = Maintenance::state();

    if ($state === null) {
        echo "Status: UP — the site is being served normally.\n\n";
        return;
    }

    echo "\nStatus: DOWN for maintenance\n";
    echo "  Since:   " . ($state['started_at'] !== '' ? $state['started_at'] : 'unknown')
       . ' (' . Maintenance::runningFor() . ")\n";
    echo "  Put on by: " . ($state['by'] !== '' ? $state['by'] : 'unknown') . "\n";
    echo "  Visitors see: " . $state['message'] . "\n";
    echo "  Switch file:  " . Maintenance::file() . "\n\n";
    echo "  php bin/maintenance.php off    # to bring it back up\n\n";
}
