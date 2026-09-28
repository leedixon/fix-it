<?php
declare(strict_types=1);

namespace FixListed\Controllers\Account;

use FixListed\Core\AccountController;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\TeamAlert;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\QuoteRepository;
use FixListed\Repositories\ReviewRepository;

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

        $reviews = new ReviewRepository($this->db, $this->scope);

        return $this->page('account/dashboard', [
            'title'  => 'Your account — Fix Listed',
            'jobs'   => ($profile['status'] === 'active')
                ? (new JobRepository($this->db, $this->scope))->forPro($proId, 12)
                : [],
            'myQuotes' => $quotes->forPro($proId, 10),
            'stats'    => $quotes->statsForPro($proId),
            // What people said, and what it averages to. A tradesperson
            // could see their rating on their own public profile and
            // nowhere in the account they actually sign in to.
            'reviews'  => $reviews->forProOwner($proId),
            'rating'   => (float) ($profile['rating_avg'] ?? 0),
            'reviewN'  => (int) ($profile['rating_count'] ?? 0),
        ]);
    }

    /**
     * Answer a review.
     *
     * /my/reviews/{id}/reply. Published immediately — see
     * ReviewRepository::reply for why a verified trader's answer is not
     * queued behind the same moderation as an anonymous review.
     *
     * The team is told, because "published immediately" and "unwatched" are
     * not the same thing.
     */
    public function reply(string $id): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was saved.');
            return Response::redirect('/my');
        }

        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }

        $text = trim((string) $this->request->input('reply', ''));
        if (mb_strlen($text) > 1500) {
            Session::flash('bad', 'That reply is too long — 1500 characters at most.');
            return Response::redirect('/my');
        }

        $reviews = new ReviewRepository($this->db, $this->scope);
        if (!$reviews->reply((int) $id, (int) $profile['id'], $text)) {
            Session::flash('bad', 'That review could not be answered.');
            return Response::redirect('/my');
        }

        if ($text !== '') {
            TeamAlert::send(
                $this->db,
                $this->view,
                (int) $this->market['id'],
                'listings.moderate',
                'Reply posted: ' . ($profile['business_name'] ?: 'a tradesperson'),
                ($profile['business_name'] ?: 'A tradesperson')
                    . ' has answered a review. It is already on their public profile.',
                ['Business' => (string) $profile['business_name'], 'Reply' => $text],
                abs_url('/pros/' . $profile['slug']),
                'See the profile',
            );
            Session::flash('ok', 'Your answer is on your profile.');
        } else {
            Session::flash('ok', 'Your answer has been taken down.');
        }

        return Response::redirect('/my');
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
