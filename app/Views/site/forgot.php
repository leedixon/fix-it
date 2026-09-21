<?php
/** @var bool $sent */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect-tight">
  <div class="wrap" style="max-width:420px">
    <h1 style="font-size:30px">Reset your password</h1>

    <?php if ($sent): ?>
      <div class="flash flash-ok" style="margin-top:20px">
        <?= icon('check', 17) ?>
        <span>If that address has an account, a link is on its way. It works once and lasts an hour.</span>
      </div>
      <p class="small muted" style="margin-top:18px">
        Nothing arrived? Check the spam folder, then
        <a href="<?= e(url('/contact')) ?>">email us</a> and we will sort it out by hand.
      </p>
    <?php else: ?>
      <p class="muted" style="margin-top:10px">
        Type your email and we will send a link to set a new one.
      </p>
      <form class="card" method="post" action="<?= e(url('/forgot-password')) ?>" style="padding:26px;margin-top:20px">
        <?= \FixListed\Core\Csrf::field() ?>
        <label class="field">
          <span>Email</span>
          <input type="email" name="email" autocomplete="username" required autofocus>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Send the link</button>
      </form>
    <?php endif; ?>
  </div>
</section>
