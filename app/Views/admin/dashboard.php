<?php
/** @var array $counts @var array $queue @var array $recent @var array $market */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-h">
  <div>
    <h1>Dashboard</h1>
    <p><?= e($market['name']) ?> · <?= e($market['code']) ?></p>
  </div>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('/')) ?>">Open the site <?= icon('arrow', 14) ?></a>
</div>

<div class="adm-stats">
  <div class="stat">
    <div class="k">Waiting on you</div>
    <div class="v"><?= (int) $counts['applications'] ?></div>
    <div class="d<?= $counts['applications'] > 0 ? '' : ' down' ?>">
      <?= $counts['applications'] > 0 ? 'applications to review' : 'queue is clear' ?>
    </div>
  </div>
  <div class="stat">
    <div class="k">Live listings</div>
    <div class="v"><?= (int) $counts['pros_live'] ?></div>
    <div class="d"><?= (int) $counts['pros_real'] ?> real, <?= (int) $counts['pros_live'] - (int) $counts['pros_real'] ?> sample</div>
  </div>
  <div class="stat">
    <div class="k">Open jobs</div>
    <div class="v"><?= (int) $counts['jobs_open'] ?></div>
    <?php if ($counts['jobs_unpaid'] > 0): ?>
      <div class="d down"><?= (int) $counts['jobs_unpaid'] ?> never paid for</div>
    <?php endif; ?>
  </div>
  <div class="stat">
    <div class="k">Revenue, 30 days</div>
    <div class="v"><?= e(money((int) $counts['revenue_30d'])) ?></div>
    <div class="d"><?= (int) $counts['waitlist'] ?> on the waiting list</div>
  </div>
</div>

<div class="panel" style="margin-bottom:22px">
  <div class="panel-h">
    <h3>Applications waiting</h3>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/applications')) ?>">See all</a>
  </div>
  <?php if ($queue === []): ?>
    <div class="adm-empty">
      Nothing waiting. New applications land here the moment a tradesperson sends one.
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Business</th><th>Trade area</th><th>Licence</th><th>Applied</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($queue as $a): ?>
          <tr>
            <td>
              <strong><?= e($a['business_name'] ?: $a['first_name'] . ' ' . $a['last_name']) ?></strong><br>
              <span class="tiny muted"><?= e($a['email']) ?></span>
            </td>
            <td><?= e($a['home_county'] ?? '—') ?></td>
            <td class="mono tiny"><?= e(trim(($a['license_state'] ?? '') . ' ' . ($a['license_number'] ?? '')) ?: '—') ?></td>
            <td class="tiny muted"><?= e(ago($a['created_at'])) ?></td>
            <td><a class="btn btn-dark btn-sm" href="<?= e(url('/admin/applications/' . $a['id'])) ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-h">
    <h3>Recent activity</h3>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/activity')) ?>">Full log</a>
  </div>
  <?php if ($recent === []): ?>
    <div class="adm-empty">Nothing recorded yet.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td class="mono tiny"><?= e($r['action']) ?></td>
            <td class="tiny muted"><?= e($r['actor_email'] ?? 'system') ?></td>
            <td class="tiny muted"><?= e(ago($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
