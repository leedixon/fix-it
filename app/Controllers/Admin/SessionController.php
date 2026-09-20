<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\AdminController;
use FixListed\Core\Auth;
use FixListed\Core\Csrf;
use FixListed\Core\Response;
use FixListed\Core\Session;

final class SessionController extends AdminController
{
    public function form(): Response
    {
        if ($this->auth->is(Auth::ROLE_SUPER, Auth::ROLE_ADMIN)) {
            return Response::redirect('/admin');
        }
        return Response::html(
            $this->view->render('admin/login', [
                'title' => 'Sign in — Fix Listed admin',
                'error' => Session::pull('_login_error', ''),
                'email' => '',
            ], null),
        );
    }

    public function login(): Response
    {
        if (!Csrf::check($this->request->input('_csrf'))) {
            Session::put('_login_error', 'That form expired. Try again.');
            return Response::redirect('/admin/login');
        }

        $email  = (string) $this->request->input('email', '');
        $result = $this->auth->attempt($email, (string) $this->request->input('password', ''), $this->request->ip());

        if (!$result['ok']) {
            // The reason goes to the log, never to the screen: "no such email"
            // versus "wrong password" tells an attacker which accounts exist.
            error_log('Admin login failed for ' . $email . ': ' . $result['reason'] . ' from ' . $this->request->ip());

            Session::put('_login_error', $result['reason'] === 'locked'
                ? 'Too many attempts. This account is locked for 15 minutes.'
                : 'That email and password do not match.');
            return Response::redirect('/admin/login');
        }

        if (!$this->auth->is(Auth::ROLE_SUPER, Auth::ROLE_ADMIN)) {
            // A real account, but not an administrator. Signed straight back
            // out rather than left open on an admin URL — but with the
            // session kept, or the message below dies with it and they are
            // bounced to a blank form with no explanation.
            $this->auth->forgetUser();
            Session::put('_login_error', 'That account cannot use the admin.');
            return Response::redirect('/admin/login');
        }

        $this->record('admin.signed_in', 'user', $this->auth->id());

        $intended = Session::pull('_admin_intended');
        return Response::redirect(is_string($intended) && str_starts_with($intended, '/admin') ? $intended : '/admin');
    }

    public function logout(): Response
    {
        if (Csrf::check($this->request->input('_csrf'))) {
            $this->record('admin.signed_out', 'user', $this->auth->id());
            $this->auth->logout();
        }
        return Response::redirect('/admin/login');
    }
}
