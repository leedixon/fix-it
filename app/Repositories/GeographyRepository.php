<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Repository;

/** Counties and cities within one market. */
final class GeographyRepository extends Repository
{
    protected function table(): string
    {
        return 'cities';
    }

    /** @return array<int,array<string,mixed>> */
    public function counties(): array
    {
        return $this->scopedAll(
            'SELECT c.id, c.fips, c.name, c.short_name, c.slug, c.state
               FROM counties c
               JOIN market_counties mc ON mc.county_id = c.id
              WHERE mc.market_id = :market_id
              ORDER BY c.short_name'
        );
    }

    /** Cities with a landing page, in display order. */
    public function pageCities(): array
    {
        return $this->scopedAll(
            'SELECT ci.id, ci.name, ci.slug, co.short_name AS county
               FROM cities ci
               JOIN counties co ON co.id = ci.county_id
              WHERE ci.market_id = :market_id AND ci.has_page = 1
              ORDER BY ci.sort_order, ci.name'
        );
    }

    /** Every city, for the "where is the job?" picker when posting. */
    public function allCities(): array
    {
        return $this->scopedAll(
            'SELECT ci.id, ci.name, ci.slug, ci.county_id, co.short_name AS county
               FROM cities ci
               JOIN counties co ON co.id = ci.county_id
              WHERE ci.market_id = :market_id
              ORDER BY co.short_name, ci.name'
        );
    }

    public function findCity(string $slug): ?array
    {
        return $this->scopedOne(
            'SELECT ci.*, co.short_name AS county, co.slug AS county_slug
               FROM cities ci
               JOIN counties co ON co.id = ci.county_id
              WHERE ci.market_id = :market_id AND ci.slug = :slug
              LIMIT 1',
            ['slug' => $slug],
        );
    }
}
