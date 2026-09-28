<?php
/**
 * One lesson.
 *
 * The video slot sits above the words and disappears entirely when there is
 * no video, which is the point of building it this way: the page is complete
 * and useful today, and gains a video the day one is recorded without
 * anything being rebuilt.
 *
 * @var array $lesson @var array $siblings @var array $crumbs
 */
require_once __DIR__ . '/../partials/icons.php';
?>

<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap lesson-wrap">
    <article class="lesson">
      <div class="eyebrow eyebrow-brass">
        <?= icon('clock', 13) ?> <?= (int) $lesson['minutes'] ?> min read
      </div>
      <h1 style="font-size:clamp(27px,4.2vw,40px);margin-top:12px"><?= e($lesson['title']) ?></h1>
      <p class="muted" style="margin-top:12px;font-size:17px;max-width:60ch"><?= e($lesson['summary']) ?></p>

      <?php if ($lesson['video'] !== ''): ?>
        <div class="lesson-video">
          <iframe src="<?= e($lesson['video']) ?>" title="<?= e($lesson['title']) ?>"
                  loading="lazy" allowfullscreen
                  referrerpolicy="no-referrer"
                  sandbox="allow-scripts allow-same-origin allow-presentation"></iframe>
        </div>
      <?php endif; ?>

      <div class="lesson-body">
        <?php $body = $lesson['body']; require __DIR__ . '/../partials/lesson_body.php'; ?>
      </div>

      <div class="lesson-foot">
        <p class="tiny muted">
          Something here wrong or out of date? <a href="<?= e(url('/contact')) ?>">Tell us</a> —
          we would rather fix it than have somebody follow it.
        </p>
      </div>
    </article>

    <aside class="lesson-side">
      <div class="panel">
        <div class="panel-h"><h3>More in this track</h3></div>
        <nav class="lesson-nav">
          <?php foreach ($siblings as $slug => $s): ?>
            <a href="<?= e(url('/academy/' . $slug)) ?>"
               <?= $slug === $lesson['slug'] ? 'class="on" aria-current="page"' : '' ?>>
              <?= e($s['nav']) ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
      <p class="tiny muted" style="margin-top:14px">
        <a href="<?= e(url('/academy')) ?>">← All of the academy</a>
      </p>
    </aside>
  </div>
</section>
