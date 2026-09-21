<?php
declare(strict_types=1);

namespace FixListed\Controllers\Account;

use FixListed\Core\AccountController;
use FixListed\Core\Response;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\QuoteRepository;

final class DashboardController extends AccountController
{
    public function index(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        $profile = $this->profile();

        // An approved account with no profile should not be possible, but an
        // admin signing in here is — and they have no listing. Either way the
        // dashboard has nothing to show, so it says so rather than breaking.
        if ($profile === null) {
            return $this->page('account/no_profile', [
                'title' => 'Your account — Fix Listed',
            ]);
        }

        $proId  = (int) $profile['id'];
        $quotes = new QuoteRepository($this->db, $this->scope);

        return $this->page('account/dashboard', [
            'title'  => 'Your account — Fix Listed',
            'jobs'   => ($profile['status'] === 'active')
                ? (new JobRepository($this->db, $this->scope))->forPro($proId, 12)
                : [],
            'myQuotes' => $quotes->forPro($proId, 10),
            'stats'    => $quotes->statsForPro($proId),
        ]);
    }

    public function quotes(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }

        return $this->page('account/quotes', [
            'title'    => 'Your quotes — Fix Listed',
            'myQuotes' => (new QuoteRepository($this->db, $this->scope))->forPro((int) $profile['id'], 100),
        ]);
    }
}
