<?php
/**
 * A tradesperson's profile.
 *
 * @var array $pro
 * @var array $skills
 * @var array $proCounties
 * @var array $reviews
 * @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';

$isDemo   = !empty($pro['is_demo']);
$name     = (string) ($pro['display_name'] ?? $pro['business_name']);
$rating   = (float) $pro['rating_avg'];
$reviewN  = (int) $pro['rating_count'];
$verified = !empty($pro['license_verified_at']) && !empty($pro['insurance_verified_at']);
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a>
    <span class="sep">/</span>
    <a href="<?= e(url('/pros')) ?>">Tradespeople</a>
    <span class="sep">/</span>
    <span><?= e($name) ?></span>
  </nav>
</div>

<section class="sect-tight">
  <div class="wrap profile-hd">
    <div>
      <?php if ($isDemo): ?>
        <div class="flash flash-bad">
          <?= icon('alert', 17) ?>
          <span><b>This is a sample profile.</b> <?= e($name) ?> is not a real business — it is
          example data used while the site is being built. Do not try to contact it.</span>
        </div>
      <?php endif; ?>

      <div class="pro-top" style="gap:18px;align-items:center">
        <div class="av" style="width:74px;height:74px;font-size:26px;background:<?= e(avatar_tint($name)) ?>">
          <?= e(initials($name)) ?>
        </div>
        <div style="min-width:0">
          <h1 style="font-size:clamp(28px,4vw,40px)"><?= e($name) ?></h1>
          <p class="muted" style="margin-top:6px">
            <?= e($pro['headline']) ?>
            <?php if (!empty($pro['home_city'])): ?> · <?= e($pro['home_city']) ?><?php endif; ?>
            <?php if (!empty($pro['years_experience'])): ?> · <?= (int) $pro['years_experience'] ?> years<?php endif; ?>
          </p>
        </div>
      </div>

      <div class="tags" style="margin-top:16px">
        <?php if ($isDemo): ?><span class="badge b-sample">Sample listing</span><?php endif; ?>
        <?php if ($verified): ?><span class="badge b-verified">Licence &amp; insurance verified</span><?php endif; ?>
        <?php if (!empty($pro['background_checked_at'])): ?><span class="badge b-verified">Background checked</span><?php endif; ?>
        <?php if ($reviewN > 0): ?>
          <span class="badge b-flat"><?= e(stars($rating)) ?> <?= e(number_format($rating, 1)) ?> · <?= $reviewN ?> reviews</span>
        <?php endif; ?>
      </div>

      <?php if (!empty($pro['bio'])): ?>
        <div class="bio" style="margin-top:26px">
          <?php foreach (preg_split('/\n\s*\n/', (string) $pro['bio']) ?: [] as $para): ?>
            <p><?= e(trim($para)) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($skills !== []): ?>
        <h2 style="font-size:22px;margin-top:34px">What <?= e($name) ?> does</h2>
        <div class="chipset" style="margin-top:14px">
          <?php foreach ($skills as $skill): ?>
            <span class="chip"><?= e($skill) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($proCounties !== []): ?>
        <h2 style="font-size:22px;margin-top:34px">Where they work</h2>
        <p class="muted small" style="margin-top:6px">Counties this business will travel to.</p>
        <div class="chipset" style="margin-top:14px">
          <?php foreach ($proCounties as $c): ?>
            <a class="chip" href="<?= e(url_q('/pros', ['county' => $c['slug']])) ?>"><?= e($c['short_name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($reviews !== []): ?>
        <h2 style="font-size:22px;margin-top:40px">Reviews</h2>
        <div class="grid" style="gap:14px;margin-top:16px">
          <?php foreach ($reviews as $r): ?>
            <div class="rev">
              <div class="stars"><span class="s"><?= e(stars((float) $r['rating'])) ?></span></div>
              <q><?= e($r['body']) ?></q>
              <div class="who">
                <span class="small"><?= e($r['first_name']) ?> <?= e(mb_substr((string) $r['last_name'], 0, 1)) ?>.</span>
                <?php if (!empty($r['job_value_cents'])): ?>
                  <span class="tiny muted">· job worth <?= e(money((int) $r['job_value_cents'])) ?></span>
                <?php endif; ?>
                <span class="tiny muted" style="margin-left:auto"><?= e(ago($r['created_at'])) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="summary">
      <div class="panel-h"><h3>Get a quote</h3></div>

      <?php if (!empty($pro['hourly_rate_cents'])): ?>
        <div class="kv" style="padding:12px 20px">
          <span class="k">Hourly rate</span>
          <span class="mono"><?= e(money((int) $pro['hourly_rate_cents'])) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($pro['response_minutes'])): ?>
        <div class="kv" style="padding:12px 20px">
          <span class="k">Usually replies</span>
          <span><?= e(response_time((int) $pro['response_minutes'])) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($pro['jobs_completed'])): ?>
        <div class="kv" style="padding:12px 20px">
          <span class="k">Jobs completed</span>
          <span class="mono"><?= (int) $pro['jobs_completed'] ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($pro['license_number'])): ?>
        <div class="kv" style="padding:12px 20px">
          <span class="k">Licence</span>
          <span class="mono tiny"><?= e($pro['license_state']) ?> <?= e($pro['license_number']) ?></span>
        </div>
      <?php endif; ?>

      <div style="padding:18px 20px">
        <a class="btn btn-primary btn-block" href="<?= e(url('/post-a-job')) ?>">Post your job</a>
        <p class="tiny muted" style="margin-top:10px;text-align:center">
          Describe the job once and every pro covering your county can quote it — including this one.
        </p>
      </div>

      <div class="seal">
        <?= icon('shield', 16) ?>
        <span>Your phone number and address stay private until you choose to share them with a
        tradesperson you have picked.</span>
      </div>
    </aside>
  </div>
</section>
