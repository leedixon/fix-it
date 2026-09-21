<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Config;
use FixListed\Core\Database;
use FixListed\Core\Mailer;
use FixListed\Core\Request;
use FixListed\Core\Response;
use FixListed\Core\Stripe;
use FixListed\Core\TenantScope;
use FixListed\Core\View;
use FixListed\Repositories\JobPostingRepository;

/**
 * Stripe's webhook. The only thing that makes a paid job visible.
 *
 * Not a Controller subclass: it renders nothing, has no market resolved from
 * the request, and must not touch the session. It is a machine talking to a
 * machine, and every extra thing it shares with the public site is another
 * thing that can go wrong at the moment money moves.
 *
 * Four rules it follows, each learned the hard way by somebody:
 *
 *  1. Verify the signature before reading the body. Anything else is an
 *     unauthenticated "mark this job paid" endpoint on a public URL.
 *  2. Record the event before processing it, keyed on Stripe's event id. A
 *     retry then finds it already handled instead of doing the work twice.
 *  3. Answer 200 quickly. Stripe times out at 20 seconds and retries, and
 *     sending a dozen emails is not something to do while it waits.
 *  4. Return 200 for anything understood, including events being ignored.
 *     A 500 makes Stripe retry forever over something that was never going
 *     to work.
 */
final class WebhookController
{
    public function __construct(
        private readonly Database $db,
        private readonly Request $request,
        private readonly View $view,
    ) {
    }

    public function stripe(): Response
    {
        $stripe = Stripe::fromConfig();
        if ($stripe === null) {
            error_log('Stripe webhook arrived but no keys are configured.');
            return Response::json(['error' => 'not configured'], 503);
        }

        $payload   = (string) file_get_contents('php://input');
        $signature = (string) ($this->request->server['HTTP_STRIPE_SIGNATURE'] ?? '');

        try {
            $event = $stripe->verifyWebhook($payload, $signature);
        } catch (\Throwable $e) {
            // 400, not 500: the request was bad, and Stripe should not retry
            // something that will fail identically every time.
            error_log('Rejected a Stripe webhook: ' . $e->getMessage());
            return Response::json(['error' => 'signature'], 400);
        }

        $eventId = (string) $event['id'];
        $type    = (string) $event['type'];

        // INSERT IGNORE against a unique index is the whole idempotency
        // mechanism: a duplicate delivery inserts nothing and is acknowledged.
        $inserted = $this->db->affected(
            'INSERT IGNORE INTO webhook_events (stripe_event_id, type, payload) VALUES (:id, :t, :p)',
            ['id' => $eventId, 't' => $type, 'p' => $payload],
        );
        if ($inserted === 0) {
            return Response::json(['ok' => true, 'duplicate' => true]);
        }

        try {
            $handled = match ($type) {
                'checkout.session.completed' => $this->sessionCompleted($event),
                'charge.refunded'            => $this->chargeRefunded($event),
                default                      => null,
            };
        } catch (\Throwable $e) {
            // Recorded as failed and answered 200. A retry would hit the
            // duplicate check above and do nothing, so the useful thing is a
            // row someone can look at rather than a retry storm.
            $this->mark($eventId, 'failed', $e->getMessage());
            error_log('Stripe webhook ' . $eventId . ' (' . $type . ') failed: ' . $e->getMessage());
            return Response::json(['ok' => true, 'stored' => 'failed']);
        }

        $this->mark($eventId, $handled === null ? 'ignored' : 'processed');

        // The response goes out before the emails. Stripe gets its 200 in
        // milliseconds and the sending happens with nobody waiting on it.
        if (is_array($handled)) {
            $this->finishRequest(Response::json(['ok' => true]));
            $this->announce($handled);
            exit;
        }

        return Response::json(['ok' => true]);
    }

    /**
     * @param array<string,mixed> $event
     * @return array<string,mixed>|null the published job, or null if nothing changed
     */
    private function sessionCompleted(array $event): ?array
    {
        $session = $event['data']['object'] ?? [];

        // Stripe allows an unpaid session to complete when a payment method
        // settles later. Only 'paid' publishes anything.
        if (($session['payment_status'] ?? '') !== 'paid') {
            return null;
        }

        $marketId = $this->marketForSession($session);
        if ($marketId === null) {
            throw new \RuntimeException('No job matches session ' . ($session['id'] ?? '?'));
        }

        $repo = new JobPostingRepository($this->db, TenantScope::market($marketId));

        return $repo->completePayment(
            (string) $session['id'],
            (string) ($session['payment_intent'] ?? ''),
            (int) ($session['amount_total'] ?? 0),
        );
    }

    /**
     * A refund happened, here or in the Stripe dashboard.
     *
     * Recorded either way. A refund issued by hand in the dashboard and never
     * reflected here is how the books stop matching.
     *
     * @param array<string,mixed> $event
     */
    private function chargeRefunded(array $event): ?array
    {
        $charge = $event['data']['object'] ?? [];
        $intent = (string) ($charge['payment_intent'] ?? '');
        if ($intent === '') {
            return null;
        }

        $refunded = (int) ($charge['amount_refunded'] ?? 0);
        $total    = (int) ($charge['amount'] ?? 0);

        $this->db->affected(
            "UPDATE payments
                SET status = :status, refunded_cents = :refunded,
                    refunded_at = COALESCE(refunded_at, NOW()),
                    stripe_charge_id = :charge
              WHERE stripe_payment_intent = :intent",
            [
                'status'   => $refunded >= $total ? 'refunded' : 'partially_refunded',
                'refunded' => $refunded,
                'charge'   => (string) ($charge['id'] ?? ''),
                'intent'   => $intent,
            ],
        );

        // A fully refunded listing comes off the board. Leaving it up after
        // giving the money back is the worst of both.
        if ($refunded >= $total) {
            $this->db->affected(
                "UPDATE jobs j
                   JOIN payments p ON p.job_id = j.id
                    SET j.status = 'removed'
                  WHERE p.stripe_payment_intent = :intent AND j.status = 'active'",
                ['intent' => $intent],
            );
        }

        return null;
    }

    /** Which market a session belongs to, via the job its metadata names. */
    private function marketForSession(array $session): ?int
    {
        $reference = (string) ($session['metadata']['job_reference'] ?? '');
        if ($reference !== '') {
            $market = $this->db->value(
                'SELECT market_id FROM jobs WHERE reference = :r LIMIT 1',
                ['r' => $reference],
            );
            if ($market !== null) {
                return (int) $market;
            }
        }

        // Falls back to the payment row, which was written before the visitor
        // ever reached Stripe, so it exists even if metadata went missing.
        $market = $this->db->value(
            'SELECT market_id FROM payments WHERE stripe_checkout_session = :s LIMIT 1',
            ['s' => (string) ($session['id'] ?? '')],
        );

        return $market !== null ? (int) $market : null;
    }

    private function mark(string $eventId, string $status, string $error = ''): void
    {
        $this->db->affected(
            'UPDATE webhook_events
                SET status = :s, error = :e, attempts = attempts + 1, processed_at = NOW()
              WHERE stripe_event_id = :id',
            ['s' => $status, 'e' => mb_substr($error, 0, 255), 'id' => $eventId],
        );
    }

    /** Sends the receipt, and tells the tradespeople who cover that county. */
    private function announce(array $job): void
    {
        try {
            $mailer = Mailer::fromConfig();

            $mailer->send(
                (string) $job['email'],
                'Your job is live — ' . $job['reference'],
                $this->view->render('emails.job_live', [
                    'title'     => 'Your job is live',
                    'preheader' => 'Tradespeople in your county can see it now.',
                    'name'      => (string) $job['first_name'],
                    'jobTitle'  => (string) $job['title'],
                    'reference' => (string) $job['reference'],
                    'jobUrl'    => abs_url('/jobs/' . $job['reference']),
                    'expires'   => (string) ($job['expires_at'] ?? ''),
                ], 'emails.layout'),
                "Your job \"{$job['title']}\" is live.\n\nReference: {$job['reference']}\n"
                . abs_url('/jobs/' . $job['reference']) . "\n",
            );

            $repo = new JobPostingRepository($this->db, TenantScope::market(
                (int) $this->db->value('SELECT market_id FROM jobs WHERE id = :i', ['i' => $job['id']])
            ));

            foreach ($repo->prosToNotify((int) $job['county_id'], (int) $job['trade_id']) as $pro) {
                $mailer->send(
                    (string) $pro['email'],
                    'New ' . $job['trade_name'] . ' job in ' . ($job['city_name'] ?? 'your area'),
                    $this->view->render('emails.job_alert', [
                        'title'     => 'A job you can quote',
                        'preheader' => (string) $job['title'],
                        'name'      => (string) $pro['first_name'],
                        'jobTitle'  => (string) $job['title'],
                        'trade'     => (string) $job['trade_name'],
                        'city'      => (string) ($job['city_name'] ?? ''),
                        'summary'   => excerpt((string) $job['description'], 220),
                        'quoteUrl'  => abs_url('/my/quote/' . $job['reference']),
                    ], 'emails.layout'),
                    "New {$job['trade_name']} job: {$job['title']}\n\n"
                    . abs_url('/my/quote/' . $job['reference']) . "\n",
                );
            }
        } catch (\Throwable $e) {
            // The money is taken and the job is live. A failed email is worth
            // a log line, never an exception that unwinds a completed payment.
            error_log('Could not announce job ' . ($job['reference'] ?? '?') . ': ' . $e->getMessage());
        }
    }

    private function finishRequest(Response $response): void
    {
        $response->send();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
}
