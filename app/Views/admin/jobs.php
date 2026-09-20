<?php
/** @var array $jobs @var string $status */
require_once __DIR__ . '/../partials/icons.php';
$tab = static fn (string $s, string $label): string =>
    '<a class="btn btn-sm ' . ($status === $s ? 'btn-dark' : 'btn-ghost') . '" href="'
    . e(url_q('/admin/jobs', ['status' => $s])) . '">' . e($label) . '</a>';
?>
<div class="adm-h">
  <div>
    <h1>Jobs</h1>
    <p>Including the ones that never got paid for — those are invisible on the site by design.</p>
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?= $tab('', 'All') ?><?= $tab('active', 'Live') ?><?= $tab('pending_payment', 'Unpaid') ?><?= $tab('removed', 'Removed') ?>
  </div>
</div>

<div class="panel">
  <?php if ($jobs === []): ?>
    <div class="adm-empty">No jobs match that.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Reference</th><th>Job</th><th>Status</th><th>Budget</th><th>Quotes</th><th>Posted</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($jobs as $j): ?>
          <tr>
            <td class="mono tiny"><?= e($j['reference']) ?></td>
            <td class="wrap-cell">
              <strong><?= e($j['title']) ?></strong>
              <?php if ($j['is_demo']): ?><span class="badge b-sample">Sample</span><?php endif; ?><br>
              <span class="tiny muted"><?= e($j['trade_name']) ?> · <?= e($j['city_name'] ?? '—') ?> · <?= e($j['poster_email']) ?></span>
            </td>
            <td>
              <?php $s = (string) $j['status']; ?>
              <span class="badge <?= $s === 'active' ? 'b-live' : ($s === 'pending_payment' ? 'b-pending' : 'b-flat') ?>">
                <?= e(str_replace('_', ' ', $s)) ?>
              </span>
            </td>
            <td class="num tiny"><?= e(budget(
                $j['budget_min_cents'] !== null ? (int) $j['budget_min_cents'] : null,
                $j['budget_max_cents'] !== null ? (int) $j['budget_max_cents'] : null)) ?></td>
            <td class="num"><?= (int) $j['quote_count'] ?></td>
            <td class="tiny muted"><?= e(ago($j['created_at'])) ?></td>
            <td style="display:flex;gap:6px">
              <?php if ($j['status'] === 'active'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/jobs/' . $j['reference'])) ?>" target="_blank" rel="noopener">View</a>
                <form method="post" action="<?= e(url('/admin/jobs/' . $j['id'] . '/remove')) ?>">
                  <?= \FixListed\Core\Csrf::field() ?>
                  <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
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
