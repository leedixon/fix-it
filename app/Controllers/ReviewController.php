<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\Csrf;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\TeamAlert;
use FixListed\Core\Validator;
use FixListed\Repositories\ReviewRepository;

/**
 * Leaving a review — /review/{token}.
 *
 * The one page on the site that a homeowner reaches without an account and
 * without paying, because a homeowner has neither. They posted a job, they
 * got a reference, and weeks later an email asks how it went. The token in
 * that link is the entire authorisation.
 *
 * Which is why it is not the job reference. That is printed on the public
 * jobs board; this is 32 random bytes that appear in one email and nowhere
 * else.
 *
 * The form asks two questions in one pass — who did you hire, and how was
 * it — because asking a homeowner to maintain state between those two
 * moments is asking for a step nobody completes. jobs.hired_pro_id has sat
 * in the schema since the first migration precisely because there was never
 * a natural moment to fill it. This is the moment.
 */
final class ReviewController extends Controller
{
    /** Longest a review body may be. Long enough for a real account of a job. */
    private const MAX_BODY = 2000;

    public function form(string $token): Response
    {
        [$job, $pros] = $this->open($token);

        return $this->page('site/review', [
            'title'    => 'How did it go? — Fix Listed',
            'noindex'  => true,
            'job'      => $job,
            'pros'     => $pros,
            'token'    => $token,
            'maxBody'  => self::MAX_BODY,
            'old'      => [],
            'errors'   => [],
            // No breadcrumbs and no analytics beyond the pageview. This is a
            // private page reached from an email; it is not part of the
            // funnel and should not be measured as one.
        ]);
    }

    public function submit(string $token): Response
    {
        if (!Csrf::check($this->request->input('_csrf'))) {
            return Response::redirect('/review/' . $token);
        }

        [$job, $pros] = $this->open($token);

        $body = $this->request->body;
        $ids  = array_map(static fn (array $p): string => (string) $p['id'], $pros);
        $v    = new Validator($body);

        $v->required('pro_id', 'Who you hired')
          ->in('pro_id', $ids, 'Who you hired')
          ->required('rating', 'Your rating')
          ->in('rating', ['1', '2', '3', '4', '5'], 'Your rating')
          ->max('body', self::MAX_BODY, 'What happened');

        if (!$v->passes()) {
            return $this->page('site/review', [
                'title'   => 'How did it go? — Fix Listed',
                'noindex' => true,
                'job'     => $job,
                'pros'    => $pros,
                'token'   => $token,
                'maxBody' => self::MAX_BODY,
                'old'     => $body,
                'errors'  => $v->errors(),
            ], 422);
        }

        $reviews = new ReviewRepository($this->db, $this->scope);
        $reviews->submit(
            (int) $job['id'],
            (int) $v->value('pro_id'),
            (int) $job['user_id'],
            (int) $v->value('rating'),
            trim($v->value('body')),
        );

        // Taken from the list already on screen rather than queried again —
        // it was validated against that list, so it is certainly in it.
        $pro = ['business_name' => 'a tradesperson'];
        foreach ($pros as $candidate) {
            if ((int) $candidate['id'] === (int) $v->value('pro_id')) {
                $pro = $candidate;
                break;
            }
        }

        /*
         * Tell whoever moderates.
         *
         * A review lands in a queue and shows nowhere until somebody acts on
         * it. Without this the first one sat there unseen — and a homeowner
         * who took the trouble to write about a local business deserves
         * better than it being found a fortnight later. listings.moderate,
         * because the people who can publish it are the people to tell.
         */
        TeamAlert::send(
            $this->db,
            $this->view,
            (int) $this->market['id'],
            'listings.moderate',
            'Review waiting: ' . $pro['business_name'],
            'A homeowner has rated ' . $pro['business_name'] . '. It is held for moderation and '
                . 'will not appear on their profile until it is published.',
            [
                'Business' => (string) $pro['business_name'],
                'Rating'   => $v->value('rating') . ' out of 5',
                'Job'      => (string) $job['reference'],
                'Comment'  => trim($v->value('body')) !== '' ? 'yes' : 'a rating only',
            ],
            abs_url('/admin/reviews'),
            'Read it',
        );

        Session::flash('ok', 'Thank you — that is with us.');

        return Response::redirect('/review/' . $token . '/thanks');
    }

    public function thanks(string $token): Response
    {
        // Deliberately still resolves the token rather than showing a generic
        // page: somebody re-opening the link should land here, not on a form
        // that will refuse them.
        $reviews = new ReviewRepository($this->db, $this->scope);
        if ($reviews->findByToken($token) === null) {
            throw new NotFound('review/thanks');
        }

        return $this->page('site/review_thanks', [
            'title'   => 'Thank you — Fix Listed',
            'noindex' => true,
            'flashes' => Session::takeFlashes(),
        ]);
    }

    /**
     * Resolve the token, or refuse.
     *
     * A bad token, an already-reviewed job and a job nobody quoted all end
     * the same way: a 404. Not an explanation. "That job already has a
     * review" told to whoever holds the link is a way of confirming a guessed
     * token is real, and there is nothing useful the holder of a wrong link
     * can do with a better error.
     *
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}
     */
    private function open(string $token): array
    {
        $reviews = new ReviewRepository($this->db, $this->scope);
        $job     = $reviews->findByToken($token);

        if ($job === null || (int) $job['review_count'] > 0) {
            throw new NotFound('review/' . substr($token, 0, 8));
        }

        $pros = $reviews->quotedOn((int) $job['id']);
        if ($pros === []) {
            throw new NotFound('review/no-quotes');
        }

        return [$job, $pros];
    }
}
