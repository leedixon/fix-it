<?php
declare(strict_types=1);

namespace FixListed\Core;

use RuntimeException;

/**
 * Minimal Stripe client — Checkout sessions, refunds, and webhook signatures.
 *
 * Hand-rolled for the same reason the SMTP client is: shared hosting with no
 * Composer. Only the handful of calls this site makes are here, and the
 * official library is a drop-in replacement the day a dependency manager
 * exists.
 *
 * Two things worth knowing about Stripe's API, because both bite:
 *
 *  - It takes form encoding, not JSON. Nested values use bracket syntax
 *    (line_items[0][price_data][unit_amount]), which http_build_query
 *    produces from a nested array for free.
 *  - Money is an integer in the smallest currency unit. So is every amount in
 *    this application, which is not a coincidence.
 *
 * The connection is ordinary HTTPS on port 443 — the one outbound port this
 * host does not interfere with. See MailApi for what happens on the ports it
 * does.
 */
final class Stripe
{
    private const BASE = 'https://api.stripe.com/v1/';

    /** Stripe rejects a signature older than this, and so do we. */
    private const SIGNATURE_TOLERANCE = 300;

    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret = '',
        private readonly int $timeout = 20,
        /**
         * Overridable so the flow can be exercised end to end against a local
         * stub, the way Stripe's own libraries allow. Defaults to the real
         * API and is never set in production config.
         */
        private readonly string $apiBase = self::BASE,
    ) {
    }

    public static function fromConfig(): ?self
    {
        $key = (string) Config::get('stripe.secret_key', '');
        if ($key === '') {
            return null;
        }
        return new self(
            $key,
            (string) Config::get('stripe.webhook_secret', ''),
            20,
            (string) Config::get('stripe.api_base', self::BASE),
        );
    }

    /** True when live keys are in use, so the UI can say so honestly. */
    public function isLive(): bool
    {
        return str_starts_with($this->secretKey, 'sk_live_');
    }

    /**
     * Creates a Checkout session and returns it.
     *
     * $idempotencyKey makes a retry safe: the same key returns the original
     * session rather than charging twice. It is the job reference, so a
     * homeowner who double-clicks or reloads gets one session and one charge.
     *
     * @param array<string,mixed> $metadata travels with the payment and comes
     *        back on the webhook — this is how the webhook knows which job
     *        a payment belongs to without trusting anything in the URL.
     * @return array<string,mixed>
     */
    public function createCheckoutSession(
        int $amountCents,
        string $productName,
        string $description,
        string $successUrl,
        string $cancelUrl,
        string $customerEmail,
        array $metadata,
        string $idempotencyKey,
    ): array {
        return $this->post('checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'customer_email' => $customerEmail,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => $productName,
                        'description' => mb_substr($description, 0, 200),
                    ],
                ],
            ]],
            // On both the session and the payment intent: the webhook for a
            // session carries session metadata, and a refund or dispute later
            // is found through the intent.
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'expires_at' => time() + 1800,
        ], $idempotencyKey);
    }

    /**
     * Creates a Checkout session for a monthly placement subscription.
     *
     * Built from inline price_data like the one-off above, so there are no
     * Product or Price objects to create in the dashboard and keep in step
     * with the market's own pricing. The market row is the single source of
     * what a plan costs; Stripe is told the number at checkout.
     *
     * customer_creation is left to Stripe: a subscription always produces a
     * customer, and that id is what later opens the billing portal.
     *
     * @param array<string,mixed> $metadata carried onto the subscription, so
     *        every invoice webhook that follows can be traced back to a pro
     *        without a lookup table.
     * @return array<string,mixed>
     */
    public function createSubscriptionSession(
        int $amountCents,
        string $planName,
        string $description,
        string $successUrl,
        string $cancelUrl,
        string $customerEmail,
        array $metadata,
        string $idempotencyKey,
        string $existingCustomerId = '',
    ): array {
        $params = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $amountCents,
                    'recurring' => ['interval' => 'month'],
                    'product_data' => [
                        'name' => $planName,
                        'description' => mb_substr($description, 0, 200),
                    ],
                ],
            ]],
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
        ];

        // A pro who cancelled and came back already has a customer. Reusing it
        // keeps one billing history and one portal, instead of a second
        // customer with the same email that nobody can reconcile later.
        if ($existingCustomerId !== '') {
            $params['customer'] = $existingCustomerId;
        } else {
            $params['customer_email'] = $customerEmail;
        }

        return $this->post('checkout/sessions', $params, $idempotencyKey);
    }

    /**
     * A link into Stripe's own billing portal.
     *
     * Cancelling, changing a card and downloading invoices all happen there.
     * Building those screens here would mean holding card details, PCI scope
     * and a second implementation of everything Stripe already does.
     *
     * @return array<string,mixed>
     */
    public function billingPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->post('billing_portal/sessions', [
            'customer'   => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    /** @return array<string,mixed> */
    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->get('subscriptions/' . rawurlencode($subscriptionId));
    }

    /** @return array<string,mixed> */
    public function retrieveInvoice(string $invoiceId): array
    {
        return $this->get('invoices/' . rawurlencode($invoiceId));
    }

    /**
     * Ends a subscription now, not at the end of the period.
     *
     * Used for one thing: undoing a placement that was paid for but could not
     * be granted because the last slot sold first. Everything a pro chooses to
     * cancel goes through Stripe's own portal instead.
     *
     * @return array<string,mixed>
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->request(
            'DELETE',
            'subscriptions/' . rawurlencode($subscriptionId),
            null,
            [],
        );
    }

    /** @return array<string,mixed> */
    public function retrieveSession(string $sessionId): array
    {
        return $this->get('checkout/sessions/' . rawurlencode($sessionId));
    }

    /**
     * Refunds a payment in full.
     *
     * Keyed on the payment intent so the sweep that refunds unanswered jobs
     * cannot double-refund one if it runs twice.
     *
     * @return array<string,mixed>
     */
    public function refund(string $paymentIntentId, string $reason, string $idempotencyKey): array
    {
        return $this->post('refunds', [
            'payment_intent' => $paymentIntentId,
            // Stripe's own vocabulary is narrow; the real reason goes in
            // metadata where it stays readable.
            'reason' => 'requested_by_customer',
            'metadata' => ['fixlisted_reason' => mb_substr($reason, 0, 400)],
        ], $idempotencyKey);
    }

    /**
     * Verifies a webhook signature and returns the decoded event.
     *
     * This is the only thing standing between the endpoint and anybody who
     * can POST to a public URL. Without it, "mark this job paid" is an
     * unauthenticated request.
     *
     * @throws RuntimeException when the signature is missing, stale or wrong
     * @return array<string,mixed>
     */
    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        if ($this->webhookSecret === '') {
            throw new RuntimeException('No webhook secret is configured, so no event can be trusted.');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = (int) $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new RuntimeException('Malformed Stripe-Signature header.');
        }

        // A replayed event from days ago must not be accepted, even with a
        // signature that was genuine at the time.
        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            throw new RuntimeException('Signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);

        $matched = false;
        foreach ($signatures as $candidate) {
            // Constant time, and every candidate is checked: bailing early on
            // the first match leaks timing, and Stripe sends more than one
            // during a secret rotation.
            if (hash_equals($expected, $candidate)) {
                $matched = true;
            }
        }
        if (!$matched) {
            throw new RuntimeException('Signature does not match the payload.');
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || !isset($event['id'], $event['type'])) {
            throw new RuntimeException('Event body is not a Stripe event.');
        }

        return $event;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function post(string $path, array $params, string $idempotencyKey = ''): array
    {
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        return $this->request('POST', $path, http_build_query($params, '', '&', PHP_QUERY_RFC3986), $headers);
    }

    /** @return array<string,mixed> */
    private function get(string $path): array
    {
        return $this->request('GET', $path, null, []);
    }

    /**
     * @param array<int,string> $headers
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The curl extension is required to reach Stripe.');
        }

        $ch = curl_init($this->apiBase . $path);
        if ($ch === false) {
            throw new RuntimeException('curl could not be initialised.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER     => array_merge([
                'Authorization: Bearer ' . $this->secretKey,
                'Stripe-Version: 2024-06-20',
            ], $headers),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Could not reach Stripe' . ($error !== '' ? ': ' . $error : '') . '.');
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Stripe returned something that was not JSON (HTTP ' . $status . ').');
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message'] ?? 'Unknown error';
            $type    = $decoded['error']['type'] ?? '';
            // The key itself is never in the message, but the log is not a
            // place to be careless either.
            throw new RuntimeException('Stripe refused the request (' . $status
                . ($type !== '' ? ', ' . $type : '') . '): ' . $message);
        }

        return $decoded;
    }
}
