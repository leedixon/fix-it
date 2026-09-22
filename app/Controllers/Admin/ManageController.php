<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\AdminController;
use FixListed\Core\Auth;
use FixListed\Core\Mailer;
use FixListed\Core\PasswordReset;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Repositories\AdminRepository;
use FixListed\Repositories\LicenceRepository;
use FixListed\Repositories\MarketRepository;
use FixListed\Repositories\TeamRepository;
use FixListed\Repositories\TradeRepository;

/** The list screens, and the few actions that act on a row. */
final class ManageController extends AdminController
{
    public function pros(): Response
    {
        if ($denied = $this->guardCan('listings.moderate')) {
            return $denied;
        }
        $admin  = new AdminRepository($this->db, $this->scope);
        $status = (string) $this->request->input('status', '');

        return $this->page('admin/pros', [
            'title'   => 'Tradespeople — Fix Listed admin',
            'pros'    => $admin->pros($status),
            'status'  => $status,
            'pending' => $admin->counts()['applications'],
        ]);
    }

    /** Suspend or reinstate a listing. */
    public function setProStatus(string $id): Response
    {
        if ($denied = $this->guardCan('listings.moderate')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/pros');
        }

        $status = (string) $this->request->input('status', '');
        if (!in_array($status, ['active', 'suspended'], true)) {
            Session::flash('bad', 'Unknown status.');
            return Response::redirect('/admin/pros');
        }

        $this->db->affected(
            'UPDATE pro_profiles SET status = :status WHERE id = :id AND market_id = :market_id',
            ['status' => $status, 'id' => (int) $id, 'market_id' => $this->scope->marketId],
        );
        $this->record('pro.status_changed', 'pro_profile', (int) $id, ['to' => $status]);

        Session::flash('ok', $status === 'suspended'
            ? 'Listing suspended. It is off the directory immediately.'
            : 'Listing is live again.');
        return Response::redirect('/admin/pros');
    }

    public function jobs(): Response
    {
        if ($denied = $this->guardCan('jobs.moderate')) {
            return $denied;
        }
        $admin  = new AdminRepository($this->db, $this->scope);
        $status = (string) $this->request->input('status', '');

        return $this->page('admin/jobs', [
            'title'   => 'Jobs — Fix Listed admin',
            'jobs'    => $admin->jobs($status),
            'status'  => $status,
            'pending' => $admin->counts()['applications'],
        ]);
    }

    public function removeJob(string $id): Response
    {
        if ($denied = $this->guardCan('jobs.moderate')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/jobs');
        }

        // Removed, never deleted. A paid listing has a payment and possibly
        // quotes hanging off it, and the refund question is answered later by
        // a person looking at the record.
        $this->db->affected(
            "UPDATE jobs SET status = 'removed' WHERE id = :id AND market_id = :market_id",
            ['id' => (int) $id, 'market_id' => $this->scope->marketId],
        );
        $this->record('job.removed', 'job', (int) $id, ['reason' => (string) $this->request->input('reason', '')]);

        Session::flash('ok', 'Job removed from the board. The record is kept.');
        return Response::redirect('/admin/jobs');
    }

    public function users(): Response
    {
        if ($denied = $this->guardCan('people.view')) {
            return $denied;
        }
        $admin = new AdminRepository($this->db, $this->scope);
        $role  = (string) $this->request->input('role', '');

        return $this->page('admin/users', [
            'title'    => 'People — Fix Listed admin',
            'users'    => $admin->users($role),
            'role'     => $role,
            'waitlist' => $admin->waitlist(50),
            'pending'  => $admin->counts()['applications'],
        ]);
    }

    /**
     * Sends a live tradesperson a fresh link to set their password.
     *
     * The gap this closes: approval publishes a profile and emails the only
     * sign-in link that account will ever get. If that email fails — and it
     * can, silently, on a mail provider having a bad afternoon — the person
     * is live, findable by homeowners, and locked out, with nothing an
     * administrator can do about it short of SSH.
     *
     * Issuing a new link invalidates any outstanding one, so this doubles as
     * the fix for a link sent to a mistyped address.
     */
    public function resendProLink(string $id): Response
    {
        if ($denied = $this->guardCan('listings.moderate')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was sent.');
            return Response::redirect('/admin/pros');
        }

        $pro = $this->db->one(
            'SELECT p.id, p.business_name, u.id AS user_id, u.email, u.first_name, u.last_name
               FROM pro_profiles p JOIN users u ON u.id = p.user_id
              WHERE p.id = :id AND p.market_id = :market LIMIT 1',
            ['id' => (int) $id, 'market' => $this->scope->marketId],
        );

        if ($pro === null) {
            Session::flash('bad', 'No such listing.');
            return Response::redirect('/admin/pros');
        }

        $token = (new PasswordReset($this->db))->issue((int) $pro['user_id'], true);
        $name  = $pro['business_name'] ?: trim($pro['first_name'] . ' ' . $pro['last_name']);

        try {
            $ok = Mailer::fromConfig()->send(
                (string) $pro['email'],
                'Your Fix Listed sign-in link',
                $this->view->render('emails.password_link', [
                    'title'     => 'Set your password',
                    'preheader' => 'A fresh link to get into your Fix Listed account.',
                    'name'      => (string) $pro['first_name'],
                    'link'      => abs_url('/set-password/' . $token),
                    'invite'    => true,
                    'hours'     => 0,
                    'days'      => PasswordReset::INVITE_DAYS,
                ], 'emails.layout'),
                "Set your Fix Listed password here:\n" . abs_url('/set-password/' . $token) . "\n",
            );
        } catch (\Throwable $e) {
            error_log('Resend to pro ' . $pro['id'] . ' failed: ' . $e->getMessage());
            $ok = false;
        }

        $this->record('pro.link_resent', 'pro_profile', (int) $pro['id'], ['sent' => $ok]);

        Session::flash($ok ? 'good' : 'bad', $ok
            ? 'A fresh sign-in link is on its way to ' . $pro['email']
              . '. Any previous one has stopped working.'
            : 'That email did not send. Run php bin/check.php, then try again — '
              . $name . ' still has no way in.');

        return Response::redirect('/admin/pros');
    }

    /**
     * Removes an ordinary account — a tradesperson or a homeowner.
     *
     * Superadmin only, like every other deletion. Marked deleted rather than
     * erased: their jobs, quotes and reviews point at this row, and a real
     * DELETE would either cascade a paying customer's history away or leave
     * it pointing at nothing.
     *
     * Staff accounts are not removable here. They go through /admin/team,
     * which knows how to refuse the last superadmin.
     */
    public function removeUser(string $id): Response
    {
        if ($denied = $this->guardCan('users.delete')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/users');
        }

        $userId = (int) $id;
        $user   = $this->db->one(
            'SELECT id, email, role, status FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId],
        );

        if ($user === null || (string) $user['status'] === 'deleted') {
            Session::flash('bad', 'No such account.');
            return Response::redirect('/admin/users');
        }
        if ($userId === $this->auth->id()) {
            Session::flash('bad', 'You cannot remove your own account.');
            return Response::redirect('/admin/users');
        }
        if (in_array((string) $user['role'], Auth::STAFF_ROLES, true)) {
            Session::flash('bad', 'That is a team member. Remove them from the Team screen, '
                . 'which knows not to lock the last owner out.');
            return Response::redirect('/admin/users');
        }

        $typed = mb_strtolower(trim((string) $this->request->input('confirm_email', '')));
        if ($typed !== mb_strtolower((string) $user['email'])) {
            Session::flash('bad', 'Nothing was removed — the email did not match.');
            return Response::redirect('/admin/users');
        }

        // Logged before the row is scrubbed, while there is still something
        // worth logging.
        $this->record('user.removed', 'user', $userId, [
            'email' => (string) $user['email'],
            'role'  => (string) $user['role'],
        ]);

        (new TeamRepository($this->db))->remove($userId);

        // A tradesperson's listing goes with them. Leaving a live profile
        // behind an account nobody can sign into means a homeowner quoting
        // into silence.
        $this->db->affected(
            "UPDATE pro_profiles SET status = 'suspended' WHERE user_id = :id",
            ['id' => $userId],
        );

        Session::flash('good', $user['email'] . ' has been removed. Their listing is off the '
            . 'directory and their history stays in the activity log.');

        return Response::redirect('/admin/users');
    }

    /**
     * Advertising: who is paying for position, and what it delivered.
     *
     * Read-only by design rather than by omission. A placement is granted by
     * a paid Stripe subscription and revoked when that subscription ends, so
     * an admin button that granted one by hand would create a placement no
     * invoice backs. Selling and cancelling both belong to the pro's own
     * screen and Stripe's billing portal.
     *
     * Managers see the placements; the prices and what the inventory is worth
     * are money, and money is the superadmin's alone.
     */
    public function advertising(): Response
    {
        if ($denied = $this->guardCan('placements.view')) {
            return $denied;
        }
        $admin = new AdminRepository($this->db, $this->scope);

        $sold = [];
        foreach ($admin->adInventory() as $row) {
            $sold[(string) $row['plan']] = (int) $row['sold'];
        }

        return $this->page('admin/advertising', [
            'title'      => 'Advertising — Fix Listed admin',
            'placements' => $admin->placements(),
            'sold'       => $sold,
            'pending'    => $admin->counts()['applications'],
        ]);
    }

    /**
     * Licensing guidance, per state and trade.
     *
     * Superadmin only: what this table says decides whether a verified badge
     * appears on a public profile, which is a claim the site makes on
     * somebody's behalf.
     */
    public function licensing(): Response
    {
        if ($denied = $this->guardCan('licensing.manage')) {
            return $denied;
        }
        $licences = new LicenceRepository($this->db);

        return $this->page('admin/licensing', [
            'title'   => 'Licensing — Fix Listed admin',
            'rules'   => $licences->all(),
            'trades'  => (new TradeRepository($this->db))->all(),
            'edit'    => $this->request->input('edit') !== null
                            ? $licences->find((int) $this->request->input('edit'))
                            : null,
            'pending' => (new AdminRepository($this->db, $this->scope))->counts()['applications'],
        ]);
    }

    public function saveLicensing(): Response
    {
        if ($denied = $this->guardCan('licensing.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was saved.');
            return Response::redirect('/admin/licensing');
        }

        $state = mb_strtoupper(trim((string) $this->request->input('state', '')));
        if (!preg_match('/^[A-Z]{2}$/', $state)) {
            Session::flash('bad', 'A state is two letters, like IL. Nothing was saved.');
            return Response::redirect('/admin/licensing');
        }

        $url = trim((string) $this->request->input('lookup_url', ''));
        if ($url !== '' && !preg_match('#^https://#i', $url)) {
            // Only https: this link is opened by an administrator from a page
            // that talks about verification, and a plain-http register is not
            // one to send them to.
            Session::flash('bad', 'The lookup link has to start with https://. Nothing was saved.');
            return Response::redirect('/admin/licensing');
        }

        $id = $this->request->input('id') !== null && $this->request->input('id') !== ''
            ? (int) $this->request->input('id')
            : null;

        (new LicenceRepository($this->db))->save($id, [
            'state'     => $state,
            'trade'     => (int) $this->request->input('trade_id', '0'),
            'licensed'  => $this->request->input('licensed') === '1' ? 1 : 0,
            'authority' => mb_substr(trim((string) $this->request->input('authority', '')), 0, 120),
            'url'       => mb_substr($url, 0, 255),
            'format'    => mb_substr(trim((string) $this->request->input('number_format', '')), 0, 60),
            'guidance'  => mb_substr(trim((string) $this->request->input('guidance', '')), 0, 600),
        ]);

        $this->record('licensing.saved', 'licence_authority', $id, ['state' => $state]);
        Session::flash('ok', 'Saved. The review screen will use this from the next application.');
        return Response::redirect('/admin/licensing');
    }

    public function deleteLicensing(string $id): Response
    {
        if ($denied = $this->guardCan('licensing.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/licensing');
        }

        (new LicenceRepository($this->db))->delete((int) $id);
        $this->record('licensing.deleted', 'licence_authority', (int) $id);

        // Deleting is not neutral: the review screen then says it has no
        // guidance rather than falling back to something reassuring.
        Session::flash('ok', 'Removed. Applications for that trade now show "no guidance yet" until it is replaced.');
        return Response::redirect('/admin/licensing');
    }

    public function markets(): Response
    {
        if ($denied = $this->guardCan('markets.manage')) {
            return $denied;
        }

        return $this->page('admin/markets', [
            'title'   => 'Markets — Fix Listed admin',
            'markets' => (new MarketRepository($this->db))->all(),
            'pending' => (new AdminRepository($this->db, $this->scope))->counts()['applications'],
        ]);
    }

    /** Pricing and inventory caps, which only a superadmin may move. */
    public function updateMarket(string $id): Response
    {
        if ($denied = $this->guardCan('finance.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/markets');
        }

        $cents = static function (?string $v): ?int {
            $raw = preg_replace('/[^0-9.]/', '', (string) $v) ?? '';
            return $raw === '' || !is_numeric($raw) ? null : (int) round(((float) $raw) * 100);
        };

        $fee       = $cents($this->request->input('listing_fee'));
        $boost     = $cents($this->request->input('boost_price'));
        $spotlight = $cents($this->request->input('spotlight_price'));

        if ($fee === null || $boost === null || $spotlight === null) {
            Session::flash('bad', 'Those prices did not parse. Nothing was changed.');
            return Response::redirect('/admin/markets');
        }

        $this->db->affected(
            'UPDATE markets
                SET listing_fee_cents = :fee, boost_price_cents = :boost,
                    spotlight_price_cents = :spotlight, boost_slots = :bslots, spotlight_slots = :sslots
              WHERE id = :id',
            [
                'fee' => $fee, 'boost' => $boost, 'spotlight' => $spotlight,
                'bslots' => max(0, (int) $this->request->input('boost_slots', '0')),
                'sslots' => max(0, (int) $this->request->input('spotlight_slots', '0')),
                'id' => (int) $id,
            ],
        );

        $this->record('market.pricing_changed', 'market', (int) $id, [
            'listing_fee_cents' => $fee, 'boost_price_cents' => $boost,
            'spotlight_price_cents' => $spotlight,
        ]);

        Session::flash('ok', 'Pricing updated. It applies to the next listing, not to ones already paid for.');
        return Response::redirect('/admin/markets');
    }
}
