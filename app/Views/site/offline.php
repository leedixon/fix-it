<?php
/**
 * What somebody sees when the network has gone.
 *
 * Served by the service worker from its cache, so it must not need anything
 * the network would have to fetch. It says what is true — the site is fine,
 * the phone is not connected — rather than implying something is broken
 * here, which is what a browser's own error page implies.
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect">
  <div class="wrap" style="max-width:520px;text-align:center">
    <div class="eyebrow" style="justify-content:center"><?= icon('alert', 14) ?> No connection</div>
    <h1 style="font-size:clamp(26px,4.2vw,36px);margin-top:12px">You are offline</h1>
    <p class="muted" style="margin-top:14px;font-size:17px">
      Fix Listed is still there — your phone cannot reach it at the moment. This tends to sort
      itself out as soon as you have a signal again.
    </p>
    <p class="muted small" style="margin-top:14px">
      Nothing you were part-way through has been lost. Jobs, quotes and listings all live on the
      site rather than on your phone.
    </p>
    <div class="hero-cta" style="margin-top:26px;justify-content:center">
      <a class="btn btn-primary btn-lg" href="<?= e(url('/')) ?>">Try again</a>
    </div>
  </div>
</section>
