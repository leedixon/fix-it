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
use FixListed\Repositories\AdvertisingRepository;
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
                'checkout.session.completed'    => $this->sessionCompleted($event),
                'charge.refunded'               => $this->chargeRefunded($event),
                'invoice.paid'                  => $this->invoicePaid($event),
                'invoice.payment_failed'        => $this->invoiceFailed($event),
                'customer.subscription.updated' => $this->subscriptionUpdated($event),
                'customer.subscription.deleted' => $this->subscriptionDeleted($event),
                default                         => null,
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

        // Two different things arrive on this event: a homeowner paying the
        // listing fee, and a tradesperson starting a monthly placement. The
        // mode tells them apart, and metadata confirms it — neither alone,
        // because a future product would break whichever we trusted.
        if (($session['mode'] ?? '') === 'subscription'
            || ($session['metadata']['kind'] ?? '') === 'placement') {
            $this->placementStarted($session);
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

    /**
     * A tradesperson has paid for placement.
     *
     * The capacity check happens here, inside the activation transaction,
     * rather than only on the page that sold it: two pros can reach Stripe
     * for the last slot within the same second, and both will be charged. The
     * one who loses gets their money back automatically — an apology and a
     * refund beats a slot that was already gone.
     *
     * @param array<string,mixed> $session
     */
    private function placementStarted(array $session): void
    {
        $meta     = $session['metadata'] ?? [];
        $localId  = (int) ($meta['subscription_id'] ?? 0);
        $marketId = (int) ($meta['market_id'] ?? 0);
        $stripeId = (string) ($session['subscription'] ?? '');

        if ($localId < 1 || $marketId < 1 || $stripeId === '') {
            throw new \RuntimeException('Placement session ' . ($session['id'] ?? '?') . ' is missing metadata.');
        }

        $market = $this->db->one('SELECT * FROM markets WHERE id = :id', ['id' => $marketId]);
        if ($market === null) {
            throw new \RuntimeException('Placement session names market ' . $marketId . ', which does not exist.');
        }

        $plan = (string) ($meta['plan'] ?? 'boost');
        $ads  = new AdvertisingRepository($this->db, TenantScope::market($marketId));

        // The session says nothing about what was paid for through when, so
        // the subscription is read back for its period. If that call fails
        // the placement still goes live with an unknown period — the money
        // arrived, and a missing date is not a reason to withhold what was
        // bought. invoice.paid fills it in at the first renewal regardless.
        [$periodStart, $periodEnd] = $this->periodOf($stripeId);

        $granted = $ads->activate(
            $localId,
            $stripeId,
            (string) ($session['customer'] ?? ''),
            $periodStart,
            $periodEnd,
            (int) ($market[$plan . '_slots'] ?? 0),
        );

        if (!$granted) {
            $ads->abandon($localId, 'no ' . $plan . ' slot was free');
            $this->undoOversold($session, $stripeId, $plan, $marketId);
        }
    }

    /**
     * What a subscription is paid up through, asked of Stripe directly.
     *
     * @return array{0:?string,1:?string}
     */
    private function periodOf(string $stripeSubscriptionId): array
    {
        try {
            $stripe = Stripe::fromConfig();
            if ($stripe === null) {
                return [null, null];
            }
            $sub = $stripe->retrieveSubscription($stripeSubscriptionId);

            return [
                isset($sub['current_period_start'])
                    ? gmdate('Y-m-d H:i:s', (int) $sub['current_period_start']) : null,
                isset($sub['current_period_end'])
                    ? gmdate('Y-m-d H:i:s', (int) $sub['current_period_end']) : null,
            ];
        } catch (\Throwable $e) {
            error_log('Could not read the period for ' . $stripeSubscriptionId . ': ' . $e->getMessage());
            return [null, null];
        }
    }

    /**
     * Money taken for a slot that was gone. Cancel and refund, in that order.
     *
     * Cancelling first stops the next month being billed even if the refund
     * fails; a failed refund is a line in the log that a person can act on,
     * a subscription nobody cancelled is a charge every month forever.
     *
     * @param array<string,mixed> $session
     */
    private function undoOversold(
        array $session,
        string $stripeSubscriptionId,
        string $plan,
        int $marketId,
    ): void {
        error_log(sprintf(
            'Placement oversold: %s paid for %s but no slot was free. Cancelling and refunding.',
            $session['id'] ?? '?',
            $plan,
        ));

        $stripe = Stripe::fromConfig();
        if ($stripe === null) {
            return;
        }

        try {
            $stripe->cancelSubscription($stripeSubscriptionId);
        } catch (\Throwable $e) {
            error_log('Could not cancel oversold subscription ' . $stripeSubscriptionId . ': ' . $e->getMessage());
        }

        try {
            $invoiceId = (string) ($session['invoice'] ?? '');
            if ($invoiceId === '') {
                return;
            }
            $invoice = $stripe->retrieveInvoice($invoiceId);
            $intent  = (string) ($invoice['payment_intent'] ?? '');
            if ($intent !== '') {
                $stripe->refund(
                    $intent,
                    'Placement slot was already taken',
                    'oversold-' . $stripeSubscriptionId,
                );
            }
        } catch (\Throwable $e) {
            error_log('Could not refund oversold subscription ' . $stripeSubscriptionId . ': ' . $e->getMessage());
        }

        $this->alertOperator($session, $plan, $marketId);
    }

    /**
     * Tells a person that somebody's money came back.
     *
     * The refund is automatic; the apology is not. Whoever runs the market
     * should be the one to reach the tradesperson, before the tradesperson
     * reaches them wondering what happened.
     *
     * @param array<string,mixed> $session
     */
    private function alertOperator(array $session, string $plan, int $marketId): void
    {
        $to = (string) Config::get('mail.alert_to', '');
        if ($to === '') {
            return;
        }

        $pro = $this->db->one(
            'SELECT u.email, u.first_name, u.last_name, p.business_name
               FROM pro_profiles p JOIN users u ON u.id = p.user_id
              WHERE p.id = :id AND p.market_id = :market LIMIT 1',
            ['id' => (int) ($session['metadata']['pro_id'] ?? 0), 'market' => $marketId],
        ) ?? [];

        $who = trim((string) ($pro['business_name'] ?? '')) !== ''
            ? (string) $pro['business_name']
            : trim(($pro['first_name'] ?? '') . ' ' . ($pro['last_name'] ?? ''));

        $text = "A tradesperson paid for a {$plan} placement and every slot was already taken.\n\n"
              . 'Who: ' . ($who !== '' ? $who : 'unknown') . ' <' . ($pro['email'] ?? 'unknown') . ">\n"
              . 'Stripe session: ' . ($session['id'] ?? '?') . "\n\n"
              . "The subscription has been cancelled and the payment refunded automatically.\n"
              . "Please get in touch with them before they get in touch with you.\n";

        try {
            Mailer::fromConfig()->send(
                $to,
                'Placement oversold — refunded automatically',
                '<pre style="font:14px/1.6 monospace">' . e($text) . '</pre>',
                $text,
            );
        } catch (\Throwable $e) {
            error_log('Could not send the oversold alert: ' . $e->getMessage());
        }
    }

    /**
     * A monthly invoice was paid: the first one, or a renewal.
     *
     * This is what actually keeps a placement up. The checkout event grants
     * it once; every month after that, this is the only proof the money
     * arrived, and a subscription that went past_due comes back here.
     *
     * @param array<string,mixed> $event
     */
    private function invoicePaid(array $event): ?array
    {
        $invoice  = $event['data']['object'] ?? [];
        $stripeId = $this->subscriptionIdOf($invoice);
        if ($stripeId === '') {
            return null;
        }

        [$marketId, $row] = $this->placementFor($stripeId);
        if ($row === null) {
            return null;
        }

        $ads    = new AdvertisingRepository($this->db, TenantScope::market($marketId));
        $market = $this->db->one('SELECT * FROM markets WHERE id = :id', ['id' => $marketId]) ?? [];
        $period = $invoice['lines']['data'][0]['period'] ?? [];

        $ads->activate(
            (int) $row['id'],
            $stripeId,
            (string) ($invoice['customer'] ?? ''),
            isset($period['start']) ? gmdate('Y-m-d H:i:s', (int) $period['start']) : null,
            isset($period['end'])   ? gmdate('Y-m-d H:i:s', (int) $period['end'])   : null,
            (int) ($market[(string) $row['plan'] . '_slots'] ?? 0),
        );

        return null;
    }

    /**
     * A card was declined. The listing stays up.
     *
     * Stripe retries a failed payment for two weeks, and most of those
     * succeed. Pulling a paying tradesperson off the page over an expired
     * card is how you lose the customer rather than collect the payment —
     * customer.subscription.deleted is what ends a placement.
     *
     * @param array<string,mixed> $event
     */
    private function invoiceFailed(array $event): ?array
    {
        $stripeId = $this->subscriptionIdOf($event['data']['object'] ?? []);
        if ($stripeId === '') {
            return null;
        }

        [$marketId, $row] = $this->placementFor($stripeId);
        if ($row !== null) {
            (new AdvertisingRepository($this->db, TenantScope::market($marketId)))->markPastDue($stripeId);
        }

        return null;
    }

    /**
     * Something changed in Stripe's portal. The one thing worth recording
     * here is a pending cancellation, so the pro's own screen agrees with
     * what they just did.
     *
     * @param array<string,mixed> $event
     */
    private function subscriptionUpdated(array $event): ?array
    {
        $sub      = $event['data']['object'] ?? [];
        $stripeId = (string) ($sub['id'] ?? '');
        if ($stripeId === '') {
            return null;
        }

        [$marketId, $row] = $this->placementFor($stripeId);
        if ($row === null) {
            return null;
        }

        $ads = new AdvertisingRepository($this->db, TenantScope::market($marketId));
        $ads->markCancelAtPeriodEnd($stripeId, !empty($sub['cancel_at_period_end']));

        // Stripe can exhaust its retries and mark a subscription unpaid
        // without deleting it. That is the end of the placement too.
        if (in_array((string) ($sub['status'] ?? ''), ['canceled', 'unpaid'], true)) {
            $ads->endSubscription($stripeId);
        }

        return null;
    }

    /**
     * The subscription is over. The slot goes back on sale.
     *
     * @param array<string,mixed> $event
     */
    private function subscriptionDeleted(array $event): ?array
    {
        $stripeId = (string) ($event['data']['object']['id'] ?? '');
        if ($stripeId === '') {
            return null;
        }

        [$marketId, $row] = $this->placementFor($stripeId);
        if ($row !== null) {
            (new AdvertisingRepository($this->db, TenantScope::market($marketId)))->endSubscription($stripeId);
        }

        return null;
    }

    /**
     * An invoice names its subscription in one of two places depending on the
     * API version, so both are read.
     *
     * @param array<string,mixed> $invoice
     */
    private function subscriptionIdOf(array $invoice): string
    {
        $direct = $invoice['subscription'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }
        if (is_array($direct) && isset($direct['id'])) {
            return (string) $direct['id'];
        }
        return (string) ($invoice['parent']['subscription_details']['subscription'] ?? '');
    }

    /**
     * Finds the local subscription behind a Stripe id, and the market it is in.
     *
     * The market is read from the row rather than resolved from the request,
     * because a webhook has no request to resolve one from — the same reason
     * every other lookup in this class works this way.
     *
     * @return array{0:int,1:array<string,mixed>|null}
     */
    private function placementFor(string $stripeSubscriptionId): array
    {
        $row = $this->db->one(
            'SELECT id, market_id, pro_id, plan FROM subscriptions
              WHERE stripe_subscription_id = :sub LIMIT 1',
            ['sub' => $stripeSubscriptionId],
        );

        return [$row === null ? 0 : (int) $row['market_id'], $row];
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
