<?php
/**
 * One handbook lesson. Same renderer as the public academy — one set of
 * blocks, one look, no second styling to keep in step.
 *
 * @var array $lesson @var array $siblings
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-head">
  <div>
    <p class="tiny muted"><a href="<?= e(url('/admin/handbook')) ?>">← Handbook</a></p>
    <h1 style="margin-top:6px"><?= e($lesson['title']) ?></h1>
    <p class="muted small"><?= e($lesson['summary']) ?></p>
  </div>
</div>

<div class="lesson-wrap">
  <article class="lesson">
    <?php if ($lesson['video'] !== ''): ?>
      <div class="lesson-video" style="margin-top:0">
        <iframe src="<?= e($lesson['video']) ?>" title="<?= e($lesson['title']) ?>"
                loading="lazy" allowfullscreen referrerpolicy="no-referrer"
                sandbox="allow-scripts allow-same-origin allow-presentation"></iframe>
      </div>
    <?php endif; ?>
    <div class="lesson-body">
      <?php $body = $lesson['body']; require __DIR__ . '/../partials/lesson_body.php'; ?>
    </div>
  </article>

  <aside class="lesson-side">
    <div class="panel">
      <div class="panel-h"><h3>Handbook</h3></div>
      <nav class="lesson-nav">
        <?php foreach ($siblings as $slug => $s): ?>
          <a href="<?= e(url('/admin/handbook/' . $slug)) ?>"
             <?= $slug === $lesson['slug'] ? 'class="on" aria-current="page"' : '' ?>><?= e($s['nav']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
</div>
