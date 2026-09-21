<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Demo;
use FixListed\Core\Repository;

final class JobRepository extends Repository
{
    protected function table(): string
    {
        return 'jobs';
    }

    /**
     * The public jobs board.
     *
     * status = 'active' is the load-bearing condition: a job sits in
     * 'pending_payment' until the Stripe webhook confirms the charge, and a
     * job in that state must never be visible to anyone. Nothing public reads
     * jobs without this filter.
     *
     * @return array<int,array<string,mixed>>
     */
    public function board(?int $tradeId = null, int $limit = 50): array
    {
        $tradeFilter = $tradeId !== null ? 'AND j.trade_id = :trade_id' : '';
        $demoFilter  = Demo::filter('j');

        return $this->scopedAll(
            "SELECT j.id, j.reference, j.title, j.description, j.zip, j.urgency, j.is_demo,
                    j.budget_min_cents, j.budget_max_cents, j.quote_count,
                    j.published_at, t.name AS trade_name, t.slug AS trade_slug,
                    ci.name AS city_name, ci.slug AS city_slug, co.short_name AS county_name
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
               LEFT JOIN cities   ci ON ci.id = j.city_id
               LEFT JOIN counties co ON co.id = j.county_id
              WHERE j.market_id = :market_id
                AND j.status = 'active'
                {$demoFilter}
                {$tradeFilter}
              ORDER BY j.published_at DESC
              LIMIT {$limit}",
            $tradeId !== null ? ['trade_id' => $tradeId] : [],
        );
    }

    public function findByReference(string $reference): ?array
    {
        $demoFilter = Demo::filter('j');

        return $this->scopedOne(
            "SELECT j.*, t.name AS trade_name
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
              WHERE j.market_id = :market_id
                AND j.reference = :reference
                AND j.status = 'active'
                {$demoFilter}
              LIMIT 1",
            ['reference' => $reference],
        );
    }

    /** Open jobs in one city — what a city landing page shows. */
    public function inCity(int $cityId, int $limit = 20): array
    {
        $demoFilter = Demo::filter('j');

        return $this->scopedAll(
            "SELECT j.id, j.reference, j.title, j.description, j.zip, j.urgency, j.quote_count, j.is_demo,
                    j.budget_min_cents, j.budget_max_cents, j.published_at,
                    t.name AS trade_name
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
              WHERE j.market_id = :market_id
                AND j.city_id = :city_id
                AND j.status = 'active'
                {$demoFilter}
              ORDER BY j.published_at DESC
              LIMIT {$limit}",
            ['city_id' => $cityId],
        );
    }

    /**
     * Jobs a given tradesperson can actually quote.
     *
     * Their counties, still open, and flagged with whether they already sent
     * a quote — showing a job with no indication that they answered it three
     * days ago is how a board stops feeling like a to-do list.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forPro(int $proId, int $limit = 50): array
    {
        $demoFilter = Demo::filter('j');

        return $this->scopedAll(
            "SELECT j.id, j.reference, j.title, j.description, j.zip, j.urgency, j.is_demo,
                    j.budget_min_cents, j.budget_max_cents, j.quote_count, j.published_at,
                    t.name AS trade_name, ci.name AS city_name, co.short_name AS county_name,
                    EXISTS (SELECT 1 FROM quotes q
                             WHERE q.job_id = j.id AND q.pro_id = :pro_id) AS already_quoted
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
               LEFT JOIN cities   ci ON ci.id = j.city_id
               LEFT JOIN counties co ON co.id = j.county_id
              WHERE j.market_id = :market_id
                AND j.status = 'active'
                {$demoFilter}
                AND EXISTS (SELECT 1 FROM pro_county_areas a
                             WHERE a.pro_id = :pro_id2
                               AND a.county_id = j.county_id
                               AND a.market_id = :market_id)
              ORDER BY j.published_at DESC
              LIMIT {$limit}",
            ['pro_id' => $proId, 'pro_id2' => $proId],
        );
    }

    public function countOpen(): int
    {
        $demoFilter = Demo::filter('j');

        return (int) $this->scopedValue(
            "SELECT COUNT(*) FROM jobs j
              WHERE j.market_id = :market_id AND j.status = 'active' {$demoFilter}"
        );
    }
}
