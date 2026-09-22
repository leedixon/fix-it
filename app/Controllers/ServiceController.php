<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Seo;
use FixListed\Core\TradeCopy;
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
            // Trade names do not survive being lowercased into the middle of
            // a sentence ("a odd jobs job"), and this string is what a search
            // result shows. TradeCopy::jobPhrase carries the article.
            'description' => $count > 0
                ? self::businesses($count, (string) $trade['name']) . ' listed across '
                    . $this->market['name'] . '. Post ' . TradeCopy::jobPhrase($slug)
                    . ' once for a flat fee and they quote you directly — no commission.'
                : $trade['name'] . ' in ' . $this->market['name']
                    . ': what the work covers, what it costs to ask, and what to check before you hire.',
            'trade'       => $trade,
            'copy'        => $copy,
            'licence'     => $licence,
            'pros'        => $listed,
            'proCount'    => $count,
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
                ? self::businesses($count, (string) $trade['name']) . ' covering '
                    . $this->market['name'] . ($count === 1 ? ' is' : ' are') . ' listed on Fix Listed.'
                : 'Fix Listed is open to ' . strtolower((string) $trade['name'])
                    . ' businesses covering ' . $this->market['name'] . '.',
        ];
    }

    /**
     * "1 plumbing business", "2 carpentry businesses".
     *
     * A count and a noun, agreeing. Small, and it appears in the meta
     * description and in the structured data, which is to say in the two
     * places a stranger forms their first opinion of whether this site is
     * maintained by anyone.
     */
    private static function businesses(int $count, string $tradeName): string
    {
        return $count . ' ' . strtolower($tradeName) . ' ' . ($count === 1 ? 'business' : 'businesses');
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
        /*
         * Questions phrased so they work for every trade name.
         *
         * "What does a odd jobs job usually involve?" is what you get from
         * interpolating the name into a sentence, and this text is published
         * to Google, where it may be read aloud. Each question below takes
         * the name as a noun on its own, which every one of the ten survives.
         */
        $name  = strtolower((string) $trade['name']);
        $pairs = [];

        if ($copy !== null) {
            $pairs['What does ' . $trade['name'] . ' cover?'] = $copy['intro'];
            $pairs['What should I check before hiring for ' . $name . '?'] = $copy['ask'];
        }

        // Only when there is real guidance. The repository returns known=false
        // for a state nobody has entered yet, and that answer is "we do not
        // know", which is honest on the page and not worth marking up.
        //
        // The state is not named in the question — the market's is not always
        // Illinois, and the answer says which one it is anyway.
        if (($licence['known'] ?? false) === true && ($licence['guidance'] ?? '') !== '') {
            $pairs['Is ' . $name . ' licensed by the state?'] = (string) $licence['guidance'];
        }

        $pairs['What does it cost to get quotes on Fix Listed?'] =
            'Posting a job costs a flat ' . money((int) $this->market['listing_fee_cents'])
            . '. Tradespeople quote for free and Fix Listed takes no commission on the work.';

        return Seo::faq($pairs);
    }
}
