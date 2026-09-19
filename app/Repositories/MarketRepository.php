<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Database;

/**
 * Markets are the tenants, so this repository is deliberately NOT scoped —
 * resolving which market a visitor is in has to happen before a scope exists.
 * It reads the markets table only.
 */
final class MarketRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> Live markets, for the market picker. */
    public function live(): array
    {
        return $this->db->all(
            "SELECT id, slug, name, code, city, state, listing_fee_cents
               FROM markets WHERE status = 'live' ORDER BY name"
        );
    }

    /** @return array<int,array<string,mixed>> Every market, including staged. Superadmin only. */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM markets ORDER BY status DESC, name');
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->one('SELECT * FROM markets WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM markets WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** The default market a visitor lands in when the URL names none. */
    public function default(): ?array
    {
        return $this->db->one(
            "SELECT * FROM markets WHERE status = 'live' ORDER BY launched_at ASC, id ASC LIMIT 1"
        );
    }

    /** What a job post costs here, in cents. Zero means this market is running free. */
    public function listingFeeCents(int $marketId): int
    {
        return (int) $this->db->value(
            'SELECT listing_fee_cents FROM markets WHERE id = :id',
            ['id' => $marketId],
        );
    }
}
