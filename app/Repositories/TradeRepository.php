<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Database;
use FixListed\Core\Demo;

/**
 * Trades are global reference data, like counties — the same ten categories in
 * every market. Deliberately not a tenant Repository: there is no market_id on
 * the table for one to scope by.
 *
 * The per-market counts that the home page shows are a separate, scoped query,
 * because those very much do differ by market.
 */
final class TradeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->all(
            'SELECT id, slug, name, icon FROM trades WHERE active = 1 ORDER BY sort_order, name'
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->one(
            'SELECT id, slug, name, icon FROM trades WHERE slug = :slug AND active = 1 LIMIT 1',
            ['slug' => $slug],
        );
    }

    /**
     * Trades with how many active pros cover this market, for the category
     * tiles. A tile claiming a category the market cannot actually serve is
     * the fastest way to lose a visitor's trust, so the count travels with the
     * name and the template decides what to do when it is zero.
     *
     * @return array<int,array<string,mixed>>
     */
    public function withProCounts(int $marketId): array
    {
        $demoFilter = Demo::filter('p');

        return $this->db->all(
            "SELECT t.id, t.slug, t.name, t.icon,
                    (SELECT COUNT(DISTINCT p.id)
                       FROM pro_profiles p
                       JOIN pro_trades pt ON pt.pro_id = p.id AND pt.trade_id = t.id
                      WHERE p.status = 'active'
                        {$demoFilter}
                        AND EXISTS (SELECT 1 FROM pro_county_areas a
                                     WHERE a.pro_id = p.id AND a.market_id = :market_id)
                    ) AS pro_count
               FROM trades t
              WHERE t.active = 1
              ORDER BY t.sort_order, t.name",
            ['market_id' => $marketId],
        );
    }
}
