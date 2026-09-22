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

    /**
     * Real published reviews for one pro: how many, and their average.
     *
     * This is what an aggregateRating may be built from, and it exists
     * separately from pro_profiles.rating_avg for two reasons.
     *
     * The stored columns are a nightly rollup that counts seeded reviews, and
     * a star rating published to a search engine has to come from reviews
     * that people actually left. Marking up invented ones is the fabricated-
     * review case the structured-data policy names outright, and it is the
     * kind of thing that gets rich results turned off for a whole domain.
     *
     * And it is computed rather than read so that zero is really zero. A pro
     * with no reviews gets no rating node at all — not a rating of 0, which
     * renders as one empty star and is worse than showing nothing.
     *
     * @return array{count:int,average:float}
     */
    public function publishedStats(int $proId): array
    {
        $row = $this->scopedOne(
            "SELECT COUNT(*) AS n, AVG(r.rating) AS avg_rating
               FROM reviews r
              WHERE r.market_id = :market_id
                AND r.pro_id = :pro_id
                AND r.status = 'published'
                AND r.is_demo = 0",
            ['pro_id' => $proId],
        );

        $count = (int) ($row['n'] ?? 0);

        return [
            'count'   => $count,
            'average' => $count > 0 ? round((float) $row['avg_rating'], 2) : 0.0,
        ];
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
