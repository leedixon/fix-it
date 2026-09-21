<?php
/**
 * After Checkout.
 *
 * There are three states, and conflating them is how a homeowner ends up
 * paying twice. Live means the webhook arrived. Waiting means the money is
 * taken and the webhook is a second behind — the page refreshes itself rather
 * than inviting a second attempt. Unpaid means they never completed Checkout.
 *
 * @var array|null $job @var bool $paid @var string $payment
 */
require_once __DIR__ . '/../partials/icons.php';
$waiting = $job !== null && !$paid && $payment !== 'failed';
?>
<?php if ($waiting): ?>
  <?php /* Confirming takes a second or two. Reloading beats a spinner that
           lies, and beats a button somebody might press twice. */ ?>
  <meta http-equiv="refresh" content="4">
<?php endif; ?>

<section class="sect">
  <div class="wrap" style="max-width:640px;text-align:center">
    <?php if ($job === null): ?>
      <h1 style="font-size:clamp(26px,4vw,38px)">We could not find that job</h1>
      <p class="muted" style="margin:14px auto 0;max-width:46ch">
        If you have just paid, check your email — the receipt has your reference on it. Otherwise
        the link may have been mistyped.
      </p>
      <div class="hero-cta" style="justify-content:center;margin-top:26px">
        <a class="btn btn-primary" href="<?= e(url('/post-a-job')) ?>">Post a job</a>
        <a class="btn btn-ghost" href="<?= e(url('/contact')) ?>">Get help</a>
      </div>

    <?php elseif ($paid): ?>
      <div class="paid">
        <div class="tick"><?= icon('check', 28) ?></div>
        <h1 style="font-size:clamp(28px,4.4vw,40px)">Your job is live</h1>
        <p class="muted" style="margin:16px auto 0;max-width:48ch">
          Every tradesperson covering <?= e($job['county_name'] ?? 'your county') ?> can see it now,
          and quotes come straight to you. Most jobs get their first within a day.
        </p>

        <div class="card" style="padding:18px 20px;margin-top:24px;text-align:left">
          <div class="kv"><span class="k">Reference</span><span class="mono"><?= e($job['reference']) ?></span></div>
          <div class="kv"><span class="k">Job</span><span><?= e($job['title']) ?></span></div>
          <div class="kv"><span class="k">Live until</span><span><?= e(date('j F Y', strtotime((string) $job['expires_at']))) ?></span></div>
        </div>

        <p class="small muted" style="margin-top:18px">
          A receipt is on its way to <?= e($job['email']) ?>. Keep the reference —
          it is how we find your job if you need us.
        </p>

        <div class="hero-cta" style="justify-content:center;margin-top:26px">
          <a class="btn btn-primary" href="<?= e(url('/jobs/' . $job['reference'])) ?>">See your job</a>
          <a class="btn btn-ghost" href="<?= e(url('/pros')) ?>">Browse tradespeople</a>
        </div>
      </div>

    <?php elseif ($payment === 'failed'): ?>
      <h1 style="font-size:clamp(26px,4vw,38px)">That payment did not go through</h1>
      <p class="muted" style="margin:14px auto 0;max-width:46ch">
        Nothing was charged and your job is saved as
        <span class="mono"><?= e($job['reference']) ?></span>. You can pick it up where you left off.
      </p>
      <a class="btn btn-primary" style="margin-top:24px"
         href="<?= e(url_q('/post-a-job/resume', ['ref' => $job['reference']])) ?>">Try again</a>

    <?php else: ?>
      <div class="paid">
        <div class="tick" style="background:var(--brass-wash);color:var(--brass)"><?= icon('clock', 28) ?></div>
        <h1 style="font-size:clamp(26px,4vw,38px)">Confirming your payment</h1>
        <p class="muted" style="margin:16px auto 0;max-width:46ch">
          This takes a second or two. The page will update on its own —
          <strong>there is no need to pay again</strong>.
        </p>
        <p class="small muted" style="margin-top:16px">
          Your reference is <span class="mono"><?= e($job['reference']) ?></span>. If this is still
          here in a few minutes, email <a href="mailto:hello@fixlisted.com">hello@fixlisted.com</a>
          with that reference and we will sort it out.
        </p>
      </div>
    <?php endif; ?>
  </div>
</section>
