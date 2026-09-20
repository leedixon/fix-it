<?php require_once __DIR__ . '/../partials/icons.php'; ?>
<section class="sect">
  <div class="wrap" style="text-align:center;max-width:620px">
    <div class="eyebrow eyebrow-brass">404</div>
    <h1 style="font-size:clamp(30px,5vw,46px);margin-top:12px">That page is not here</h1>
    <p class="muted" style="margin:16px auto 0;max-width:46ch">
      The link may be old, or the listing may have been taken down. Everything else is still
      where you left it.
    </p>
    <div class="hero-cta" style="justify-content:center;margin-top:28px">
      <a class="btn btn-primary" href="<?= e(url('/pros')) ?>">Browse tradespeople</a>
      <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Back to the home page</a>
    </div>
  </div>
</section>
