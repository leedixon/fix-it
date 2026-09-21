<?php
/** @var array $job @var int $fee */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect">
  <div class="wrap" style="max-width:600px;text-align:center">
    <h1 style="font-size:clamp(26px,4vw,38px)">Your job is saved</h1>
    <p class="muted" style="margin:14px auto 0;max-width:46ch">
      Nothing was charged. <span class="mono"><?= e($job['reference']) ?></span> is waiting, and it
      goes live the moment the fee is paid.
    </p>

    <div class="card" style="padding:18px 20px;margin-top:24px;text-align:left">
      <div class="kv"><span class="k">Job</span><span><?= e($job['title']) ?></span></div>
      <div class="kv"><span class="k">Trade</span><span><?= e($job['trade_name']) ?></span></div>
      <div class="kv"><span class="k">Fee</span><span class="mono"><?= e(money($fee)) ?></span></div>
    </div>

    <p class="small muted" style="margin-top:20px">
      To finish it, post the job again with the same details — or email
      <a href="mailto:hello@fixlisted.com?subject=<?= e(rawurlencode('Finish job ' . $job['reference'])) ?>">hello@fixlisted.com</a>
      quoting that reference and we will send you a payment link.
    </p>

    <div class="hero-cta" style="justify-content:center;margin-top:24px">
      <a class="btn btn-primary" href="<?= e(url('/post-a-job')) ?>">Start again</a>
      <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Back to the home page</a>
    </div>
  </div>
</section>
