<?php
/**
 * One posting on the jobs board, shaped as a card for the grid.
 *
 * The description excerpt is here rather than only on the detail page because
 * a title alone does not tell a tradesperson whether a job is worth their
 * afternoon, and making them open ten pages to find out is how a board stops
 * being read.
 *
 * @var array $job
 */
$isDemo = !empty($job['is_demo']);
$place  = trim(($job['city_name'] ?? '') . (!empty($job['county_name']) && !empty($job['city_name']) ? ', ' : '') . ($job['county_name'] ?? ''));
$quotes = (int) ($job['quote_count'] ?? 0);
$href   = url('/jobs/' . $job['reference']);
?>
<article class="job">
  <div class="body">
    <h3>
      <a href="<?= e($href) ?>"><?= e($job['title']) ?></a>
      <?php if ($isDemo): ?> <span class="badge b-sample">Sample</span><?php endif; ?>
    </h3>
    <div class="meta">
      <?php if (!empty($job['trade_name'])): ?><span class="mono"><?= e($job['trade_name']) ?></span><?php endif; ?>
      <?php if ($place !== ''): ?><span><?= e($place) ?></span><?php endif; ?>
      <span><?= e(urgency_label((string) ($job['urgency'] ?? ''))) ?></span>
      <?php if (!empty($job['published_at'])): ?><span><?= e(ago($job['published_at'])) ?></span><?php endif; ?>
      <span><?= $quotes ?> quote<?= $quotes === 1 ? '' : 's' ?></span>
    </div>
    <?php if (!empty($job['description'])): ?>
      <p class="excerpt"><?= e(excerpt((string) $job['description'], 130)) ?></p>
    <?php endif; ?>
  </div>

  <div class="job-foot">
    <div class="budget">
      <div class="v"><?= e(budget(
          isset($job['budget_min_cents']) ? (int) $job['budget_min_cents'] : null,
          isset($job['budget_max_cents']) ? (int) $job['budget_max_cents'] : null,
      )) ?></div>
      <div class="tiny muted">Homeowner budget</div>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e($href) ?>">See the job</a>
  </div>
</article>
