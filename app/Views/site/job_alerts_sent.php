<?php
/**
 * After a signup. Deliberately says nothing about whether the address was
 * already on the list — that would be a free membership check for anybody
 * who wanted one.
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect">
  <div class="wrap" style="max-width:520px;text-align:center">
    <div class="eyebrow eyebrow-brass" style="justify-content:center"><?= icon('mail', 14) ?> One more step</div>
    <h1 style="font-size:clamp(26px,4.2vw,36px);margin-top:12px">Check your email</h1>
    <p class="muted" style="margin-top:14px;font-size:17px">
      We have sent you a link to confirm the address. Click it and the alerts start — nothing
      is sent before then.
    </p>
    <p class="muted small" style="margin-top:14px">
      Nothing there after a minute or two? Look in spam, and check the address you typed.
    </p>
    <div class="hero-cta" style="margin-top:26px;justify-content:center">
      <a class="btn btn-ghost btn-lg" href="<?= e(url('/jobs')) ?>">Back to the jobs board</a>
    </div>
  </div>
</section>
