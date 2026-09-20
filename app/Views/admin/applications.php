<?php
/** @var array $applications */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-h">
  <div>
    <h1>Applications</h1>
    <p>Nothing here is visible on the public site until you approve it.</p>
  </div>
</div>

<div class="panel">
  <?php if ($applications === []): ?>
    <div class="adm-empty">
      <p><strong>The queue is clear.</strong></p>
      <p style="margin-top:8px">New applications from <a href="<?= e(url('/list-your-business')) ?>">the listing form</a>
         appear here, and you get an email when one arrives.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead>
          <tr><th>Business</th><th>Contact</th><th>Base</th><th>Licence</th><th>Insurance</th><th>Applied</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($applications as $a): ?>
          <tr>
            <td class="wrap-cell">
              <strong><?= e($a['business_name'] ?: $a['first_name'] . ' ' . $a['last_name']) ?></strong><br>
              <span class="tiny muted"><?= e($a['headline']) ?></span>
            </td>
            <td class="tiny">
              <?= e($a['email']) ?><br>
              <span class="muted"><?= e($a['phone']) ?></span>
            </td>
            <td><?= e($a['home_county'] ?? '—') ?></td>
            <td class="mono tiny"><?= e(trim(($a['license_state'] ?? '') . ' ' . ($a['license_number'] ?? '')) ?: '—') ?></td>
            <td class="tiny"><?= e($a['insurance_carrier'] ?: '—') ?></td>
            <td class="tiny muted"><?= e(ago($a['created_at'])) ?></td>
            <td><a class="btn btn-dark btn-sm" href="<?= e(url('/admin/applications/' . $a['id'])) ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
