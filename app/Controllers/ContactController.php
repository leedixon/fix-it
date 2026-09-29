<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\Response;
use FixListed\Core\Csrf;
use FixListed\Core\Session;
use FixListed\Core\TeamAlert;
use FixListed\Core\Validator;
use FixListed\Repositories\ContactRepository;

/**
 * The contact page, and what happens when somebody uses it.
 *
 * It used to be two mailto: links. Those fail quietly in the ways that matter
 * most: a phone with no mail app opens nothing, a webmail user gets a blank
 * compose window in a tab they did not ask for, and a message that does get
 * sent exists only in one inbox — where a bounce or a spam filter loses it
 * with no record anywhere that somebody wrote in.
 *
 * So the message is written to the database first and the email is attempted
 * second. The email is allowed to fail. The row is the record.
 *
 * The tabs are radio buttons, styled. No JavaScript, like the rest of this
 * site: the whole form arrives with the page, works with scripting off, and a
 * chosen tab survives a validation failure because it round-trips as a value
 * rather than as the state of a widget.
 */
final class ContactController extends Controller
{
    private const FORM_KEY = 'contact_form_opened_at';

    /** More than this from one address in an hour is somebody stuck, not an attack. */
    private const PER_HOUR = 5;

    public function show(): Response
    {
        Session::put(self::FORM_KEY, time());

        return $this->form();
    }

    public function send(): Response
    {
        $body = $this->request->body;

        if (!Csrf::check($this->request->input('_csrf'))) {
            return $this->form(
                $body,
                ['_form' => 'That form had been open a long time. Please send it again.'],
                422,
            );
        }

        // A field no person sees and every naive bot fills. Answered as if it
        // worked — a bot that is told it failed learns to try again.
        if (trim((string) $this->request->input('website', '')) !== '') {
            return Response::redirect('/contact/thanks');
        }

        $opened = Session::get(self::FORM_KEY);
        if (is_int($opened) && time() - $opened < 3) {
            return Response::redirect('/contact/thanks');
        }

        $audience = (string) $this->request->input('audience', 'other');
        if (!in_array($audience, ContactRepository::AUDIENCES, true)) {
            $audience = 'other';
        }

        $v = new Validator($body);
        $v->required('name', 'Your name')->max('name', 120, 'Your name')
          ->required('email', 'Email')->email('email')->max('email', 191, 'Email')
          ->max('phone', 32, 'Phone')
          ->max('job_reference', 32, 'Job reference')
          ->max('business_name', 160, 'Business name')
          ->max('subject', 200, 'Subject')
          ->required('message', 'Message')->min('message', 20, 'Message');

        if (!$v->passes()) {
            return $this->form($body, $v->errors(), 422);
        }

        $repo  = new ContactRepository($this->db, $this->scope);
        $email = mb_strtolower(trim((string) $this->request->input('email', '')));

        /*
         * Not a security boundary — anybody determined changes the address.
         * It stops one frustrated person sending the same message nine times
         * because the page gave them no receipt they believed.
         */
        if ($repo->recentlyFrom($email) >= self::PER_HOUR) {
            return $this->form($body, [
                '_form' => 'We already have several messages from this address in the last hour. '
                         . 'We will reply to those — there is no need to send another.',
            ], 429);
        }

        $id = $repo->add([
            'audience'      => $audience,
            'name'          => trim((string) $this->request->input('name', '')),
            'email'         => $email,
            'phone'         => trim((string) $this->request->input('phone', '')),
            'job_reference' => strtoupper(trim((string) $this->request->input('job_reference', ''))),
            'business_name' => trim((string) $this->request->input('business_name', '')),
            'subject'       => trim((string) $this->request->input('subject', '')),
            'message'       => trim((string) $this->request->input('message', '')),
        ], $this->request->ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        $this->notify($id, $audience, $body);

        Session::forget(self::FORM_KEY);

        return Response::redirect('/contact/thanks');
    }

    public function thanks(): Response
    {
        return $this->page('site/contact_thanks', [
            'title'       => 'Message sent — Fix Listed',
            'description' => 'We have your message and will reply by email.',
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Contact', 'href' => '/contact'],
                ['label' => 'Sent'],
            ],
        ]);
    }

    /**
     * Tell the team. Failure here never fails the request.
     *
     * The message is already saved, so a mail outage costs a notification and
     * not the message. notified_at stays null, which is how the admin screen
     * shows that nobody was told.
     *
     * @param array<string,mixed> $body
     */
    private function notify(int $id, string $audience, array $body): void
    {
        $label = match ($audience) {
            'homeowner' => 'a homeowner',
            'pro'       => 'a tradesperson',
            default     => 'someone',
        };

        $facts = array_filter([
            'From'      => trim((string) ($body['name'] ?? '')),
            'Email'     => trim((string) ($body['email'] ?? '')),
            'Phone'     => trim((string) ($body['phone'] ?? '')),
            'Job'       => strtoupper(trim((string) ($body['job_reference'] ?? ''))),
            'Business'  => trim((string) ($body['business_name'] ?? '')),
            'Subject'   => trim((string) ($body['subject'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        try {
            $sent = TeamAlert::send(
                $this->db,
                $this->view,
                (int) $this->market['id'],
                // Every staff member works the inbox. A message is somebody
                // asking for help, which is the queue, not a personnel record.
                'admin.access',
                'Message from ' . $label . ' — Fix Listed',
                mb_substr(trim((string) ($body['message'] ?? '')), 0, 400),
                $facts,
                abs_url('/admin/messages'),
                'Read it in the admin',
            );

            if ($sent > 0) {
                (new ContactRepository($this->db, $this->scope))->markNotified($id);
            }
        } catch (\Throwable $e) {
            // Deliberately swallowed. The visitor has been helped as far as
            // this site can help them: their message is stored.
            error_log('Contact notification failed for message ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function form(array $old = [], array $errors = [], int $status = 200): Response
    {
        return $this->page('site/contact', [
            'title'       => 'Contact Fix Listed — talk to a person',
            'description' => 'Questions about a job, a listing or getting listed in '
                . $this->market['name'] . '. A person reads every message.',
            'old'         => $old,
            'errors'      => $errors,
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Contact'],
            ],
            'analytics'   => $this->analytics('site/contact'),
        ], $status);
    }
}
