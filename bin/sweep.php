<?php
declare(strict_types=1);

/**
 * The daily sweep. Run from cron.
 *
 *   php bin/sweep.php              do the work
 *   php bin/sweep.php --dry-run    say what it would do, change nothing
 *
 * Three jobs, in order of how much they matter:
 *
 *  1. Refund listings that never got a quote. The site promises this in
 *     writing, on the pricing page and in the terms, and a promise that only
 *     happens when somebody remembers to run something is not a promise. This
 *     is the code that makes it true.
 *  2. Expire listings past their run.
 *  3. Tidy away checkouts that were abandoned, so the admin's job list shows
 *     real work rather than a month of half-finished attempts.
 *
 * Safe to run twice: every step is driven by a status that the step itself
 * changes, so a second run in the same minute finds nothing left to do.
 *
 * Cron, once a day, a little after midnight Chicago time:
 *   7 6 * * *  cd ~/fixlisted && php bin/sweep.php >> storage/logs/sweep.log 2>&1
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use FixListed\Core\AuditLog;
use FixListed\Core\Config;
use FixListed\Core\Database;
use FixListed\Core\Mailer;
use FixListed\Core\Stripe;
use FixListed\Core\View;

$dryRun = in_array('--dry-run', $argv, true);
$db     = Database::fromConfig();
$stripe = Stripe::fromConfig();
$audit  = new AuditLog($db);
$view   = new View(BASE_PATH . '/app/Views');

printf("\nFix Listed — sweep %s%s\n\n", date('Y-m-d H:i'), $dryRun ? '  (dry run)' : '');

// --- 1. refund the listings nobody answered ---------------------------------
//
// The window is per market, and measured from publication rather than from
// payment: a job that went live late should get its full window.
$unanswered = $db->all(
    "SELECT j.id, j.reference, j.title, j.published_at, u.email, u.first_name,
            p.id AS payment_id, p.stripe_payment_intent, p.amount_cents, m.refund_window_hours
       FROM jobs j
       JOIN markets m ON m.id = j.market_id
       JOIN users u ON u.id = j.user_id
       JOIN payments p ON p.job_id = j.id AND p.kind = 'job_listing' AND p.status = 'succeeded'
      WHERE j.status = 'active'
        AND j.quote_count = 0
        AND m.refund_window_hours > 0
        AND j.published_at <= NOW() - INTERVAL m.refund_window_hours HOUR"
);

echo "1. Listings with no quotes past the refund window: " . count($unanswered) . "\n";

foreach ($unanswered as $job) {
    $label = $job['reference'] . '  ' . mb_substr((string) $job['title'], 0, 44);

    if ($dryRun) {
        echo "   would refund  {$label}  " . money((int) $job['amount_cents']) . "\n";
        continue;
    }

    // A listing paid for at a fee of zero has nothing to refund, but should
    // still come off the board and still tell the homeowner.
    $needsRefund = (int) $job['amount_cents'] > 0 && (string) $job['stripe_payment_intent'] !== '';

    if ($needsRefund && $stripe === null) {
        echo "   SKIPPED       {$label} — Stripe is not configured, so nothing can be refunded\n";
        continue;
    }

    try {
        if ($needsRefund) {
            $stripe->refund(
                (string) $job['stripe_payment_intent'],
                'No quotes within the refund window',
                // Keyed on the payment, so a second run cannot refund twice
                // even if the first run died between the call and the update.
                'refund-job-' . $job['payment_id'],
            );
        }

        // The webhook will also record the refund when it arrives; both write
        // the same values, and whichever lands first is right.
        $db->affected(
            "UPDATE payments
                SET status = 'refunded', refunded_cents = amount_cents,
                    refunded_at = COALESCE(refunded_at, NOW()),
                    refund_reason = 'No quotes within the refund window'
              WHERE id = :id",
            ['id' => $job['payment_id']],
        );
        $db->affected("UPDATE jobs SET status = 'removed' WHERE id = :id", ['id' => $job['id']]);

        $audit->record('job.auto_refunded', null, 'job', (int) $job['id'], [
            'reference' => $job['reference'],
            'amount_cents' => (int) $job['amount_cents'],
        ]);

        tell($view, (string) $job['email'], (string) $job['first_name'], $job);

        echo "   refunded      {$label}  " . money((int) $job['amount_cents']) . "\n";
    } catch (Throwable $e) {
        // Left active so the next run tries again. A listing that stays up
        // one more day is a far smaller problem than one quietly dropped.
        echo "   FAILED        {$label} — " . $e->getMessage() . "\n";
        error_log('Auto-refund failed for ' . $job['reference'] . ': ' . $e->getMessage());
    }
}

// --- 2. expire listings that have run their course --------------------------
$expired = $dryRun
    ? (int) $db->value("SELECT COUNT(*) FROM jobs WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()")
    : $db->affected("UPDATE jobs SET status = 'expired' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()");

echo "\n2. Listings past their run: {$expired}" . ($dryRun ? ' (would expire)' : ' expired') . "\n";

// --- 3. tidy away abandoned checkouts ---------------------------------------
//
// Someone who started posting and never paid. Seven days is long enough that
// nobody is coming back to finish it, and keeping them clutters the admin's
// job list with attempts rather than work.
$abandoned = $dryRun
    ? (int) $db->value("SELECT COUNT(*) FROM jobs WHERE status = 'pending_payment' AND created_at <= NOW() - INTERVAL 7 DAY")
    : $db->affected("UPDATE jobs SET status = 'removed' WHERE status = 'pending_payment' AND created_at <= NOW() - INTERVAL 7 DAY");

echo "3. Abandoned checkouts older than a week: {$abandoned}" . ($dryRun ? ' (would tidy)' : ' tidied') . "\n\n";

/** Tells the homeowner the money is on its way back, before they ask. */
function tell(View $view, string $email, string $name, array $job): void
{
    try {
        Mailer::fromConfig()->send(
            $email,
            'Refunded — nobody quoted ' . $job['reference'],
            $view->render('emails.job_refunded', [
                'title'     => 'We have refunded you',
                'preheader' => 'Nobody quoted your job, so the fee is on its way back.',
                'name'      => $name,
                'jobTitle'  => (string) $job['title'],
                'reference' => (string) $job['reference'],
                'amount'    => money((int) $job['amount_cents']),
                'replyTo'   => (string) Config::get('mail.reply_to', ''),
            ], 'emails.layout'),
            "Nobody quoted \"{$job['title']}\" ({$job['reference']}), so we have refunded "
            . money((int) $job['amount_cents']) . " to the card that paid it.\n\n"
            . "Reply to this email and we will help you get it in front of the right people.\n",
        );
    } catch (Throwable $e) {
        error_log('Refund email failed for ' . $job['reference'] . ': ' . $e->getMessage());
    }
}
