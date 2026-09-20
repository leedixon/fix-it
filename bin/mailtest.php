<?php
declare(strict_types=1);

/**
 * Diagnoses "I signed up and got no email".
 *
 *   php bin/mailtest.php you@example.com
 *
 * Three separate things can fail and they need telling apart: the form may
 * not have saved, mail() may be refusing to hand off, or the message may be
 * sent and landing in spam. This reports each one rather than guessing.
 */

require __DIR__ . '/../app/bootstrap.php';

use FixListed\Core\Config;
use FixListed\Core\Database;
use FixListed\Core\Mailer;
use FixListed\Core\View;

$to = $argv[1] ?? (string) Config::get('mail.alert_to', '');
if ($to === '') {
    exit("Usage: php bin/mailtest.php you@example.com\n");
}

echo "\n--- 1. did the signup save? ---\n";
try {
    $rows = Database::fromConfig()->all(
        'SELECT created_at, role, name, email, trade, counties FROM waitlist ORDER BY id DESC LIMIT 5'
    );
    if ($rows === []) {
        echo "  NO ROWS. The form never reached the database — this is not a mail problem.\n";
        echo "  Check the browser console on fixlisted.com when submitting, and\n";
        echo "  confirm signup.php is present in the document root.\n";
    } else {
        foreach ($rows as $r) {
            printf("  %s  %-10s %-20s %s\n", $r['created_at'], $r['role'], $r['name'], $r['email']);
        }
    }
} catch (Throwable $e) {
    echo '  database error: ' . $e->getMessage() . "\n";
}

echo "\n--- 2. what addresses are configured? ---\n";
printf("  from:     %s\n", (string) Config::get('mail.from_address'));
printf("  alert to: %s\n", (string) Config::get('mail.alert_to'));
printf("  testing:  %s\n", $to);
printf("  transport: %s\n", Mailer::fromConfig()->transport());

echo "\n--- 3. will the message actually send? ---\n";
$plain = @mail($to, 'Fix Listed plain test', "If you are reading this, mail() works.\n",
    'From: ' . Config::get('mail.from_address'));
echo '  bare mail(): ' . ($plain ? 'accepted' : 'REFUSED — the local mail system rejected it') . "\n";

$mailer = Mailer::fromConfig();
$view = new View(dirname(__DIR__) . '/app/Views');
$html = $view->render('emails.waitlist_pro', [
    'title' => 'Test — founding list',
    'preheader' => 'Delivery test from bin/mailtest.php',
    'name' => 'Test Person',
    'trade' => 'Plumbing',
    'counties' => 'Winnebago, Stephenson',
    'replyTo' => (string) Config::get('mail.from_address'),
], 'emails.layout');

$sent = $mailer->send($to, 'Fix Listed — delivery test', $html, "Delivery test.\n");
echo '  templated send: ' . ($sent ? 'accepted' : 'REFUSED') . "\n";

echo "\n--- 4. where errors are recorded ---\n";
$log = ini_get('error_log');
echo '  php error_log: ' . ($log !== '' && $log !== false ? $log : '(not set — cPanel usually writes error_log in the document root)') . "\n";

echo "\n";
echo "'accepted' only means PHP handed the message off. It does NOT mean anyone\n";
echo "received it. If nothing arrives, open cPanel > Email > Track Delivery and\n";
echo "read the 'Delivered To' column for these addresses:\n\n";
echo "  :blackhole:            the address is an ALIAS THAT DISCARDS MAIL, not a\n";
echo "                         mailbox. Everything sent to it is accepted and then\n";
echo "                         thrown away — including replies, which matters because\n";
echo "                         the signup emails ask people to reply. Fix it in\n";
echo "                         cPanel > Email Accounts by creating a real mailbox.\n";
echo "  dovecot_virtual_delivery   a real mailbox. Check it via cPanel webmail.\n";
echo "  mailchannels_smtp      it left the server for an outside provider. If it\n";
echo "                         never arrives, it is spam filtering — check the spam\n";
echo "                         folder, then SPF and DKIM for the sending domain.\n\n";
