<?php
/** @var array $entries */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-h">
  <div>
    <h1>Activity log</h1>
    <p>Every administrative change, appended and never edited. This is how you answer
       "who took that listing down, and when" three months from now.</p>
  </div>
</div>

<div class="panel">
  <?php if ($entries === []): ?>
    <div class="adm-empty">Nothing recorded yet.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Subject</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($entries as $r): ?>
          <tr>
            <td class="tiny muted" title="<?= e($r['created_at']) ?>"><?= e(ago($r['created_at'])) ?></td>
            <td class="tiny"><?= e($r['actor_email'] ?? 'system') ?></td>
            <td class="mono tiny"><?= e($r['action']) ?></td>
            <td class="tiny muted"><?= e($r['subject_type']) ?><?= $r['subject_id'] ? ' #' . (int) $r['subject_id'] : '' ?></td>
            <td class="tiny muted wrap-cell"><?= e($r['meta'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
