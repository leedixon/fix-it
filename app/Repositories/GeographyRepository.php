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
            'SELECT ci.id, ci.name, ci.slug, ci.state, co.short_name AS county, co.slug AS county_slug
               FROM cities ci
               JOIN counties co ON co.id = ci.county_id
              WHERE ci.market_id = :market_id AND ci.has_page = 1
              ORDER BY ci.sort_order, ci.name'
        );
    }

    /**
     * The city pages that have something on them.
     *
     * A city page is a list of the tradespeople covering that town and the
     * jobs open in it. With neither, it is a heading, one sentence and a row
     * of filter links — the same shape nineteen times over, which is what
     * "doorway page" and "thin content" describe, and asking to be indexed
     * anyway is asking to be judged on the worst version of the site.
     *
     * So the sitemap offers a town once it has something, and not before.
     * Nothing has to be switched on by hand: the first real tradesperson to
     * cover a county puts every town in that county into the next sitemap
     * read, and the first job posted in a town does it for that town alone.
     *
     * Demo rows do not count. A crawler cannot see the SAMPLE badge, and a
     * page full of invented businesses is worse to have indexed than an
     * empty one.
     *
     * @return array<int,array<string,mixed>>
     */
    public function pageCitiesWithContent(): array
    {
        return $this->scopedAll(
            "SELECT ci.id, ci.name, ci.slug, ci.state, co.short_name AS county, co.slug AS county_slug
               FROM cities ci
               JOIN counties co ON co.id = ci.county_id
              WHERE ci.market_id = :market_id AND ci.has_page = 1
                AND (EXISTS (SELECT 1
                               FROM pro_profiles p
                               JOIN pro_county_areas a ON a.pro_id = p.id
                              WHERE p.market_id = ci.market_id
                                AND p.status = 'active'
                                AND p.is_demo = 0
                                AND a.county_id = ci.county_id)
                  OR EXISTS (SELECT 1
                               FROM jobs j
                              WHERE j.market_id = ci.market_id
                                AND j.city_id = ci.id
                                AND j.status = 'active'
                                AND j.is_demo = 0))
              ORDER BY ci.sort_order, ci.name"
        );
    }

    /**
     * A city from its URL segment — 'freeport-il' rather than 'freeport'.
     *
     * The suffix is matched in SQL instead of being parsed off in PHP, so
     * there is exactly one definition of the URL shape and no guessing about
     * where a name ends. 'mount-carroll-il' would otherwise have to be split
     * on the last hyphen and hoped about; here the database simply answers
     * whether any row spells itself that way.
     *
     * Forty-six rows, so the unindexed expression costs nothing measurable.
     */
    public function findCityByPath(string $segment): ?array
    {
        return $this->scopedOne(
            "SELECT ci.*, co.short_name AS county, co.slug AS county_slug
               FROM cities ci
               JOIN counties co ON co.id = ci.county_id
              WHERE ci.market_id = :market_id
                AND CONCAT(ci.slug, '-', LOWER(ci.state)) = :segment
              LIMIT 1",
            ['segment' => $segment],
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
