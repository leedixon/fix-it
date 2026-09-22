<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Repository;

/**
 * Reads for the back end.
 *
 * Separate from the public repositories on purpose: these deliberately ignore
 * the filters the public ones enforce. The directory must never show a
 * suspended profile or a job awaiting payment; the admin exists precisely to
 * look at those. Mixing the two sets of queries in one class is how a
 * `status` filter goes missing from a public page.
 *
 * Still market-scoped. An administrator seeing every market is a superadmin
 * decision made in the controller, not a default of the data layer.
 */
final class AdminRepository extends Repository
{
    protected function table(): string
    {
        return 'pro_profiles';
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $one = fn (string $sql): int => (int) $this->scopedValue($sql);

        return [
            'applications' => $one("SELECT COUNT(*) FROM pro_profiles WHERE market_id = :market_id AND status = 'pending_review'"),
            'pros_live'    => $one("SELECT COUNT(*) FROM pro_profiles WHERE market_id = :market_id AND status = 'active'"),
            'pros_real'    => $one("SELECT COUNT(*) FROM pro_profiles WHERE market_id = :market_id AND status = 'active' AND is_demo = 0"),
            'jobs_open'    => $one("SELECT COUNT(*) FROM jobs WHERE market_id = :market_id AND status = 'active'"),
            'jobs_unpaid'  => $one("SELECT COUNT(*) FROM jobs WHERE market_id = :market_id AND status = 'pending_payment'"),
            'people'       => $one("SELECT COUNT(*) FROM users WHERE market_id = :market_id"),
            'waitlist'     => (int) $this->db->value('SELECT COUNT(*) FROM waitlist'),
            'moderation'   => $one("SELECT COUNT(*) FROM moderation_items WHERE market_id = :market_id AND status = 'open'"),
            'revenue_30d'  => $one("SELECT COALESCE(SUM(amount_cents),0) FROM payments
                                     WHERE market_id = :market_id AND status = 'succeeded'
                                       AND paid_at >= NOW() - INTERVAL 30 DAY"),
        ];
    }

    /** Every profile, whatever its status — the admin's whole point. */
    public function pros(string $status = '', int $limit = 200): array
    {
        $filter = $status !== '' ? ' AND p.status = :status' : '';

        return $this->scopedAll(
            "SELECT p.id, p.slug, p.business_name, p.status, p.is_demo, p.rating_avg, p.rating_count,
                    p.license_verified_at, p.insurance_verified_at, p.created_at, p.published_at,
                    COALESCE(NULLIF(p.business_name,''), TRIM(CONCAT(u.first_name,' ',u.last_name))) AS display_name,
                    u.email, u.phone,
                    (SELECT GROUP_CONCAT(t.name ORDER BY t.sort_order SEPARATOR ', ')
                       FROM pro_trades pt JOIN trades t ON t.id = pt.trade_id
                      WHERE pt.pro_id = p.id) AS trades
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
              WHERE p.market_id = :market_id {$filter}
              ORDER BY FIELD(p.status,'pending_review','active','draft','suspended'), p.created_at DESC
              LIMIT {$limit}",
            $status !== '' ? ['status' => $status] : [],
        );
    }

    /** Jobs in every state, including the ones that never got paid for. */
    public function jobs(string $status = '', int $limit = 200): array
    {
        $filter = $status !== '' ? ' AND j.status = :status' : '';

        return $this->scopedAll(
            "SELECT j.id, j.reference, j.title, j.status, j.is_demo, j.quote_count, j.view_count,
                    j.budget_min_cents, j.budget_max_cents, j.created_at, j.published_at,
                    t.name AS trade_name, ci.name AS city_name,
                    u.email AS poster_email
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
               LEFT JOIN cities ci ON ci.id = j.city_id
               JOIN users u ON u.id = j.user_id
              WHERE j.market_id = :market_id {$filter}
              ORDER BY j.created_at DESC
              LIMIT {$limit}",
            $status !== '' ? ['status' => $status] : [],
        );
    }

    /**
     * Accounts in this market, optionally one role at a time.
     *
     * The role is bound, not interpolated — and the bug this replaced was the
     * other failure: `:role` went into the SQL and the value was never passed,
     * so every filtered tab threw "Invalid parameter number" and rendered a
     * 500. Unfiltered worked, which is why it survived.
     *
     * @return array<int,array<string,mixed>>
     */
    public function users(string $role = '', int $limit = 200): array
    {
        $filter = $role !== '' ? ' AND u.role = :role' : '';

        return $this->scopedAll(
            "SELECT u.id, u.role, u.email, u.first_name, u.last_name, u.phone,
                    u.status, u.is_demo, u.created_at, u.last_login_at
               FROM users u
              WHERE u.market_id = :market_id {$filter}
              ORDER BY u.created_at DESC
              LIMIT {$limit}",
            $role !== '' ? ['role' => $role] : [],
        );
    }

    /** The waiting list captured by the holding page, newest first. */
    public function waitlist(int $limit = 200): array
    {
        return $this->db->all(
            "SELECT name, email, role, counties, created_at
               FROM waitlist ORDER BY created_at DESC LIMIT {$limit}"
        );
    }

    /**
     * Paid placement as it stands.
     *
     * Reads across placements and subscriptions because the question an
     * administrator asks is "who is paying for position, in which slot, and
     * is that subscription still good" — which is one sentence and two tables.
     */
    public function placements(): array
    {
        return $this->scopedAll(
            "SELECT pl.id, pl.slot, pl.position, pl.status AS placement_status,
                    s.plan, s.status AS sub_status, s.current_period_end,
                    COALESCE(NULLIF(p.business_name,''), TRIM(CONCAT(u.first_name,' ',u.last_name))) AS pro_name,
                    p.slug, p.is_demo,
                    -- Per placement, not per pro. Summing by pro repeated one
                    -- pro's whole total on every row they hold, which reads as
                    -- each placement having delivered all of it.
                    (SELECT COALESCE(SUM(impressions),0) FROM ad_stats_daily d
                      WHERE d.placement_id = pl.id AND d.stat_date >= CURDATE() - INTERVAL 30 DAY) AS impressions_30d,
                    (SELECT COALESCE(SUM(clicks),0) FROM ad_stats_daily d
                      WHERE d.placement_id = pl.id AND d.stat_date >= CURDATE() - INTERVAL 30 DAY) AS clicks_30d
               FROM ad_placements pl
               JOIN pro_profiles p ON p.id = pl.pro_id
               JOIN users u ON u.id = p.user_id
               LEFT JOIN subscriptions s ON s.id = pl.subscription_id
              WHERE pl.market_id = :market_id
              ORDER BY FIELD(s.plan,'spotlight','boost'), pl.position"
        );
    }

    /**
     * How much inventory is sold, against the caps the market sets.
     *
     * Counted in subscriptions, because that is the unit the cap is written
     * in and the unit a pro buys. Counting placements instead showed "7 of 3
     * spotlight sold" the moment one subscription held more than one row —
     * a market that looks oversold when it is not.
     */
    public function adInventory(): array
    {
        return $this->scopedAll(
            "SELECT s.plan, COUNT(*) AS sold
               FROM subscriptions s
              WHERE s.market_id = :market_id
                AND s.status IN ('active','trialing','past_due')
              GROUP BY s.plan"
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function activity(int $limit = 100): array
    {
        return $this->db->all(
            "SELECT a.action, a.subject_type, a.subject_id, a.meta, a.created_at,
                    u.email AS actor_email
               FROM audit_log a
               LEFT JOIN users u ON u.id = a.actor_user_id
              ORDER BY a.created_at DESC
              LIMIT {$limit}"
        );
    }
}
