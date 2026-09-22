<?php
/**
 * Admin chrome.
 *
 * @var string $content
 * @var string $title
 * @var array  $market
 * @var string $path
 * @var array|null $me
 * @var bool   $isSuper
 * @var array  $flashes
 * @var int    $pending
 * @var callable $can
 */
require_once __DIR__ . '/../partials/icons.php';

$nav = static function (string $href, string $label, string $icon, string $current, int $badge = 0): string {
    $on = $current === $href || ($href !== '/admin' && str_starts_with($current, $href));
    return '<a href="' . e(url($href)) . '"' . ($on ? ' class="on" aria-current="page"' : '') . '>'
        . icon($icon, 15) . '<span>' . e($label) . '</span>'
        . ($badge > 0 ? '<span class="count">' . $badge . '</span>' : '')
        . '</a>';
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Marcellus&family=Hanken+Grotesk:ital,wght@0,300..800;1,300..700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
</head>
<body>
<div class="adm">
  <?php
  /*
   * The drawer, opened by a checkbox and no JavaScript.
   *
   * The input is clipped rather than display:none, which keeps it in the tab
   * order and operable with Space — so the menu is reachable from a keyboard
   * and a screen reader announces it as expanded or collapsed, for free.
   * .adm-scrim is a second label for the same checkbox, so tapping outside
   * the drawer closes it the way it does in every other app.
   *
   * It replaces a horizontally scrolling row of eleven items, which fitted
   * but which nobody would think to scroll.
   */
  ?>
  <input type="checkbox" id="adm-menu" class="adm-menu-state" aria-label="Menu">
  <label class="adm-menu-btn" for="adm-menu">
    <span class="adm-menu-ico"><?= icon('menu', 20) ?></span>
    <span class="logo"><b>Fix</b> <i style="font-style:normal;color:var(--brass-3)">Listed</i></span>
  </label>
  <label class="adm-scrim" for="adm-menu" aria-hidden="true"></label>

  <aside class="adm-side">
    <a class="logo" href="<?= e(url('/admin')) ?>"><b>Fix</b> <i style="font-style:normal;color:var(--brass-3)">Listed</i></a>

    <!--
      Every item asks the same question the controller behind it asks, so a
      link can never appear to somebody the screen would then 404. Hidden
      rather than greyed: a door you cannot open is one more thing to email
      the owner about.
    -->
    <nav class="adm-nav" aria-label="Admin">
      <?= $nav('/admin', 'Dashboard', 'chart', $path) ?>
      <?php if ($can('applications.review')): ?>
        <?= $nav('/admin/applications', 'Applications', 'inbox', $path, $pending) ?>
      <?php endif; ?>
      <?php if ($can('listings.moderate')): ?>
        <?= $nav('/admin/pros', 'Tradespeople', 'tools', $path) ?>
      <?php endif; ?>
      <?php if ($can('jobs.moderate')): ?>
        <?= $nav('/admin/jobs', 'Jobs', 'clipboard', $path) ?>
      <?php endif; ?>
      <?php if ($can('people.view')): ?>
        <?= $nav('/admin/users', 'People', 'user', $path) ?>
      <?php endif; ?>
      <?php if ($can('placements.view')): ?>
        <?= $nav('/admin/advertising', 'Advertising', 'megaphone', $path) ?>
      <?php endif; ?>
      <?php if ($can('licensing.manage')): ?>
        <?= $nav('/admin/licensing', 'Licensing', 'shield', $path) ?>
      <?php endif; ?>
      <?php if ($can('team.manage')): ?>
        <?= $nav('/admin/team', 'Team', 'users', $path) ?>
      <?php endif; ?>
      <?php if ($can('markets.manage')): ?>
        <?= $nav('/admin/markets', 'Markets', 'pin', $path) ?>
      <?php endif; ?>
      <?php if ($can('maintenance.manage')): ?>
        <?= $nav('/admin/maintenance', 'Maintenance', 'lock', $path) ?>
      <?php endif; ?>
      <?php if ($can('activity.view')): ?>
        <?= $nav('/admin/activity', 'Activity log', 'clock', $path) ?>
      <?php endif; ?>
    </nav>

    <div class="who">
      <b><?= e(trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?: ($me['email'] ?? '')) ?></b>
      <span><?= e(\FixListed\Core\Auth::roleLabel((string) ($me['role'] ?? ''))) ?></span>
      <form method="post" action="<?= e(url('/admin/logout')) ?>" style="margin-top:10px">
        <?= \FixListed\Core\Csrf::field() ?>
        <button class="btn btn-onink btn-sm btn-block" type="submit">Sign out</button>
      </form>
      <p style="margin-top:12px"><a href="<?= e(url('/')) ?>" style="font-size:12px">← View the site</a></p>
    </div>
  </aside>

  <main class="adm-main">
    <?php if (\FixListed\Core\Maintenance::isOn()): ?>
      <!--
        Standing, on every admin page, not just the maintenance screen. The
        way this goes wrong is not turning it on — it is forgetting it is on,
        because an administrator sees the site working perfectly the whole
        time. This is the only thing that tells you the public does not.
      -->
      <div class="maint-bar">
        <?= icon('alert', 15) ?>
        <span><strong>The site is down for visitors</strong> — you are seeing it because you are
          signed in as an administrator<?php
            $for = \FixListed\Core\Maintenance::runningFor();
            echo $for !== '' ? ', for ' . e($for) : '';
          ?>.</span>
        <?php if ($isSuper): ?>
          <a href="<?= e(url('/admin/maintenance')) ?>">Put it back up →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= $f['type'] === 'ok' ? 'ok' : 'bad' ?>">
        <?= icon($f['type'] === 'ok' ? 'check' : 'alert', 17) ?>
        <span><?= e($f['message']) ?></span>
      </div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= e(asset('assets/js/password.js')) ?>" defer></script>
</body>
</html>
