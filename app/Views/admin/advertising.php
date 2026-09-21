<?php
/**
 * Advertising, as it actually stands.
 *
 * @var array $placements @var array $sold @var array $market @var callable $can
 */
require_once __DIR__ . '/../partials/icons.php';
$boostSold     = $sold['boost'] ?? 0;
$spotlightSold = $sold['spotlight'] ?? 0;
$boostCap      = (int) $market['boost_slots'];
$spotlightCap  = (int) $market['spotlight_slots'];
$bar = static function (int $sold, int $cap): string {
    $pct = $cap > 0 ? min(100, (int) round($sold / $cap * 100)) : 0;
    return '<div class="bar" style="margin-top:10px"><i style="width:' . $pct . '%"></i></div>';
};
?>
<div class="adm-h">
  <div>
    <h1>Advertising</h1>
    <p>Placement is sold by the month and is always labelled on the site.</p>
  </div>
</div>

<div class="flash flash-note">
  <?= icon('info', 17) ?>
  <span>
    <strong style="color:var(--text)">How this sells itself.</strong>
    A tradesperson buys a plan from <em>Get seen first</em> in their own account, pays through
    Stripe Checkout, and the webhook grants the placement. Cancelling, card changes and invoices
    all happen in Stripe's billing portal, so there is nothing here you have to do by hand. If
    the last slot sells twice in the same moment, the second payment is cancelled and refunded
    automatically and you get an email to follow it up.
  </span>
</div>

<div class="adm-stats" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat">
    <div class="k">Spotlight</div>
    <div class="v"><?= $spotlightSold ?> / <?= $spotlightCap ?></div>
    <?php if ($can('finance.view')): ?>
      <div class="d"><?= e(money((int) $market['spotlight_price_cents'])) ?> per month</div>
    <?php endif; ?>
    <?= $bar($spotlightSold, $spotlightCap) ?>
  </div>
  <div class="stat">
    <div class="k">Boost</div>
    <div class="v"><?= $boostSold ?> / <?= $boostCap ?></div>
    <?php if ($can('finance.view')): ?>
      <div class="d"><?= e(money((int) $market['boost_price_cents'])) ?> per month</div>
    <?php endif; ?>
    <?= $bar($boostSold, $boostCap) ?>
  </div>
  <?php if ($can('finance.view')): ?>
    <div class="stat">
      <div class="k">Monthly value, if sold out</div>
      <div class="v"><?= e(money($spotlightCap * (int) $market['spotlight_price_cents'] + $boostCap * (int) $market['boost_price_cents'])) ?></div>
      <div class="d">At today's prices and caps</div>
    </div>
  <?php else: ?>
    <!-- A manager needs to know how full the inventory is. What it earns is
         not theirs to see, so the tile answers the operational question
         instead of the financial one. -->
    <div class="stat">
      <div class="k">Slots left</div>
      <div class="v"><?= max(0, $spotlightCap - (int) $spotlightSold) + max(0, $boostCap - (int) $boostSold) ?></div>
      <div class="d">across both plans</div>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-h">
    <h3>Placements</h3>
    <span class="tiny muted" style="margin-left:auto">Scarcity is the product — if everyone can buy the top slot, nobody's top slot is worth anything.</span>
  </div>
  <?php if ($placements === []): ?>
    <div class="adm-empty">
      <p><strong>Nothing sold yet.</strong></p>
      <p style="margin-top:8px">Placements appear here once a tradesperson subscribes.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Business</th><th>Plan</th><th>Position</th><th>Subscription</th><th>Renews</th><th>Impressions 30d</th><th>Clicks 30d</th><th>CTR</th></tr></thead>
        <tbody>
        <?php foreach ($placements as $p): ?>
          <?php $imp = (int) $p['impressions_30d']; $clk = (int) $p['clicks_30d']; ?>
          <tr>
            <td>
              <?= e($p['pro_name']) ?>
              <?php if ($p['is_demo']): ?><span class="badge b-sample">Sample</span><?php endif; ?>
            </td>
            <!-- The same badge the public card wears, so what an administrator
                 reads here matches what a visitor sees on the listing. -->
            <td><span class="badge <?= $p['plan'] === 'spotlight' ? 'b-featured' : 'b-promoted' ?>"><?= e($p['plan'] ?? '—') ?></span></td>
            <td class="tiny">#<?= (int) $p['position'] ?></td>
            <td class="tiny"><?= e($p['sub_status'] ?? 'none') ?></td>
            <td class="tiny"><?= $p['current_period_end'] !== null
                ? e(date('j M', strtotime((string) $p['current_period_end'])))
                : '—' ?></td>
            <td class="num"><?= number_format($imp) ?></td>
            <td class="num"><?= number_format($clk) ?></td>
            <td class="num"><?= $imp > 0 ? number_format($clk / $imp * 100, 1) . '%' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
