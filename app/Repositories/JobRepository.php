<?php
declare(strict_types=1);

namespace FixListed\Repositories;

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

        return $this->scopedAll(
            "SELECT j.id, j.reference, j.title, j.description, j.zip, j.urgency,
                    j.budget_min_cents, j.budget_max_cents, j.quote_count,
                    j.published_at, t.name AS trade_name, t.slug AS trade_slug
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
              WHERE j.market_id = :market_id
                AND j.status = 'active'
                {$tradeFilter}
              ORDER BY j.published_at DESC
              LIMIT {$limit}",
            $tradeId !== null ? ['trade_id' => $tradeId] : [],
        );
    }

    public function findByReference(string $reference): ?array
    {
        return $this->scopedOne(
            "SELECT j.*, t.name AS trade_name
               FROM jobs j
               JOIN trades t ON t.id = j.trade_id
              WHERE j.market_id = :market_id
                AND j.reference = :reference
                AND j.status = 'active'
              LIMIT 1",
            ['reference' => $reference],
        );
    }

    public function countOpen(): int
    {
        return (int) $this->scopedValue(
            "SELECT COUNT(*) FROM jobs WHERE market_id = :market_id AND status = 'active'"
        );
    }
}
