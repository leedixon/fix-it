<?php
declare(strict_types=1);

namespace FixListed\Controllers\Account;

use FixListed\Core\Controller;
use FixListed\Core\Csrf;
use FixListed\Core\PasswordReset;
use FixListed\Repositories\TeamRepository;
use FixListed\Core\Response;
use FixListed\Core\Session;

/** Setting a password from an emailed link — both the invite and the reset. */
final class PasswordController extends Controller
{
    public function form(string $token): Response
    {
        $reset = (new PasswordReset($this->db))->resolve($token);

        return $this->page('site/set_password', [
            'title'   => 'Set your password — Fix Listed',
            'valid'   => $reset !== null,
            'token'   => $token,
            'name'    => $reset['first_name'] ?? '',
            'error'   => Session::pull('_pw_error', ''),
            'noindex' => true,
        ], $reset === null ? 410 : 200);
    }

    public function submit(string $token): Response
    {
        if (!Csrf::check($this->request->input('_csrf'))) {
            Session::put('_pw_error', 'That form expired. Try again.');
            return Response::redirect('/set-password/' . $token);
        }

        $service = new PasswordReset($this->db);
        $reset   = $service->resolve($token);
        if ($reset === null) {
            // Resolved again here, not trusted from the GET: a link can expire
            // or be spent in the minutes the form sits open.
            return Response::redirect('/set-password/' . $token);
        }

        $password = (string) $this->request->input('password', '');
        $again    = (string) $this->request->input('password_confirm', '');

        if ($password !== $again) {
            Session::put('_pw_error', 'Those two passwords are not the same.');
            return Response::redirect('/set-password/' . $token);
        }
        // Length beats character classes: three unrelated words outlast a
        // short password with a symbol in it, and people remember them.
        if (mb_strlen($password) < 10) {
            Session::put('_pw_error', 'Use at least 10 characters. A few words you will remember is ideal.');
            return Response::redirect('/set-password/' . $token);
        }

        $service->complete((int) $reset['reset_id'], (int) $reset['id'], $password);

        // Signed in straight away. Making somebody who just proved they own
        // the mailbox type the password they set ten seconds ago is friction
        // for nothing.
        $this->auth->attempt((string) $reset['email'], $password, $this->request->ip());

        // A staff member lands in the admin, not on a tradesperson's
        // dashboard — /my has nothing on it for somebody with no listing,
        // and a first impression of an empty screen invites an email asking
        // where the admin panel is.
        if ($this->auth->can('admin.access')) {
            (new TeamRepository($this->db))->markAccepted((int) $reset['id']);
            Session::flash('ok', 'Password set. Welcome to Fix Listed.');
            return Response::redirect('/admin');
        }

        Session::flash('ok', 'Password set. You are signed in.');
        return Response::redirect('/my');
    }
}
