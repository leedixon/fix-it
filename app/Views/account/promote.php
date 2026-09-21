<?php
/**
 * @var array $plans @var array|null $subscription @var array $totals
 * @var array $daily @var bool $canPay @var array|null $profile @var bool $isLive
 * @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';

$status  = (string) ($subscription['status'] ?? '');
$holding = in_array($status, ['active', 'trialing', 'past_due'], true);
$myPlan  = $holding ? (string) $subscription['plan'] : '';

// Stripe's portal is the only place to cancel or change a card, so the button
// only appears once there is a customer for it to open. Offering it without
// one sends a pro to an error and leaves them thinking they cannot get out.
$hasBilling = $holding && $canPay && !empty($subscription['stripe_customer_id']);

$impressions = (int) $totals['impressions'];
$clicks      = (int) $totals['clicks'];
$rate        = $impressions > 0 ? round($clicks / $impressions * 100, 1) : 0.0;

// The tallest day, so the bars have something to scale against.
$peak = 1;
foreach ($daily as $row) {
    $peak = max($peak, (int) $row['impressions']);
}
?>
<div class="adm-h">
  <div>
    <h1 style="font-size:28px">Get seen first</h1>
    <p>What your listing does now, and what it costs to move it up the page.</p>
  </div>
  <?php if ($hasBilling): ?>
    <form method="post" action="<?= e(url('/my/billing')) ?>">
      <?= \FixListed\Core\Csrf::field() ?>
      <button class="btn btn-ghost btn-sm" type="submit">Billing &amp; invoices</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($status === 'past_due'): ?>
  <div class="flash flash-bad">
    <?= icon('alert', 17) ?>
    <div><strong>Your last payment did not go through.</strong>
      Your placement is still up while Stripe retries the card. Update it under
      <em>Billing &amp; invoices</em> and nothing else changes.</div>
  </div>
<?php endif; ?>

<!--
  The numbers come first, before any price. A tradesperson deciding whether to
  spend $49 a month deserves to know what their listing already delivers —
  and if the answer is "not much yet", they should be able to see that and
  keep their money.
-->
<div class="adm-stats" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat">
    <div class="k">Times your listing was shown</div>
    <div class="v"><?= number_format($impressions) ?></div>
    <div class="tiny muted">last <?= (int) $totals['days'] ?> days</div>
  </div>
  <div class="stat">
    <div class="k">People who opened it</div>
    <div class="v"><?= number_format($clicks) ?></div>
    <div class="tiny muted">one per person per day</div>
  </div>
  <div class="stat">
    <div class="k">Of those who saw it</div>
    <div class="v"><?= $rate ?>%</div>
    <div class="tiny muted">clicked through</div>
  </div>
</div>

<?php if ($daily !== []): ?>
  <div class="panel" style="margin-bottom:26px">
    <h3 class="panel-h">Day by day</h3>
    <div class="spark spark-tall" role="img"
         aria-label="Times your listing was shown each day for the last <?= (int) $totals['days'] ?> days">
      <?php foreach ($daily as $row): ?>
        <i style="height:<?= max(2, (int) round((int) $row['impressions'] / $peak * 100)) ?>%"
           title="<?= e(date('j M', strtotime((string) $row['stat_date']))) ?>: <?= (int) $row['impressions'] ?> shown, <?= (int) $row['clicks'] ?> opened"></i>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <div class="empty" style="margin-bottom:26px">
    <div class="ic"><?= icon('chart', 24) ?></div>
    <h3>Nothing counted yet</h3>
    <p>We started counting the day your listing went live. Come back in a week and this
       will show what it has been doing.</p>
  </div>
<?php endif; ?>

<h2 style="font-size:20px;margin:0 0 4px">Move up the page</h2>
<p class="muted" style="margin:0 0 18px">
  Both plans lift your listing on the directory, the home page and every town page you cover.
  Cancel whenever you like — you keep the rest of the month you have paid for.
</p>

<div class="plans">
  <?php foreach (['spotlight', 'boost'] as $key): ?>
    <?php
      $plan  = $plans[$key];
      $mine  = $myPlan === $key;
      $spare = (int) $plan['available'];
    ?>
    <article class="plan<?= $key === 'spotlight' ? ' plan-top' : '' ?><?= $mine ? ' plan-mine' : '' ?>">
      <div class="plan-h">
        <h3><?= $key === 'spotlight' ? 'Spotlight' : 'Boost' ?></h3>
        <?php if ($mine): ?><span class="badge b-live">Your plan</span><?php endif; ?>
      </div>
      <div class="plan-p"><?= e(money((int) $plan['price_cents'])) ?><span>/month</span></div>
      <ul class="plan-l">
        <?php if ($key === 'spotlight'): ?>
          <li><?= icon('check', 14) ?> Top of the list, above everyone</li>
          <li><?= icon('check', 14) ?> A <span class="badge b-featured">Featured</span> badge on your card</li>
          <li><?= icon('check', 14) ?> Shown on the home page</li>
        <?php else: ?>
          <li><?= icon('check', 14) ?> Above the standard listings</li>
          <li><?= icon('check', 14) ?> A <span class="badge b-promoted">Promoted</span> badge on your card</li>
        <?php endif; ?>
        <li><?= icon('check', 14) ?> Every town page in your counties</li>
        <li><?= icon('check', 14) ?> These numbers, updated daily</li>
      </ul>

      <p class="tiny muted plan-s">
        <?php if ($spare > 0): ?>
          <?= $spare ?> of <?= (int) $plan['slots'] ?> left in <?= e((string) $market['name']) ?>
        <?php else: ?>
          All <?= (int) $plan['slots'] ?> taken in <?= e((string) $market['name']) ?>
        <?php endif; ?>
      </p>

      <?php if ($mine && $hasBilling): ?>
        <form method="post" action="<?= e(url('/my/billing')) ?>">
          <?= \FixListed\Core\Csrf::field() ?>
          <button class="btn btn-ghost" type="submit" style="width:100%">Manage or cancel</button>
        </form>
      <?php elseif ($mine): ?>
        <p class="tiny muted" style="margin:0">
          This plan is running. Email us to change or stop it.
        </p>
      <?php elseif ($holding): ?>
        <p class="tiny muted" style="margin:0">
          <?= $hasBilling ? 'Switch plans in <em>Billing &amp; invoices</em>.' : 'Email us to switch plans.' ?>
        </p>
      <?php elseif (!$isLive): ?>
        <button class="btn btn-dark" style="width:100%" disabled>Your listing is being checked</button>
      <?php elseif ($spare < 1): ?>
        <button class="btn btn-dark" style="width:100%" disabled>Sold out</button>
      <?php elseif (!$canPay): ?>
        <button class="btn btn-dark" style="width:100%" disabled>Not on sale yet</button>
      <?php else: ?>
        <form method="post" action="<?= e(url('/my/promote')) ?>">
          <?= \FixListed\Core\Csrf::field() ?>
          <input type="hidden" name="plan" value="<?= e($key) ?>">
          <button class="btn <?= $key === 'spotlight' ? 'btn-dark' : 'btn-ghost' ?>" type="submit"
                  style="width:100%">Start <?= $key === 'spotlight' ? 'Spotlight' : 'Boost' ?></button>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>

<p class="tiny muted" style="margin-top:18px">
  Paid placement is always labelled on the public site. Ratings, reviews and the verified badge
  cannot be bought at any price — that is the whole reason anyone trusts the list.
</p>
