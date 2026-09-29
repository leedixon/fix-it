<?php
/** @var array $market @var array $crumbs */
require_once __DIR__ . '/../partials/icons.php';
?>
<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap" style="max-width:620px;text-align:center">
    <div class="ic" style="width:56px;height:56px;border-radius:99px;background:var(--jade-wash);
                           color:var(--jade);display:grid;place-items:center;margin:0 auto">
      <?= icon('check', 26) ?>
    </div>
    <h1 style="font-size:clamp(28px,4.4vw,40px);margin-top:20px">Message sent</h1>
    <p class="muted" style="margin-top:14px;font-size:17px">
      It is saved and a person will read it. We reply by email, usually the same day —
      check your spam folder if nothing has arrived by tomorrow.
    </p>
    <p class="tiny muted" style="margin-top:18px">
      Nothing was sent to a mailing list, and we do not pass your details to anyone.
    </p>
    <div class="hero-cta" style="margin-top:26px;justify-content:center">
      <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Back to the site</a>
      <a class="btn btn-primary" href="<?= e(url('/academy')) ?>">Read the academy</a>
    </div>
  </div>
</section>
