<?php
/**
 * One job posting.
 *
 * The homeowner's name, address and phone number are deliberately absent. A
 * job board that publishes them is a scraping target, and the privacy promise
 * on the posting form has to be true on the page it produces.
 *
 * @var array $job
 * @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';

$isDemo = !empty($job['is_demo']);
$quotes = (int) $job['quote_count'];
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a>
    <span class="sep">/</span>
    <a href="<?= e(url('/jobs')) ?>">Jobs board</a>
    <span class="sep">/</span>
    <span class="mono"><?= e($job['reference']) ?></span>
  </nav>
</div>

<section class="sect-tight">
  <div class="wrap wiz">
    <div>
      <?php if ($isDemo): ?>
        <div class="flash flash-bad">
          <?= icon('alert', 17) ?>
          <span><b>This is a sample job.</b> Nobody posted it — it is example data used while the
          site is being built.</span>
        </div>
      <?php endif; ?>

      <div class="eyebrow">
        <?= e($job['trade_name']) ?> · posted <?= e(ago($job['published_at'])) ?>
      </div>
      <h1 style="font-size:clamp(26px,3.6vw,38px);margin-top:10px"><?= e($job['title']) ?></h1>

      <div class="tags" style="margin-top:16px">
        <span class="badge b-flat mono"><?= e($job['reference']) ?></span>
        <span class="badge b-live"><?= e(urgency_label((string) $job['urgency'])) ?></span>
        <?php if ($isDemo): ?><span class="badge b-sample">Sample</span><?php endif; ?>
      </div>

      <div class="bio" style="margin-top:26px">
        <?php foreach (preg_split('/\n\s*\n/', (string) $job['description']) ?: [] as $para): ?>
          <p><?= e(trim($para)) ?></p>
        <?php endforeach; ?>
      </div>

      <div class="card" style="padding:20px;margin-top:30px">
        <h2 style="font-size:19px">What happens when you quote</h2>
        <p class="muted small" style="margin-top:8px">
          Your quote goes to the homeowner with your profile attached. If they pick you, you agree
          the price with them directly and they pay you — not us. Fix Listed takes no cut of the
          job, now or later.
        </p>
      </div>
    </div>

    <aside class="summary">
      <div class="sum-row"><span class="k">Trade</span><span><?= e($job['trade_name']) ?></span></div>
      <div class="sum-row"><span class="k">Area</span><span><?= e($job['zip']) ?></span></div>
      <div class="sum-row"><span class="k">Timing</span><span><?= e(urgency_label((string) $job['urgency'])) ?></span></div>
      <div class="sum-row"><span class="k">Quotes so far</span><span class="mono"><?= $quotes ?></span></div>
      <div class="sum-row total">
        <span>Budget</span>
        <span><?= e(budget(
            $job['budget_min_cents'] !== null ? (int) $job['budget_min_cents'] : null,
            $job['budget_max_cents'] !== null ? (int) $job['budget_max_cents'] : null,
        )) ?></span>
      </div>

      <div style="padding:18px 20px">
        <?php if (!empty($me)): ?>
          <a class="btn btn-primary btn-block" href="<?= e(url('/my/quote/' . $job['reference'])) ?>">Quote this job</a>
          <p class="tiny muted" style="margin-top:10px;text-align:center">
            Free to send. You keep the whole job.
          </p>
        <?php else: ?>
          <a class="btn btn-primary btn-block" href="<?= e(url('/sign-in')) ?>">Sign in to quote</a>
          <p class="tiny muted" style="margin-top:10px;text-align:center">
            Free. <a href="<?= e(url('/list-your-business')) ?>">Not listed yet?</a>
          </p>
        <?php endif; ?>
      </div>

      <div class="seal">
        <?= icon('lock', 16) ?>
        <span>The homeowner's name, address and phone number are not published. They are shared
        only with the tradesperson they choose.</span>
      </div>
    </aside>
  </div>
</section>
