<?php
/** @var string $error @var string $email */
require_once __DIR__ . '/../partials/icons.php';
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
<div class="adm-login">
  <div class="box">
    <a class="logo" href="<?= e(url('/')) ?>" style="font-size:22px"><b>Fix</b> <i>Listed</i></a>
    <h1 style="font-size:23px;margin-top:18px">Admin</h1>

    <?php if ($error !== ''): ?>
      <div class="flash flash-bad" style="margin-top:18px">
        <?= icon('alert', 17) ?><span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/admin/login')) ?>" style="margin-top:20px">
      <?= \FixListed\Core\Csrf::field() ?>
      <label class="field">
        <span>Email</span>
        <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
      </label>
      <label class="field">
        <span>Password</span>
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>

    <p class="tiny muted" style="margin-top:16px">
      Five wrong attempts locks the account for fifteen minutes.
    </p>
  </div>
</div>
</body>
</html>
