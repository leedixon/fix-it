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
