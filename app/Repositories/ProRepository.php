<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Demo;
use FixListed\Core\Repository;

final class ProRepository extends Repository
{
    protected function table(): string
    {
        return 'pro_profiles';
    }

    /**
     * The directory listing.
     *
     * Coverage is tested with EXISTS rather than a JOIN. A pro who covers four
     * of the market's six counties matches four rows in pro_county_areas, and
     * a join would list them four times — EXISTS asks the yes/no question the
     * query actually means, and needs no DISTINCT to undo the damage.
     *
     * Ordering is the advertising product: Spotlight, then Boost, then
     * everyone else by rating. `is_ad` rides along so the template can label
     * paid placement — every ad here is labelled, because the ratings and
     * verified badges beside it are only worth something if visitors believe
     * they were not bought.
     *
     * @return array<int,array<string,mixed>>
     */
    public function directory(?int $tradeId = null, ?int $countyId = null, int $limit = 50): array
    {
        $params     = [];
        $filters    = '';
        $demoFilter = Demo::filter('p');

        if ($countyId !== null) {
            $filters .= ' AND a.county_id = :county_id';
            $params['county_id'] = $countyId;
        }
        $tradeFilter = '';
        if ($tradeId !== null) {
            $tradeFilter = ' AND EXISTS (SELECT 1 FROM pro_trades pt
                                          WHERE pt.pro_id = p.id AND pt.trade_id = :trade_id)';
            $params['trade_id'] = $tradeId;
        }

        return $this->scopedAll(
            "SELECT p.id, p.slug, p.business_name, p.headline, p.hourly_rate_cents, p.is_demo,
                    -- Sole traders work under their own name and leave
                    -- business_name empty, so the card has nothing to print.
                    -- The fallback belongs here rather than in the template,
                    -- because every page that lists a pro needs the same answer.
                    COALESCE(NULLIF(p.business_name, ''),
                             TRIM(CONCAT(u.first_name, ' ', u.last_name))) AS display_name,
                    p.years_experience, p.rating_avg, p.rating_count, p.jobs_completed,
                    p.response_minutes, p.license_verified_at, p.insurance_verified_at,
                    p.background_checked_at,
                    hc.name AS home_city, hco.short_name AS home_county,
                    s.plan AS ad_plan,
                    (s.plan IS NOT NULL) AS is_ad,
                    pl.id AS placement_id,
                    pl.position AS ad_position
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
               LEFT JOIN cities   hc  ON hc.id  = p.home_city_id
               LEFT JOIN counties hco ON hco.id = p.home_county_id
               LEFT JOIN ad_placements pl
                 ON pl.pro_id = p.id
                AND pl.market_id = :market_id
                AND pl.slot = 'directory_top'
                AND pl.status = 'active'
               -- past_due counts as paying. Stripe retries a declined card
               -- for two weeks and usually wins; taking a tradesperson off
               -- the page in the meantime is how you lose the customer rather
               -- than collect the payment. It also keeps this in step with
               -- the webhook, which leaves the placement up for the same
               -- reason.
               LEFT JOIN subscriptions s
                 ON s.id = pl.subscription_id
                AND s.status IN ('active','trialing','past_due')
              WHERE p.status = 'active'
                {$demoFilter}
                AND EXISTS (SELECT 1 FROM pro_county_areas a
                             WHERE a.pro_id = p.id
                               AND a.market_id = :market_id
                               {$filters})
                {$tradeFilter}
              -- A placement whose subscription is not paying must not move
              -- anybody: position is read only when a plan came with it.
              -- Otherwise a lapsed row quietly reorders the page, and an
              -- unbadged listing sitting above earned ones is the one thing
              -- this directory cannot afford to do.
              ORDER BY (s.plan = 'spotlight') DESC,
                       (s.plan = 'boost') DESC,
                       CASE WHEN s.plan IS NULL THEN 9999 ELSE pl.position END ASC,
                       p.rating_avg DESC,
                       p.rating_count DESC
              LIMIT {$limit}",
            $params,
        );
    }

    public function findBySlug(string $slug): ?array
    {
        $demoFilter = Demo::filter('p');

        return $this->scopedOne(
            "SELECT p.*, u.first_name, u.last_name,
                    COALESCE(NULLIF(p.business_name, ''),
                             TRIM(CONCAT(u.first_name, ' ', u.last_name))) AS display_name,
                    hc.name AS home_city, hco.short_name AS home_county
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
               LEFT JOIN cities   hc  ON hc.id  = p.home_city_id
               LEFT JOIN counties hco ON hco.id = p.home_county_id
              WHERE p.slug = :slug
                AND p.status = :status
                {$demoFilter}
                AND EXISTS (SELECT 1 FROM pro_county_areas a
                             WHERE a.pro_id = p.id AND a.market_id = :market_id)
              LIMIT 1",
            ['slug' => $slug, 'status' => 'active'],
        );
    }

    public function countActive(?int $countyId = null): int
    {
        $filter     = $countyId !== null ? ' AND a.county_id = :county_id' : '';
        $demoFilter = Demo::filter('p');
        return (int) $this->scopedValue(
            "SELECT COUNT(*)
               FROM pro_profiles p
              WHERE p.status = :status
                {$demoFilter}
                AND EXISTS (SELECT 1 FROM pro_county_areas a
                             WHERE a.pro_id = p.id AND a.market_id = :market_id {$filter})",
            $countyId !== null ? ['status' => 'active', 'county_id' => $countyId] : ['status' => 'active'],
        );
    }

    /** The counties a pro will actually drive to, for their profile page. */
    public function counties(int $proId): array
    {
        return $this->scopedAll(
            'SELECT c.id, c.short_name, c.slug
               FROM pro_county_areas a
               JOIN counties c ON c.id = a.county_id
              WHERE a.pro_id = :pro_id AND a.market_id = :market_id
              ORDER BY c.short_name',
            ['pro_id' => $proId],
        );
    }

    /** @return array<int,string> */
    public function skills(int $proId): array
    {
        return array_column(
            $this->scopedAll(
                'SELECT s.label
                   FROM pro_skills s
                   JOIN pro_profiles p ON p.id = s.pro_id
                  WHERE s.pro_id = :pro_id
                    AND EXISTS (SELECT 1 FROM pro_county_areas a
                                 WHERE a.pro_id = p.id AND a.market_id = :market_id)
                  ORDER BY s.sort_order',
                ['pro_id' => $proId],
            ),
            'label',
        );
    }
}
