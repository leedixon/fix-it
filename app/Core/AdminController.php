<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Base for everything behind /admin.
 *
 * The gate is here and nowhere else. Every admin controller extends this and
 * calls guard() first, so "is this person allowed" is one method with one
 * implementation rather than a check each new screen has to remember to make
 * — the kind of thing that is correct on eleven screens and forgotten on the
 * twelfth.
 */
abstract class AdminController
{
    public function __construct(
        protected readonly Database $db,
        protected readonly TenantScope $scope,
        /** @var array<string,mixed> */
        protected readonly array $market,
        protected readonly View $view,
        protected readonly Request $request,
        protected readonly Auth $auth,
    ) {
    }

    /**
     * Refuses anyone who is not an administrator.
     *
     * Returns a Response to send, or null to carry on. Not an exception,
     * because the correct answer for a signed-out visitor is a login page,
     * not an error — and the correct answer for a signed-in tradesperson
     * poking at /admin is a 404, which does not confirm the URL exists.
     */
    protected function guard(): ?Response
    {
        if (!$this->auth->check()) {
            Session::put('_admin_intended', $this->request->path);
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->can('admin.access')) {
            throw new NotFound('admin');
        }
        return null;
    }

    /**
     * Refuses anyone without this capability.
     *
     * A 404, not a redirect with an explanation. The screens behind these are
     * hidden from the navigation of anyone who cannot use them, so a staff
     * member reaching one has either typed the URL or been sent it, and
     * neither deserves confirmation that it exists. It also keeps the
     * behaviour identical whether you lack the capability or the page is not
     * there at all.
     */
    protected function guardCan(string $capability): ?Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        if (!$this->auth->can($capability)) {
            throw new NotFound('admin: ' . $capability);
        }
        return null;
    }

    /** Shorthand for the several screens that are the owner's alone. */
    protected function guardSuper(): ?Response
    {
        return $this->guardCan('markets.manage');
    }

    /** @param array<string,mixed> $data */
    protected function page(string $template, array $data = [], int $status = 200): Response
    {
        $defaults = [
            'title'    => 'Admin — Fix Listed',
            'market'   => $this->market,
            'path'     => $this->request->path,
            'me'       => $this->auth->user(),
            'isSuper'  => $this->auth->isSuperadmin(),
            // Templates ask what this person may do rather than what they
            // are, so a nav item or a button disappears by asking the same
            // question the controller asked.
            'can'      => fn (string $capability): bool => $this->auth->can($capability),
            'flashes'  => Session::takeFlashes(),
            'pending'  => 0,
        ];

        return Response::html(
            $this->view->render($template, $data + $defaults, 'layouts/admin'),
            $status,
        );
    }

    protected function audit(): AuditLog
    {
        return new AuditLog($this->db);
    }

    /** Every admin write is recorded, with who and from where. */
    protected function record(string $action, string $subjectType, ?int $subjectId, array $meta = []): void
    {
        $this->audit()->record(
            $action,
            $this->auth->id(),
            $subjectType,
            $subjectId,
            $meta,
            $this->scope->crossMarket ? null : $this->scope->marketId,
            $this->request->ip(),
        );
    }

    /** POSTs carry a token; a mismatch means the session expired or it is forged. */
    protected function checkCsrf(): bool
    {
        return Csrf::check($this->request->input('_csrf'));
    }
}
