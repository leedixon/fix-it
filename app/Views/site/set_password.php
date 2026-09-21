<?php
/** @var bool $valid @var string $token @var string $name @var string $error */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect-tight">
  <div class="wrap" style="max-width:440px">
    <?php if (!$valid): ?>
      <h1 style="font-size:30px">That link has expired</h1>
      <p class="muted" style="margin-top:12px">
        These links work once and then stop, which is what keeps them safe. Ask for a fresh one and
        it will be in your inbox in a minute.
      </p>
      <a class="btn btn-primary" style="margin-top:20px" href="<?= e(url('/forgot-password')) ?>">
        Send me a new link
      </a>
    <?php else: ?>
      <h1 style="font-size:30px">Set your password<?= $name !== '' ? ', ' . e($name) : '' ?></h1>
      <p class="muted" style="margin-top:10px">
        Pick something you do not use anywhere else. A few unrelated words beats a short one with a
        symbol in it, and you will actually remember it.
      </p>

      <?php if ($error !== ''): ?>
        <div class="flash flash-bad" style="margin-top:20px">
          <?= icon('alert', 17) ?><span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form class="card" method="post" action="<?= e(url('/set-password/' . $token)) ?>" style="padding:26px;margin-top:20px">
        <?= \FixListed\Core\Csrf::field() ?>
        <label class="field">
          <span>New password</span>
          <input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus>
        </label>
        <label class="field">
          <span>Again, to be sure</span>
          <input type="password" name="password_confirm" autocomplete="new-password" minlength="10" required>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Set it and sign me in</button>
      </form>
    <?php endif; ?>
  </div>
</section>
