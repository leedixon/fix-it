<?php
/**
 * Chrome for the signed-in tradesperson.
 *
 * Wears the public header rather than a separate shell: a pro moving between
 * their dashboard and the jobs board should not feel like they crossed into a
 * different product.
 *
 * @var string $content @var string $title @var array $market @var string $path
 * @var array|null $profile @var bool $isLive @var array $flashes @var array|null $me
 */
require_once __DIR__ . '/../partials/icons.php';

$nav = static function (string $href, string $label, string $icon, string $current): string {
    $on = $current === $href || ($href !== '/my' && str_starts_with($current, $href));
    return '<a href="' . e(url($href)) . '"' . ($on ? ' class="on" aria-current="page"' : '') . '>'
        . icon($icon, 15) . '<span>' . e($label) . '</span></a>';
};

$status = (string) ($profile['status'] ?? '');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Marcellus&family=Hanken+Grotesk:ital,wght@0,300..800;1,300..700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

<header class="chrome">
  <div class="wrap chrome-in">
    <a class="logo" href="<?= e(url('/')) ?>"><b>Fix</b> <i>Listed</i></a>
    <nav class="nav" aria-label="Primary">
      <a href="<?= e(url('/jobs')) ?>">Jobs board</a>
      <a href="<?= e(url('/pros')) ?>">Directory</a>
    </nav>
    <div class="chrome-r">
      <?php if (in_array($me['role'] ?? '', ['superadmin', 'market_admin'], true)): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin')) ?>">Admin</a>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/sign-out')) ?>">
        <?= \FixListed\Core\Csrf::field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</header>

<main id="main" class="wrap acct">
  <aside class="acct-side">
    <nav class="acct-nav" aria-label="Your account">
      <?= $nav('/my', 'Jobs for you', 'clipboard', $path) ?>
      <?= $nav('/my/quotes', 'Your quotes', 'inbox', $path) ?>
      <?= $nav('/my/listing', 'Your listing', 'tools', $path) ?>
    </nav>

    <?php if ($profile !== null): ?>
      <div class="acct-status">
        <div class="k">Your listing</div>
        <div style="margin-top:7px">
          <?php if ($status === 'active'): ?>
            <span class="badge b-live">Live</span>
            <p class="tiny muted" style="margin-top:8px">
              <a href="<?= e(url('/pros/' . $profile['slug'])) ?>">See your public profile →</a>
            </p>
          <?php elseif ($status === 'pending_review'): ?>
            <span class="badge b-pending">Being checked</span>
            <p class="tiny muted" style="margin-top:8px">
              We are checking your licence and insurance. You will be able to quote jobs as soon as
              that is done.
            </p>
          <?php else: ?>
            <span class="badge b-flat"><?= e(str_replace('_', ' ', $status)) ?></span>
            <p class="tiny muted" style="margin-top:8px">
              Your listing is not visible. Email <a href="mailto:hello@fixlisted.com">hello@fixlisted.com</a>
              and we will sort it out.
            </p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </aside>

  <div>
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= $f['type'] === 'ok' ? 'ok' : 'bad' ?>">
        <?= icon($f['type'] === 'ok' ? 'check' : 'alert', 17) ?>
        <span><?= e($f['message']) ?></span>
      </div>
    <?php endforeach; ?>
    <?= $content ?>
  </div>
</main>
</body>
</html>
