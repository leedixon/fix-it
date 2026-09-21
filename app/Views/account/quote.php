<?php
/** @var array $job @var array $old @var array $errors */
require_once __DIR__ . '/../partials/icons.php';
$v   = static fn (string $k, string $d = ''): string => is_string($old[$k] ?? null) ? $old[$k] : $d;
$err = static fn (string $k): string => $errors[$k] ?? '';
$type = $v('amount_type', 'fixed');
?>
<div class="adm-h">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/my')) ?>">← Jobs for you</a></p>
    <h1 style="font-size:26px;margin-top:8px"><?= e($job['title']) ?></h1>
    <p><?= e($job['trade_name']) ?> · <?= e($job['zip']) ?> · <?= e(urgency_label((string) $job['urgency'])) ?></p>
  </div>
</div>

<?php if ($err('_form') !== ''): ?>
  <div class="flash flash-bad"><?= icon('alert', 17) ?><span><?= e($err('_form')) ?></span></div>
<?php endif; ?>

<div class="adm-review">
  <form class="panel" method="post" action="<?= e(url('/my/quote/' . $job['reference'])) ?>">
    <?= \FixListed\Core\Csrf::field() ?>
    <div class="panel-h"><h3>Your quote</h3></div>
    <div style="padding:20px">

      <label class="field">
        <span>How you are pricing this</span>
        <select name="amount_type" id="qtype">
          <option value="fixed"           <?= $type === 'fixed' ? 'selected' : '' ?>>A fixed price</option>
          <option value="range"           <?= $type === 'range' ? 'selected' : '' ?>>A range</option>
          <option value="hourly"          <?= $type === 'hourly' ? 'selected' : '' ?>>An hourly rate</option>
          <option value="visit_required"  <?= $type === 'visit_required' ? 'selected' : '' ?>>I need to see it first</option>
        </select>
      </label>

      <div class="grid g2" style="gap:0 18px">
        <label class="field">
          <span>Amount</span>
          <input class="<?= $err('amount') ? 'bad' : '' ?>" type="text" name="amount"
                 value="<?= e($v('amount')) ?>" placeholder="$450" inputmode="decimal">
          <?php if ($err('amount')): ?><b class="err"><?= e($err('amount')) ?></b><?php endif; ?>
        </label>
        <label class="field">
          <span>Top of the range <em style="text-transform:none;letter-spacing:0;font-style:normal">— only for a range</em></span>
          <input class="<?= $err('amount_max') ? 'bad' : '' ?>" type="text" name="amount_max"
                 value="<?= e($v('amount_max')) ?>" placeholder="$700" inputmode="decimal">
          <?php if ($err('amount_max')): ?><b class="err"><?= e($err('amount_max')) ?></b><?php endif; ?>
        </label>
      </div>

      <label class="field">
        <span>When you could start</span>
        <input class="<?= $err('can_start_on') ? 'bad' : '' ?>" type="date" name="can_start_on" value="<?= e($v('can_start_on')) ?>">
        <?php if ($err('can_start_on')): ?><b class="err"><?= e($err('can_start_on')) ?></b><?php endif; ?>
      </label>

      <label class="field">
        <span>Your message to the homeowner</span>
        <textarea class="<?= $err('message') ? 'bad' : '' ?>" name="message" rows="7" required
          placeholder="What you would do, what is included, anything you would need to check first. The quotes that get picked read like a person wrote them."><?= e($v('message')) ?></textarea>
        <?php if ($err('message')): ?><b class="err"><?= e($err('message')) ?></b><?php endif; ?>
      </label>

      <button class="btn btn-primary btn-block btn-lg" type="submit">Send this quote</button>
      <p class="tiny muted" style="margin-top:10px;text-align:center">
        Free to send. You deal with the homeowner directly and keep the whole job.
      </p>
    </div>
  </form>

  <aside>
    <div class="panel" style="margin-bottom:18px">
      <div class="panel-h"><h3>The job</h3></div>
      <div style="padding:18px 20px">
        <div class="kv"><span class="k">Budget</span><span class="mono"><?= e(budget(
            $job['budget_min_cents'] !== null ? (int) $job['budget_min_cents'] : null,
            $job['budget_max_cents'] !== null ? (int) $job['budget_max_cents'] : null)) ?></span></div>
        <div class="kv"><span class="k">Timing</span><span><?= e(urgency_label((string) $job['urgency'])) ?></span></div>
        <div class="kv"><span class="k">Area</span><span class="mono"><?= e($job['zip']) ?></span></div>
        <div class="kv"><span class="k">Quotes so far</span><span class="mono"><?= (int) $job['quote_count'] ?></span></div>
        <div class="kv"><span class="k">Posted</span><span><?= e(ago($job['published_at'])) ?></span></div>

        <h3 style="font-size:15px;margin:18px 0 8px">What they wrote</h3>
        <div class="bio" style="font-size:14.5px">
          <?php foreach (preg_split('/\n\s*\n/', (string) $job['description']) ?: [] as $p): ?>
            <p><?= e(trim($p)) ?></p>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="seal" style="border:1px solid var(--line);border-radius:4px">
      <?= icon('info', 16) ?>
      <span>One quote per job. If you need to change it after sending, reply to the homeowner
      directly — they have your details once you quote.</span>
    </div>
  </aside>
</div>
