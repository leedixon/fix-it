<?php
declare(strict_types=1);

namespace FixListed\Repositories;

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
        $params = [];
        $filters = '';

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
            "SELECT p.id, p.slug, p.business_name, p.headline, p.hourly_rate_cents,
                    p.years_experience, p.rating_avg, p.rating_count, p.jobs_completed,
                    p.response_minutes, p.license_verified_at, p.insurance_verified_at,
                    p.background_checked_at,
                    hc.name AS home_city, hco.short_name AS home_county,
                    s.plan AS ad_plan,
                    (s.plan IS NOT NULL) AS is_ad,
                    pl.position AS ad_position
               FROM pro_profiles p
               LEFT JOIN cities   hc  ON hc.id  = p.home_city_id
               LEFT JOIN counties hco ON hco.id = p.home_county_id
               LEFT JOIN ad_placements pl
                 ON pl.pro_id = p.id
                AND pl.market_id = :market_id
                AND pl.slot = 'directory_top'
                AND pl.status = 'active'
               LEFT JOIN subscriptions s
                 ON s.id = pl.subscription_id
                AND s.status = 'active'
              WHERE p.status = 'active'
                AND EXISTS (SELECT 1 FROM pro_county_areas a
                             WHERE a.pro_id = p.id
                               AND a.market_id = :market_id
                               {$filters})
                {$tradeFilter}
              ORDER BY (s.plan = 'spotlight') DESC,
                       (s.plan = 'boost') DESC,
                       pl.position ASC,
                       p.rating_avg DESC,
                       p.rating_count DESC
              LIMIT {$limit}",
            $params,
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->scopedOne(
            'SELECT p.*, u.first_name, u.last_name,
                    hc.name AS home_city, hco.short_name AS home_county
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
               LEFT JOIN cities   hc  ON hc.id  = p.home_city_id
               LEFT JOIN counties hco ON hco.id = p.home_county_id
              WHERE p.slug = :slug
                AND p.status = :status
                AND EXISTS (SELECT 1 FROM pro_county_areas a
                             WHERE a.pro_id = p.id AND a.market_id = :market_id)
              LIMIT 1',
            ['slug' => $slug, 'status' => 'active'],
        );
    }

    public function countActive(?int $countyId = null): int
    {
        $filter = $countyId !== null ? ' AND a.county_id = :county_id' : '';
        return (int) $this->scopedValue(
            "SELECT COUNT(*)
               FROM pro_profiles p
              WHERE p.status = :status
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
