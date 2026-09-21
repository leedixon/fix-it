<?php
/**
 * One tradesperson in the directory grid.
 *
 * @var array $pro
 */
require_once __DIR__ . '/icons.php';

$isDemo    = !empty($pro['is_demo']);

/*
 * Every paid lift is labelled, not just the top tier.
 *
 * Boost was moving a listing up the page with no badge at all, which reads to
 * a visitor as an earned ranking. That is precisely the undisclosed paid
 * placement this directory's credibility rests on not doing — and the ratings
 * and verified badges beside it are only worth something if people believe
 * they were not bought.
 */
$adPlan    = !empty($pro['is_ad']) ? (string) ($pro['ad_plan'] ?? '') : '';
$isFeature = $adPlan === 'spotlight';
$isBoosted = $adPlan === 'boost';
$placement = $adPlan !== '' ? (int) ($pro['placement_id'] ?? 0) : 0;

// Noted, not written. The counter runs after the response has been sent, so
// a page full of paid cards still costs a visitor nothing to load.
if ($placement > 0) {
    \FixListed\Core\AdTracker::seen($placement, (int) $pro['id']);
}
$rating    = (float) ($pro['rating_avg'] ?? 0);
$reviews   = (int) ($pro['rating_count'] ?? 0);
$verified  = !empty($pro['license_verified_at']) && !empty($pro['insurance_verified_at']);
$name      = (string) ($pro['display_name'] ?? $pro['business_name']);
?>
<article class="pro<?= $isFeature ? ' featured' : '' ?><?= $isBoosted ? ' promoted' : '' ?><?= $isDemo ? ' sample' : '' ?>"
         <?= $placement > 0 ? 'data-placement="' . $placement . '"' : '' ?>>
  <div class="pro-top">
    <div class="av" style="background:<?= e(avatar_tint($name)) ?>"><?= e(initials($name)) ?></div>
    <div style="min-width:0">
      <div class="pro-name">
        <?= e($name) ?>
        <?php if ($isDemo): ?><span class="badge b-sample">Sample</span><?php endif; ?>
        <?php if ($isFeature): ?><span class="badge b-featured">Featured</span><?php endif; ?>
        <?php if ($isBoosted): ?><span class="badge b-promoted">Promoted</span><?php endif; ?>
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
    <a class="btn btn-dark btn-sm" href="<?= e(
        $placement > 0 ? url('/go/' . $placement) : url('/pros/' . $pro['slug'])
    ) ?>">View profile</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/post-a-job')) ?>">Request a quote</a>
    <?php if (!empty($pro['response_minutes'])): ?>
      <span class="resp">Replies in <?= e(response_time((int) $pro['response_minutes'])) ?></span>
    <?php endif; ?>
  </div>
</article>
