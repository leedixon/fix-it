<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Repositories\ProRepository;
use FixListed\Repositories\ReviewRepository;
use FixListed\Repositories\TradeRepository;

final class DirectoryController extends Controller
{
    public function index(): Response
    {
        $pros   = new ProRepository($this->db, $this->scope);
        $county = $this->selectedCounty();
        $trade  = $this->selectedTrade();

        $results = $pros->directory($trade['id'] ?? null, $county['id'] ?? null, 60);

        // The title says what was actually filtered, because a page titled
        // "Tradespeople" tells a search engine and a visitor nothing about
        // which ones.
        $what  = $trade['name'] ?? 'Tradespeople';
        $where = $county['name'] ?? $this->market['name'];

        return $this->page('site/directory', [
            'title'       => $what . ' in ' . $where . ' — Fix Listed',
            'description' => 'Vetted ' . strtolower((string) ($trade['name'] ?? 'tradespeople'))
                . ' serving ' . $where . '. Licence and insurance checked. Quote directly, no commission.',
            'pros'        => $results,
            'county'      => $county,
            'trade'       => $trade,
            'trades'      => (new TradeRepository($this->db))->all(),
            'analytics'   => $this->analytics('site/directory', [
                'trade'          => $trade['slug'] ?? null,
                'county'         => $county['slug'] ?? null,
                'listings_shown' => count($results),
            ]),
            'crumbs'      => $trade !== null || $county !== null
                ? [['label' => 'Home', 'href' => '/'],
                   ['label' => 'Tradespeople', 'href' => '/pros'],
                   ['label' => (string) ($trade['name'] ?? $county['short_name'])]]
                : [['label' => 'Home', 'href' => '/'], ['label' => 'Tradespeople']],
        ]);
    }

    public function show(string $slug): Response
    {
        $repo = new ProRepository($this->db, $this->scope);
        $pro  = $repo->findBySlug($slug);
        if ($pro === null) {
            throw new NotFound('pro/' . $slug);
        }

        $id       = (int) $pro['id'];
        $reviews  = new ReviewRepository($this->db, $this->scope);
        $skills   = $repo->skills($id);
        $counties = $repo->counties($id);

        return $this->page('site/pro', [
            'title'       => $pro['display_name'] . ' — ' . ($pro['headline'] ?: 'Fix Listed'),
            'description' => excerpt((string) $pro['bio'], 155),
            'pro'         => $pro,
            'skills'      => $skills,
            'proCounties' => $counties,
            'reviews'     => $reviews->forPro($id),
            'analytics'   => $this->analytics('site/pro', [
                // The slug, not the name. It is the identifier the reports
                // need and it is not somebody's name in a third party's logs.
                'pro' => (string) $pro['slug'],
            ]),
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Tradespeople', 'href' => '/pros'],
                ['label' => (string) $pro['display_name']],
            ],
            'jsonLd'      => array_values(array_filter([
                $this->businessNode($pro, $skills, $counties, $reviews->publishedStats($id)),
            ])),
        ]);
    }

    /**
     * A tradesperson's profile as a LocalBusiness.
     *
     * Two hard rules, both about not publishing things that are not true.
     *
     * A seeded profile gets no node at all. The page itself says "this is a
     * sample profile" in a banner and badges every card, so a person cannot
     * be fooled; structured data has no banner, and a fabricated business
     * described to a search engine as a local business is exactly the harm
     * the disclosure exists to prevent.
     *
     * And aggregateRating comes only from real published reviews, counted at
     * request time — never from the denormalised rating columns, which
     * include seeded reviews. No reviews means no rating node, rather than a
     * rating of zero.
     *
     * @param array<string,mixed> $pro
     * @param array<int,string> $skills
     * @param array<int,array<string,mixed>> $counties the counties they cover
     * @param array{count:int,average:float} $stats
     * @return array<string,mixed>
     */
    private function businessNode(array $pro, array $skills, array $counties, array $stats): array
    {
        if (!empty($pro['is_demo'])) {
            return [];
        }

        $path = '/pros/' . $pro['slug'];
        $name = (string) ($pro['display_name'] ?? $pro['business_name']);

        /*
         * Address to the town, and no further.
         *
         * Most of these are people working out of their own homes, and their
         * street address is not on the profile because it should not be. The
         * locality is what a homeowner needs and what a search engine can use.
         */
        $address = [
            '@type'           => 'PostalAddress',
            'addressLocality' => (string) ($pro['home_city'] ?? ''),
            'addressRegion'   => (string) ($this->market['state'] ?? 'IL'),
            'addressCountry'  => 'US',
        ];

        $rating = $stats['count'] > 0 ? [
            '@type'       => 'AggregateRating',
            'ratingValue' => $stats['average'],
            'reviewCount' => $stats['count'],
            'bestRating'  => 5,
            'worstRating' => 1,
        ] : null;

        return [
            '@type'       => 'LocalBusiness',
            '@id'         => abs_url($path) . '#business',
            'name'        => $name,
            'url'         => abs_url($path),
            'description' => excerpt((string) ($pro['bio'] ?? ''), 300),
            'address'     => $address,
            // The counties they actually said they will drive to, not the
            // whole market — the coverage is the useful claim and it is the
            // one the profile page shows.
            'areaServed'  => array_map(
                fn (array $c): array => [
                    '@type' => 'AdministrativeArea',
                    'name'  => $c['short_name'] . ' County, ' . ($this->market['state'] ?? 'IL'),
                ],
                $counties,
            ),
            'knowsAbout'  => array_values($skills),
            // A free-text field, so the honest thing is the rate they set —
            // not a made-up band. Left off entirely when they have not set one.
            'priceRange'  => isset($pro['hourly_rate_cents']) && $pro['hourly_rate_cents'] !== null
                ? money((int) $pro['hourly_rate_cents']) . ' per hour'
                : null,
            'aggregateRating' => $rating,
        ];
    }
}
