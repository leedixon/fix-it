<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\ProRepository;
use FixListed\Repositories\TradeRepository;

/**
 * City landing pages: /in/freeport.
 *
 * These are the pages that earn search traffic. Somebody types "handyman
 * Freeport IL", not "handyman Stephenson County" — cities are how people
 * describe where they live, while counties are how the coverage data is
 * actually stored. The page bridges the two: it is addressed by city, and it
 * lists the pros who cover that city's county.
 */
final class CityController extends Controller
{
    public function show(string $slug): Response
    {
        $geo  = new GeographyRepository($this->db, $this->scope);
        $city = $geo->findCity($slug);
        if ($city === null) {
            throw new NotFound('city/' . $slug);
        }

        $pros     = new ProRepository($this->db, $this->scope);
        $countyId = (int) $city['county_id'];

        return $this->page('site/city', [
            'title'       => 'Handymen and trades in ' . $city['name'] . ', IL — Fix Listed',
            'description' => 'Licensed tradespeople serving ' . $city['name']
                . ' and the rest of ' . $city['county'] . '. Post a job, get quotes directly, pay no commission.',
            'city'        => $city,
            'pros'        => $pros->directory(null, $countyId, 24),
            'proCount'    => $pros->countActive($countyId),
            'cityJobs'    => (new JobRepository($this->db, $this->scope))->inCity((int) $city['id'], 8),
            'tradeTiles'  => (new TradeRepository($this->db))->withProCounts((int) $this->market['id']),
            'otherCities' => $geo->pageCities(),
        ]);
    }
}
