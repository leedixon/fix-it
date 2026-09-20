<?php
/**
 * One posting on the jobs board.
 *
 * @var array $job
 */
$isDemo = !empty($job['is_demo']);
$place  = trim(($job['city_name'] ?? '') . (!empty($job['county_name']) && !empty($job['city_name']) ? ', ' : '') . ($job['county_name'] ?? ''));
$quotes = (int) ($job['quote_count'] ?? 0);
?>
<article class="job">
  <div style="flex:1;min-width:220px">
    <h3>
      <a href="<?= e(url('/jobs/' . $job['reference'])) ?>"><?= e($job['title']) ?></a>
      <?php if ($isDemo): ?> <span class="badge b-sample">Sample</span><?php endif; ?>
    </h3>
    <div class="meta">
      <?php if (!empty($job['trade_name'])): ?><span class="mono"><?= e($job['trade_name']) ?></span><?php endif; ?>
      <?php if ($place !== ''): ?><span><?= e($place) ?></span><?php endif; ?>
      <span><?= e(urgency_label((string) ($job['urgency'] ?? ''))) ?></span>
      <?php if (!empty($job['published_at'])): ?><span>Posted <?= e(ago($job['published_at'])) ?></span><?php endif; ?>
      <span><?= $quotes ?> quote<?= $quotes === 1 ? '' : 's' ?></span>
    </div>
  </div>

  <div class="budget">
    <div class="v"><?= e(budget(
        isset($job['budget_min_cents']) ? (int) $job['budget_min_cents'] : null,
        isset($job['budget_max_cents']) ? (int) $job['budget_max_cents'] : null,
    )) ?></div>
    <div class="tiny muted">Homeowner budget</div>
  </div>

  <a class="btn btn-ghost btn-sm" href="<?= e(url('/jobs/' . $job['reference'])) ?>">See the job</a>
</article>
