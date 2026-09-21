<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\AdminController;
use FixListed\Core\Auth;
use FixListed\Core\Mailer;
use FixListed\Core\PasswordReset;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\Validator;
use FixListed\Repositories\TeamRepository;

/**
 * Who runs the site, and what each of them may do.
 *
 * Superadmin only, all of it. Somebody who can add a colleague can add
 * themselves a second account, and somebody who can change a role can promote
 * themselves — so the ability to manage the team is the ability to become
 * anything, and it stays with the owner.
 *
 * Three rules hold across every action here, and each exists because the
 * failure it prevents has no way back through the interface:
 *
 *  1. **Nobody acts on their own account.** No self-demotion, no
 *     self-suspension, no self-deletion. The mistake is one click and the
 *     recovery is an SSH session.
 *  2. **The last owner cannot be removed, demoted or suspended.** A platform
 *     with no superadmin who can sign in is a platform nobody can administer.
 *  3. **A password is never set by the person creating the account.** Staff
 *     get an emailed link and choose their own, so there is no temporary
 *     password to be reused, forwarded or left in a message thread.
 */
final class TeamController extends AdminController
{
    private const ASSIGNABLE = [Auth::ROLE_SUPER, Auth::ROLE_ADMIN, Auth::ROLE_MODERATOR];

    public function index(): Response
    {
        if ($denied = $this->guardCan('team.manage')) {
            return $denied;
        }

        return $this->page('admin/team', [
            'title'       => 'Team — Fix Listed admin',
            'team'        => (new TeamRepository($this->db))->all(),
            'inviteDays'  => PasswordReset::STAFF_INVITE_DAYS,
            'roles'       => self::ASSIGNABLE,
            'old'         => [],
            'errors'      => [],
        ]);
    }

    public function invite(): Response
    {
        if ($denied = $this->guardCan('team.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nobody was invited.');
            return Response::redirect('/admin/team');
        }

        $v = new Validator($this->request->body);
        $v->required('email', 'Email')->email('email')
          ->required('first_name', 'First name')->max('first_name', 80, 'First name')
          ->max('last_name', 80, 'Last name')
          // required as well as in(): in() passes an empty value through, so
          // on its own it would accept a form with no role chosen at all.
          ->required('role', 'Role')
          ->in('role', self::ASSIGNABLE, 'Role');

        $role  = (string) $v->value('role');
        $email = mb_strtolower(trim((string) $v->value('email')));

        if (!$v->passes()) {
            return $this->back($v->errors());
        }

        $team     = new TeamRepository($this->db);
        $existing = $team->findByEmail($email);

        if ($existing !== null && in_array((string) $existing['role'], self::ASSIGNABLE, true)) {
            Session::flash('bad', 'That email is already on the team. Change their role instead.');
            return Response::redirect('/admin/team');
        }
        if ($existing !== null && (string) $existing['status'] === 'deleted') {
            Session::flash('bad', 'That account was removed. Use a different email address.');
            return Response::redirect('/admin/team');
        }

        $marketId  = (int) $this->market['id'];
        $invitedBy = (int) $this->auth->id();

        if ($existing !== null) {
            // A tradesperson or homeowner who is also going to help run the
            // site. Their existing password stays working — wiping it to
            // force a set-password link would lock them out of the account
            // they already use.
            $userId = (int) $existing['id'];
            $team->promote($userId, $role, $marketId, $invitedBy);
            $this->record('team.promoted', 'user', $userId, ['to' => $role]);

            Session::flash('good', trim($existing['first_name'] . ' ' . $existing['last_name'])
                . ' already had an account, so it has been upgraded to '
                . Auth::roleLabel($role) . '. They sign in with the password they already have.');

            return Response::redirect('/admin/team');
        }

        $userId = $team->invite(
            $email,
            (string) $v->value('first_name'),
            (string) $v->value('last_name'),
            $role,
            $marketId,
            $invitedBy,
        );
        $this->record('team.invited', 'user', $userId, ['role' => $role, 'email' => $email]);

        if (!$this->sendInvite($userId, $email, (string) $v->value('first_name'), $role)) {
            Session::flash('bad', 'The account was created but the invite email did not send. '
                . 'Use "Resend invite" to try again.');
            return Response::redirect('/admin/team');
        }

        Session::flash('good', 'Invited ' . $email . ' as ' . Auth::roleLabel($role)
            . '. The link in their email works once and expires in '
            . PasswordReset::STAFF_INVITE_DAYS . ' days.');

        return Response::redirect('/admin/team');
    }

    public function resend(string $id): Response
    {
        if ($denied = $this->guardCan('team.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            return Response::redirect('/admin/team');
        }

        $team = new TeamRepository($this->db);
        $user = $team->find((int) $id);
        if ($user === null) {
            Session::flash('bad', 'No such team member.');
            return Response::redirect('/admin/team');
        }
        if ((string) $user['status'] !== 'active') {
            Session::flash('bad', 'That account is suspended. Restore it before sending a link.');
            return Response::redirect('/admin/team');
        }

        // Issuing a new token invalidates the outstanding one, so a resend
        // also quietly kills a link that went to the wrong address.
        if (!$this->sendInvite((int) $user['id'], (string) $user['email'],
                               (string) $user['first_name'], (string) $user['role'])) {
            Session::flash('bad', 'That email did not send. Check php bin/check.php for the mail setup.');
            return Response::redirect('/admin/team');
        }

        $this->record('team.invite_resent', 'user', (int) $user['id']);
        Session::flash('good', 'A fresh link is on its way to ' . $user['email']
            . '. Any previous one has stopped working.');

        return Response::redirect('/admin/team');
    }

    public function setRole(string $id): Response
    {
        if ($denied = $this->guardCan('team.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            return Response::redirect('/admin/team');
        }

        $team   = new TeamRepository($this->db);
        $userId = (int) $id;
        $role   = (string) $this->request->input('role', '');
        $user   = $team->find($userId);

        if ($user === null || !in_array($role, self::ASSIGNABLE, true)) {
            Session::flash('bad', 'That change does not make sense.');
            return Response::redirect('/admin/team');
        }
        if ($userId === $this->auth->id()) {
            Session::flash('bad', 'You cannot change your own role. Ask another superadmin, '
                . 'or use php bin/admin.php.');
            return Response::redirect('/admin/team');
        }
        if ((string) $user['role'] === Auth::ROLE_SUPER
            && $role !== Auth::ROLE_SUPER
            && $team->activeSuperadmins($userId) < 1) {
            Session::flash('bad', 'That is the only superadmin who can sign in. Promote somebody '
                . 'else first, or the platform ends up with nobody who can administer it.');
            return Response::redirect('/admin/team');
        }

        $team->setRole($userId, $role, (int) $this->market['id']);
        $this->record('team.role_changed', 'user', $userId,
            ['from' => (string) $user['role'], 'to' => $role]);

        Session::flash('good', trim($user['first_name'] . ' ' . $user['last_name'])
            . ' is now ' . Auth::roleLabel($role) . '. It takes effect on their next page load.');

        return Response::redirect('/admin/team');
    }

    public function setStatus(string $id): Response
    {
        if ($denied = $this->guardCan('team.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            return Response::redirect('/admin/team');
        }

        $team   = new TeamRepository($this->db);
        $userId = (int) $id;
        $status = (string) $this->request->input('status', '');
        $user   = $team->find($userId);

        if ($user === null || !in_array($status, ['active', 'suspended'], true)) {
            Session::flash('bad', 'That change does not make sense.');
            return Response::redirect('/admin/team');
        }
        if ($userId === $this->auth->id()) {
            Session::flash('bad', 'You cannot suspend your own account.');
            return Response::redirect('/admin/team');
        }
        if ($status === 'suspended'
            && (string) $user['role'] === Auth::ROLE_SUPER
            && $team->activeSuperadmins($userId) < 1) {
            Session::flash('bad', 'That is the last superadmin who can sign in.');
            return Response::redirect('/admin/team');
        }

        $team->setStatus($userId, $status);
        $this->record('team.status_changed', 'user', $userId, ['to' => $status]);

        Session::flash('good', $status === 'suspended'
            ? trim($user['first_name'] . ' ' . $user['last_name'])
              . ' is suspended. They are signed out on their next request, and any invite link '
              . 'they were sent has stopped working.'
            : trim($user['first_name'] . ' ' . $user['last_name']) . ' can sign in again.');

        return Response::redirect('/admin/team');
    }

    /**
     * Removes a person for good.
     *
     * Guarded by typing their email, because this is the one action on this
     * screen with no undo button — and a confirm dialog somebody clicks
     * through by reflex is not a guard.
     */
    public function remove(string $id): Response
    {
        if ($denied = $this->guardCan('users.delete')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            return Response::redirect('/admin/team');
        }

        $team   = new TeamRepository($this->db);
        $userId = (int) $id;
        $user   = $team->find($userId);

        if ($user === null) {
            Session::flash('bad', 'No such team member.');
            return Response::redirect('/admin/team');
        }
        if ($userId === $this->auth->id()) {
            Session::flash('bad', 'You cannot remove your own account.');
            return Response::redirect('/admin/team');
        }
        if ((string) $user['role'] === Auth::ROLE_SUPER && $team->activeSuperadmins($userId) < 1) {
            Session::flash('bad', 'That is the last superadmin who can sign in. '
                . 'Promote somebody else first.');
            return Response::redirect('/admin/team');
        }

        $typed = mb_strtolower(trim((string) $this->request->input('confirm_email', '')));
        if ($typed !== mb_strtolower((string) $user['email'])) {
            Session::flash('bad', 'Nothing was removed — the email did not match. '
                . 'Type ' . $user['email'] . ' exactly to confirm.');
            return Response::redirect('/admin/team');
        }

        // Recorded before the row is scrubbed, or the log loses the one
        // detail anybody will want back: who this was.
        $this->record('team.removed', 'user', $userId, [
            'email' => (string) $user['email'],
            'role'  => (string) $user['role'],
            'name'  => trim($user['first_name'] . ' ' . $user['last_name']),
        ]);

        $team->remove($userId);

        Session::flash('good', $user['email'] . ' has been removed and can no longer sign in. '
            . 'What they did while they were here stays in the activity log.');

        return Response::redirect('/admin/team');
    }

    /** @param array<string,string> $errors */
    private function back(array $errors): Response
    {
        return $this->page('admin/team', [
            'title'      => 'Team — Fix Listed admin',
            'team'       => (new TeamRepository($this->db))->all(),
            'inviteDays' => PasswordReset::STAFF_INVITE_DAYS,
            'roles'      => self::ASSIGNABLE,
            'old'        => $this->request->body,
            'errors'     => $errors,
        ]);
    }

    /** Issues a one-time link and emails it. False if the email did not go. */
    private function sendInvite(int $userId, string $email, string $firstName, string $role): bool
    {
        $token = (new PasswordReset($this->db))->issue($userId, true, true);
        $me    = $this->auth->user() ?? [];
        $who   = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));

        try {
            return Mailer::fromConfig()->send(
                $email,
                'Your Fix Listed admin account',
                $this->view->render('emails.team_invite', [
                    'title'     => 'You have been added to Fix Listed',
                    'preheader' => 'Set your password to get in.',
                    'name'      => $firstName,
                    'inviter'   => $who !== '' ? $who : (string) ($me['email'] ?? 'The owner'),
                    'roleLabel' => Auth::roleLabel($role),
                    'roleBlurb' => Auth::roleBlurb($role),
                    'market'    => (string) $this->market['name'],
                    'days'      => PasswordReset::STAFF_INVITE_DAYS,
                    'setUpUrl'  => abs_url('/set-password/' . $token),
                ], 'emails.layout'),
                "You have been given a " . Auth::roleLabel($role) . " account on Fix Listed.\n\n"
                . "Set your password here — the link works once and expires in "
                . PasswordReset::STAFF_INVITE_DAYS . " days:\n"
                . abs_url('/set-password/' . $token) . "\n",
            );
        } catch (\Throwable $e) {
            error_log('Team invite email failed for ' . $email . ': ' . $e->getMessage());
            return false;
        }
    }
}
