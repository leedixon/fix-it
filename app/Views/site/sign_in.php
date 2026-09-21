<?php
/** @var string $error @var string $email */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect-tight">
  <div class="wrap" style="max-width:420px">
    <div class="eyebrow eyebrow-brass">Tradespeople</div>
    <h1 style="font-size:32px;margin-top:10px">Sign in</h1>
    <p class="muted" style="margin-top:10px">Quote jobs and manage your listing.</p>

    <?php if ($error !== ''): ?>
      <div class="flash flash-bad" style="margin-top:20px">
        <?= icon('alert', 17) ?><span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form class="card" method="post" action="<?= e(url('/sign-in')) ?>" style="padding:26px;margin-top:20px">
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
      <p class="tiny muted" style="margin-top:14px;text-align:center">
        <a href="<?= e(url('/forgot-password')) ?>">Forgotten your password?</a>
      </p>
    </form>

    <p class="small muted" style="margin-top:22px;text-align:center">
      Not listed yet? <a href="<?= e(url('/list-your-business')) ?>">List your business</a> — it is free.
    </p>
  </div>
</section>
