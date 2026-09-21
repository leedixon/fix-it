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
  <aside class="adm-side">
    <a class="logo" href="<?= e(url('/admin')) ?>"><b>Fix</b> <i style="font-style:normal;color:var(--brass-3)">Listed</i></a>

    <nav class="adm-nav" aria-label="Admin">
      <?= $nav('/admin', 'Dashboard', 'chart', $path) ?>
      <?= $nav('/admin/applications', 'Applications', 'inbox', $path, $pending) ?>
      <?= $nav('/admin/pros', 'Tradespeople', 'tools', $path) ?>
      <?= $nav('/admin/jobs', 'Jobs', 'clipboard', $path) ?>
      <?= $nav('/admin/users', 'People', 'user', $path) ?>
      <?= $nav('/admin/advertising', 'Advertising', 'megaphone', $path) ?>
      <?php if ($isSuper): ?>
        <?= $nav('/admin/licensing', 'Licensing', 'shield', $path) ?>
        <?= $nav('/admin/markets', 'Markets', 'pin', $path) ?>
      <?php endif; ?>
      <?= $nav('/admin/activity', 'Activity log', 'clock', $path) ?>
    </nav>

    <div class="who">
      <b><?= e(trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?: ($me['email'] ?? '')) ?></b>
      <span><?= e($isSuper ? 'Superadmin' : 'Market admin') ?></span>
      <form method="post" action="<?= e(url('/admin/logout')) ?>" style="margin-top:10px">
        <?= \FixListed\Core\Csrf::field() ?>
        <button class="btn btn-onink btn-sm btn-block" type="submit">Sign out</button>
      </form>
      <p style="margin-top:12px"><a href="<?= e(url('/')) ?>" style="font-size:12px">← View the site</a></p>
    </div>
  </aside>

  <main class="adm-main">
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= $f['type'] === 'ok' ? 'ok' : 'bad' ?>">
        <?= icon($f['type'] === 'ok' ? 'check' : 'alert', 17) ?>
        <span><?= e($f['message']) ?></span>
      </div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
