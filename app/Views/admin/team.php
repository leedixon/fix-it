<?php
/**
 * @var array $team @var int $inviteDays @var array $roles
 * @var array $old @var array $errors @var array|null $me @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';

use FixListed\Core\Auth;

$err = static fn (string $f): string => $errors[$f] ?? '';
$val = static fn (string $f): string => (string) ($old[$f] ?? '');
$myId = (int) ($me['id'] ?? 0);
?>
<div class="adm-h">
  <div>
    <h1>Team</h1>
    <p>Who runs Fix Listed, and what each of them can reach.</p>
  </div>
</div>

<?php if ($err('_form') !== ''): ?>
  <div class="flash flash-bad"><?= icon('alert', 17) ?><span><?= e($err('_form')) ?></span></div>
<?php endif; ?>

<div class="panel" style="margin-bottom:22px">
  <div class="panel-h">
    <h3>People</h3>
    <span class="tiny muted" style="margin-left:auto"><?= count($team) ?> with access</span>
  </div>
  <div class="tablewrap">
    <table>
      <thead><tr>
        <th>Name</th><th>Role</th><th>Status</th><th>Last seen</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($team as $u): ?>
        <?php
          $uid      = (int) $u['id'];
          $isMe     = $uid === $myId;
          $name     = trim($u['first_name'] . ' ' . $u['last_name']);
          $pending  = !$u['has_password'];
          $suspended = $u['status'] !== 'active';
        ?>
        <tr>
          <td>
            <strong><?= e($name !== '' ? $name : '—') ?></strong>
            <?php if ($isMe): ?><span class="badge b-flat">You</span><?php endif; ?>
            <div class="tiny muted"><?= e($u['email']) ?></div>
            <?php if ($u['invited_by_email'] !== null): ?>
              <div class="tiny muted">invited by <?= e($u['invited_by_email']) ?></div>
            <?php endif; ?>
          </td>

          <td>
            <?php if ($isMe): ?>
              <!-- No self-demotion. One click, and the recovery is an SSH
                   session — so the control simply is not here. -->
              <span class="badge b-featured"><?= e(Auth::roleLabel((string) $u['role'])) ?></span>
            <?php else: ?>
              <form method="post" action="<?= e(url('/admin/team/' . $uid . '/role')) ?>"
                    style="display:flex;gap:6px;align-items:center">
                <?= \FixListed\Core\Csrf::field() ?>
                <select name="role" class="sel-sm" onchange="this.form.submit()">
                  <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r) ?>"<?= $u['role'] === $r ? ' selected' : '' ?>>
                      <?= e(Auth::roleLabel($r)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-ghost btn-sm" type="submit">Set</button></noscript>
              </form>
            <?php endif; ?>
          </td>

          <td>
            <?php if ($suspended): ?>
              <span class="badge b-flat">Suspended</span>
            <?php elseif ($pending): ?>
              <span class="badge b-pending">Invite sent</span>
              <?php if (!$u['live_invites']): ?>
                <div class="tiny muted" style="margin-top:4px">link expired</div>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge b-live">Active</span>
            <?php endif; ?>
          </td>

          <td class="tiny muted"><?= $u['last_login_at'] ? e(ago($u['last_login_at'])) : 'never' ?></td>

          <td>
            <?php if ($isMe): ?>
              <span class="tiny muted">—</span>
            <?php else: ?>
              <div class="row-acts">
                <?php if ($pending && !$suspended): ?>
                  <form method="post" action="<?= e(url('/admin/team/' . $uid . '/resend')) ?>">
                    <?= \FixListed\Core\Csrf::field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit">Resend invite</button>
                  </form>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/team/' . $uid . '/status')) ?>">
                  <?= \FixListed\Core\Csrf::field() ?>
                  <input type="hidden" name="status" value="<?= $suspended ? 'active' : 'suspended' ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">
                    <?= $suspended ? 'Restore' : 'Suspend' ?>
                  </button>
                </form>
              </div>

              <!-- Removal asks for the email to be typed. A confirm dialog is
                   clicked through by reflex; this one cannot be. -->
              <form method="post" action="<?= e(url('/admin/team/' . $uid . '/remove')) ?>"
                    class="row-acts row-remove">
                <?= \FixListed\Core\Csrf::field() ?>
                <input type="email" name="confirm_email" class="inp-sm"
                       placeholder="type their email to remove" autocomplete="off" required
                       aria-label="Type <?= e($u['email']) ?> to confirm removal">
                <button class="btn btn-danger btn-sm" type="submit">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<form class="panel" method="post" action="<?= e(url('/admin/team')) ?>">
  <?= \FixListed\Core\Csrf::field() ?>
  <div class="panel-h"><h3>Add somebody</h3></div>
  <div style="padding:18px 20px">
    <div class="grid g3" style="gap:0 18px">
      <label class="field">
        <span>First name</span>
        <input type="text" name="first_name" value="<?= e($val('first_name')) ?>" required>
        <?php if ($err('first_name')): ?><small class="bad"><?= e($err('first_name')) ?></small><?php endif; ?>
      </label>
      <label class="field">
        <span>Last name</span>
        <input type="text" name="last_name" value="<?= e($val('last_name')) ?>">
      </label>
      <label class="field">
        <span>Email</span>
        <input type="email" name="email" value="<?= e($val('email')) ?>" required autocomplete="off">
        <?php if ($err('email')): ?><small class="bad"><?= e($err('email')) ?></small><?php endif; ?>
      </label>
    </div>

    <fieldset class="roleset">
      <legend>What they can do</legend>
      <?php foreach (array_reverse($roles) as $r): ?>
        <label class="rolepick">
          <input type="radio" name="role" value="<?= e($r) ?>"
                 <?= $val('role') === $r || ($val('role') === '' && $r === Auth::ROLE_MODERATOR) ? 'checked' : '' ?>>
          <span>
            <strong><?= e(Auth::roleLabel($r)) ?></strong>
            <small><?= e(Auth::roleBlurb($r)) ?></small>
          </span>
        </label>
      <?php endforeach; ?>
      <?php if ($err('role')): ?><small class="bad"><?= e($err('role')) ?></small><?php endif; ?>
    </fieldset>

    <button class="btn btn-dark" type="submit" style="margin-top:6px">Send the invite</button>
    <p class="tiny muted" style="margin-top:10px">
      They get an email with a link to set their own password. Nobody here ever knows it, and
      the link works once and expires in <?= (int) $inviteDays ?> days.
      If the address already has a Fix Listed account, it is upgraded instead and they keep
      the password they have.
    </p>
  </div>
</form>

<div class="panel" style="margin-top:22px">
  <div class="panel-h"><h3>What only you can do</h3></div>
  <div style="padding:18px 20px">
    <ul class="plain-list">
      <li><?= icon('lock', 14) ?> <span><strong>Money.</strong> Revenue, what a plan costs and what
          the inventory is worth are invisible to a Manager and a Moderator. Prices and slot caps
          are yours to change.</span></li>
      <li><?= icon('lock', 14) ?> <span><strong>This screen.</strong> Anyone who can add a colleague
          can add themselves a second account, so managing the team stays with the owner.</span></li>
      <li><?= icon('lock', 14) ?> <span><strong>Removing an account.</strong> Suspending is
          reversible and a Manager still cannot do it. Removing is not reversible and is
          yours alone.</span></li>
      <li><?= icon('info', 14) ?> <span><strong>You cannot act on yourself here</strong>, and the
          last superadmin who can sign in cannot be removed, demoted or suspended by anyone.
          Use <code class="mono tiny">php bin/admin.php</code> if you ever need to.</span></li>
    </ul>
  </div>
</div>
