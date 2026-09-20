<?php
/**
 * Advertising, as it actually stands.
 *
 * @var array $placements @var array $sold @var array $market
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

<div class="flash flash-ok" style="background:var(--paper-2);color:var(--text-2);border-color:var(--line)">
  <?= icon('info', 17) ?>
  <span>
    <strong style="color:var(--text)">Where this is up to.</strong>
    The inventory, the caps, the paid ordering in the directory and the labelling are all built and
    working — a placement with an active subscription already lifts a listing and shows as
    <em>Featured</em>. What does not exist yet is the part that sells one: the checkout, the
    Stripe subscription and the self-serve screen a tradesperson uses. That lands with the
    money path.
  </span>
</div>

<div class="adm-stats" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat">
    <div class="k">Spotlight</div>
    <div class="v"><?= $spotlightSold ?> / <?= $spotlightCap ?></div>
    <div class="d"><?= e(money((int) $market['spotlight_price_cents'])) ?> per month</div>
    <?= $bar($spotlightSold, $spotlightCap) ?>
  </div>
  <div class="stat">
    <div class="k">Boost</div>
    <div class="v"><?= $boostSold ?> / <?= $boostCap ?></div>
    <div class="d"><?= e(money((int) $market['boost_price_cents'])) ?> per month</div>
    <?= $bar($boostSold, $boostCap) ?>
  </div>
  <div class="stat">
    <div class="k">Monthly value, if sold out</div>
    <div class="v"><?= e(money($spotlightCap * (int) $market['spotlight_price_cents'] + $boostCap * (int) $market['boost_price_cents'])) ?></div>
    <div class="d">At today's prices and caps</div>
  </div>
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
        <thead><tr><th>Business</th><th>Plan</th><th>Slot</th><th>Subscription</th><th>Impressions 30d</th><th>Clicks 30d</th><th>CTR</th></tr></thead>
        <tbody>
        <?php foreach ($placements as $p): ?>
          <?php $imp = (int) $p['impressions_30d']; $clk = (int) $p['clicks_30d']; ?>
          <tr>
            <td>
              <?= e($p['pro_name']) ?>
              <?php if ($p['is_demo']): ?><span class="badge b-sample">Sample</span><?php endif; ?>
            </td>
            <td><span class="badge <?= $p['plan'] === 'spotlight' ? 'b-featured' : 'b-flat' ?>"><?= e($p['plan'] ?? '—') ?></span></td>
            <td class="tiny"><?= e(str_replace('_', ' ', (string) $p['slot'])) ?> #<?= (int) $p['position'] ?></td>
            <td class="tiny"><?= e($p['sub_status'] ?? 'none') ?></td>
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
