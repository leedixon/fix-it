<?php
/**
 * The listing application.
 *
 * @var array $trades
 * @var array $counties
 * @var array $market
 * @var array $old      what they submitted, when it came back with errors
 * @var array $errors   field => message
 */
require_once __DIR__ . '/../partials/icons.php';

$old = $old ?? [];
$errors = $errors ?? [];
$v   = static fn (string $k, string $d = ''): string => is_string($old[$k] ?? null) ? $old[$k] : $d;
$err = static fn (string $k): string => $errors[$k] ?? '';
$bad = static fn (string $k): string => isset($errors[$k]) ? ' bad' : '';
$chosen = static function (string $k, int $id) use ($old): bool {
    $vals = $old[$k] ?? [];
    return is_array($vals) && in_array((string) $id, array_map('strval', $vals), true);
};
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a>
    <span class="sep">/</span>
    <a href="<?= e(url('/for-pros')) ?>">For tradespeople</a>
    <span class="sep">/</span>
    <span>List your business</span>
  </nav>
</div>

<section class="sect-tight">
  <div class="wrap wiz">
    <div>
      <div class="eyebrow eyebrow-brass">Free listing</div>
      <h1 style="font-size:clamp(28px,4.4vw,42px);margin-top:10px">List your business</h1>
      <p class="muted" style="margin-top:12px;max-width:56ch">
        One form. We check your licence and insurance, then your profile goes live across
        <?= e($market['name']) ?>. No commission and no per-lead charge, ever.
      </p>

      <?php if ($err('_form') !== ''): ?>
        <div class="flash flash-bad" style="margin-top:22px">
          <?= icon('alert', 17) ?><span><?= e($err('_form')) ?></span>
        </div>
      <?php elseif ($errors !== []): ?>
        <div class="flash flash-bad" style="margin-top:22px">
          <?= icon('alert', 17) ?>
          <span>Nothing was sent yet — there <?= count($errors) === 1 ? 'is one thing' : 'are a few things' ?>
          to fix below. Everything you typed is still here.</span>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/list-your-business')) ?>" class="wizcard" style="margin-top:24px">
        <?= \FixListed\Core\Csrf::field() ?>
        <?php /* Hidden from people, irresistible to naive bots. */ ?>
        <div style="position:absolute;left:-9999px" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <h2 style="font-size:20px">Who you are</h2>
        <div class="grid g2" style="margin-top:16px;gap:0 18px">
          <label class="field">
            <span>First name</span>
            <input class="<?= e(trim($bad('first_name'))) ?>" type="text" name="first_name"
                   value="<?= e($v('first_name')) ?>" autocomplete="given-name" required>
            <?php if ($err('first_name')): ?><b class="err"><?= e($err('first_name')) ?></b><?php endif; ?>
          </label>
          <label class="field">
            <span>Last name</span>
            <input class="<?= e(trim($bad('last_name'))) ?>" type="text" name="last_name"
                   value="<?= e($v('last_name')) ?>" autocomplete="family-name" required>
            <?php if ($err('last_name')): ?><b class="err"><?= e($err('last_name')) ?></b><?php endif; ?>
          </label>
          <label class="field">
            <span>Email</span>
            <input class="<?= e(trim($bad('email'))) ?>" type="email" name="email"
                   value="<?= e($v('email')) ?>" autocomplete="email" required>
            <?php if ($err('email')): ?><b class="err"><?= e($err('email')) ?></b><?php endif; ?>
          </label>
          <label class="field">
            <span>Phone</span>
            <input class="<?= e(trim($bad('phone'))) ?>" type="tel" name="phone"
                   value="<?= e($v('phone')) ?>" autocomplete="tel" required>
            <?php if ($err('phone')): ?><b class="err"><?= e($err('phone')) ?></b><?php endif; ?>
          </label>
        </div>

        <label class="field">
          <span>Business name <span class="hint">— leave blank if you trade under your own name</span></span>
          <input class="<?= e(trim($bad('business_name'))) ?>" type="text" name="business_name"
                 value="<?= e($v('business_name')) ?>" placeholder="e.g. Ojo Plumbing" autocomplete="organization">
          <?php if ($err('business_name')): ?><b class="err"><?= e($err('business_name')) ?></b><?php endif; ?>
        </label>

        <hr class="hr" style="margin:26px 0">
        <h2 style="font-size:20px">What you do</h2>

        <label class="field" style="margin-top:16px">
          <span>Your trades — tick everything you take on</span>
          <div class="chipset-check">
            <?php foreach ($trades as $t): ?>
              <label class="chip-check">
                <input type="checkbox" name="trades[]" value="<?= (int) $t['id'] ?>"
                       <?= $chosen('trades', (int) $t['id']) ? 'checked' : '' ?>>
                <?= e($t['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($err('trades')): ?><b class="err"><?= e($err('trades')) ?></b><?php endif; ?>
        </label>

        <label class="field">
          <span>Counties you will drive to — the first one is your base</span>
          <div class="chipset-check">
            <?php foreach ($counties as $c): ?>
              <label class="chip-check">
                <input type="checkbox" name="counties[]" value="<?= (int) $c['id'] ?>"
                       <?= $chosen('counties', (int) $c['id']) ? 'checked' : '' ?>>
                <?= e($c['short_name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($err('counties')): ?><b class="err"><?= e($err('counties')) ?></b><?php endif; ?>
        </label>

        <label class="field">
          <span>One line describing you</span>
          <input class="<?= e(trim($bad('headline'))) ?>" type="text" name="headline"
                 value="<?= e($v('headline')) ?>" placeholder="e.g. Master Plumber — 18 years" required>
          <?php if ($err('headline')): ?><b class="err"><?= e($err('headline')) ?></b><?php endif; ?>
        </label>

        <label class="field">
          <span>About your business — this is what homeowners read first</span>
          <textarea class="<?= e(trim($bad('bio'))) ?>" name="bio" rows="6" required
            placeholder="What you specialise in, how you quote, what a homeowner should expect. Write it the way you would say it."><?= e($v('bio')) ?></textarea>
          <?php if ($err('bio')): ?><b class="err"><?= e($err('bio')) ?></b><?php endif; ?>
        </label>

        <div class="grid g3" style="gap:0 18px">
          <label class="field">
            <span>Years doing this</span>
            <input type="number" name="years_experience" min="0" max="70" value="<?= e($v('years_experience')) ?>">
          </label>
          <label class="field">
            <span>Hourly rate <span class="hint">— optional</span></span>
            <input type="text" name="hourly_rate" value="<?= e($v('hourly_rate')) ?>" placeholder="$75" inputmode="decimal">
          </label>
          <label class="field">
            <span>Base ZIP</span>
            <input type="text" name="zip" value="<?= e($v('zip')) ?>" placeholder="61032" inputmode="numeric">
          </label>
        </div>

        <hr class="hr" style="margin:26px 0">
        <h2 style="font-size:20px">Licence and insurance</h2>
        <p class="muted small" style="margin-top:8px">
          Leave these blank if your trade does not require a licence. We check whatever you give
          us before the badge appears on your profile.
        </p>

        <div class="grid g3" style="margin-top:16px;gap:0 18px">
          <label class="field">
            <span>Licence number</span>
            <input type="text" name="license_number" value="<?= e($v('license_number')) ?>" placeholder="058-123456">
          </label>
          <label class="field">
            <span>Issuing state</span>
            <input type="text" name="license_state" value="<?= e($v('license_state', 'IL')) ?>" maxlength="2" size="2">
          </label>
          <label class="field">
            <span>Insurance carrier</span>
            <input type="text" name="insurance_carrier" value="<?= e($v('insurance_carrier')) ?>" placeholder="e.g. State Farm">
          </label>
        </div>

        <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:10px">
          Send my application <?= icon('arrow', 15) ?>
        </button>
        <p class="tiny muted" style="margin-top:12px;text-align:center">
          No payment, no card, no account to set up. We will email you within two working days.
        </p>
      </form>
    </div>

    <aside class="summary">
      <div class="panel-h"><h3>What it costs</h3></div>
      <div class="sum-row"><span class="k">Profile</span><span>Free</span></div>
      <div class="sum-row"><span class="k">Quoting a job</span><span>Free</span></div>
      <div class="sum-row"><span class="k">Commission on work won</span><span>None</span></div>
      <div class="sum-row"><span class="k">Per-lead charge</span><span>None</span></div>
      <div class="sum-row total"><span>Total</span><span>$0</span></div>
      <div class="seal">
        <?= icon('shield', 16) ?>
        <span>Your licence and insurance are checked by a person before your profile goes live.
        That is what the verified badge on the site means.</span>
      </div>
    </aside>
  </div>
</section>
