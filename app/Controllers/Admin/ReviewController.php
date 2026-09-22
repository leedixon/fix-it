<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\AdminController;
use FixListed\Core\Config;
use FixListed\Core\Mailer;
use FixListed\Core\NotFound;
use FixListed\Core\PasswordReset;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\View;
use FixListed\Repositories\AdminRepository;
use FixListed\Repositories\LicenceRepository;
use FixListed\Repositories\ProApplicationRepository;
use FixListed\Repositories\ProRepository;

/**
 * The application queue — the screen this whole back end exists for.
 *
 * Approving is not a single button because it is not a single decision: the
 * profile carries a "licence verified" badge and an "insurance verified"
 * badge, and each is a claim the site makes on the administrator's behalf.
 * They are ticked separately, so a pro with a licence but no certificate of
 * insurance yet can go live honestly rather than with a badge nobody checked.
 */
final class ReviewController extends AdminController
{
    public function index(): Response
    {
        if ($denied = $this->guardCan('applications.review')) {
            return $denied;
        }
        $repo = new ProApplicationRepository($this->db, $this->scope);

        return $this->page('admin/applications', [
            'title'        => 'Applications — Fix Listed admin',
            'applications' => $repo->pending(),
            'pending'      => $repo->countPending(),
        ]);
    }

    public function show(string $id): Response
    {
        if ($denied = $this->guardCan('applications.review')) {
            return $denied;
        }
        $repo = new ProApplicationRepository($this->db, $this->scope);
        $pro  = $repo->find((int) $id);
        if ($pro === null) {
            throw new NotFound('application/' . $id);
        }

        $pros = new ProRepository($this->db, $this->scope);

        return $this->page('admin/application', [
            'title'       => 'Review: ' . ($pro['business_name'] ?: $pro['first_name'] . ' ' . $pro['last_name']),
            'pro'         => $pro,
            'skills'      => $pros->skills((int) $pro['id']),
            'proCounties' => $pros->counties((int) $pro['id']),
            'trades'      => $this->tradesFor((int) $pro['id']),
            'guides'      => (new LicenceRepository($this->db))
                                ->forApplication((string) $pro['license_state'], $this->tradesFor((int) $pro['id'])),
            'pending'     => $repo->countPending(),
        ]);
    }

    public function approve(string $id): Response
    {
        if ($denied = $this->guardCan('applications.review')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/applications/' . (int) $id);
        }

        $repo = new ProApplicationRepository($this->db, $this->scope);
        $pro  = $repo->find((int) $id);
        if ($pro === null) {
            throw new NotFound('application/' . $id);
        }

        $licence   = $this->request->input('licence_verified') === '1';
        $insurance = $this->request->input('insurance_verified') === '1';

        $repo->approve((int) $id, (int) $this->auth->id(), $licence, $insurance);
        $this->record('pro.approved', 'pro_profile', (int) $id, [
            'licence_verified'   => $licence,
            'insurance_verified' => $insurance,
            'slug'               => $pro['slug'],
        ]);

        $sent = $this->tell($pro, 'approved', '');

        // The stakes are higher here than on a decline. That email carries the
        // only link they have to set a password, so an approved tradesperson
        // whose email never arrived has a published profile and no way into
        // it — waiting, while the queue says the job is done.
        Session::flash('ok', ($pro['business_name'] ?: $pro['first_name']) . ' is live.'
            . ($sent ? ' They have been emailed a link to set their password.' : ''));

        if (!$sent) {
            Session::flash('bad', 'Their profile is published, but the email to ' . $pro['email']
                . ' did not send — so they have no way to sign in yet. Fix the mail setup '
                . '(php bin/check.php), then send them a link from Tradespeople.');
        }

        return Response::redirect('/admin/applications');
    }

    public function reject(string $id): Response
    {
        if ($denied = $this->guardCan('applications.review')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/applications/' . (int) $id);
        }

        $repo = new ProApplicationRepository($this->db, $this->scope);
        $pro  = $repo->find((int) $id);
        if ($pro === null) {
            throw new NotFound('application/' . $id);
        }

        $note = trim((string) $this->request->input('note', ''));
        $repo->reject((int) $id, (int) $this->auth->id(), $note);
        $this->record('pro.rejected', 'pro_profile', (int) $id, ['note' => $note, 'slug' => $pro['slug']]);

        // null when the box was unticked — declining quietly is a deliberate
        // option, and "not emailed" must read differently from "the email
        // failed".
        $sent = $this->request->input('tell_them') === '1'
            ? $this->tell($pro, 'rejected', $note)
            : null;

        Session::flash('ok', 'Application declined and taken out of the queue.' . $this->sendNote($sent));
        $this->warnIfUnsent($sent, (string) $pro['email']);

        return Response::redirect('/admin/applications');
    }

    /**
     * The trades on an application, with ids — the licensing lookup needs
     * both: the id to find the right authority, the name to show.
     *
     * @return array<int,array{id:int,name:string}>
     */
    private function tradesFor(int $proId): array
    {
        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
            $this->db->all(
                'SELECT t.id, t.name FROM pro_trades pt JOIN trades t ON t.id = pt.trade_id
                  WHERE pt.pro_id = :id ORDER BY pt.is_primary DESC, t.sort_order',
                ['id' => $proId],
            ),
        );
    }

    /**
     * Tells the applicant what was decided. Returns whether it actually went.
     *
     * Still wrapped: the decision is already committed, and a mail failure
     * must not leave an approved profile looking un-approved to the
     * administrator who just approved it.
     *
     * But it no longer fails *silently*. The whole point of this screen is to
     * tell somebody they are live, or that they are not — and an approval
     * whose email never arrived is a tradesperson sitting waiting, with a
     * queue that says the job is done and a log nobody reads. The caller says
     * so on screen.
     */
    private function tell(array $pro, string $outcome, string $note): bool
    {
        try {
            $mailer = Mailer::fromConfig();
            $view   = new View(BASE_PATH . '/app/Views');
            $name   = $pro['business_name'] ?: trim($pro['first_name'] . ' ' . $pro['last_name']);

            if ($outcome === 'approved') {
                // The invite link is issued here and nowhere else. Before
                // this, an approved tradesperson had a live profile and no
                // way to sign in at all — the email told them they were live
                // and then stopped. It lasts a fortnight, because approval
                // emails sit unread over a weekend.
                $token = (new PasswordReset($this->db))->issue((int) $pro['user_id'], true);

                return $mailer->send(
                    (string) $pro['email'],
                    'You are live on Fix Listed',
                    $view->render('emails.pro_approved', [
                        'title'      => 'You are live',
                        'preheader'  => 'Your profile is published and homeowners can find you.',
                        'name'       => (string) $pro['first_name'],
                        'business'   => $name,
                        'profileUrl' => abs_url('/pros/' . $pro['slug']),
                        'jobsUrl'    => abs_url('/jobs'),
                        'setUpUrl'   => abs_url('/set-password/' . $token),
                        'market'     => (string) $this->market['name'],
                    ], 'emails.layout'),
                    "Your Fix Listed profile is live: " . abs_url('/pros/' . $pro['slug']) . "\n\n"
                    . "Set your password and start quoting: " . abs_url('/set-password/' . $token) . "\n\n"
                    . "Open jobs in your counties: " . abs_url('/jobs') . "\n",
                );
            }

            return $mailer->send(
                (string) $pro['email'],
                'About your Fix Listed application',
                $view->render('emails.pro_rejected', [
                    'title'     => 'About your application',
                    'preheader' => 'We could not list you just yet.',
                    'name'      => (string) $pro['first_name'],
                    'note'      => $note,
                    'replyTo'   => (string) Config::get('mail.reply_to', ''),
                ], 'emails.layout'),
                "We could not list you just yet.\n\n" . ($note !== '' ? $note . "\n\n" : '')
                . "Reply to this email and we will go through it with you.\n",
            );
        } catch (\Throwable $e) {
            error_log('Decision mail failed for pro ' . ($pro['id'] ?? '?') . ': ' . $e->getMessage());
            return false;
        }
    }

    /** What to add to the flash, so the outcome of the send is on screen. */
    private function sendNote(?bool $sent): string
    {
        return match ($sent) {
            true  => ' They have been emailed.',
            false => '',
            null  => ' They were not emailed, as you asked.',
        };
    }

    /** The same failure, said the same way, on both decisions. */
    private function warnIfUnsent(?bool $sent, string $email): void
    {
        if ($sent !== false) {
            return;
        }
        Session::flash('bad', 'The decision was saved but the email to ' . $email
            . ' did not send. They have not been told. Check php bin/check.php for the '
            . 'mail setup, then use "Resend" once it is fixed.');
    }
}
