<?php require_once __DIR__ . '/../partials/icons.php'; ?>
<section class="sect">
  <div class="wrap" style="max-width:640px;text-align:center">
    <div class="paid">
      <div class="tick"><?= icon('check', 28) ?></div>
      <h1 style="font-size:clamp(28px,4.4vw,40px)">Application sent</h1>
      <p class="muted" style="margin:16px auto 0;max-width:48ch">
        We have it. A person is going to check your licence and insurance, and you will hear back
        within two working days — sooner, most likely.
      </p>
      <p class="muted" style="margin:14px auto 0;max-width:48ch">
        Check your email for a confirmation. If it is not there in a few minutes, look in the spam
        folder and mark it as not spam, or the rest of our email will end up there too.
      </p>
      <div class="hero-cta" style="justify-content:center;margin-top:28px">
        <a class="btn btn-primary" href="<?= e(url('/jobs')) ?>">See the jobs board</a>
        <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Back to the home page</a>
      </div>
    </div>
  </div>
</section>
