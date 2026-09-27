<?php
/**
 * After a review is submitted.
 *
 * Says what happens next rather than just "thanks". Somebody who has just
 * written three paragraphs about a bad job wants to know whether anyone is
 * going to read it, and a page that only thanks them implies nobody will.
 *
 * @var array $flashes
 */
require_once __DIR__ . '/../partials/icons.php';
?>

<section class="sect">
  <div class="wrap" style="max-width:560px;text-align:center">
    <div class="eyebrow eyebrow-brass" style="justify-content:center">Got it</div>
    <h1 style="font-size:clamp(26px,4.2vw,38px);margin-top:12px">Thank you</h1>
    <p class="muted" style="margin-top:14px;font-size:17px">
      We read every review before it goes up, usually within a day or two. Once it is published it
      appears on that tradesperson's profile and counts towards their rating.
    </p>
    <p class="muted" style="margin-top:12px">
      They can reply to it, but they cannot remove it. Neither can we, unless it breaks the rules
      in our <a href="<?= e(url('/terms')) ?>">terms</a>.
    </p>
    <div class="hero-cta" style="margin-top:28px;justify-content:center">
      <a class="btn btn-ghost btn-lg" href="<?= e(url('/pros')) ?>">Browse the directory</a>
    </div>
  </div>
</section>
