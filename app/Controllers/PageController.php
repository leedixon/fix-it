<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Academy;
use FixListed\Core\Controller;
use FixListed\Core\Response;
use FixListed\Core\Seo;
use FixListed\Repositories\AcademyRepository;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\ProRepository;

/** The pages that sell rather than list: pricing, the pro pitch, the legals. */
final class PageController extends Controller
{
    public function pricing(): Response
    {
        return $this->page('site/pricing', [
            'crumbs'      => [['label' => 'Home', 'href' => '/'], ['label' => 'Pricing']],
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
            /*
             * Not "List your trade business" — /list-your-business already
             * has that title, and two URLs under one title compete with each
             * other for the query rather than adding up. This page argues the
             * case; that one is the form.
             */
            'title'       => 'Why list with Fix Listed — no commission, no lead resale',
            'description' => 'A profile is free, quoting is free, and you keep the whole job. '
                . 'No commission and no lead resale in ' . $this->market['name'] . '.',
            'openJobs'    => (new JobRepository($this->db, $this->scope))->countOpen(),
            'proCount'    => (new ProRepository($this->db, $this->scope))->countActive(),
            'boost'       => (int) $this->market['boost_price_cents'],
            'spotlight'   => (int) $this->market['spotlight_price_cents'],
            /*
             * The tradespeople track, read rather than retyped, so a lesson
             * taken down in the admin stops being advertised here too — and
             * so the titles on this page cannot drift from the real ones.
             */
            'lessons'     => (new AcademyRepository($this->db))->track(Academy::TRACK_PRO),
            /*
             * No breadcrumbs on this one. The page opens with a full-bleed
             * dark hero and there is nowhere to put a trail that does not
             * look like a mistake — and a BreadcrumbList describing a trail
             * the page does not show is the exact thing the guidelines are
             * about. The FAQ below is on the page, so it is marked up.
             */
            /*
             * The page's own "The questions every pro asks first" section,
             * repeated here as FAQPage.
             *
             * Word for word, deliberately. The guideline is that marked-up
             * answers must be on the page, and the only way to be sure of
             * that in six months is to keep the two copies close enough that
             * changing one without the other looks wrong — so if this text
             * and site/for_pros.php ever disagree, the template is right and
             * this is the bug.
             */
            'jsonLd'      => [Seo::faq([
                'Do you sell my lead to anyone else?' =>
                    'No. A job is posted once and shown to the pros covering that county. We do not '
                    . 'sell contact details to anybody, here or elsewhere.',
                'What do you take when I win a job?' =>
                    'Nothing. The homeowner pays their listing fee to us and pays you for the work. '
                    . 'We are not in the middle of that second payment at all.',
                'Why do you check licences?' =>
                    'Because the badge is worth nothing if it is not checked, and an unlicensed '
                    . "competitor undercutting you on a licensed job is your problem as much as the "
                    . "homeowner's.",
                'What is the paid placement for?' =>
                    'Position on the page, and nothing else. It is built from your existing profile, '
                    . 'it is labelled wherever it appears, and slots are limited so the top of the '
                    . 'page still means something.',
            ])],
        ]);
    }

    public function legal(string $which): Response
    {
        return $this->page('site/legal_' . $which, [
            'title'   => ($which === 'terms' ? 'Terms of use' : 'Privacy') . ' — Fix Listed',
            // Both pages shipped with no description, so the snippet was
            // whatever Google chose to lift — usually the first clause of a
            // legal sentence, which reads badly and says nothing.
            'description' => $which === 'terms'
                ? 'The terms you agree to when you post a job or list a business on Fix Listed: '
                    . 'fees, refunds, reviews, and what we do and do not do.'
                : 'What Fix Listed collects, why, who it is shared with, and how to get it '
                    . 'deleted. No lead reselling, here or anywhere.',
            'updated' => 'September 2026',
            'crumbs'  => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => $which === 'terms' ? 'Terms of use' : 'Privacy'],
            ],
        ]);
    }

    /**
     * The page the service worker shows when the network is gone.
     *
     * A real route rather than a static file, so it wears the same chrome as
     * everything else and cannot drift out of the design. noindex, because a
     * search result reading "You are offline" would be a strange thing to
     * find.
     */
    public function offline(): Response
    {
        return $this->page('site/offline', [
            'title'   => 'You are offline — Fix Listed',
            'noindex' => true,
        ]);
    }

    public function contact(): Response
    {
        return $this->page('site/contact', [
            'crumbs'      => [['label' => 'Home', 'href' => '/'], ['label' => 'Contact']],
            'title'       => 'Contact — Fix Listed',
            'description' => 'How to reach Fix Listed.',
        ]);
    }
}
