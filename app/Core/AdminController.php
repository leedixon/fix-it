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
        if (!$this->auth->is(Auth::ROLE_SUPER, Auth::ROLE_ADMIN)) {
            throw new NotFound('admin');
        }
        return null;
    }

    /** Only a superadmin may cross market boundaries or change pricing. */
    protected function guardSuper(): ?Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        if (!$this->auth->isSuperadmin()) {
            Session::flash('bad', 'That is a superadmin-only screen.');
            return Response::redirect('/admin');
        }
        return null;
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
