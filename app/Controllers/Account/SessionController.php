<?php
declare(strict_types=1);

namespace FixListed\Controllers\Account;

use FixListed\Core\Auth;
use FixListed\Core\AuditLog;
use FixListed\Core\Config;
use FixListed\Core\Controller;
use FixListed\Core\Csrf;
use FixListed\Core\Mailer;
use FixListed\Core\PasswordReset;
use FixListed\Core\Response;
use FixListed\Core\Session;

/**
 * Signing in, and the forgotten-password path.
 *
 * Extends the public Controller rather than AccountController, because these
 * pages are the ones you see when you are not signed in and they wear the
 * public chrome.
 */
final class SessionController extends Controller
{
    public function form(): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/my');
        }

        return $this->page('site/sign_in', [
            'title'       => 'Sign in — Fix Listed',
            'description' => 'Sign in to quote jobs and manage your listing.',
            'error'       => Session::pull('_signin_error', ''),
            'email'       => Session::pull('_signin_email', ''),
            'noindex'     => true,
        ]);
    }

    public function login(): Response
    {
        if (!Csrf::check($this->request->input('_csrf'))) {
            Session::put('_signin_error', 'That form expired. Try again.');
            return Response::redirect('/sign-in');
        }

        $email  = (string) $this->request->input('email', '');
        $result = $this->auth->attempt($email, (string) $this->request->input('password', ''), $this->request->ip());

        if (!$result['ok']) {
            error_log('Sign-in failed for ' . $email . ': ' . $result['reason'] . ' from ' . $this->request->ip());

            Session::put('_signin_email', $email);
            Session::put('_signin_error', match ($result['reason']) {
                'locked'   => 'Too many attempts. This account is locked for 15 minutes.',
                // A pro approved but who never set a password has no hash at
                // all. Telling them to check their email is the actual answer,
                // and it reveals nothing that the sign-up form does not.
                default    => 'That email and password do not match. If you have just been approved, '
                            . 'use the link in your approval email to set a password first.',
            });
            return Response::redirect('/sign-in');
        }

        $intended = Session::pull('_intended');
        return Response::redirect(
            is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')
                ? $intended
                : '/my'
        );
    }

    public function logout(): Response
    {
        if (Csrf::check($this->request->input('_csrf'))) {
            /*
             * Staff signing out is logged here too, not only at
             * /admin/logout.
             *
             * The account menu in the public header signs everybody out
             * through this one route, so without this an administrator who
             * signed out from the marketing site left no trace — on a screen
             * whose own heading promises every administrative change,
             * appended and never edited.
             */
            if ($this->auth->is(...Auth::STAFF_ROLES)) {
                (new AuditLog($this->db))->record(
                    'admin.signed_out',
                    $this->auth->id(),
                    'user',
                    $this->auth->id(),
                    ['from' => 'site'],
                    (int) $this->market['id'],
                    $this->request->ip(),
                );
            }
            $this->auth->logout();
        }
        return Response::redirect('/');
    }

    public function forgotForm(): Response
    {
        return $this->page('site/forgot', [
            'title'   => 'Reset your password — Fix Listed',
            'sent'    => (bool) Session::pull('_forgot_sent', false),
            'noindex' => true,
        ]);
    }

    public function forgot(): Response
    {
        if (!Csrf::check($this->request->input('_csrf'))) {
            return Response::redirect('/forgot-password');
        }

        $email = mb_strtolower(trim((string) $this->request->input('email', '')));

        $user = $this->db->one(
            "SELECT id, first_name FROM users WHERE email = :e AND status = 'active' LIMIT 1",
            ['e' => $email],
        );

        // The answer is identical whether or not the account exists. Anything
        // else turns this form into a way of finding out who is listed.
        if ($user !== null) {
            try {
                $token = (new PasswordReset($this->db))->issue((int) $user['id']);
                $this->sendResetLink($email, (string) $user['first_name'], $token, false);
            } catch (\Throwable $e) {
                error_log('Password reset mail failed for ' . $email . ': ' . $e->getMessage());
            }
        }

        Session::put('_forgot_sent', true);
        return Response::redirect('/forgot-password');
    }

    private function sendResetLink(string $email, string $name, string $token, bool $invite): void
    {
        $mailer = Mailer::fromConfig();
        $link   = abs_url('/set-password/' . $token);

        $mailer->send(
            $email,
            $invite ? 'Set your Fix Listed password' : 'Reset your Fix Listed password',
            $this->view->render('emails.password_link', [
                'title'     => $invite ? 'Set your password' : 'Reset your password',
                'preheader' => 'This link works once and then expires.',
                'name'      => $name,
                'link'      => $link,
                'invite'    => $invite,
                'hours'     => PasswordReset::RESET_HOURS,
                'days'      => PasswordReset::INVITE_DAYS,
            ], 'emails.layout'),
            ($invite ? "Set your Fix Listed password:\n\n" : "Reset your Fix Listed password:\n\n")
            . $link . "\n\nIf you did not ask for this, ignore it — nothing changes until the link is used.\n",
        );
    }
}
