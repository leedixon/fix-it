<?php
/**
 * The posting form.
 *
 * One page, not a wizard. A four-step wizard for eight fields loses people at
 * every step boundary, and everything here fits on one screen on a laptop.
 *
 * @var array $trades @var array $cities @var int $fee @var int $listingDays
 * @var int $refundHours @var array $old @var array $errors @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';
$v   = static fn (string $k, string $d = ''): string => is_string($old[$k] ?? null) ? $old[$k] : $d;
$err = static fn (string $k): string => $errors[$k] ?? '';
$bad = static fn (string $k): string => isset($errors[$k]) ? ' bad' : '';
?>

<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap wiz">
    <div>
      <div class="eyebrow eyebrow-brass">One flat fee</div>
      <h1 style="font-size:clamp(28px,4.4vw,42px);margin-top:10px">Post a job</h1>
      <p class="muted" style="margin-top:12px;max-width:56ch">
        Describe what needs doing. Every tradesperson covering your county sees it, quotes come to
        you, and you deal with them directly.
      </p>

      <?php if ($err('_form') !== ''): ?>
        <div class="flash flash-bad" style="margin-top:22px">
          <?= icon('alert', 17) ?><span><?= e($err('_form')) ?></span>
        </div>
      <?php elseif ($errors !== []): ?>
        <div class="flash flash-bad" style="margin-top:22px">
          <?= icon('alert', 17) ?>
          <span>Nothing has been charged — there <?= count($errors) === 1 ? 'is one thing' : 'are a few things' ?>
          to fix below. Everything you typed is still here.</span>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/post-a-job')) ?>" class="wizcard" style="margin-top:24px">
        <?= \FixListed\Core\Csrf::field() ?>
        <div style="position:absolute;left:-9999px" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <h2 style="font-size:20px">The job</h2>

        <label class="field" style="margin-top:16px">
          <span>A short title</span>
          <input class="<?= e(trim($bad('title'))) ?>" type="text" name="title" value="<?= e($v('title')) ?>"
                 placeholder="e.g. Sump pump failed, water in the basement" required>
          <?php if ($err('title')): ?><b class="err"><?= e($err('title')) ?></b><?php endif; ?>
        </label>

        <label class="field">
          <span>What needs doing</span>
          <textarea class="<?= e(trim($bad('description'))) ?>" name="description" rows="7" required
            placeholder="What is wrong, what you have tried, anything a tradesperson would need to know before quoting. The more you write, the better the quotes."><?= e($v('description')) ?></textarea>
          <?php if ($err('description')): ?><b class="err"><?= e($err('description')) ?></b><?php endif; ?>
        </label>

        <div class="grid g2" style="gap:0 18px">
          <label class="field">
            <span>Trade</span>
            <select class="<?= e(trim($bad('trade_id'))) ?>" name="trade_id" required>
              <option value="">Choose one…</option>
              <?php foreach ($trades as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= $v('trade_id') === (string) $t['id'] ? 'selected' : '' ?>>
                  <?= e($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($err('trade_id')): ?><b class="err"><?= e($err('trade_id')) ?></b><?php endif; ?>
          </label>

          <label class="field">
            <span>When</span>
            <select name="urgency">
              <?php foreach (['asap' => 'As soon as possible', 'this_week' => 'This week',
                              'this_month' => 'This month', 'flexible' => 'No rush'] as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $v('urgency', 'this_week') === $key ? 'selected' : '' ?>>
                  <?= e($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Town</span>
            <select class="<?= e(trim($bad('city_id'))) ?>" name="city_id" required>
              <option value="">Choose one…</option>
              <?php $county = null; foreach ($cities as $c): ?>
                <?php if ($county !== $c['county']): ?>
                  <?php if ($county !== null): ?></optgroup><?php endif; ?>
                  <optgroup label="<?= e($c['county']) ?>">
                  <?php $county = $c['county']; ?>
                <?php endif; ?>
                <option value="<?= (int) $c['id'] ?>" <?= $v('city_id') === (string) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach; ?>
              <?php if ($county !== null): ?></optgroup><?php endif; ?>
            </select>
            <?php if ($err('city_id')): ?><b class="err"><?= e($err('city_id')) ?></b><?php endif; ?>
          </label>

          <label class="field">
            <span>ZIP code</span>
            <input class="<?= e(trim($bad('zip'))) ?>" type="text" name="zip" value="<?= e($v('zip')) ?>"
                   placeholder="61032" inputmode="numeric" required>
            <?php if ($err('zip')): ?><b class="err"><?= e($err('zip')) ?></b><?php endif; ?>
          </label>
        </div>

        <label class="field">
          <span>Rough budget <span class="hint">— optional, but jobs with one get more quotes</span></span>
          <div class="grid g2" style="gap:0 14px">
            <input type="text" name="budget_min" value="<?= e($v('budget_min')) ?>" placeholder="$200" inputmode="decimal">
            <input class="<?= e(trim($bad('budget_max'))) ?>" type="text" name="budget_max"
                   value="<?= e($v('budget_max')) ?>" placeholder="$600" inputmode="decimal">
          </div>
          <?php if ($err('budget_max')): ?><b class="err"><?= e($err('budget_max')) ?></b><?php endif; ?>
        </label>

        <hr class="hr" style="margin:26px 0">
        <h2 style="font-size:20px">Where quotes go</h2>
        <p class="muted small" style="margin-top:8px">
          Your name and number are <strong>not</strong> published. They go only to a tradesperson
          you choose to share them with.
        </p>

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

        <button class="btn btn-primary btn-lg btn-block" type="submit">
          <?= $fee > 0 ? 'Continue to payment — ' . e(money($fee)) : 'Post this job — free' ?>
          <?= icon('arrow', 15) ?>
        </button>
        <p class="tiny muted" style="margin-top:12px;text-align:center">
          <?= $fee > 0
            ? 'You will see your job once more before anything is charged.'
            : 'This market is free to post in at the moment.' ?>
        </p>
      </form>
    </div>

    <aside class="summary">
      <div class="panel-h"><h3>What you pay</h3></div>
      <div class="sum-row"><span class="k">Listing</span><span><?= e(money($fee)) ?></span></div>
      <div class="sum-row"><span class="k">Quotes</span><span>Unlimited</span></div>
      <div class="sum-row"><span class="k">Commission on the work</span><span>None</span></div>
      <div class="sum-row total"><span>Total</span><span><?= e(money($fee)) ?></span></div>

      <div class="seal">
        <?= icon('check', 16) ?>
        <span>Live for <?= $listingDays ?> days, seen by every tradesperson covering your county.</span>
      </div>
      <div class="seal">
        <?= icon('shield', 16) ?>
        <span>No quotes within <?= $refundHours ?> hours and the fee comes back automatically, to the
        card that paid it.</span>
      </div>
      <div class="seal">
        <?= icon('lock', 16) ?>
        <span>Card handled by Stripe. Fix Listed never sees the number.</span>
      </div>
    </aside>
  </div>
</section>
