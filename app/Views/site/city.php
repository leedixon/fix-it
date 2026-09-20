<?php
/**
 * A city landing page — the pages that earn search traffic.
 *
 * @var array $city
 * @var array $pros
 * @var int   $proCount
 * @var array $cityJobs
 * @var array $tradeTiles
 * @var array $otherCities
 * @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a>
    <span class="sep">/</span>
    <a href="<?= e(url_q('/pros', ['county' => $city['county_slug']])) ?>"><?= e($city['county']) ?></a>
    <span class="sep">/</span>
    <span><?= e($city['name']) ?></span>
  </nav>
</div>

<section class="sect-tight">
  <div class="wrap">
    <div class="eyebrow eyebrow-brass"><?= e($city['county']) ?> · <?= e($market['name']) ?></div>
    <h1 style="font-size:clamp(30px,4.6vw,48px);margin-top:12px">
      Handymen and trades in <?= e($city['name']) ?>
    </h1>
    <p class="muted" style="margin-top:14px;max-width:60ch;font-size:17px">
      <?= $proCount > 0
        ? e($proCount) . ' tradespeople cover ' . e($city['name']) . ' and the rest of ' . e($city['county']) . '.'
        : 'The directory is open for tradespeople covering ' . e($city['name']) . '.' ?>
      Post what needs doing once, for a flat fee, and they come to you.
    </p>

    <div class="hero-cta" style="margin-top:24px">
      <a class="btn btn-primary btn-lg" href="<?= e(url('/pricing')) ?>">Post a job</a>
      <a class="btn btn-ghost btn-lg" href="<?= e(url_q('/pros', ['county' => $city['county_slug']])) ?>">
        Browse <?= e($city['county']) ?>
      </a>
    </div>
  </div>
</section>

<hr class="hr">

<section class="sect-tight">
  <div class="wrap">
    <div class="head">
      <div><h2 style="font-size:28px">Covering <?= e($city['name']) ?></h2></div>
    </div>

    <?php if ($pros === []): ?>
      <?php
      $emptyTitle   = 'No listings for ' . $city['name'] . ' yet';
      $emptyBody    = 'Nobody covering ' . $city['county'] . ' has listed with us yet. If you work in '
                    . $city['name'] . ', a profile is free and you would be the first one here.';
      $emptyCta     = 'List your business — free';
      $emptyCtaHref = url('/for-pros');
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

<?php if ($cityJobs !== []): ?>
<section class="sect-tight">
  <div class="wrap">
    <div class="head">
      <div><h2 style="font-size:28px">Open jobs in <?= e($city['name']) ?></h2></div>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('/jobs')) ?>">All jobs <?= icon('arrow', 14) ?></a>
    </div>
    <div class="grid" style="gap:12px">
      <?php foreach ($cityJobs as $job): ?>
        <?php require __DIR__ . '/../partials/job_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sect-tight">
  <div class="wrap">
    <div class="eyebrow" style="margin-bottom:12px">Trades in <?= e($city['name']) ?></div>
    <div class="chipset">
      <?php foreach ($tradeTiles as $t): ?>
        <a class="chip" href="<?= e(url_q('/pros', ['trade' => $t['slug'], 'county' => $city['county_slug']])) ?>">
          <?= e($t['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (count($otherCities) > 1): ?>
      <div class="eyebrow" style="margin:28px 0 12px">Nearby towns</div>
      <div class="chipset">
        <?php foreach ($otherCities as $c): ?>
          <?php if ($c['slug'] === $city['slug']) { continue; } ?>
          <a class="chip" href="<?= e(url('/in/' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
