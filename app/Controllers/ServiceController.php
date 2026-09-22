<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Seo;
use FixListed\Core\TradeCopy;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\LicenceRepository;
use FixListed\Repositories\ProRepository;
use FixListed\Repositories\TradeRepository;

/**
 * Trade pages: /services/plumbing.
 *
 * The counterpart to the city pages. A city page answers "who is near me";
 * this answers "what does this trade do and what should I check before I let
 * one in the house". The two cross-link, which is the whole point — somebody
 * arriving on "plumber Freeport IL" should be one click from either.
 *
 * The page stands up with nobody listed. That is not a compromise: the
 * licensing guidance and the what-to-ask copy are the most useful things on
 * it either way, and a page that only works once the directory is full is a
 * page that cannot help fill it.
 */
final class ServiceController extends Controller
{
    public function show(string $slug): Response
    {
        $trades = new TradeRepository($this->db);
        $trade  = $trades->findBySlug($slug);
        if ($trade === null) {
            throw new NotFound('services/' . $slug);
        }

        $tradeId = (int) $trade['id'];
        $pros    = new ProRepository($this->db, $this->scope);
        $count   = $pros->countActive(null, $tradeId);
        // What the page says and what the markup says are two different
        // numbers on purpose — see ProRepository::countReal().
        $real    = $pros->countReal(null, $tradeId);
        $path    = Seo::servicePath($slug);
        $copy    = TradeCopy::for($slug);
        $listed  = $pros->directory($tradeId, null, 12);

        /*
         * Licensing guidance, straight from the table the admin screen edits.
         *
         * The state is the market's, not the visitor's. Somebody reading this
         * from out of state is being told how Illinois works, which is the
         * correct answer for a page about hiring a tradesperson in Illinois.
         */
        $licence = (new LicenceRepository($this->db))
            ->forTrade((string) ($this->market['state'] ?? 'IL'), $tradeId);

        return $this->page('site/service', [
            'title'       => $trade['name'] . ' in ' . $this->market['name'] . ' — Fix Listed',
            'description' => $count > 0
                ? $count . ' ' . strtolower((string) $trade['name']) . ' businesses listed across '
                    . $this->market['name'] . '. Post the job once for a flat fee and they quote you '
                    . 'directly — no commission.'
                : strtolower((string) $trade['name']) . ' in ' . $this->market['name']
                    . ': what the work covers, what it costs to ask, and what to check before you hire.',
            'trade'       => $trade,
            'copy'        => $copy,
            'licence'     => $licence,
            'pros'        => $listed,
            'proCount'    => $count,
            'cities'      => (new GeographyRepository($this->db, $this->scope))->pageCities(),
            'otherTrades' => $trades->all(),
            'fee'         => (int) $this->market['listing_fee_cents'],
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Services', 'href' => '/pros'],
                ['label' => $trade['name']],
            ],
            'analytics'   => $this->analytics('site/service', [
                'trade'          => (string) $trade['slug'],
                // Whether this page had anything to show is the question
                // worth asking of it: a launching-soon page that converts is
                // a page worth building more of.
                'listings_shown' => count($listed),
            ]),
            'jsonLd'      => array_values(array_filter([
                $this->serviceNode($trade, $path, $real),
                $this->faqNode($trade, $copy, $licence),
            ])),
        ]);
    }

    /**
     * @param array<string,mixed> $trade
     * @return array<string,mixed>
     */
    private function serviceNode(array $trade, string $path, int $count): array
    {
        $areaServed = [];
        foreach ($this->counties() as $county) {
            $areaServed[] = ['@type' => 'AdministrativeArea', 'name' => $county['name'] . ', ' . $county['state']];
        }

        return [
            '@type'       => 'Service',
            '@id'         => abs_url($path) . '#service',
            'name'        => $trade['name'] . ' in ' . $this->market['name'],
            'serviceType' => $trade['name'],
            'url'         => abs_url($path),
            'provider'    => ['@id' => Seo::organizationId()],
            'areaServed'  => $areaServed,
            // No offers node. Fix Listed charges the homeowner to post a job;
            // it does not price the plumbing, and a price on this node would
            // read as the cost of the work itself.
            'description' => $count > 0
                ? $count . ' ' . strtolower((string) $trade['name']) . ' businesses covering '
                    . $this->market['name'] . ' are listed on Fix Listed.'
                : 'Fix Listed is open to ' . strtolower((string) $trade['name'])
                    . ' businesses covering ' . $this->market['name'] . '.',
        ];
    }

    /**
     * FAQPage, built only from answers that are printed on the page.
     *
     * Every question here has its answer in site/service.php in the same
     * words. If the copy for a trade has not been written, the questions that
     * would have quoted it are left out rather than filled in with something
     * plausible.
     *
     * @param array<string,mixed> $trade
     * @param array{intro:string,jobs:array<int,string>,ask:string}|null $copy
     * @param array<string,mixed> $licence
     * @return array<string,mixed>
     */
    private function faqNode(array $trade, ?array $copy, array $licence): array
    {
        $name  = strtolower((string) $trade['name']);
        $pairs = [];

        if ($copy !== null) {
            $pairs['What does a ' . $name . ' job usually involve?'] = $copy['intro'];
            $pairs['What should I check before hiring for ' . $name . '?'] = $copy['ask'];
        }

        // Only when there is real guidance. The repository returns known=false
        // for a state nobody has entered yet, and that answer is "we do not
        // know", which is honest on the page and not worth marking up.
        if (($licence['known'] ?? false) === true && ($licence['guidance'] ?? '') !== '') {
            $pairs['Does Illinois license ' . $name . '?'] = (string) $licence['guidance'];
        }

        $pairs['What does it cost to get quotes on Fix Listed?'] =
            'Posting a job costs a flat ' . money((int) $this->market['listing_fee_cents'])
            . '. Tradespeople quote for free and Fix Listed takes no commission on the work.';

        return Seo::faq($pairs);
    }
}
