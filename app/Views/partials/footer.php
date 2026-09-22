<?php
/**
 * The footer, and the site's link backbone.
 *
 * The trade and town lists at the bottom are here rather than on a "browse"
 * page for one reason: every landing page has to be reachable from every
 * other page. A page only the sitemap knows about is a page nothing links to,
 * and search engines crawl what is linked to. Less abstractly, it is how
 * somebody reading the Freeport page finds the Lena one.
 *
 * They sit in their own full-width band rather than as a fifth and sixth
 * column. The four columns above hold three or four links each; these hold
 * ten and nineteen and will hold more, and a nineteen-item column next to a
 * three-item one is a footer four screens tall on a phone.
 *
 * Both lists come from the database through the chrome — see
 * Controller::pageCities(). Turning a town's landing page on or off is one
 * column in one row, and the footer follows without being edited.
 *
 * @var array $market
 * @var array $navTrades
 * @var array $navCities
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
          <li><a href="<?= e(url('/post-a-job')) ?>">Post a job — $10</a></li>
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
        <h4>Fix Listed</h4>
        <ul>
          <li><a href="<?= e(url('/for-pros')) ?>">Why list with us</a></li>
          <li><a href="<?= e(url('/contact')) ?>">Contact</a></li>
        </ul>
      </div>
    </div>

    <?php if (!empty($navTrades)): ?>
      <div class="foot-band">
        <h4>Trades</h4>
        <ul>
          <?php foreach ($navTrades as $t): ?>
            <li><a href="<?= e(service_url((string) $t['slug'])) ?>"><?= e($t['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($navCities)): ?>
      <div class="foot-band">
        <h4>Towns we cover</h4>
        <ul>
          <?php foreach ($navCities as $c): ?>
            <li><a href="<?= e(city_url($c)) ?>"><?= e($c['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

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
