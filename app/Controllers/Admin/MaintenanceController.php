<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\AdminController;
use FixListed\Core\Maintenance;
use FixListed\Core\Response;
use FixListed\Core\Session;

/**
 * Taking the site down and putting it back up, from the admin panel.
 *
 * Superadmin only. A market admin runs one market; taking the whole platform
 * offline is not a market-level decision, and the blast radius of getting it
 * wrong is every visitor.
 *
 * This screen is the convenient way to do it. `php bin/maintenance.php` is
 * the reliable way, and it is the one that still works when the reason you
 * want maintenance mode is that this screen will not load. Both write the
 * same file.
 */
final class MaintenanceController extends AdminController
{
    public function index(): Response
    {
        if ($denied = $this->guardCan('maintenance.manage')) {
            return $denied;
        }

        return $this->page('admin/maintenance', [
            'title'   => 'Maintenance — Fix Listed admin',
            'state'   => Maintenance::state(),
            'running' => Maintenance::runningFor(),
            'switchFile' => Maintenance::file(),
        ]);
    }

    public function update(): Response
    {
        if ($denied = $this->guardCan('maintenance.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was changed.');
            return Response::redirect('/admin/maintenance');
        }

        $wantsOn = $this->request->input('mode') === 'on';
        $message = trim((string) $this->request->input('message', ''));
        $user    = $this->auth->user() ?? [];
        $who     = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $who     = $who !== '' ? $who : (string) ($user['email'] ?? 'admin');

        if ($wantsOn) {
            if (!Maintenance::on($message, $who)) {
                // Worth saying exactly where to go next: somebody who cannot
                // take the site down from here needs the command, not an
                // apology.
                Session::flash('bad', 'Could not write the switch file. storage/ may not be writable — '
                    . 'run php bin/maintenance.php on over SSH instead.');
                return Response::redirect('/admin/maintenance');
            }

            $this->record('maintenance.on', 'site', null, ['message' => $message]);
            Session::flash('good', 'The site is down for visitors. You still see it as normal — '
                . 'the banner at the top is your reminder that the public does not.');

            return Response::redirect('/admin/maintenance');
        }

        if (!Maintenance::off()) {
            Session::flash('bad', 'Could not remove the switch file. Run php bin/maintenance.php off, '
                . 'or delete storage/maintenance.json by hand.');
            return Response::redirect('/admin/maintenance');
        }

        $this->record('maintenance.off', 'site', null);
        Session::flash('good', 'The site is back up. Visitors are being served normally again.');

        return Response::redirect('/admin/maintenance');
    }
}
