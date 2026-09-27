<?php
/**
 * The review form — reached only from the link in a "how did it go?" email.
 *
 * Two questions, one screen. Who did you hire, and how was it. Anything more
 * and the thing people actually came to do — press five hammers and leave —
 * gets buried under a form.
 *
 * The rating is five radio buttons dressed as hammers. Radios rather than a
 * star-widget because this site ships one JavaScript file and it is not this:
 * a radio group works with no script, works with a keyboard, is announced
 * properly by a screen reader, and cannot be submitted half-set.
 *
 * @var array  $job    @var array $pros  @var string $token
 * @var int    $maxBody
 * @var array  $old    @var array $errors
 */
require_once __DIR__ . '/../partials/icons.php';
$v   = static fn (string $k, string $d = ''): string => is_string($old[$k] ?? null) ? $old[$k] : $d;
$err = static fn (string $k): string => $errors[$k] ?? '';

/** The words under each hammer count. A bare number means nothing. */
$words = [
    1 => 'Poor',
    2 => 'Below par',
    3 => 'Fine',
    4 => 'Good',
    5 => 'Excellent',
];
?>

<section class="sect-tight">
  <div class="wrap" style="max-width:640px">
    <div class="eyebrow eyebrow-brass">Job <?= e($job['reference']) ?></div>
    <h1 style="font-size:clamp(26px,4.2vw,38px);margin-top:10px">How did it go?</h1>
    <p class="muted" style="margin-top:12px">
      You posted <b><?= e($job['title']) ?></b>. Telling other people round here how it went is
      the whole reason this directory is worth anything.
    </p>

    <?php if ($errors !== []): ?>
      <div class="flash flash-bad" style="margin-top:22px">
        <?= icon('alert', 17) ?>
        <span>There <?= count($errors) === 1 ? 'is one thing' : 'are a couple of things' ?>
        to fix below. Everything you typed is still here.</span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/review/' . $token)) ?>" class="card"
          style="margin-top:24px;padding:24px">
      <?= \FixListed\Core\Csrf::field() ?>

      <?php /* --- who --------------------------------------------------- */ ?>
      <fieldset style="border:0;padding:0;margin:0">
        <legend class="leg">Which one did you hire?</legend>
        <p class="tiny muted" style="margin:4px 0 12px">
          Only the businesses that quoted this job are listed.
        </p>
        <?php if ($err('pro_id') !== ''): ?>
          <p class="err" style="margin:0 0 10px"><?= e($err('pro_id')) ?></p>
        <?php endif; ?>

        <div class="pick">
          <?php foreach ($pros as $p): ?>
            <label class="pick-row">
              <input type="radio" name="pro_id" value="<?= e($p['id']) ?>"
                     <?= $v('pro_id') === (string) $p['id'] ? 'checked' : '' ?>>
              <span>
                <b><?= e($p['business_name']) ?></b>
                <?php if ((int) $p['is_demo'] === 1): ?>
                  <span class="badge b-sample">Sample</span>
                <?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <?php /* --- how many hammers ---------------------------------------- */ ?>
      <fieldset style="border:0;padding:0;margin:26px 0 0">
        <legend class="leg">How was the work?</legend>
        <?php if ($err('rating') !== ''): ?>
          <p class="err" style="margin:4px 0 0"><?= e($err('rating')) ?></p>
        <?php endif; ?>

        <div class="hammers">
          <?php foreach ($words as $n => $word): ?>
            <label class="hammer-opt">
              <input type="radio" name="rating" value="<?= $n ?>"
                     <?= $v('rating') === (string) $n ? 'checked' : '' ?>>
              <span class="hammer-mark"><?= rating_marks((float) $n, 17) ?></span>
              <span class="hammer-word"><?= e($word) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <?php /* --- and what happened ---------------------------------------- */ ?>
      <label class="field" for="body" style="margin-top:26px">
        <span>What happened? (optional)</span>
        <p class="tiny muted" style="margin:-2px 0 8px;letter-spacing:0;text-transform:none;font-family:var(--sans)">
          What they did, whether they turned up when they said, whether the price held. This is
          published next to their name once we have read it.
        </p>
        <textarea id="body" name="body" rows="5" maxlength="<?= (int) $maxBody ?>"
                  class="<?= $err('body') !== '' ? 'bad' : '' ?>"
                  placeholder="They came out the same day and…"><?= e($v('body')) ?></textarea>
        <?php if ($err('body') !== ''): ?><span class="err"><?= e($err('body')) ?></span><?php endif; ?>
      </label>

      <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:26px">
        Send it in
      </button>

      <p class="tiny muted" style="margin-top:14px;text-align:center">
        We read every review before it appears. Your first name and last initial are shown with it;
        your email address never is.
      </p>
    </form>
  </div>
</section>
