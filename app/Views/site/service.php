<?php
/**
 * A trade page — /services/plumbing.
 *
 * Written to be worth reading before the directory has anybody in it. The
 * licensing block and the what-to-ask line are the parts that hold their
 * value on day one, so they come before the listings rather than after them.
 *
 * @var array      $trade
 * @var array|null $copy        TradeCopy::for(), or null for a trade nobody has written up
 * @var array      $licence     LicenceRepository::forTrade()
 * @var array      $pros
 * @var int        $proCount
 * @var array      $cities
 * @var array      $otherTrades
 * @var int        $fee
 * @var array      $market
 * @var array      $crumbs
 */
require_once __DIR__ . '/../partials/icons.php';

$name  = (string) $trade['name'];
$lower = strtolower($name);
?>

<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap">
    <div class="eyebrow eyebrow-brass"><?= icon((string) $trade['icon'], 14) ?> <?= e($market['name']) ?></div>
    <h1 style="font-size:clamp(30px,4.6vw,48px);margin-top:12px">
      <?= e($name) ?> in <?= e($market['name']) ?>
    </h1>

    <?php if ($copy !== null): ?>
      <p class="muted" style="margin-top:14px;max-width:62ch;font-size:17px"><?= e($copy['intro']) ?></p>
    <?php endif; ?>

    <p class="muted" style="margin-top:12px;max-width:62ch;font-size:17px">
      <?php if ($proCount > 0): ?>
        <?= e($proCount) ?> <?= e($proCount === 1 ? 'business' : 'businesses') ?> covering
        <?= e($market['name']) ?> <?= e($proCount === 1 ? 'is' : 'are') ?> listed for this trade.
      <?php else: ?>
        No <?= e($lower) ?> businesses have listed with us yet — this part of the directory is
        still filling up.
      <?php endif; ?>
      Posting a job costs a flat <?= e(money($fee)) ?>, quotes come back free, and Fix Listed
      takes no cut of the work.
    </p>

    <div class="hero-cta" style="margin-top:24px">
      <a class="btn btn-primary btn-lg" href="<?= e(url_q('/post-a-job', ['trade' => $trade['slug']])) ?>">
        Post a <?= e($lower) ?> job
      </a>
      <?php if ($proCount > 0): ?>
        <a class="btn btn-ghost btn-lg" href="<?= e(url_q('/pros', ['trade' => $trade['slug']])) ?>">
          Browse all <?= e($proCount) ?>
        </a>
      <?php else: ?>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/list-your-business')) ?>">
          List your business — free
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<hr class="hr">

<?php if ($copy !== null): ?>
<section class="sect-tight">
  <div class="wrap">
    <div class="grid g2" style="align-items:start">
      <div>
        <h2 style="font-size:26px">What people call a <?= e($lower) ?> for</h2>
        <ul class="muted" style="margin-top:14px;line-height:1.9;padding-left:18px">
          <?php foreach ($copy['jobs'] as $job): ?>
            <li><?= e($job) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card" style="padding:22px">
        <h2 style="font-size:19px"><?= icon('shield', 17) ?> Before you hire</h2>
        <p class="muted small" style="margin-top:10px;line-height:1.7"><?= e($copy['ask']) ?></p>

        <?php
        /*
         * Licensing, straight from the table an administrator maintains.
         *
         * known === false means nobody has entered guidance for this state
         * yet, and the honest thing is to say nothing rather than reassure.
         */
        ?>
        <?php if (($licence['known'] ?? false) === true): ?>
          <div style="margin-top:16px;border-top:1px solid var(--line);padding-top:14px">
            <div class="small" style="font-weight:600;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              Licensing in <?= e($market['state'] ?? 'IL') ?>
              <?php if ((int) $licence['licensed'] === 1): ?>
                <span class="badge b-verified">State licensed</span>
              <?php else: ?>
                <span class="badge b-flat">Not state licensed</span>
              <?php endif; ?>
            </div>
            <?php if (!empty($licence['authority'])): ?>
              <p class="tiny muted" style="margin-top:6px"><?= e($licence['authority']) ?><?php
                if (!empty($licence['number_format'])): ?> · numbers look like
                <span class="mono"><?= e($licence['number_format']) ?></span><?php endif; ?></p>
            <?php endif; ?>
            <p class="tiny muted" style="margin-top:8px;line-height:1.6"><?= e($licence['guidance']) ?></p>
            <?php if (!empty($licence['lookup_url'])): ?>
              <p style="margin-top:10px">
                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener noreferrer"
                   href="<?= e($licence['lookup_url']) ?>">Check the register <?= icon('arrow', 13) ?></a>
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<hr class="hr">
<?php endif; ?>

<section class="sect-tight">
  <div class="wrap">
    <div class="head">
      <div><h2 style="font-size:28px"><?= e($name) ?> businesses</h2></div>
      <?php if ($proCount > count($pros)): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url_q('/pros', ['trade' => $trade['slug']])) ?>">
          All <?= e($proCount) ?> <?= icon('arrow', 14) ?>
        </a>
      <?php endif; ?>
    </div>

    <?php if ($pros === []): ?>
      <?php
      $emptyTitle   = 'Launching soon in ' . $market['name'];
      $emptyBody    = 'No ' . $lower . ' businesses have listed here yet. If this is your trade, '
                    . 'a profile is free, quoting is free, and you would be the first one on this page.';
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

<section class="sect-tight">
  <div class="wrap">
    <?php if ($cities !== []): ?>
      <div class="eyebrow" style="margin-bottom:12px"><?= e($name) ?> by town</div>
      <div class="chipset">
        <?php foreach ($cities as $c): ?>
          <a class="chip" href="<?= e(city_url($c)) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="eyebrow" style="margin:28px 0 12px">Other trades</div>
    <div class="chipset">
      <?php foreach ($otherTrades as $t): ?>
        <?php if ($t['slug'] === $trade['slug']) { continue; } ?>
        <a class="chip" href="<?= e(service_url((string) $t['slug'])) ?>"><?= e($t['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
