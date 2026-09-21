<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Database;
use FixListed\Core\Repository;

/**
 * Creating a job, and the payment that makes it visible.
 *
 * A job is born 'pending_payment' and nothing public reads it in that state.
 * Only the Stripe webhook moves it to 'active'. That ordering is the whole
 * design: the return URL the browser lands on after Checkout is a claim by
 * the browser, and a browser can be told to load any URL by anyone.
 */
final class JobPostingRepository extends Repository
{
    protected function table(): string
    {
        return 'jobs';
    }

    /**
     * @param array<string,mixed> $data
     * @return array{job_id:int,user_id:int,reference:string}
     */
    public function create(array $data): array
    {
        return $this->db->transaction(function (Database $db) use ($data): array {
            $marketId = $this->scope->marketId;

            // A homeowner posts without an account. One is created quietly so
            // the job has an owner, receipts have somewhere to go, and they
            // can be given a password later if they ever want to sign in.
            $user = $db->one('SELECT id FROM users WHERE email = :e LIMIT 1', ['e' => $data['email']]);
            if ($user !== null) {
                $userId = (int) $user['id'];
                // Their name and number may have changed since last time, and
                // the newest job is the best evidence of what to use.
                $db->affected(
                    'UPDATE users SET first_name = :f, last_name = :l, phone = :p,
                                      market_id = COALESCE(market_id, :m)
                      WHERE id = :id',
                    [
                        'f' => $data['first_name'], 'l' => $data['last_name'],
                        'p' => $data['phone'], 'm' => $marketId, 'id' => $userId,
                    ],
                );
            } else {
                $userId = (int) $db->insert(
                    "INSERT INTO users (market_id, role, email, first_name, last_name, phone, status)
                     VALUES (:m, 'homeowner', :e, :f, :l, :p, 'active')",
                    [
                        'm' => $marketId, 'e' => $data['email'], 'f' => $data['first_name'],
                        'l' => $data['last_name'], 'p' => $data['phone'],
                    ],
                );
            }

            $reference = $this->uniqueReference($db, (string) $data['market_code']);

            $jobId = (int) $db->insert(
                "INSERT INTO jobs
                    (market_id, user_id, trade_id, county_id, city_id, reference, title, description,
                     zip, urgency, budget_min_cents, budget_max_cents, status)
                 VALUES
                    (:m, :user, :trade, :county, :city, :ref, :title, :description,
                     :zip, :urgency, :bmin, :bmax, 'pending_payment')",
                [
                    'm' => $marketId, 'user' => $userId, 'trade' => $data['trade_id'],
                    'county' => $data['county_id'], 'city' => $data['city_id'],
                    'ref' => $reference, 'title' => $data['title'],
                    'description' => $data['description'], 'zip' => $data['zip'],
                    'urgency' => $data['urgency'], 'bmin' => $data['budget_min_cents'],
                    'bmax' => $data['budget_max_cents'],
                ],
            );

            return ['job_id' => $jobId, 'user_id' => $userId, 'reference' => $reference];
        });
    }

    /**
     * A reference a person can read down the phone: NWI-4K2P9M.
     *
     * No vowels, so it cannot spell anything unfortunate, and no 0/O or 1/I,
     * which are the characters people get wrong when reading one aloud.
     */
    private function uniqueReference(Database $db, string $marketCode): string
    {
        $alphabet = '23456789BCDFGHJKLMNPQRSTVWXYZ';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $tail = '';
            for ($i = 0; $i < 6; $i++) {
                $tail .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = mb_substr($marketCode, 0, 3) . '-' . $tail;

            $taken = $db->value('SELECT COUNT(*) FROM jobs WHERE reference = :r', ['r' => $reference]);
            if ((int) $taken === 0) {
                return $reference;
            }
        }
        // 29^6 is 594 million; twenty collisions means something is wrong
        // enough that a silent fallback would hide it.
        throw new \RuntimeException('Could not generate a unique job reference.');
    }

    /** The pending payment row, written before the visitor leaves for Stripe. */
    public function startPayment(int $jobId, int $userId, int $amountCents, string $sessionId): int
    {
        return (int) $this->db->insert(
            "INSERT INTO payments
                (market_id, user_id, kind, job_id, amount_cents, stripe_checkout_session, status)
             VALUES (:m, :u, 'job_listing', :j, :a, :s, 'pending')",
            [
                'm' => $this->scope->marketId, 'u' => $userId, 'j' => $jobId,
                'a' => $amountCents, 's' => $sessionId,
            ],
        );
    }

    /**
     * Marks a listing paid and makes it visible. The only path to 'active'.
     *
     * Idempotent, and it has to be: Stripe retries a webhook it did not get a
     * 200 for, and will happily deliver the same event several times. The
     * payment row is locked and re-read inside the transaction, so a second
     * delivery finds it already succeeded and changes nothing.
     *
     * @return array<string,mixed>|null the job to send a receipt for, or null
     *         when this payment was already completed
     */
    public function completePayment(string $sessionId, string $paymentIntent, int $amountPaid): ?array
    {
        return $this->db->transaction(function (Database $db) use ($sessionId, $paymentIntent, $amountPaid): ?array {
            $payment = $db->one(
                'SELECT id, job_id, user_id, amount_cents, status
                   FROM payments WHERE stripe_checkout_session = :s FOR UPDATE',
                ['s' => $sessionId],
            );
            if ($payment === null || $payment['status'] === 'succeeded') {
                return null;
            }

            // Worth knowing about, never worth withholding the listing for:
            // they paid, and arguing with them about it later is our problem
            // rather than theirs.
            if ((int) $payment['amount_cents'] !== $amountPaid) {
                error_log(sprintf(
                    'Stripe paid %d for payment %d, which expected %d (session %s)',
                    $amountPaid, $payment['id'], $payment['amount_cents'], $sessionId,
                ));
            }

            $db->affected(
                "UPDATE payments
                    SET status = 'succeeded', paid_at = NOW(), stripe_payment_intent = :pi
                  WHERE id = :id",
                ['pi' => $paymentIntent !== '' ? $paymentIntent : null, 'id' => $payment['id']],
            );

            $days = (int) $db->value(
                'SELECT job_listing_days FROM markets WHERE id = :m',
                ['m' => $this->scope->marketId],
            );

            $db->affected(
                "UPDATE jobs
                    SET status = 'active',
                        published_at = COALESCE(published_at, NOW()),
                        expires_at = NOW() + INTERVAL :days DAY
                  WHERE id = :id AND market_id = :m AND status = 'pending_payment'",
                ['days' => $days > 0 ? $days : 30, 'id' => $payment['job_id'], 'm' => $this->scope->marketId],
            );

            return $db->one(
                'SELECT j.id, j.reference, j.title, j.description, j.county_id, j.trade_id,
                        j.expires_at, t.name AS trade_name, ci.name AS city_name,
                        u.email, u.first_name
                   FROM jobs j
                   JOIN trades t ON t.id = j.trade_id
                   JOIN users u ON u.id = j.user_id
                   LEFT JOIN cities ci ON ci.id = j.city_id
                  WHERE j.id = :id',
                ['id' => $payment['job_id']],
            );
        });
    }

    /**
     * The tradespeople who should hear about a new job.
     *
     * The site promises a homeowner that every pro covering their county sees
     * the post. This is that promise, as a query: live listings, in the right
     * county, who work that trade.
     *
     * @return array<int,array<string,mixed>>
     */
    public function prosToNotify(int $countyId, int $tradeId): array
    {
        return $this->scopedAll(
            "SELECT DISTINCT u.email, u.first_name,
                    COALESCE(NULLIF(p.business_name,''), TRIM(CONCAT(u.first_name,' ',u.last_name))) AS name
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
               JOIN pro_county_areas a ON a.pro_id = p.id AND a.market_id = :market_id
               JOIN pro_trades pt ON pt.pro_id = p.id
              WHERE p.market_id = :market_id
                AND p.status = 'active'
                AND p.is_demo = 0
                AND a.county_id = :county
                AND pt.trade_id = :trade
              LIMIT 200",
            ['county' => $countyId, 'trade' => $tradeId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findByReference(string $reference): ?array
    {
        return $this->scopedOne(
            'SELECT j.*, t.name AS trade_name, ci.name AS city_name, co.short_name AS county_name,
                    u.email, u.first_name
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
               JOIN users u ON u.id = j.user_id
               LEFT JOIN cities ci ON ci.id = j.city_id
               LEFT JOIN counties co ON co.id = j.county_id
              WHERE j.market_id = :market_id AND j.reference = :ref
              LIMIT 1',
            ['ref' => $reference],
        );
    }

    /** What the return page shows while waiting for the webhook. */
    public function paymentStatusFor(int $jobId): string
    {
        return (string) ($this->db->value(
            "SELECT status FROM payments WHERE job_id = :j AND kind = 'job_listing'
              ORDER BY id DESC LIMIT 1",
            ['j' => $jobId],
        ) ?? 'pending');
    }
}
