<?php
/** @var array $profile @var array $trades @var array $counties
 *  @var array $myTrades @var array $myCounties @var array $old @var array $errors */
require_once __DIR__ . '/../partials/icons.php';
$val = static fn (string $k, mixed $fallback = ''): string =>
    is_string($old[$k] ?? null) ? $old[$k] : (string) ($fallback ?? '');
$err = static fn (string $k): string => $errors[$k] ?? '';
$rate = $profile['hourly_rate_cents'] !== null
    ? rtrim(rtrim(number_format((int) $profile['hourly_rate_cents'] / 100, 2, '.', ''), '0'), '.') : '';
?>
<div class="adm-h">
  <div>
    <h1 style="font-size:28px">Your listing</h1>
    <p>This is what homeowners see. Changes are live straight away.</p>
  </div>
  <?php if ($profile['status'] === 'active'): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/pros/' . $profile['slug'])) ?>" target="_blank" rel="noopener">
      View it <?= icon('arrow', 13) ?>
    </a>
  <?php endif; ?>
</div>

<?php if ($errors !== []): ?>
  <div class="flash flash-bad">
    <?= icon('alert', 17) ?>
    <span>Nothing was saved — there <?= count($errors) === 1 ? 'is one thing' : 'are a few things' ?> to fix below.</span>
  </div>
<?php endif; ?>

<form class="panel" method="post" action="<?= e(url('/my/listing')) ?>">
  <?= \FixListed\Core\Csrf::field() ?>
  <div class="panel-h"><h3>What homeowners see</h3></div>
  <div style="padding:20px">

    <label class="field">
      <span>Business name <em style="text-transform:none;letter-spacing:0;font-style:normal">— blank if you trade under your own name</em></span>
      <input type="text" name="business_name" value="<?= e($val('business_name', $profile['business_name'])) ?>">
    </label>

    <label class="field">
      <span>One line describing you</span>
      <input class="<?= $err('headline') ? 'bad' : '' ?>" type="text" name="headline"
             value="<?= e($val('headline', $profile['headline'])) ?>" required>
      <?php if ($err('headline')): ?><b class="err"><?= e($err('headline')) ?></b><?php endif; ?>
    </label>

    <label class="field">
      <span>About your business</span>
      <textarea class="<?= $err('bio') ? 'bad' : '' ?>" name="bio" rows="7" required><?= e($val('bio', $profile['bio'])) ?></textarea>
      <?php if ($err('bio')): ?><b class="err"><?= e($err('bio')) ?></b><?php endif; ?>
    </label>

    <label class="field">
      <span>Your trades</span>
      <div class="chipset" style="margin-top:2px">
        <?php foreach ($trades as $t): ?>
          <label class="chip" style="display:flex;align-items:center;gap:7px;cursor:pointer">
            <input type="checkbox" name="trades[]" value="<?= (int) $t['id'] ?>"
                   <?= in_array((int) $t['id'], $myTrades, true) ? 'checked' : '' ?>>
            <?= e($t['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if ($err('trades')): ?><b class="err"><?= e($err('trades')) ?></b><?php endif; ?>
    </label>

    <label class="field">
      <span>Counties you cover — this decides which jobs you see</span>
      <div class="chipset" style="margin-top:2px">
        <?php foreach ($counties as $c): ?>
          <label class="chip" style="display:flex;align-items:center;gap:7px;cursor:pointer">
            <input type="checkbox" name="counties[]" value="<?= (int) $c['id'] ?>"
                   <?= in_array((int) $c['id'], $myCounties, true) ? 'checked' : '' ?>>
            <?= e($c['short_name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if ($err('counties')): ?><b class="err"><?= e($err('counties')) ?></b><?php endif; ?>
    </label>

    <div class="grid g3" style="gap:0 18px">
      <label class="field">
        <span>Years doing this</span>
        <input type="number" name="years_experience" min="0" max="70"
               value="<?= e($val('years_experience', (string) $profile['years_experience'])) ?>">
      </label>
      <label class="field">
        <span>Hourly rate</span>
        <input type="text" name="hourly_rate" value="<?= e($val('hourly_rate', $rate)) ?>" placeholder="$75" inputmode="decimal">
      </label>
      <label class="field">
        <span>Base ZIP</span>
        <input type="text" name="zip" value="<?= e($val('zip', $profile['base_zip'])) ?>" inputmode="numeric">
      </label>
    </div>

    <hr class="hr" style="margin:22px 0">
    <h3 style="font-size:17px">Licence and insurance</h3>
    <div class="flash flash-bad" style="margin-top:12px;background:var(--paper-2);color:var(--text-2);border-color:var(--line)">
      <?= icon('info', 17) ?>
      <span>Changing your licence number takes the verified badge off your profile and sends it back
      for a quick re-check. That is what makes the badge worth anything.</span>
    </div>

    <div class="grid g2" style="gap:0 18px;margin-top:16px">
      <label class="field">
        <span>Licence number</span>
        <input type="text" name="license_number" value="<?= e($val('license_number', $profile['license_number'])) ?>">
      </label>
      <label class="field">
        <span>Insurance carrier</span>
        <input type="text" name="insurance_carrier" value="<?= e($val('insurance_carrier', $profile['insurance_carrier'])) ?>">
      </label>
    </div>

    <button class="btn btn-primary btn-lg" type="submit">Save my listing</button>
  </div>
</form>
