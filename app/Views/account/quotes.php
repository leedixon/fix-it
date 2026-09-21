<?php
/** @var array $myQuotes */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-h">
  <div>
    <h1 style="font-size:28px">Your quotes</h1>
    <p>Everything you have sent, and what happened to it.</p>
  </div>
</div>

<div class="panel">
  <?php if ($myQuotes === []): ?>
    <div class="adm-empty">
      <p><strong>You have not quoted anything yet.</strong></p>
      <p style="margin-top:8px"><a href="<?= e(url('/my')) ?>">See what is open in your counties →</a></p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Job</th><th>Trade</th><th>Your price</th><th>Status</th><th>Seen</th><th>Sent</th></tr></thead>
        <tbody>
        <?php foreach ($myQuotes as $q): ?>
          <tr>
            <td class="wrap-cell">
              <a href="<?= e(url('/jobs/' . $q['reference'])) ?>"><?= e($q['title']) ?></a><br>
              <span class="tiny muted"><?= e($q['city_name'] ?? '') ?> · <span class="mono"><?= e($q['reference']) ?></span></span>
            </td>
            <td class="tiny"><?= e($q['trade_name']) ?></td>
            <td class="num tiny"><?= e(quote_price($q)) ?></td>
            <td><span class="badge <?= $q['status'] === 'accepted' ? 'b-live' : ($q['status'] === 'declined' ? 'b-flat' : 'b-pending') ?>"><?= e($q['status']) ?></span></td>
            <td class="tiny muted"><?= $q['viewed_at'] ? e(ago($q['viewed_at'])) : 'not yet' ?></td>
            <td class="tiny muted"><?= e(ago($q['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
