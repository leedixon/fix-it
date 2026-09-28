<?php
/**
 * Confirmed, or unsubscribed. One view, because the two are the same page
 * with different words and a different next step.
 *
 * @var string $mode  confirmed | unsubscribed | pro_unsubscribed
 * @var string $email
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect">
  <div class="wrap" style="max-width:540px;text-align:center">
    <?php if ($mode === 'confirmed'): ?>
      <div class="eyebrow eyebrow-brass" style="justify-content:center"><?= icon('check', 14) ?> Done</div>
      <h1 style="font-size:clamp(26px,4.2vw,36px);margin-top:12px">You are on the list</h1>
      <p class="muted" style="margin-top:14px;font-size:17px">
        New jobs matching your trades and counties will arrive as they are posted. Every one of
        them has a link at the bottom to stop it.
      </p>
      <p class="muted" style="margin-top:14px">
        Quoting is free and we take nothing from the work. If you want homeowners to be able to
        find you between jobs, a listing is free as well.
      </p>
      <div class="hero-cta" style="margin-top:26px;justify-content:center">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/list-your-business')) ?>">List your business — free</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/jobs')) ?>">See the board</a>
      </div>
    <?php else: ?>
      <div class="eyebrow" style="justify-content:center">Stopped</div>
      <h1 style="font-size:clamp(26px,4.2vw,36px);margin-top:12px">No more job emails</h1>
      <p class="muted" style="margin-top:14px;font-size:17px">
        <?php if ($mode === 'pro_unsubscribed'): ?>
          We have stopped the job alerts. <b>Your listing is untouched</b> — you are still in the
          directory, homeowners can still find you, and you can still quote anything on the board.
        <?php else: ?>
          That address will not get any more job alerts from us.
        <?php endif; ?>
      </p>
      <p class="muted small" style="margin-top:14px">
        Changed your mind? <a href="<?= e(url('/jobs/alerts')) ?>">Sign up again</a> — it takes a moment.
      </p>
      <div class="hero-cta" style="margin-top:26px;justify-content:center">
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/jobs')) ?>">Open jobs</a>
      </div>
    <?php endif; ?>
  </div>
</section>
