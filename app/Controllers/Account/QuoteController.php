<?php
declare(strict_types=1);

namespace FixListed\Controllers\Account;

use FixListed\Core\AccountController;
use FixListed\Core\Mailer;
use FixListed\Core\HaltWith;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\Validator;
use FixListed\Core\View;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\QuoteRepository;
use RuntimeException;

/**
 * Quoting a job.
 *
 * The quote form asks for the shape of the price rather than forcing a single
 * number, because most trades genuinely cannot give one from a paragraph of
 * text. "I need to see it" is a legitimate answer and is offered as one — the
 * alternative is a board full of invented figures nobody honours.
 */
final class QuoteController extends AccountController
{
    public function form(string $reference): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        [$job, $profile] = $this->jobAndProfile($reference);

        if ((new QuoteRepository($this->db, $this->scope))->hasQuoted((int) $job['id'], (int) $profile['id'])) {
            Session::flash('bad', 'You have already quoted that job. Reply to the homeowner by email to change it.');
            return Response::redirect('/my/quotes');
        }

        return $this->page('account/quote', [
            'title'  => 'Quote: ' . $job['title'],
            'job'    => $job,
            'old'    => [],
            'errors' => [],
        ]);
    }

    public function submit(string $reference): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        [$job, $profile] = $this->jobAndProfile($reference);

        if (!$this->checkCsrf()) {
            return $this->back($job, $this->request->body, ['_form' => 'That form expired. Send it again.']);
        }

        $v = new Validator($this->request->body);
        $v->required('message', 'Your message')->min('message', 30, 'Your message')
          ->in('amount_type', ['fixed', 'range', 'hourly', 'visit_required'], 'Price type');

        $type = $v->value('amount_type', 'fixed');
        $min  = $v->money('amount');
        $max  = $v->money('amount_max');

        // A price is required unless the honest answer is "I need to see it".
        if ($type !== 'visit_required' && $min === null) {
            $v->fail('amount', 'Give a figure, or choose "I need to see it first".');
        }
        if ($type === 'range') {
            if ($max === null) {
                $v->fail('amount_max', 'A range needs a top end.');
            } elseif ($min !== null && $max < $min) {
                $v->fail('amount_max', 'The top of the range is below the bottom.');
            }
        }

        $start = $v->value('can_start_on');
        if ($start !== '' && strtotime($start) === false) {
            $v->fail('can_start_on', 'That date did not make sense.');
        }

        if (!$v->passes()) {
            return $this->back($job, $this->request->body, $v->errors());
        }

        try {
            (new QuoteRepository($this->db, $this->scope))->create(
                (int) $job['id'],
                (int) $profile['id'],
                [
                    'amount_cents'     => $type === 'visit_required' ? null : $min,
                    'amount_type'      => $type,
                    'amount_max_cents' => $type === 'range' ? $max : null,
                    'message'          => $v->value('message'),
                    'can_start_on'     => $start !== '' ? date('Y-m-d', (int) strtotime($start)) : null,
                ],
            );
        } catch (RuntimeException $e) {
            return $this->back($job, $this->request->body, [
                '_form' => match ($e->getMessage()) {
                    'already_quoted' => 'You have already quoted that job.',
                    'job_not_open'   => 'That job closed while you were writing. Nothing was sent.',
                    'not_covered'    => 'That job is outside the counties on your profile.',
                    default          => 'Something went wrong and the quote was not sent.',
                },
            ]);
        }

        $this->notify($job, $profile);

        Session::flash('ok', 'Quote sent. The homeowner has it, and you will hear from them directly.');
        return Response::redirect('/my/quotes');
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function jobAndProfile(string $reference): array
    {
        $profile = $this->profile();
        if ($profile === null || $profile['status'] !== 'active') {
            // A pending or suspended listing may not quote. Sending them to
            // their own dashboard explains why; a 404 would not.
            Session::flash('bad', 'Your listing is not live yet, so you cannot quote jobs.');
            throw new HaltWith(Response::redirect('/my'));
        }

        $job = (new JobRepository($this->db, $this->scope))->findByReference(mb_strtoupper($reference));
        if ($job === null) {
            throw new NotFound('quote/' . $reference);
        }

        return [$job, $profile];
    }

    /** @param array<string,string> $errors */
    private function back(array $job, array $old, array $errors): Response
    {
        return $this->page('account/quote', [
            'title'  => 'Quote: ' . $job['title'],
            'job'    => $job,
            'old'    => $old,
            'errors' => $errors,
        ], 422);
    }

    /** Tells the homeowner a quote arrived. Never fails the quote itself. */
    private function notify(array $job, array $profile): void
    {
        try {
            $poster = $this->db->one(
                'SELECT u.email, u.first_name FROM jobs j JOIN users u ON u.id = j.user_id WHERE j.id = :id',
                ['id' => $job['id']],
            );
            if ($poster === null) {
                return;
            }

            $name = $profile['business_name'] ?: 'A tradesperson';

            Mailer::fromConfig()->send(
                (string) $poster['email'],
                'New quote on "' . $job['title'] . '"',
                (new View(BASE_PATH . '/app/Views'))->render('emails.quote_received', [
                    'title'     => 'You have a quote',
                    'preheader' => $name . ' quoted your job.',
                    'name'      => (string) $poster['first_name'],
                    'business'  => $name,
                    'jobTitle'  => (string) $job['title'],
                    'jobUrl'    => abs_url('/jobs/' . $job['reference']),
                ], 'emails.layout'),
                $name . " quoted your job \"" . $job['title'] . "\".\n\n"
                . abs_url('/jobs/' . $job['reference']) . "\n",
            );
        } catch (\Throwable $e) {
            error_log('Quote notification failed for job ' . ($job['id'] ?? '?') . ': ' . $e->getMessage());
        }
    }
}
