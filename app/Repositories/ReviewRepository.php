<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Demo;
use FixListed\Core\Repository;

final class ReviewRepository extends Repository
{
    protected function table(): string
    {
        return 'reviews';
    }

    /**
     * A pro's published reviews, newest first.
     *
     * status = 'published' is load-bearing in the same way the jobs board's
     * status filter is: a review awaiting moderation, or one that was removed,
     * must never appear on a profile.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forPro(int $proId, int $limit = 12): array
    {
        $demoFilter = Demo::filter('r');

        return $this->scopedAll(
            "SELECT r.rating, r.body, r.job_value_cents, r.created_at, r.is_demo,
                    u.first_name, u.last_name
               FROM reviews r
               JOIN users u ON u.id = r.author_user_id
              WHERE r.market_id = :market_id
                AND r.pro_id = :pro_id
                AND r.status = 'published'
                {$demoFilter}
              ORDER BY r.created_at DESC
              LIMIT {$limit}",
            ['pro_id' => $proId],
        );
    }

    /** The most recent reviews across the market, for the home page. */
    public function recent(int $limit = 3): array
    {
        $demoFilter = Demo::filter('r');

        return $this->scopedAll(
            "SELECT r.rating, r.body, r.job_value_cents, r.created_at, r.is_demo,
                    u.first_name, u.last_name,
                    p.business_name, p.slug AS pro_slug
               FROM reviews r
               JOIN users u ON u.id = r.author_user_id
               JOIN pro_profiles p ON p.id = r.pro_id
              WHERE r.market_id = :market_id
                AND r.status = 'published'
                AND p.status = 'active'
                {$demoFilter}
              ORDER BY r.created_at DESC
              LIMIT {$limit}"
        );
    }
}
