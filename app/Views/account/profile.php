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
      <span>Business name <span class="hint">— blank if you trade under your own name</span></span>
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
      <div class="chipset-check">
        <?php foreach ($trades as $t): ?>
          <label class="chip-check">
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
      <div class="chipset-check">
        <?php foreach ($counties as $c): ?>
          <label class="chip-check">
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
    <div class="flash flash-note" style="margin-top:12px">
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

<?php
/*
 * Pictures.
 *
 * Their own forms, outside the one above, so a failed upload cannot take
 * somebody's rewritten bio down with it — a 9MB photo on a van's signal
 * fails in a dozen ways and none of them should cost you your text.
 *
 * Work photos are held for moderation and the logo is not. A logo is a
 * business's own mark on its own listing; a work photo is a picture of
 * somebody else's house, and the person whose kitchen it is never agreed to
 * anything.
 */
?>
<div class="panel" style="margin-top:26px">
  <div class="panel-h"><h3>Your logo</h3></div>
  <div style="padding:18px 20px;display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <?php if (!empty($profile['logo_path'])): ?>
      <img src="<?= e(url('/img/logo/' . (int) $profile['id'])) ?>" alt=""
           width="76" height="76"
           style="width:76px;height:76px;object-fit:contain;border:1px solid var(--line);
                  border-radius:4px;background:var(--paper-2);padding:6px">
    <?php else: ?>
      <div style="width:76px;height:76px;border:1px dashed var(--line-2);border-radius:4px;
                  display:flex;align-items:center;justify-content:center;color:var(--text-3)">
        <?= icon('tools', 22) ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="flex:1;min-width:230px"
          action="<?= e(url('/my/listing/photo')) ?>">
      <?= \FixListed\Core\Csrf::field() ?>
      <input type="hidden" name="kind" value="logo">
      <label class="field" for="logo-file">
        <span><?= !empty($profile['logo_path']) ? 'Replace your logo' : 'Add your logo' ?></span>
        <input id="logo-file" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
      </label>
      <button class="btn btn-ghost btn-sm" type="submit">Upload</button>
      <p class="tiny muted" style="margin-top:8px">
        PNG keeps a transparent background. It goes up straight away — no waiting.
      </p>
    </form>
  </div>
</div>

<div class="panel" style="margin-top:26px">
  <div class="panel-h">
    <h3>Photos of your work</h3>
    <span class="tiny muted" style="margin-left:auto">
      <?= count($photos) ?> of <?= (int) $maxPhotos ?>
    </span>
  </div>

  <div style="padding:18px 20px">
    <?php if ($photos !== []): ?>
      <div class="grid g3" style="gap:12px;margin-bottom:18px">
        <?php foreach ($photos as $ph): ?>
          <figure style="margin:0">
            <img src="<?= e(url('/img/' . (int) $ph['id'])) ?>" alt=""
                 loading="lazy" width="<?= (int) $ph['width'] ?>" height="<?= (int) $ph['height'] ?>"
                 style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:3px;
                        border:1px solid var(--line);display:block">
            <figcaption style="display:flex;align-items:center;gap:8px;margin-top:6px">
              <?php if ($ph['status'] === 'approved'): ?>
                <span class="badge b-live">On your profile</span>
              <?php else: ?>
                <span class="badge b-pending">Being checked</span>
              <?php endif; ?>
              <form method="post" style="margin-left:auto"
                    action="<?= e(url('/my/listing/photo/' . (int) $ph['id'] . '/remove')) ?>">
                <?= \FixListed\Core\Csrf::field() ?>
                <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
              </form>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (count($photos) < (int) $maxPhotos): ?>
      <form id="work-upload" method="post" enctype="multipart/form-data"
            action="<?= e(url('/my/listing/photo')) ?>">
        <?= \FixListed\Core\Csrf::field() ?>
        <label class="field" for="work-file">
          <span>Add a photo</span>
          <input id="work-file" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
        </label>
        <button class="btn btn-primary btn-sm" type="submit">Upload</button>
        <p class="tiny muted" style="margin-top:8px">
          Finished work, not work in progress — a homeowner is deciding whether to let you in the
          house. We check each one before it appears, usually the same day.
          <b>Location data is stripped</b> from every photo, so a picture of a job cannot give away
          where that job was.
        </p>
      </form>
    <?php else: ?>
      <p class="tiny muted">That is the most we show. Remove one to add another.</p>
    <?php endif; ?>
  </div>
</div>
