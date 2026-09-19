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
     * Ordering is the advertising product: Spotlight first, then Boost, then
     * everyone else by rating. `is_ad` comes back with each row so the template
     * can label paid placement — every ad on this site is labelled, because the
     * ratings and verified badges beside it are only worth something if
     * visitors believe they were not bought.
     *
     * Reads pro_service_areas rather than pro_profiles.market_id, so a pro who
     * works in two cities appears in both directories.
     *
     * @return array<int,array<string,mixed>>
     */
    public function directory(?int $tradeId = null, int $limit = 50): array
    {
        $tradeJoin  = $tradeId !== null ? 'JOIN pro_trades pt ON pt.pro_id = p.id AND pt.trade_id = :trade_id' : '';

        return $this->scopedAll(
            "SELECT p.id, p.slug, p.business_name, p.headline, p.hourly_rate_cents,
                    p.years_experience, p.rating_avg, p.rating_count, p.jobs_completed,
                    p.response_minutes, p.license_verified_at, p.insurance_verified_at,
                    p.background_checked_at,
                    s.plan AS ad_plan,
                    (s.plan IS NOT NULL) AS is_ad,
                    pl.position AS ad_position
               FROM pro_profiles p
               JOIN pro_service_areas sa
                 ON sa.pro_id = p.id AND sa.market_id = :market_id
               {$tradeJoin}
               LEFT JOIN ad_placements pl
                 ON pl.pro_id = p.id
                AND pl.market_id = :market_id
                AND pl.slot = 'directory_top'
                AND pl.status = 'active'
               LEFT JOIN subscriptions s
                 ON s.id = pl.subscription_id
                AND s.status = 'active'
              WHERE p.status = 'active'
              ORDER BY (s.plan = 'spotlight') DESC,
                       (s.plan = 'boost') DESC,
                       pl.position ASC,
                       p.rating_avg DESC,
                       p.rating_count DESC
              LIMIT {$limit}",
            $tradeId !== null ? ['trade_id' => $tradeId] : [],
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->scopedOne(
            'SELECT p.*, u.first_name, u.last_name
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
               JOIN pro_service_areas sa ON sa.pro_id = p.id AND sa.market_id = :market_id
              WHERE p.slug = :slug AND p.status = :status
              LIMIT 1',
            ['slug' => $slug, 'status' => 'active'],
        );
    }

    public function countActive(): int
    {
        return (int) $this->scopedValue(
            'SELECT COUNT(*)
               FROM pro_profiles p
               JOIN pro_service_areas sa ON sa.pro_id = p.id AND sa.market_id = :market_id
              WHERE p.status = :status',
            ['status' => 'active'],
        );
    }

    /** @return array<int,string> */
    public function skills(int $proId): array
    {
        // pro_skills has no market_id of its own; it is reached through a pro
        // this repository already scoped, so the scope is inherited.
        return array_column(
            $this->db->all(
                'SELECT s.label
                   FROM pro_skills s
                   JOIN pro_profiles p ON p.id = s.pro_id
                   JOIN pro_service_areas sa ON sa.pro_id = p.id AND sa.market_id = :market_id
                  WHERE s.pro_id = :pro_id
                  ORDER BY s.sort_order',
                ['pro_id' => $proId, 'market_id' => $this->scope->marketId],
            ),
            'label',
        );
    }
}
