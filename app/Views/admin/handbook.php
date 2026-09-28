<?php
/**
 * The staff handbook index.
 *
 * @var array $lessons
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-head">
  <div>
    <h1>Handbook</h1>
    <p class="muted small">
      How to run Fix Listed, and where everything is. Written against the screens as they are —
      if something here does not match what you see, tell the owner rather than guessing.
    </p>
  </div>
</div>

<div class="lesson-grid">
  <?php foreach ($lessons as $slug => $l): ?>
    <a class="lesson-card" href="<?= e(url('/admin/handbook/' . $slug)) ?>">
      <span class="lesson-card-t"><?= e($l['title']) ?></span>
      <span class="lesson-card-s"><?= e($l['summary']) ?></span>
      <span class="lesson-card-m"><?= icon('clock', 12) ?> <?= (int) $l['minutes'] ?> min</span>
    </a>
  <?php endforeach; ?>
</div>
