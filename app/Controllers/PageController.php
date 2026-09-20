<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\Response;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\ProRepository;

/** The pages that sell rather than list: pricing, the pro pitch, the legals. */
final class PageController extends Controller
{
    public function pricing(): Response
    {
        return $this->page('site/pricing', [
            'title'       => 'Pricing — Fix Listed',
            'description' => 'One flat fee to list a job. Free for tradespeople to quote. '
                . 'Optional placement for pros who want the top of the page.',
            'fee'         => (int) $this->market['listing_fee_cents'],
            'boost'       => (int) $this->market['boost_price_cents'],
            'spotlight'   => (int) $this->market['spotlight_price_cents'],
            'listingDays' => (int) $this->market['job_listing_days'],
            'refundHours' => (int) $this->market['refund_window_hours'],
        ]);
    }

    public function forPros(): Response
    {
        return $this->page('site/for_pros', [
            'title'       => 'List your trade business — Fix Listed',
            'description' => 'A profile is free, quoting is free, and you keep the whole job. '
                . 'No commission and no lead resale in ' . $this->market['name'] . '.',
            'openJobs'    => (new JobRepository($this->db, $this->scope))->countOpen(),
            'proCount'    => (new ProRepository($this->db, $this->scope))->countActive(),
            'boost'       => (int) $this->market['boost_price_cents'],
            'spotlight'   => (int) $this->market['spotlight_price_cents'],
        ]);
    }

    public function legal(string $which): Response
    {
        return $this->page('site/legal_' . $which, [
            'title'   => ($which === 'terms' ? 'Terms of use' : 'Privacy') . ' — Fix Listed',
            'updated' => 'September 2026',
        ]);
    }

    public function contact(): Response
    {
        return $this->page('site/contact', [
            'title'       => 'Contact — Fix Listed',
            'description' => 'How to reach Fix Listed.',
        ]);
    }
}
