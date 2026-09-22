<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Seo;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\ProRepository;
use FixListed\Repositories\TradeRepository;

/**
 * City landing pages: /handyman/freeport-il.
 *
 * These are the pages that earn search traffic. Somebody types "handyman
 * Freeport IL", not "handyman Stephenson County" — cities are how people
 * describe where they live, while counties are how the coverage data is
 * actually stored. The page bridges the two: it is addressed by city, and it
 * lists the pros who cover that city's county.
 *
 * The address used to be /in/freeport. It changed because the old one said
 * nothing about what the page is for: /handyman/freeport-il contains the two
 * words somebody actually types, and the state suffix separates this Freeport
 * from the six others. The old URLs still answer — see legacy() — with a 301,
 * permanently, because anything else throws away every link they earned.
 */
final class CityController extends Controller
{
    public function show(string $segment): Response
    {
        $geo  = new GeographyRepository($this->db, $this->scope);
        $city = $geo->findCityByPath($segment);

        if ($city === null) {
            /*
             * Not a '{town}-{state}' segment. Before giving up, try it as a
             * bare town — /handyman/freeport — and send it to the canonical
             * address. People shorten URLs by hand, and one town spelled two
             * ways is two pages competing for the same search.
             */
            $bare = $geo->findCity($segment);
            if ($bare !== null) {
                return Response::movedPermanently(Seo::cityPath($bare));
            }
            throw new NotFound('handyman/' . $segment);
        }

        $pros     = new ProRepository($this->db, $this->scope);
        $countyId = (int) $city['county_id'];
        $proCount = $pros->countActive($countyId);
        $path     = Seo::cityPath($city);

        return $this->page('site/city', [
            'title'       => 'Handymen and trades in ' . $city['name'] . ', IL — Fix Listed',
            'description' => 'Licensed tradespeople serving ' . $city['name']
                . ' and the rest of ' . $city['county'] . '. Post a job, get quotes directly, pay no commission.',
            'city'        => $city,
            'pros'        => $pros->directory(null, $countyId, 24),
            'proCount'    => $proCount,
            'cityJobs'    => (new JobRepository($this->db, $this->scope))->inCity((int) $city['id'], 8),
            'tradeTiles'  => (new TradeRepository($this->db))->withProCounts((int) $this->market['id']),
            'otherCities' => $geo->pageCities(),
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => $city['county'] . ' County', 'href' => '/pros?county=' . $city['county_slug']],
                ['label' => $city['name']],
            ],
            'analytics'   => $this->analytics('site/city', [
                'city'   => (string) $city['name'],
                'county' => (string) $city['county'],
            ]),
            // The real count, not $proCount — see ProRepository::countReal().
            'jsonLd'      => [$this->serviceNode($city, $path, $pros->countReal($countyId))],
        ]);
    }

    /**
     * The old /in/{slug} address, answered once and permanently.
     *
     * It resolves the city rather than rewriting the string, so a slug that
     * never existed still 404s instead of redirecting to a page that is not
     * there — a redirect chain ending in a 404 is worse than the 404 on its
     * own, for a visitor and for a crawler.
     */
    public function legacy(string $slug): Response
    {
        $city = (new GeographyRepository($this->db, $this->scope))->findCity($slug);
        if ($city === null) {
            throw new NotFound('in/' . $slug);
        }
        return Response::movedPermanently(Seo::cityPath($city));
    }

    /**
     * What this page offers, as structured data.
     *
     * Service, not LocalBusiness: Fix Listed does not do the plumbing, it is
     * the directory where you find whoever does. Claiming to be a local
     * business in each of forty-six towns is both false and precisely the
     * pattern the guidelines call doorway pages.
     *
     * @param array<string,mixed> $city
     * @return array<string,mixed>
     */
    private function serviceNode(array $city, string $path, int $realCount): array
    {
        $place = $city['name'] . ', ' . $city['state'];

        return [
            '@type'       => 'Service',
            '@id'         => abs_url($path) . '#service',
            'name'        => 'Handyman and trade services in ' . $place,
            'serviceType' => 'Handyman',
            'url'         => abs_url($path),
            'provider'    => ['@id' => Seo::organizationId()],
            'areaServed'  => [
                '@type'           => 'City',
                'name'            => $city['name'],
                'containedInPlace' => [
                    '@type' => 'AdministrativeArea',
                    'name'  => $city['county'] . ' County, ' . $city['state'],
                ],
            ],
            // Only stated when it is true. A count of zero is left off rather
            // than published as "0 providers", and it is never rounded up.
            'description' => $realCount > 0
                ? $realCount . ' tradespeople listed on Fix Listed cover ' . $place
                    . '. Post a job once for a flat fee and they quote you directly, with no commission.'
                : 'Fix Listed is open to tradespeople covering ' . $place
                    . '. Post a job once for a flat fee and quotes come to you directly, with no commission.',
        ];
    }
}
