<?php
/** @var array $users @var array $waitlist @var string $role @var callable $can @var array|null $me */
require_once __DIR__ . '/../partials/icons.php';
$tab = static fn (string $r, string $label): string =>
    '<a class="btn btn-sm ' . ($role === $r ? 'btn-dark' : 'btn-ghost') . '" href="'
    . e(url_q('/admin/users', ['role' => $r])) . '">' . e($label) . '</a>';
?>
<div class="adm-h">
  <div><h1>People</h1><p>Accounts in <?= e($market['name']) ?>, and the pre-launch waiting list.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?= $tab('', 'All') ?><?= $tab('pro', 'Tradespeople') ?><?= $tab('homeowner', 'Homeowners') ?><?= $tab('market_admin', 'Managers') ?><?= $tab('moderator', 'Moderators') ?>
  </div>
</div>

<div class="panel" style="margin-bottom:22px">
  <div class="panel-h"><h3>Accounts</h3></div>
  <?php if ($users === []): ?>
    <div class="adm-empty">Nobody matches that.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Last seen</th>
          <?php if ($can('users.delete')): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: '—') ?>
              <?php if ($u['is_demo']): ?><span class="badge b-sample">Sample</span><?php endif; ?>
            </td>
            <td class="tiny"><?= e($u['email']) ?></td>
            <td class="tiny"><?= e(\FixListed\Core\Auth::roleLabel((string) $u['role'])) ?></td>
            <td><span class="badge <?= $u['status'] === 'active' ? 'b-live' : 'b-flat' ?>"><?= e($u['status']) ?></span></td>
            <td class="tiny muted"><?= e(ago($u['created_at'])) ?></td>
            <td class="tiny muted"><?= $u['last_login_at'] ? e(ago($u['last_login_at'])) : 'never' ?></td>
            <?php if ($can('users.delete')): ?>
              <td style="text-align:right;white-space:nowrap">
                <?php if (in_array((string) $u['role'], \FixListed\Core\Auth::STAFF_ROLES, true)): ?>
                  <!-- Staff go through the Team screen, which knows how to
                       refuse the last owner. -->
                  <a class="tiny" href="<?= e(url('/admin/team')) ?>">on the team</a>
                <?php elseif ((int) $u['id'] === (int) ($me['id'] ?? 0) || $u['status'] === 'deleted'): ?>
                  <span class="tiny muted">—</span>
                <?php else: ?>
                  <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/remove')) ?>"
                        style="display:flex;gap:6px;justify-content:flex-end">
                    <?= \FixListed\Core\Csrf::field() ?>
                    <input type="email" name="confirm_email" class="inp-sm" required
                           autocomplete="off" placeholder="type their email"
                           aria-label="Type <?= e($u['email']) ?> to confirm removal">
                    <button class="btn btn-danger btn-sm" type="submit">Remove</button>
                  </form>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-h">
    <h3>Waiting list</h3>
    <span class="tiny muted" style="margin-left:auto">Captured by the holding page before launch</span>
  </div>
  <?php if ($waitlist === []): ?>
    <div class="adm-empty">Nobody has signed up yet.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Counties</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($waitlist as $w): ?>
          <tr>
            <td><?= e($w['name']) ?></td>
            <td class="tiny"><?= e($w['email']) ?></td>
            <td class="tiny"><?= e($w['role']) ?></td>
            <td class="tiny muted wrap-cell"><?= e($w['counties'] ?? '—') ?></td>
            <td class="tiny muted"><?= e(ago($w['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
