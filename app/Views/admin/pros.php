<?php
/** @var array $pros @var string $status */
require_once __DIR__ . '/../partials/icons.php';
$tab = static fn (string $s, string $label): string =>
    '<a class="btn btn-sm ' . ($status === $s ? 'btn-dark' : 'btn-ghost') . '" href="'
    . e(url_q('/admin/pros', ['status' => $s])) . '">' . e($label) . '</a>';
?>
<div class="adm-h">
  <div><h1>Tradespeople</h1><p>Every listing, whatever its state.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?= $tab('', 'All') ?><?= $tab('active', 'Live') ?><?= $tab('pending_review', 'Pending') ?><?= $tab('suspended', 'Suspended') ?>
  </div>
</div>

<div class="panel">
  <?php if ($pros === []): ?>
    <div class="adm-empty">No listings match that.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Business</th><th>Trades</th><th>Status</th><th>Verified</th><th>Rating</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pros as $p): ?>
          <tr>
            <td class="wrap-cell">
              <strong><?= e($p['display_name']) ?></strong>
              <?php if ($p['is_demo']): ?><span class="badge b-sample">Sample</span><?php endif; ?><br>
              <span class="tiny muted"><?= e($p['email']) ?></span>
            </td>
            <td class="wrap-cell tiny"><?= e($p['trades'] ?? '—') ?></td>
            <td>
              <?php $s = (string) $p['status']; ?>
              <span class="badge <?= $s === 'active' ? 'b-live' : ($s === 'pending_review' ? 'b-pending' : 'b-flat') ?>">
                <?= e(str_replace('_', ' ', $s)) ?>
              </span>
            </td>
            <td class="tiny">
              <?= $p['license_verified_at'] ? 'licence' : '' ?>
              <?= $p['license_verified_at'] && $p['insurance_verified_at'] ? ' · ' : '' ?>
              <?= $p['insurance_verified_at'] ? 'insurance' : '' ?>
              <?= !$p['license_verified_at'] && !$p['insurance_verified_at'] ? '—' : '' ?>
            </td>
            <td class="num"><?= $p['rating_count'] > 0 ? e(number_format((float) $p['rating_avg'], 1)) . ' (' . (int) $p['rating_count'] . ')' : '—' ?></td>
            <td style="display:flex;gap:6px">
              <?php if ($p['status'] === 'active'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/pros/' . $p['slug'])) ?>" target="_blank" rel="noopener">View</a>
              <?php elseif ($p['status'] === 'pending_review'): ?>
                <a class="btn btn-dark btn-sm" href="<?= e(url('/admin/applications/' . $p['id'])) ?>">Review</a>
              <?php endif; ?>
              <?php if ($p['status'] === 'active'): ?>
                <!-- Approval emails the only sign-in link this account gets.
                     If that send failed, the profile is live and its owner is
                     locked out — this is the way back. -->
                <form method="post" action="<?= e(url('/admin/pros/' . $p['id'] . '/resend')) ?>">
                  <?= \FixListed\Core\Csrf::field() ?>
                  <button class="btn btn-ghost btn-sm" type="submit"
                          title="Email them a fresh link to set their password">Send sign-in link</button>
                </form>
              <?php endif; ?>
              <?php if ($p['status'] !== 'pending_review'): ?>
                <form method="post" action="<?= e(url('/admin/pros/' . $p['id'] . '/status')) ?>">
                  <?= \FixListed\Core\Csrf::field() ?>
                  <input type="hidden" name="status" value="<?= $p['status'] === 'active' ? 'suspended' : 'active' ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">
                    <?= $p['status'] === 'active' ? 'Suspend' : 'Reinstate' ?>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
