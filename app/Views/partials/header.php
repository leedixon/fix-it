<?php
/**
 * Site chrome.
 *
 * The county selector is a plain GET form with a submit button, so it works
 * without JavaScript and so the chosen county lands in the URL — which is what
 * makes a filtered directory linkable and shareable.
 *
 * @var array  $market
 * @var array  $counties
 * @var string $path
 * @var bool   $showDemo
 * @var array|null $me
 */
require_once __DIR__ . '/icons.php';

$county  = $_GET['county'] ?? '';
$navItem = static function (string $href, string $label, string $current): string {
    $target = rtrim(url($href), '/') ?: '/';
    $on     = $current === $href || ($href !== '/' && str_starts_with($current, $href));
    return '<a href="' . e($target) . '"' . ($on ? ' class="on" aria-current="page"' : '') . '>' . e($label) . '</a>';
};
?>
<header class="chrome">
  <div class="wrap chrome-in">
    <a class="logo" href="<?= e(url('/')) ?>"><b>Fix</b> <i>Listed</i></a>

    <nav class="nav" aria-label="Primary">
      <?= $navItem('/pros', 'Find a pro', $path) ?>
      <?= $navItem('/jobs', 'Jobs board', $path) ?>
      <?= $navItem('/for-pros', 'For tradespeople', $path) ?>
      <?= $navItem('/pricing', 'Pricing', $path) ?>
    </nav>

    <div class="chrome-r">
      <?php if ($counties !== []): ?>
      <form class="mkt" method="get" action="<?= e(url('/pros')) ?>">
        <?= icon('pin', 15) ?>
        <label class="tiny" for="county-select" style="position:absolute;left:-9999px">County</label>
        <select id="county-select" name="county" onchange="this.form.submit()">
          <option value="">All counties</option>
          <?php foreach ($counties as $c): ?>
            <option value="<?= e($c['slug']) ?>"<?= $county === $c['slug'] ? ' selected' : '' ?>><?= e($c['short_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm btn-ghost" type="submit">Go</button></noscript>
      </form>
      <?php endif; ?>
      <?php if (!empty($me)): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/my')) ?>">Your account</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/sign-in')) ?>">Sign in</a>
      <?php endif; ?>
      <a class="btn btn-primary btn-sm" href="<?= e(url('/pricing')) ?>">Post a job — $10</a>
    </div>
  </div>
</header>

<?php if ($showDemo): ?>
<div class="demobar">
  <div class="wrap demobar-in">
    <?= icon('alert', 15) ?>
    <span><b>Sample listings.</b> The tradespeople and jobs shown here are examples, used to build
    and test the site. They are not real businesses. Anything marked <b>Sample</b> is invented.</span>
  </div>
</div>
<?php endif; ?>
