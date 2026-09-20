<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\Response;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\ProRepository;
use FixListed\Repositories\TradeRepository;

final class HomeController extends Controller
{
    public function index(): Response
    {
        $pros = new ProRepository($this->db, $this->scope);
        $jobs = new JobRepository($this->db, $this->scope);
        $geo  = new GeographyRepository($this->db, $this->scope);

        $county = $this->selectedCounty();

        return $this->page('site/home', [
            'title'       => 'Fix Listed — handymen and trades in ' . $this->market['name'],
            'description' => 'Post what needs fixing for a flat $'
                . number_format(((int) $this->market['listing_fee_cents']) / 100, 0)
                . '. Every licensed tradesperson in ' . $this->market['name']
                . ' sees it and quotes you directly. No commission, ever.',
            'county'      => $county,
            'proCount'    => $pros->countActive($county['id'] ?? null),
            'jobCount'    => $jobs->countOpen(),
            // Six is what fills two rows at the widest breakpoint and one on a
            // phone, so the section never ends on a half-empty row.
            'featured'    => $pros->directory(null, $county['id'] ?? null, 6),
            'latestJobs'  => array_slice($jobs->board(null, 4), 0, 4),
            'tradeTiles'  => (new TradeRepository($this->db))->withProCounts((int) $this->market['id']),
            'cities'      => $geo->pageCities(),
            'fee'         => (int) $this->market['listing_fee_cents'],
        ]);
    }
}
