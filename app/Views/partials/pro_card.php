<?php
/**
 * One tradesperson in the directory grid.
 *
 * @var array $pro
 */
require_once __DIR__ . '/icons.php';

$isDemo    = !empty($pro['is_demo']);
$isFeature = !empty($pro['is_ad']) && ($pro['ad_plan'] ?? '') === 'spotlight';
$rating    = (float) ($pro['rating_avg'] ?? 0);
$reviews   = (int) ($pro['rating_count'] ?? 0);
$verified  = !empty($pro['license_verified_at']) && !empty($pro['insurance_verified_at']);
$name      = (string) ($pro['display_name'] ?? $pro['business_name']);
?>
<article class="pro<?= $isFeature ? ' featured' : '' ?><?= $isDemo ? ' sample' : '' ?>">
  <div class="pro-top">
    <div class="av" style="background:<?= e(avatar_tint($name)) ?>"><?= e(initials($name)) ?></div>
    <div style="min-width:0">
      <div class="pro-name">
        <?= e($name) ?>
        <?php if ($isDemo): ?><span class="badge b-sample">Sample</span><?php endif; ?>
        <?php if ($isFeature): ?><span class="badge b-featured">Featured</span><?php endif; ?>
        <?php if ($verified): ?><span class="badge b-verified">Verified</span><?php endif; ?>
      </div>
      <div class="pro-trade">
        <?= e($pro['headline'] ?? '') ?>
        <?php if (!empty($pro['years_experience'])): ?> · <?= (int) $pro['years_experience'] ?> yrs<?php endif; ?>
      </div>
    </div>
    <?php if (!empty($pro['hourly_rate_cents'])): ?>
      <div class="rate">
        <div class="v"><?= e(money((int) $pro['hourly_rate_cents'])) ?></div>
        <div class="u">per hr</div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($reviews > 0): ?>
    <div class="stars">
      <span class="s" aria-hidden="true"><?= e(stars($rating)) ?></span>
      <b class="mono"><?= e(number_format($rating, 1)) ?></b>
      <span class="muted tiny"><?= $reviews ?> review<?= $reviews === 1 ? '' : 's' ?></span>
    </div>
  <?php else: ?>
    <div class="stars muted tiny">No reviews yet</div>
  <?php endif; ?>

  <?php if (!empty($pro['home_county'])): ?>
    <div class="tags">
      <span class="tag"><?= e($pro['home_city'] ?? '') ?><?= !empty($pro['home_city']) ? ', ' : '' ?><?= e($pro['home_county']) ?></span>
    </div>
  <?php endif; ?>

  <div class="pro-foot">
    <a class="btn btn-dark btn-sm" href="<?= e(url('/pros/' . $pro['slug'])) ?>">View profile</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/pricing')) ?>">Request a quote</a>
    <?php if (!empty($pro['response_minutes'])): ?>
      <span class="resp">Replies in <?= e(response_time((int) $pro['response_minutes'])) ?></span>
    <?php endif; ?>
  </div>
</article>
