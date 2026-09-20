<?php
/**
 * The page shell.
 *
 * @var string $content
 * @var string $title
 * @var string $description
 * @var array  $market
 * @var array  $counties
 * @var array  $navTrades
 * @var string $path
 * @var bool   $showDemo
 * @var bool   $noindex
 * @var string $bodyClass
 * @var string $ogImage    optional, overrides the default social card
 */
$description = $description ?? '';
$bodyClass   = $bodyClass ?? '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e(abs_url($path)) ?>">

<?php
/*
 * Open Graph and Twitter.
 *
 * These decide what a link to this site looks like when someone pastes it into
 * Facebook, a text message, Slack or LinkedIn — which, for a local directory
 * recruiting its first tradespeople, is most of how the link travels. Without
 * an og:image the preview is a grey box with a truncated URL.
 *
 * og:image must be absolute. A relative one is ignored silently by every
 * scraper, which looks identical to having set none at all.
 */
$ogImage = abs_asset($ogImage ?? 'assets/social/og.png');
$ogDesc  = $description !== '' ? $description
    : 'A flat-fee trades directory for ' . ($market['name'] ?? 'your area')
      . '. Post a job once, quote directly, no commission.';
?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Fix Listed">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($ogDesc) ?>">
<meta property="og:url" content="<?= e(abs_url($path)) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Fix Listed — the trades directory that doesn't take a cut of your job.">
<meta property="og:locale" content="en_US">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">

<?php if ($noindex): ?>
<!-- Removed at launch, deliberately and in one place. Until then nothing here
     should be indexed: the directory is partly sample data and the URLs move. -->
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#0E1513">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Marcellus&family=Hanken+Grotesk:ital,wght@0,300..800;1,300..700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%230E1513'/><text x='16' y='23' font-family='Georgia,serif' font-size='19' fill='%23DCB25C' text-anchor='middle'>F</text></svg>">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip" href="#main">Skip to content</a>
<?php require __DIR__ . '/../partials/header.php'; ?>
<main id="main">
<?= $content ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
