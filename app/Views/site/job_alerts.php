<?php
/**
 * The job-alert signup — the page "Get these by email" should always have
 * gone to.
 *
 * Chips, not a multi-select. A tradesperson standing in a van picking three
 * trades and two counties from a <select multiple> is a tradesperson who
 * gives up; the chip pattern is already used by the listing form for exactly
 * this choice, and it works with one thumb and no JavaScript.
 *
 * @var array $trades @var array $counties @var array $old @var array $errors
 * @var array $market @var array $crumbs
 */
require_once __DIR__ . '/../partials/icons.php';
$v    = static fn (string $k): string => is_string($old[$k] ?? null) ? $old[$k] : '';
$err  = static fn (string $k): string => $errors[$k] ?? '';
$many = static fn (string $k): array => array_map('strval', (array) ($old[$k] ?? []));
?>

<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap" style="max-width:680px">
    <div class="eyebrow eyebrow-brass"><?= icon('mail', 14) ?> Free, no listing needed</div>
    <h1 style="font-size:clamp(28px,4.4vw,42px);margin-top:12px">Get new jobs by email</h1>
    <p class="muted" style="margin-top:14px;font-size:17px;max-width:58ch">
      Pick your trades and the counties you will drive to. When a homeowner posts a job that
      matches, it lands in your inbox — usually within a minute of them paying for it.
    </p>
    <p class="muted" style="margin-top:10px;max-width:58ch">
      It costs nothing, we do not sell your address, and you can stop it from any email we send.
      <b>You do not need a listing for this</b> — though if you take one out, the alerts keep
      coming and homeowners can find you the rest of the time too.
    </p>

    <?php if ($errors !== []): ?>
      <div class="flash flash-bad" style="margin-top:22px">
        <?= icon('alert', 17) ?>
        <span>There <?= count($errors) === 1 ? 'is one thing' : 'are a few things' ?> to fix below.</span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/jobs/alerts')) ?>" class="card"
          style="margin-top:24px;padding:24px">
      <?= \FixListed\Core\Csrf::field() ?>
      <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <label class="field" for="al-email">
        <span>Your email</span>
        <input id="al-email" type="email" name="email" required autocomplete="email"
               class="<?= $err('email') !== '' ? 'bad' : '' ?>"
               value="<?= e($v('email')) ?>" placeholder="you@yourbusiness.com">
        <?php if ($err('email') !== ''): ?><span class="err"><?= e($err('email')) ?></span><?php endif; ?>
      </label>

      <div class="grid g2" style="gap:14px">
        <label class="field" for="al-first">
          <span>First name (optional)</span>
          <input id="al-first" type="text" name="first_name" maxlength="80"
                 autocomplete="given-name" value="<?= e($v('first_name')) ?>">
        </label>
        <label class="field" for="al-biz">
          <span>Business name (optional)</span>
          <input id="al-biz" type="text" name="business_name" maxlength="160"
                 autocomplete="organization" value="<?= e($v('business_name')) ?>">
        </label>
      </div>

      <fieldset style="border:0;padding:0;margin:18px 0 0">
        <legend class="leg">What do you do?</legend>
        <?php if ($err('trades') !== ''): ?>
          <p class="err" style="margin:4px 0 0"><?= e($err('trades')) ?></p>
        <?php endif; ?>
        <div class="chips" style="margin-top:10px">
          <?php foreach ($trades as $t): ?>
            <label class="chip-check">
              <input type="checkbox" name="trades[]" value="<?= e($t['id']) ?>"
                     <?= in_array((string) $t['id'], $many('trades'), true) ? 'checked' : '' ?>>
              <span><?= e($t['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset style="border:0;padding:0;margin:20px 0 0">
        <legend class="leg">Where will you travel?</legend>
        <?php if ($err('counties') !== ''): ?>
          <p class="err" style="margin:4px 0 0"><?= e($err('counties')) ?></p>
        <?php endif; ?>
        <div class="chips" style="margin-top:10px">
          <?php foreach ($counties as $c): ?>
            <label class="chip-check">
              <input type="checkbox" name="counties[]" value="<?= e($c['id']) ?>"
                     <?= in_array((string) $c['id'], $many('counties'), true) ? 'checked' : '' ?>>
              <span><?= e($c['short_name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:24px">
        Send me matching jobs
      </button>
      <p class="tiny muted" style="margin-top:12px;text-align:center">
        We will email you once to check the address is yours. Nothing is sent until you click it.
      </p>
    </form>

    <div class="card" style="margin-top:18px;padding:18px;display:flex;gap:12px;align-items:flex-start">
      <?= icon('info', 17) ?>
      <p class="small muted" style="line-height:1.65">
        <b>Already listed with us?</b> You get these automatically, for every job in the counties
        and trades on your profile. There is nothing to sign up for.
        <a href="<?= e(url('/list-your-business')) ?>">Listing is free too</a>.
      </p>
    </div>
  </div>
</section>
