<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Repository;

/**
 * Paid placement: what a plan costs, whether a slot is free, and who holds one.
 *
 * The shape of the product, in one place so it cannot drift between the page
 * that sells it and the webhook that grants it:
 *
 *  - A subscription is one row per pro per plan, and Stripe owns its status.
 *    Nothing here marks a subscription active on its own say-so; that only
 *    happens on a paid invoice.
 *  - A placement is what the directory actually reads. One per subscription,
 *    in the directory_top slot, which is what the home page, the directory
 *    and every town page all select from — so buying once lifts the listing
 *    everywhere it appears, and there is one row to revoke when payment stops.
 *  - Plan decides order and badge: spotlight above boost, boost above the
 *    unpaid. Position orders within a plan by who bought first.
 *
 * Inventory is finite on purpose. The market row caps each plan, and every
 * path that could hand out a placement counts first. Scarcity is the product:
 * if everyone can buy the top of the page, nobody's purchase is worth paying
 * for, and the directory stops being a ranking at all.
 */
final class AdvertisingRepository extends Repository
{
    /** The one slot the public pages read. Both plans land here. */
    public const SLOT = 'directory_top';

    /** Statuses that hold a slot: paid, or billed and not yet given up on. */
    private const HOLDING = ['active', 'trialing', 'past_due'];

    protected function table(): string
    {
        return 'subscriptions';
    }

    /**
     * What each plan costs, and how much of it is left.
     *
     * @param array<string,mixed> $market
     * @return array<string,array<string,mixed>>
     */
    public function plans(array $market): array
    {
        $taken = $this->slotsTaken();

        $plans = [];
        foreach (['boost', 'spotlight'] as $plan) {
            $cap  = (int) ($market[$plan . '_slots'] ?? 0);
            $sold = (int) ($taken[$plan] ?? 0);
            $plans[$plan] = [
                'plan'        => $plan,
                'price_cents' => (int) ($market[$plan . '_price_cents'] ?? 0),
                'slots'       => $cap,
                'sold'        => $sold,
                'available'   => max(0, $cap - $sold),
            ];
        }

        return $plans;
    }

    /** @return array<string,int> plan => subscriptions holding a slot */
    public function slotsTaken(): array
    {
        $rows = $this->scopedAll(
            "SELECT plan, COUNT(*) AS n
               FROM subscriptions
              WHERE market_id = :market_id
                AND status IN ('active','trialing','past_due')
              GROUP BY plan",
        );

        $out = ['boost' => 0, 'spotlight' => 0];
        foreach ($rows as $row) {
            $out[(string) $row['plan']] = (int) $row['n'];
        }
        return $out;
    }

    /**
     * Whether a plan can still be sold.
     *
     * Checked when the page renders and again inside the transaction that
     * grants the placement, because a slot can sell between the two.
     */
    public function hasCapacity(array $market, string $plan): bool
    {
        $cap = (int) ($market[$plan . '_slots'] ?? 0);
        return $cap > 0 && ($this->slotsTaken()[$plan] ?? 0) < $cap;
    }

    /**
     * The pro's current subscription, whatever its state.
     *
     * Cancelled ones are returned too: a pro who stopped paying should see
     * that they used to have a plan, and their Stripe customer id is worth
     * reusing if they come back.
     *
     * @return array<string,mixed>|null
     */
    public function forPro(int $proId): ?array
    {
        return $this->scopedOne(
            "SELECT * FROM subscriptions
              WHERE pro_id = :pro AND market_id = :market_id
              ORDER BY FIELD(status, 'active','trialing','past_due','unpaid','incomplete','canceled'),
                       id DESC
              LIMIT 1",
            ['pro' => $proId],
        );
    }

    /**
     * Records a checkout that has been started but not yet paid.
     *
     * Written before the redirect to Stripe so the webhook has a row to find.
     * Status stays 'incomplete' — it holds no slot and grants no placement
     * until an invoice is actually paid.
     */
    public function startCheckout(int $proId, string $plan, int $priceCents, string $customerId): int
    {
        $existing = $this->scopedOne(
            "SELECT id FROM subscriptions
              WHERE pro_id = :pro AND market_id = :market_id AND status = 'incomplete'
              LIMIT 1",
            ['pro' => $proId],
        );

        if ($existing !== null) {
            // One abandoned attempt per pro, rewritten. Otherwise a pro who
            // opens checkout five times leaves five dead rows behind.
            $this->scopedAffected(
                'UPDATE subscriptions
                    SET plan = :plan, price_cents = :price,
                        stripe_customer_id = COALESCE(NULLIF(:customer, \'\'), stripe_customer_id)
                  WHERE id = :id AND market_id = :market_id',
                ['plan' => $plan, 'price' => $priceCents, 'customer' => $customerId, 'id' => $existing['id']],
            );
            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO subscriptions (market_id, pro_id, plan, price_cents, stripe_customer_id, status)
             VALUES (:market, :pro, :plan, :price, NULLIF(:customer, \'\'), \'incomplete\')',
            [
                'market' => $this->scope->marketId,
                'pro' => $proId,
                'plan' => $plan,
                'price' => $priceCents,
                'customer' => $customerId,
            ],
        );
    }

    /**
     * Turns a paid subscription into a live placement.
     *
     * Everything here happens in one transaction, and the capacity check is
     * inside it: two pros can pay for the last spotlight slot seconds apart,
     * and the loser must get their money back rather than a slot that was
     * already sold. Returns false when there was no room, which is the
     * caller's cue to refund.
     */
    public function activate(
        int $subscriptionId,
        string $stripeSubscriptionId,
        string $customerId,
        ?string $periodStart,
        ?string $periodEnd,
        int $slotCap,
    ): bool {
        return (bool) $this->db->transaction(function () use (
            $subscriptionId, $stripeSubscriptionId, $customerId, $periodStart, $periodEnd, $slotCap
        ) {
            $row = $this->db->one(
                'SELECT * FROM subscriptions WHERE id = :id AND market_id = :market FOR UPDATE',
                ['id' => $subscriptionId, 'market' => $this->scope->marketId],
            );
            if ($row === null) {
                return false;
            }

            // Already live: a webhook retry, or the renewal of a subscription
            // that never lapsed. Refresh the period and leave the placement be.
            $alreadyLive = in_array((string) $row['status'], self::HOLDING, true);

            if (!$alreadyLive) {
                $taken = (int) $this->db->value(
                    "SELECT COUNT(*) FROM subscriptions
                      WHERE market_id = :market AND plan = :plan
                        AND status IN ('active','trialing','past_due')
                        AND id <> :id
                      FOR UPDATE",
                    ['market' => $this->scope->marketId, 'plan' => $row['plan'], 'id' => $subscriptionId],
                );
                // No ">= 1" guard on the cap: a cap of nought means the
                // plan is off sale, and reading it as "unlimited" here would
                // grant placements the page that sells them refuses.
                if ($taken >= $slotCap) {
                    return false;
                }
            }

            $this->db->affected(
                'UPDATE subscriptions
                    SET status = \'active\',
                        stripe_subscription_id = :sub,
                        stripe_customer_id = COALESCE(NULLIF(:customer, \'\'), stripe_customer_id),
                        -- COALESCE, not assignment: Stripe does not promise
                        -- the order of checkout.session.completed and
                        -- invoice.paid, and whichever arrives without a
                        -- period must not erase one the other already wrote.
                        current_period_start = COALESCE(:start, current_period_start),
                        current_period_end = COALESCE(:end, current_period_end),
                        cancel_at_period_end = 0,
                        canceled_at = NULL
                  WHERE id = :id AND market_id = :market',
                [
                    'sub' => $stripeSubscriptionId,
                    'customer' => $customerId,
                    'start' => $periodStart,
                    'end' => $periodEnd,
                    'id' => $subscriptionId,
                    'market' => $this->scope->marketId,
                ],
            );

            $this->placeFor($subscriptionId, (int) $row['pro_id'], (string) $row['plan']);
            return true;
        });
    }

    /**
     * Gives a subscription its placement, or wakes the one it already had.
     *
     * Position is assigned once and kept. A pro who has paid since January
     * should not slide down the page because somebody joined in March and the
     * positions were recomputed.
     */
    private function placeFor(int $subscriptionId, int $proId, string $plan): void
    {
        $existing = $this->db->one(
            'SELECT id FROM ad_placements
              WHERE subscription_id = :sub AND market_id = :market
              LIMIT 1',
            ['sub' => $subscriptionId, 'market' => $this->scope->marketId],
        );

        if ($existing !== null) {
            $this->db->affected(
                "UPDATE ad_placements
                    SET status = 'active', ends_at = NULL
                  WHERE id = :id AND market_id = :market",
                ['id' => $existing['id'], 'market' => $this->scope->marketId],
            );
            return;
        }

        $next = (int) $this->db->value(
            'SELECT COALESCE(MAX(pl.position), 0) + 1
               FROM ad_placements pl
               JOIN subscriptions s ON s.id = pl.subscription_id
              WHERE pl.market_id = :market AND pl.slot = :slot AND s.plan = :plan',
            ['market' => $this->scope->marketId, 'slot' => self::SLOT, 'plan' => $plan],
        );

        $this->db->insert(
            'INSERT INTO ad_placements (market_id, pro_id, subscription_id, slot, position, status)
             VALUES (:market, :pro, :sub, :slot, :position, \'active\')',
            [
                'market' => $this->scope->marketId,
                'pro' => $proId,
                'sub' => $subscriptionId,
                'slot' => self::SLOT,
                'position' => $next,
            ],
        );
    }

    /**
     * Payment failed, but Stripe is still retrying.
     *
     * The placement stays up. A card that expired is not the same as a pro
     * who stopped paying, and pulling somebody off the page over a retry
     * Stripe will win tomorrow is a good way to lose them.
     */
    public function markPastDue(string $stripeSubscriptionId): void
    {
        $this->scopedAffected(
            "UPDATE subscriptions SET status = 'past_due'
              WHERE stripe_subscription_id = :sub AND market_id = :market_id
                AND status IN ('active','trialing')",
            ['sub' => $stripeSubscriptionId],
        );
    }

    /**
     * Stripe has given up, or the pro cancelled. The slot goes back.
     *
     * The placement is expired rather than deleted: its impressions and
     * clicks reference it, and a pro who comes back should be able to see
     * what last year's spend did.
     */
    public function endSubscription(string $stripeSubscriptionId): void
    {
        $row = $this->scopedOne(
            'SELECT id FROM subscriptions
              WHERE stripe_subscription_id = :sub AND market_id = :market_id
              LIMIT 1',
            ['sub' => $stripeSubscriptionId],
        );
        if ($row === null) {
            return;
        }

        $this->db->transaction(function () use ($row) {
            $this->db->affected(
                "UPDATE subscriptions
                    SET status = 'canceled', canceled_at = NOW(), cancel_at_period_end = 0
                  WHERE id = :id AND market_id = :market",
                ['id' => $row['id'], 'market' => $this->scope->marketId],
            );
            $this->db->affected(
                "UPDATE ad_placements
                    SET status = 'expired', ends_at = NOW()
                  WHERE subscription_id = :id AND market_id = :market",
                ['id' => $row['id'], 'market' => $this->scope->marketId],
            );
            return true;
        });
    }

    /**
     * Closes a checkout that was paid but could not be honoured.
     *
     * Leaving the row 'incomplete' would let the pro's own screen show a plan
     * half-started that nobody is going to finish, and the next checkout would
     * silently reuse it.
     */
    public function abandon(int $subscriptionId, string $reason): void
    {
        $this->scopedAffected(
            "UPDATE subscriptions
                SET status = 'canceled', canceled_at = NOW()
              WHERE id = :id AND market_id = :market_id AND status = 'incomplete'",
            ['id' => $subscriptionId],
        );
        error_log('Subscription ' . $subscriptionId . ' abandoned: ' . $reason);
    }

    /** Records that a pro asked to stop at the end of the period they paid for. */
    public function markCancelAtPeriodEnd(string $stripeSubscriptionId, bool $cancelling): void
    {
        $this->scopedAffected(
            'UPDATE subscriptions SET cancel_at_period_end = :flag
              WHERE stripe_subscription_id = :sub AND market_id = :market_id',
            ['flag' => $cancelling ? 1 : 0, 'sub' => $stripeSubscriptionId],
        );
    }

    /** The subscription a Stripe id belongs to. @return array<string,mixed>|null */
    public function findByStripeId(string $stripeSubscriptionId): ?array
    {
        return $this->scopedOne(
            'SELECT * FROM subscriptions
              WHERE stripe_subscription_id = :sub AND market_id = :market_id
              LIMIT 1',
            ['sub' => $stripeSubscriptionId],
        );
    }

    /**
     * A live placement, with where it should send a click.
     *
     * The destination is built from the pro's own slug here rather than read
     * from the request: /go/ takes an id and nothing else, so it cannot be
     * pointed at another site.
     *
     * @return array<string,mixed>|null
     */
    public function placement(int $placementId): ?array
    {
        return $this->scopedOne(
            "SELECT pl.id, pl.pro_id, pl.status, p.slug, p.status AS pro_status
               FROM ad_placements pl
               JOIN pro_profiles p ON p.id = pl.pro_id
              WHERE pl.id = :id AND pl.market_id = :market_id
              LIMIT 1",
            ['id' => $placementId],
        );
    }

    /**
     * What a pro's placement delivered, day by day.
     *
     * @return array<int,array<string,mixed>>
     */
    public function statsFor(int $proId, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        return $this->scopedAll(
            "SELECT d.stat_date, d.impressions, d.clicks
               FROM ad_stats_daily d
              WHERE d.pro_id = :pro AND d.market_id = :market_id
                AND d.stat_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
              ORDER BY d.stat_date",
            ['pro' => $proId],
        );
    }

    /**
     * Totals for the same window, for the line a pro actually reads.
     *
     * @return array{impressions:int,clicks:int,days:int}
     */
    public function totalsFor(int $proId, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $row = $this->scopedOne(
            "SELECT COALESCE(SUM(d.impressions), 0) AS impressions,
                    COALESCE(SUM(d.clicks), 0)      AS clicks
               FROM ad_stats_daily d
              WHERE d.pro_id = :pro AND d.market_id = :market_id
                AND d.stat_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)",
            ['pro' => $proId],
        );

        return [
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks'      => (int) ($row['clicks'] ?? 0),
            'days'        => $days,
        ];
    }
}
