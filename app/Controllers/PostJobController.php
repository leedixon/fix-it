<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Config;
use FixListed\Core\Controller;
use FixListed\Core\Csrf;
use FixListed\Core\Mailer;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\Stripe;
use FixListed\Core\Validator;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\JobPostingRepository;
use FixListed\Repositories\TradeRepository;

/**
 * Posting a job, and paying for it.
 *
 * No account. A homeowner posts perhaps twice a year, and putting a signup
 * between them and the form loses posts for nothing — an account is created
 * quietly behind the form so the job has an owner and receipts have somewhere
 * to go.
 *
 * The order of operations matters and is deliberate: the job and a pending
 * payment are written *before* the visitor leaves for Stripe. A job that
 * exists unpaid is easy to see and sweep up; a payment with no job to attach
 * it to is a refund and an apology.
 */
final class PostJobController extends Controller
{
    private const FORM_KEY = 'job_form_opened_at';

    public function form(): Response
    {
        Session::put(self::FORM_KEY, time());

        return $this->page('site/post_job', [
            'title'       => 'Post a job — Fix Listed',
            'description' => 'Describe what needs doing. One flat fee, every tradesperson covering '
                . 'your county sees it, and you deal with them directly.',
            'trades'      => (new TradeRepository($this->db))->all(),
            'cities'      => (new GeographyRepository($this->db, $this->scope))->allCities(),
            'fee'         => (int) $this->market['listing_fee_cents'],
            'listingDays' => (int) $this->market['job_listing_days'],
            'refundHours' => (int) $this->market['refund_window_hours'],
            'old'         => [],
            'errors'      => [],
        ]);
    }

    public function submit(): Response
    {
        $body = $this->request->body;

        if (!Csrf::check($this->request->input('_csrf'))) {
            return $this->back($body, ['_form' => 'That form had been open a long time. Please send it again.']);
        }
        if ($this->request->input('website', '') !== '') {
            // Honeypot. Nothing is written and nothing is charged.
            return Response::redirect('/post-a-job/thanks');
        }
        $opened = Session::get(self::FORM_KEY);
        if (is_int($opened) && time() - $opened < 3) {
            return Response::redirect('/post-a-job/thanks');
        }

        $v = new Validator($body);
        $v->required('title', 'A short title')->max('title', 160, 'Title')->min('title', 12, 'A short title')
          ->required('description', 'The description')->min('description', 40, 'The description')
          ->required('trade_id', 'Trade')
          ->required('city_id', 'Town')
          ->required('zip', 'ZIP code')->max('zip', 12, 'ZIP code')
          ->required('first_name', 'First name')->max('first_name', 80, 'First name')
          ->required('last_name', 'Last name')->max('last_name', 80, 'Last name')
          ->required('email', 'Email')->email('email')
          ->required('phone', 'Phone')->max('phone', 32, 'Phone')
          ->in('urgency', ['asap', 'this_week', 'this_month', 'flexible'], 'Timing');

        // Ids come from a form, so they are checked against this market's own
        // lists rather than trusted.
        $tradeId = (int) ($v->int('trade_id') ?? 0);
        $validTrades = array_map('intval', array_column((new TradeRepository($this->db))->all(), 'id'));
        if (!in_array($tradeId, $validTrades, true)) {
            $v->fail('trade_id', 'Choose a trade.');
        }

        $cityId = (int) ($v->int('city_id') ?? 0);
        $city = null;
        foreach ((new GeographyRepository($this->db, $this->scope))->allCities() as $candidate) {
            if ((int) $candidate['id'] === $cityId) {
                $city = $candidate;
                break;
            }
        }
        if ($city === null) {
            $v->fail('city_id', 'Choose the town the work is in.');
        }

        $min = $v->money('budget_min');
        $max = $v->money('budget_max');
        if ($min !== null && $max !== null && $max < $min) {
            $v->fail('budget_max', 'The top of the budget is below the bottom.');
        }

        if (!$v->passes()) {
            return $this->back($body, $v->errors());
        }

        $fee = (int) $this->market['listing_fee_cents'];
        $repo = new JobPostingRepository($this->db, $this->scope);

        $created = $repo->create([
            'first_name' => $v->value('first_name'),
            'last_name'  => $v->value('last_name'),
            'email'      => mb_strtolower($v->value('email')),
            'phone'      => $v->value('phone'),
            'trade_id'   => $tradeId,
            'city_id'    => $cityId,
            'county_id'  => (int) $city['county_id'],
            'title'      => $v->value('title'),
            'description'=> $v->value('description'),
            'zip'        => $v->value('zip'),
            'urgency'    => $v->value('urgency', 'this_week'),
            'budget_min_cents' => $min,
            'budget_max_cents' => $max,
            'market_code'=> (string) $this->market['code'],
        ]);

        // A market can run at a fee of zero to fill the board. Sending someone
        // to Checkout for $0.00 is not possible and would be absurd anyway.
        if ($fee === 0) {
            $repo->completePayment(
                $this->freePaymentSession($repo, $created, $fee),
                '',
                0,
            );
            return Response::redirect('/post-a-job/thanks?ref=' . $created['reference']);
        }

        $stripe = Stripe::fromConfig();
        if ($stripe === null) {
            error_log('A job was posted but Stripe is not configured; job ' . $created['reference'] . ' is unpaid.');
            return $this->back($body, [
                '_form' => 'Payments are not switched on yet, so nothing was charged. '
                         . 'Email hello@fixlisted.com and we will post this for you.',
            ]);
        }

        try {
            $session = $stripe->createCheckoutSession(
                $fee,
                'Fix Listed job posting',
                $created['reference'] . ' — ' . $v->value('title'),
                abs_url('/post-a-job/thanks') . '?ref=' . $created['reference'],
                abs_url('/post-a-job/resume') . '?ref=' . $created['reference'],
                mb_strtolower($v->value('email')),
                ['job_reference' => $created['reference'], 'job_id' => (string) $created['job_id']],
                // Keyed on the job, so a double-click cannot create two
                // sessions and two charges for one listing.
                'job-' . $created['reference'],
            );
        } catch (\Throwable $e) {
            error_log('Stripe checkout failed for ' . $created['reference'] . ': ' . $e->getMessage());
            return $this->back($body, [
                '_form' => 'We could not reach the payment page just then. Nothing was charged. '
                         . 'Try again in a moment — your job is saved as ' . $created['reference'] . '.',
            ]);
        }

        $repo->startPayment(
            $created['job_id'],
            $created['user_id'],
            $fee,
            (string) $session['id'],
        );

        return Response::redirect((string) $session['url']);
    }

    /**
     * The page they land on after Checkout.
     *
     * It reports what the database says, not what the URL claims. Stripe's
     * success_url is loaded by the browser, and a browser can be pointed at
     * any URL by anybody — so arriving here is not evidence of payment. The
     * webhook is what makes a job live; this page just explains the wait,
     * which is usually a second or two.
     */
    public function thanks(): Response
    {
        $reference = mb_strtoupper((string) $this->request->input('ref', ''));
        $repo = new JobPostingRepository($this->db, $this->scope);
        $job  = $reference !== '' ? $repo->findByReference($reference) : null;

        return $this->page('site/job_posted', [
            'title'   => 'Your job is posted — Fix Listed',
            'noindex' => true,
            'job'     => $job,
            'paid'    => $job !== null && $job['status'] === 'active',
            'payment' => $job !== null ? $repo->paymentStatusFor((int) $job['id']) : 'pending',
        ]);
    }

    /** They backed out of Checkout. The job is saved; offer the way back. */
    public function resume(): Response
    {
        $reference = mb_strtoupper((string) $this->request->input('ref', ''));
        $job = (new JobPostingRepository($this->db, $this->scope))->findByReference($reference);
        if ($job === null) {
            throw new NotFound('resume/' . $reference);
        }

        return $this->page('site/job_resume', [
            'title'   => 'Finish posting your job — Fix Listed',
            'noindex' => true,
            'job'     => $job,
            'fee'     => (int) $this->market['listing_fee_cents'],
        ]);
    }

    /** @param array<string,string> $errors */
    private function back(array $old, array $errors): Response
    {
        return $this->page('site/post_job', [
            'title'       => 'Post a job — Fix Listed',
            'trades'      => (new TradeRepository($this->db))->all(),
            'cities'      => (new GeographyRepository($this->db, $this->scope))->allCities(),
            'fee'         => (int) $this->market['listing_fee_cents'],
            'listingDays' => (int) $this->market['job_listing_days'],
            'refundHours' => (int) $this->market['refund_window_hours'],
            'old'         => $old,
            'errors'      => $errors,
        ], 422);
    }

    /**
     * A stand-in session id for a free market, so the same completion path
     * runs whether money changed hands or not. One code path to be correct.
     */
    private function freePaymentSession(JobPostingRepository $repo, array $created, int $fee): string
    {
        $sessionId = 'free_' . $created['reference'];
        $repo->startPayment($created['job_id'], $created['user_id'], $fee, $sessionId);
        return $sessionId;
    }
}
