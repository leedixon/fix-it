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
        ]);
    }

    public function show(string $slug): Response
    {
        $repo = new ProRepository($this->db, $this->scope);
        $pro  = $repo->findBySlug($slug);
        if ($pro === null) {
            throw new NotFound('pro/' . $slug);
        }

        $id = (int) $pro['id'];

        return $this->page('site/pro', [
            'title'       => $pro['display_name'] . ' — ' . ($pro['headline'] ?: 'Fix Listed'),
            'description' => excerpt((string) $pro['bio'], 155),
            'pro'         => $pro,
            'skills'      => $repo->skills($id),
            'proCounties' => $repo->counties($id),
            'reviews'     => (new ReviewRepository($this->db, $this->scope))->forPro($id),
        ]);
    }
}
