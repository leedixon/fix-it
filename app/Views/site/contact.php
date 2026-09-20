<?php
/** @var array $market */
require_once __DIR__ . '/../partials/icons.php';
?>
<section class="sect-tight">
  <div class="wrap" style="max-width:720px">
    <div class="eyebrow eyebrow-brass">Contact</div>
    <h1 style="font-size:clamp(30px,4.6vw,44px);margin-top:12px">Talk to a person</h1>
    <p class="muted" style="margin-top:14px;font-size:17px">
      Fix Listed is small and local. Email reaches someone who can actually do something about it,
      usually the same day.
    </p>

    <div class="grid g2" style="margin-top:32px">
      <div class="card" style="padding:22px">
        <h2 style="font-size:19px"><?= icon('tools', 17) ?> Tradespeople</h2>
        <p class="muted small" style="margin-top:8px">
          Want to be listed in <?= e($market['name']) ?>? Send your business name, your trades, the
          counties you cover, and your licence and insurance details.
        </p>
        <p style="margin-top:14px">
          <a class="btn btn-dark btn-sm" href="mailto:hello@fixlisted.com?subject=List%20my%20business">
            hello@fixlisted.com
          </a>
        </p>
      </div>

      <div class="card" style="padding:22px">
        <h2 style="font-size:19px"><?= icon('user', 17) ?> Homeowners</h2>
        <p class="muted small" style="margin-top:8px">
          A problem with a listing, a job you posted, or a refund? Include the job reference if you
          have one — it looks like <span class="mono"><?= e($market['code']) ?>-4K2P9M</span>.
        </p>
        <p style="margin-top:14px">
          <a class="btn btn-ghost btn-sm" href="mailto:hello@fixlisted.com?subject=Help%20with%20a%20job">
            hello@fixlisted.com
          </a>
        </p>
      </div>
    </div>

    <div class="seal" style="margin-top:26px;border:1px solid var(--line);border-radius:4px">
      <?= icon('shield', 16) ?>
      <span>We never ask for card details, passwords or bank information by email. If something
      claiming to be us does, it is not us.</span>
    </div>
  </div>
</section>
