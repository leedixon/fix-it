<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\TradeRepository;

final class JobsController extends Controller
{
    public function index(): Response
    {
        $jobs  = new JobRepository($this->db, $this->scope);
        $trade = $this->selectedTrade();

        return $this->page('site/jobs', [
            'title'       => ($trade['name'] ?? 'Open') . ' jobs in ' . $this->market['name'] . ' — Fix Listed',
            'description' => 'Homeowners in ' . $this->market['name']
                . ' looking for quotes right now. Free for tradespeople to quote, and you keep the whole job.',
            'jobs'        => $jobs->board($trade['id'] ?? null, 60),
            'trade'       => $trade,
            'trades'      => (new TradeRepository($this->db))->all(),
            'openCount'   => $jobs->countOpen(),
        ]);
    }

    public function show(string $reference): Response
    {
        // References are generated uppercase ('NWI-4K2P9M'); a link typed or
        // copied in lower case should still land on the job rather than a 404.
        $job = (new JobRepository($this->db, $this->scope))->findByReference(strtoupper($reference));
        if ($job === null) {
            throw new NotFound('job/' . $reference);
        }

        return $this->page('site/job', [
            'title'       => $job['title'] . ' — Fix Listed',
            'description' => excerpt((string) $job['description'], 155),
            'job'         => $job,
        ]);
    }
}
