<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\AdminController;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Repositories\AdminRepository;
use FixListed\Repositories\LicenceRepository;
use FixListed\Repositories\MarketRepository;
use FixListed\Repositories\TradeRepository;

/** The list screens, and the few actions that act on a row. */
final class ManageController extends AdminController
{
    public function pros(): Response
    {
        if ($denied = $this->guard()) {
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
        if ($denied = $this->guard()) {
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
        if ($denied = $this->guard()) {
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
        if ($denied = $this->guard()) {
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
        if ($denied = $this->guard()) {
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
     * Advertising.
     *
     * Read-only for now, and that is the honest state of it: the schema,
     * the inventory caps and the directory's paid ordering all exist and
     * work, but nothing sells a placement yet. The screen shows what is
     * there rather than pretending at controls that do nothing.
     */
    public function advertising(): Response
    {
        if ($denied = $this->guard()) {
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
        if ($denied = $this->guardSuper()) {
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
        if ($denied = $this->guardSuper()) {
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
        if ($denied = $this->guardSuper()) {
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
        if ($denied = $this->guardSuper()) {
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
        if ($denied = $this->guardSuper()) {
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
