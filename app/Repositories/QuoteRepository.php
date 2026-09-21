<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Database;
use FixListed\Core\Repository;
use RuntimeException;

final class QuoteRepository extends Repository
{
    protected function table(): string
    {
        return 'quotes';
    }

    /**
     * @param array<string,mixed> $data
     * @throws RuntimeException 'already_quoted' | 'job_not_open' | 'not_covered'
     */
    public function create(int $jobId, int $proId, array $data): int
    {
        return $this->db->transaction(function (Database $db) use ($jobId, $proId, $data): int {
            $marketId = $this->scope->marketId;

            // Re-read the job inside the transaction rather than trusting what
            // the form was rendered from. Between loading the page and
            // submitting it, the job can be closed, removed, or have been in
            // pending_payment all along.
            $job = $db->one(
                "SELECT id, county_id, status FROM jobs
                  WHERE id = :id AND market_id = :market_id FOR UPDATE",
                ['id' => $jobId, 'market_id' => $marketId],
            );
            if ($job === null || $job['status'] !== 'active') {
                throw new RuntimeException('job_not_open');
            }

            // A tradesperson may only quote work in a county they cover. The
            // form only shows jobs they cover, so reaching here means the id
            // was edited — but the check belongs at the write, not the read.
            $covers = $db->value(
                'SELECT COUNT(*) FROM pro_county_areas
                  WHERE pro_id = :pro AND county_id = :county AND market_id = :market_id',
                ['pro' => $proId, 'county' => $job['county_id'], 'market_id' => $marketId],
            );
            if ((int) $covers === 0) {
                throw new RuntimeException('not_covered');
            }

            $existing = $db->value(
                'SELECT COUNT(*) FROM quotes WHERE job_id = :job AND pro_id = :pro',
                ['job' => $jobId, 'pro' => $proId],
            );
            if ((int) $existing > 0) {
                throw new RuntimeException('already_quoted');
            }

            $id = $db->insert(
                'INSERT INTO quotes
                    (market_id, job_id, pro_id, amount_cents, amount_type, amount_max_cents,
                     message, can_start_on)
                 VALUES (:market_id, :job, :pro, :amount, :type, :amount_max, :message, :start)',
                [
                    'market_id' => $marketId, 'job' => $jobId, 'pro' => $proId,
                    'amount' => $data['amount_cents'], 'type' => $data['amount_type'],
                    'amount_max' => $data['amount_max_cents'], 'message' => $data['message'],
                    'start' => $data['can_start_on'],
                ],
            );

            // Denormalised onto the job so the board can show "7 quotes"
            // without counting rows on every card.
            $db->affected(
                'UPDATE jobs SET quote_count = quote_count + 1 WHERE id = :id',
                ['id' => $jobId],
            );

            return (int) $id;
        });
    }

    public function hasQuoted(int $jobId, int $proId): bool
    {
        return (int) $this->scopedValue(
            'SELECT COUNT(*) FROM quotes
              WHERE market_id = :market_id AND job_id = :job AND pro_id = :pro',
            ['job' => $jobId, 'pro' => $proId],
        ) > 0;
    }

    /** A tradesperson's own quotes, newest first. */
    public function forPro(int $proId, int $limit = 50): array
    {
        return $this->scopedAll(
            "SELECT q.id, q.amount_cents, q.amount_max_cents, q.amount_type, q.status,
                    q.created_at, q.viewed_at,
                    j.reference, j.title, j.status AS job_status,
                    t.name AS trade_name, ci.name AS city_name
               FROM quotes q
               JOIN jobs j ON j.id = q.job_id
               JOIN trades t ON t.id = j.trade_id
               LEFT JOIN cities ci ON ci.id = j.city_id
              WHERE q.market_id = :market_id AND q.pro_id = :pro
              ORDER BY q.created_at DESC
              LIMIT {$limit}",
            ['pro' => $proId],
        );
    }

    /** @return array<string,int> */
    public function statsForPro(int $proId): array
    {
        $row = $this->scopedOne(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'accepted') AS won,
                    SUM(viewed_at IS NOT NULL) AS viewed
               FROM quotes
              WHERE market_id = :market_id AND pro_id = :pro",
            ['pro' => $proId],
        ) ?? [];

        return [
            'total'  => (int) ($row['total'] ?? 0),
            'won'    => (int) ($row['won'] ?? 0),
            'viewed' => (int) ($row['viewed'] ?? 0),
        ];
    }
}
