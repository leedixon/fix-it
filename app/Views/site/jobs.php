<?php
/**
 * @var array      $jobs
 * @var array|null $trade
 * @var array      $trades
 * @var int        $openCount
 * @var array      $market
 */
require_once __DIR__ . '/../partials/icons.php';
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a>
    <span class="sep">/</span>
    <?php if ($trade !== null): ?>
      <a href="<?= e(url('/jobs')) ?>">Jobs board</a>
      <span class="sep">/</span><span><?= e($trade['name']) ?></span>
    <?php else: ?>
      <span>Jobs board</span>
    <?php endif; ?>
  </nav>
</div>

<section class="sect-tight">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">Open jobs</div>
        <h2><?= e($trade['name'] ?? 'Work') ?> available in <?= e($market['name']) ?></h2>
        <p>Free to quote, and you keep the whole job — Fix Listed takes nothing from what the
           homeowner pays you.</p>
      </div>
      <a class="btn btn-primary" href="<?= e(url('/for-pros')) ?>">Get these by email</a>
    </div>

    <form class="filters" method="get" action="<?= e(url('/jobs')) ?>">
      <label class="tiny muted" for="j-trade">Trade</label>
      <select id="j-trade" name="trade">
        <option value="">All trades</option>
        <?php foreach ($trades as $t): ?>
          <option value="<?= e($t['slug']) ?>"<?= ($trade['slug'] ?? '') === $t['slug'] ? ' selected' : '' ?>>
            <?= e($t['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-dark btn-sm" type="submit">Apply</button>
      <?php if ($trade !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/jobs')) ?>">Clear</a>
      <?php endif; ?>
    </form>

    <div class="resbar">
      <div class="n"><b><?= count($jobs) ?></b> job<?= count($jobs) === 1 ? '' : 's' ?> shown
        <?= $trade !== null && $openCount !== count($jobs) ? ' · ' . $openCount . ' open in total' : '' ?></div>
      <div class="tiny muted">Newest first</div>
    </div>

    <?php if ($jobs === []): ?>
      <?php
      $emptyTitle   = $trade !== null ? 'No ' . strtolower($trade['name']) . ' jobs right now' : 'No jobs open right now';
      $emptyBody    = $trade !== null
          ? 'Nothing in this trade at the moment. The board moves quickly — set up a profile and we will email you when one lands.'
          : 'The board is empty at the moment. Homeowners\' jobs appear here the minute they are posted.';
      $emptyCta     = 'List your business — free';
      $emptyCtaHref = url('/for-pros');
      require __DIR__ . '/../partials/empty.php';
      ?>
    <?php else: ?>
      <div class="jobs-grid">
        <?php foreach ($jobs as $job): ?>
          <?php require __DIR__ . '/../partials/job_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
