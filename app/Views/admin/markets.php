<?php
/** @var array $markets */
require_once __DIR__ . '/../partials/icons.php';
$d = static fn (?int $cents): string => $cents === null ? '' : rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');
?>
<div class="adm-h">
  <div>
    <h1>Markets</h1>
    <p>Each market sets its own prices. A change applies to the next listing, never to one already paid for.</p>
  </div>
</div>

<?php foreach ($markets as $m): ?>
  <form class="panel" method="post" action="<?= e(url('/admin/markets/' . $m['id'])) ?>" style="margin-bottom:18px">
    <?= \FixListed\Core\Csrf::field() ?>
    <div class="panel-h">
      <h3><?= e($m['name']) ?> <span class="badge <?= $m['status'] === 'live' ? 'b-live' : 'b-flat' ?>"><?= e($m['status']) ?></span></h3>
      <span class="tiny muted"><?= e($m['code']) ?> · <?= e($m['city']) ?>, <?= e($m['state']) ?></span>
    </div>
    <div style="padding:18px 20px">
      <div class="grid g3" style="gap:0 18px">
        <label class="field">
          <span>Listing fee</span>
          <input type="text" name="listing_fee" value="<?= e($d((int) $m['listing_fee_cents'])) ?>" inputmode="decimal">
        </label>
        <label class="field">
          <span>Boost, per month</span>
          <input type="text" name="boost_price" value="<?= e($d((int) $m['boost_price_cents'])) ?>" inputmode="decimal">
        </label>
        <label class="field">
          <span>Spotlight, per month</span>
          <input type="text" name="spotlight_price" value="<?= e($d((int) $m['spotlight_price_cents'])) ?>" inputmode="decimal">
        </label>
        <label class="field">
          <span>Boost slots</span>
          <input type="number" name="boost_slots" min="0" max="200" value="<?= (int) $m['boost_slots'] ?>">
        </label>
        <label class="field">
          <span>Spotlight slots</span>
          <input type="number" name="spotlight_slots" min="0" max="50" value="<?= (int) $m['spotlight_slots'] ?>">
        </label>
        <div class="field" style="display:flex;align-items:flex-end">
          <button class="btn btn-dark btn-block" type="submit">Save <?= e($m['code']) ?></button>
        </div>
      </div>
      <p class="tiny muted">
        A market can launch at a fee of $0 to fill the jobs board, then switch the fee on without a
        deploy. Slots cap how much placement can be sold at once.
      </p>
    </div>
  </form>
<?php endforeach; ?>
