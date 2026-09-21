<?php
/**
 * @var array      $pros
 * @var array|null $county
 * @var array|null $trade
 * @var array      $trades
 * @var array      $counties
 * @var array      $market
 */
require_once __DIR__ . '/../partials/icons.php';

$where = $county['name'] ?? $market['name'];
$what  = $trade['name'] ?? 'Tradespeople';
$count = count($pros);
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a>
    <span class="sep">/</span>
    <?php if ($trade !== null || $county !== null): ?>
      <a href="<?= e(url('/pros')) ?>">Tradespeople</a>
      <span class="sep">/</span>
      <span><?= e($trade['name'] ?? $county['short_name']) ?></span>
    <?php else: ?>
      <span>Tradespeople</span>
    <?php endif; ?>
  </nav>
</div>

<section class="sect-tight">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">Directory</div>
        <h2><?= e($what) ?> in <?= e($where) ?></h2>
        <p>Licence and insurance are checked before a profile goes live. Paid placement is
           labelled wherever it appears.</p>
      </div>
    </div>

    <form class="filters" method="get" action="<?= e(url('/pros')) ?>">
      <label class="tiny muted" for="f-trade">Trade</label>
      <select id="f-trade" name="trade">
        <option value="">All trades</option>
        <?php foreach ($trades as $t): ?>
          <option value="<?= e($t['slug']) ?>"<?= ($trade['slug'] ?? '') === $t['slug'] ? ' selected' : '' ?>>
            <?= e($t['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="tiny muted" for="f-county">County</label>
      <select id="f-county" name="county">
        <option value="">All of <?= e($market['name']) ?></option>
        <?php foreach ($counties as $c): ?>
          <option value="<?= e($c['slug']) ?>"<?= ($county['slug'] ?? '') === $c['slug'] ? ' selected' : '' ?>>
            <?= e($c['short_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button class="btn btn-dark btn-sm" type="submit">Apply</button>
      <?php if ($trade !== null || $county !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/pros')) ?>">Clear</a>
      <?php endif; ?>
    </form>

    <div class="resbar">
      <div class="n">
        <?php if ($count === 0): ?>
          No matches
        <?php else: ?>
          <b><?= $count ?></b> <?= $count === 1 ? 'tradesperson' : 'tradespeople' ?>
          <?= $trade !== null ? 'in ' . e(strtolower($trade['name'])) : '' ?>
          <?= $county !== null ? 'covering ' . e($county['short_name']) : '' ?>
        <?php endif; ?>
      </div>
      <!-- Names both paid tiers. "Featured listings" alone left Promoted
           cards looking like they earned their position. -->
      <div class="tiny muted">Ranked by rating, then reviews.
        <span class="badge b-featured">Featured</span> and
        <span class="badge b-promoted">Promoted</span> listings are paid placement.</div>
    </div>

    <?php if ($pros === []): ?>
      <?php
      $emptyTitle   = 'Nobody matches that yet';
      $emptyBody    = 'Try a wider county or a different trade. If you run this trade in '
                    . ($county['short_name'] ?? $market['name'])
                    . ', a profile is free and you would be the first one listed.';
      $emptyCta     = 'List your business — free';
      $emptyCtaHref = url('/list-your-business');
      require __DIR__ . '/../partials/empty.php';
      ?>
    <?php else: ?>
      <div class="grid g3">
        <?php foreach ($pros as $pro): ?>
          <?php require __DIR__ . '/../partials/pro_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="sect-tight dark">
  <div class="wrap" style="display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <div>
      <h2 style="font-size:28px">Cannot find the right person?</h2>
      <p class="muted" style="margin-top:8px;max-width:52ch">
        Post the job instead. It goes to every tradesperson covering your county, and they come
        to you.
      </p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('/post-a-job')) ?>">Post a job</a>
  </div>
</section>
