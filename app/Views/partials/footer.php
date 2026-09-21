<?php
/**
 * @var array $market
 * @var array $navTrades
 */
require_once __DIR__ . '/icons.php';
?>
<footer class="foot">
  <div class="wrap">
    <div class="foot-in">
      <div>
        <a class="logo" href="<?= e(url('/')) ?>" style="color:var(--on-ink)"><b>Fix</b> <i>Listed</i></a>
        <p class="muted" style="margin-top:12px;max-width:34ch">
          A flat-fee directory for <?= e($market['name'] ?? 'your area') ?>. Homeowners pay once to
          list a job. Tradespeople quote for free and keep the whole job.
        </p>
      </div>

      <div>
        <h4>Homeowners</h4>
        <ul>
          <li><a href="<?= e(url('/pros')) ?>">Find a tradesperson</a></li>
          <li><a href="<?= e(url('/pricing')) ?>">Post a job — $10</a></li>
          <li><a href="<?= e(url('/pricing')) ?>">What the fee covers</a></li>
        </ul>
      </div>

      <div>
        <h4>Tradespeople</h4>
        <ul>
          <li><a href="<?= e(url('/list-your-business')) ?>">List your business</a></li>
          <li><a href="<?= e(url('/jobs')) ?>">Open jobs</a></li>
          <li><a href="<?= e(url('/pricing')) ?>">Advertising</a></li>
          <li><a href="<?= e(url('/sign-in')) ?>">Sign in</a></li>
        </ul>
      </div>

      <div>
        <h4>Trades</h4>
        <ul>
          <?php foreach (array_slice($navTrades, 0, 6) as $t): ?>
            <li><a href="<?= e(url_q('/pros', ['trade' => $t['slug']])) ?>"><?= e($t['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="foot-bot">
      <span>© <?= date('Y') ?> Fix Listed<?= isset($market['name']) ? ' · ' . e($market['name']) : '' ?></span>
      <span style="display:flex;gap:16px;flex-wrap:wrap">
        <a href="<?= e(url('/terms')) ?>">Terms</a>
        <a href="<?= e(url('/privacy')) ?>">Privacy</a>
        <a href="<?= e(url('/contact')) ?>">Contact</a>
      </span>
    </div>
  </div>
</footer>
