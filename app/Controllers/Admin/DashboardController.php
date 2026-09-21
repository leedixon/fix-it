<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\AdminController;
use FixListed\Core\Response;
use FixListed\Repositories\AdminRepository;
use FixListed\Repositories\ProApplicationRepository;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        $admin = new AdminRepository($this->db, $this->scope);
        $counts = $admin->counts();

        // Removed here, not just hidden in the template. A number a manager
        // may not see should not reach the page they are served at all —
        // "display:none" on a revenue figure is still a revenue figure in
        // the HTML, readable by anyone who opens the inspector.
        if (!$this->auth->can('finance.view')) {
            unset($counts['revenue_30d']);
        }

        return $this->page('admin/dashboard', [
            'title'   => 'Dashboard — Fix Listed admin',
            'counts'  => $counts,
            'pending' => $counts['applications'],
            'queue'   => (new ProApplicationRepository($this->db, $this->scope))->pending(5),
            'recent'  => $admin->activity(8),
        ]);
    }

    public function activity(): Response
    {
        if ($denied = $this->guardCan('activity.view')) {
            return $denied;
        }
        $admin = new AdminRepository($this->db, $this->scope);

        return $this->page('admin/activity', [
            'title'   => 'Activity log — Fix Listed admin',
            'entries' => $admin->activity(200),
            'pending' => $admin->counts()['applications'],
        ]);
    }
}
