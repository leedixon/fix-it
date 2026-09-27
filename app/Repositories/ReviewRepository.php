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

    /**
     * The invitation token for a job, minted on first use.
     *
     * Not the job reference. That is printed on the public jobs board at
     * /jobs/{reference}, so anyone reading the board could rate anyone's
     * tradesperson. This is 32 random bytes, stored once, never displayed
     * anywhere but in the one email it is sent in.
     */
    public function issueToken(int $jobId): string
    {
        $existing = $this->scopedValue(
            'SELECT review_token FROM jobs WHERE id = :job AND market_id = :market_id',
            ['job' => $jobId],
        );
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $this->scopedAffected(
            'UPDATE jobs SET review_token = :token, review_invited_at = NOW()
              WHERE id = :job AND market_id = :market_id',
            ['token' => $token, 'job' => $jobId],
        );

        return $token;
    }

    /**
     * The job an invitation token opens, or null.
     *
     * Returns the homeowner with it, because the review is written in their
     * name and the form has to be able to say who it thinks you are.
     *
     * @return array<string,mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        // Length-checked before the query so a truncated or padded token is
        // rejected outright rather than becoming a LIKE-shaped index scan.
        if (strlen($token) !== 64 || ctype_xdigit($token) === false) {
            return null;
        }

        return $this->scopedOne(
            "SELECT j.id, j.reference, j.title, j.published_at, j.hired_pro_id,
                    u.id AS user_id, u.first_name, u.email,
                    (SELECT COUNT(*) FROM reviews r WHERE r.job_id = j.id) AS review_count
               FROM jobs j
               JOIN users u ON u.id = j.user_id
              WHERE j.review_token = :token
                AND j.market_id = :market_id
              LIMIT 1",
            ['token' => $token],
        );
    }

    /**
     * The tradespeople who quoted this job — the only ones reviewable.
     *
     * Restricting the list to quoters is the whole anti-abuse story on this
     * form. Without it the token would let a homeowner rate any business in
     * the directory, including one that never heard of the job.
     *
     * @return array<int,array<string,mixed>>
     */
    public function quotedOn(int $jobId): array
    {
        return $this->scopedAll(
            "SELECT p.id, p.business_name, p.slug, p.is_demo
               FROM quotes q
               JOIN pro_profiles p ON p.id = q.pro_id
              WHERE q.job_id = :job
                AND p.market_id = :market_id
              ORDER BY p.business_name",
            ['job' => $jobId],
        );
    }

    /**
     * Record a review and mark who was hired.
     *
     * pending_review, never published: this is text a stranger typed about a
     * named local business, and the site publishes it under that business's
     * name. One libellous paragraph going straight live is a problem for the
     * tradesperson and for Fix Listed, and moderation is the only thing
     * standing between those two facts. The column's own default is
     * 'published' for the seed's benefit, so it is set explicitly here.
     *
     * hired_pro_id is written in the same transaction. It has been in the
     * schema from the start with nothing ever filling it.
     */
    public function submit(int $jobId, int $proId, int $authorUserId, int $rating, string $body): int
    {
        return (int) $this->db->transaction(function () use ($jobId, $proId, $authorUserId, $rating, $body): int {
            $id = $this->db->insert(
                "INSERT INTO reviews (market_id, pro_id, job_id, author_user_id, rating, body, status, is_demo)
                 VALUES (:market_id, :pro, :job, :author, :rating, :body, 'pending_review', 0)",
                [
                    'market_id' => $this->scope->marketId,
                    'pro'       => $proId,
                    'job'       => $jobId,
                    'author'    => $authorUserId,
                    'rating'    => $rating,
                    'body'      => $body === '' ? null : $body,
                ],
            );

            $this->db->affected(
                'UPDATE jobs SET hired_pro_id = :pro WHERE id = :job AND market_id = :market',
                ['pro' => $proId, 'job' => $jobId, 'market' => $this->scope->marketId],
            );

            return $id;
        });
    }

    /**
     * Recompute a pro's stored rating from their published reviews.
     *
     * pro_profiles.rating_avg and rating_count are a rollup, and until now
     * nothing recomputed them — they were whatever the seed wrote. Called
     * whenever a review changes state, because a moderator publishing a
     * review and the profile not moving is the kind of thing that gets
     * reported as "the site is broken".
     *
     * Sample profiles are left alone, and finding out why took running this.
     *
     * The seed writes rating_avg and rating_count straight onto nine profiles
     * without creating the reviews to back them: the database holds three
     * review rows against profiles claiming 187, 143, 96 and so on. Those
     * numbers are stage dressing, and every page that prints them also prints
     * a Sample badge next to them.
     *
     * So recalculating one of them does not correct anything — it just makes
     * one invented profile disagree with the other eight. Publishing a single
     * review against Brenner Heating took it from "24 reviews" to "1 review",
     * which is a truer number and a worse page.
     *
     * Real profiles are recomputed from real rows, which is the only case
     * that exists in production: demo rows are purged before launch, so a
     * genuine review can never land on one there.
     */
    public function recalculate(int $proId): void
    {
        $this->scopedAffected(
            "UPDATE pro_profiles p
                SET p.rating_count = (
                      SELECT COUNT(*) FROM reviews r
                       WHERE r.pro_id = p.id AND r.status = 'published'),
                    p.rating_avg = COALESCE((
                      SELECT ROUND(AVG(r.rating), 2) FROM reviews r
                       WHERE r.pro_id = p.id AND r.status = 'published'), 0)
              WHERE p.id = :pro AND p.market_id = :market_id AND p.is_demo = 0",
            ['pro' => $proId],
        );
    }

    /**
     * Reviews waiting on a moderator, oldest first.
     *
     * Oldest first because this is a queue, and a queue that shows the newest
     * item at the top is one where the oldest never gets done.
     *
     * @return array<int,array<string,mixed>>
     */
    public function awaitingModeration(): array
    {
        return $this->scopedAll(
            "SELECT r.id, r.rating, r.body, r.created_at, r.pro_id,
                    p.business_name, p.slug AS pro_slug,
                    j.reference AS job_reference, j.title AS job_title,
                    u.first_name, u.last_name
               FROM reviews r
               JOIN pro_profiles p ON p.id = r.pro_id
               JOIN jobs j ON j.id = r.job_id
               JOIN users u ON u.id = r.author_user_id
              WHERE r.market_id = :market_id
                AND r.status = 'pending_review'
              ORDER BY r.created_at ASC",
        );
    }

    /** One review, for a moderator acting on it. @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->scopedOne(
            'SELECT * FROM reviews WHERE id = :id AND market_id = :market_id',
            ['id' => $id],
        );
    }

    /**
     * Publish or remove a review, and move the pro's rating with it.
     *
     * The rollup is refreshed on both paths. Removing a review that inflated
     * an average has to deflate it again, or moderation is theatre.
     */
    public function moderate(int $id, string $status): bool
    {
        if (!in_array($status, ['published', 'removed'], true)) {
            return false;
        }

        $review = $this->find($id);
        if ($review === null) {
            return false;
        }

        $this->scopedAffected(
            'UPDATE reviews SET status = :status WHERE id = :id AND market_id = :market_id',
            ['status' => $status, 'id' => $id],
        );
        $this->recalculate((int) $review['pro_id']);

        return true;
    }

    /**
     * Jobs old enough to ask about, that got at least one quote and have not
     * been asked yet. Driven by the sweep.
     *
     * At least one quote, because "how did it go?" for a job nobody answered
     * is a worse message than silence — and that job is being refunded by
     * step one of the same sweep anyway.
     *
     * @return array<int,array<string,mixed>>
     */
    public function dueForInvite(int $afterDays = 14, int $limit = 50): array
    {
        return $this->scopedAll(
            "SELECT j.id, j.reference, j.title, j.quote_count,
                    u.first_name, u.email
               FROM jobs j
               JOIN users u ON u.id = j.user_id
              WHERE j.market_id = :market_id
                AND j.status IN ('active', 'expired', 'closed')
                AND j.is_demo = 0
                AND j.quote_count > 0
                AND j.review_invited_at IS NULL
                AND j.published_at IS NOT NULL
                AND j.published_at < DATE_SUB(NOW(), INTERVAL {$afterDays} DAY)
              ORDER BY j.published_at ASC
              LIMIT {$limit}",
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
