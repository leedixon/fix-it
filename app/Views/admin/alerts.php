<?php
/**
 * Job alert subscribers.
 *
 * Read this as a call list rather than a mailing list. Everybody on it wants
 * work in a named trade and a named county and has not taken out a listing,
 * which makes it the most useful page on this site while supply is the thing
 * holding everything up.
 *
 * @var array $alerts @var array $tally
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-head">
  <div>
    <h1>Job alerts</h1>
    <p class="muted small">
      Tradespeople who asked to hear about new jobs. Free, and no listing required — which is
      why this doubles as a list of people worth ringing.
    </p>
  </div>
</div>

<div class="adm-stats" style="margin-bottom:20px">
  <div class="card" style="padding:16px">
    <div class="k">Getting alerts</div>
    <div class="v mono" style="font-size:26px"><?= (int) $tally['live'] ?></div>
  </div>
  <div class="card" style="padding:16px">
    <div class="k">Not confirmed</div>
    <div class="v mono" style="font-size:26px"><?= (int) $tally['pending'] ?></div>
    <div class="tiny muted">nothing is sent to these</div>
  </div>
  <div class="card" style="padding:16px">
    <div class="k">Went on to list</div>
    <div class="v mono" style="font-size:26px"><?= (int) $tally['converted'] ?></div>
  </div>
  <div class="card" style="padding:16px">
    <div class="k">Unsubscribed</div>
    <div class="v mono" style="font-size:26px"><?= (int) $tally['gone'] ?></div>
  </div>
</div>

<?php if ($alerts === []): ?>
  <div class="card" style="padding:40px;text-align:center">
    <?= icon('mail', 24) ?>
    <p style="margin-top:10px;font-size:17px">Nobody has signed up yet.</p>
    <p class="muted small" style="margin-top:6px">
      The signup is linked from the jobs board — <span class="mono">/jobs/alerts</span>.
    </p>
  </div>
<?php else: ?>
  <div class="panel">
    <div class="tablewrap">
      <table>
        <thead><tr>
          <th>Who</th><th>Trades</th><th>Counties</th><th>State</th><th>Signed up</th>
        </tr></thead>
        <tbody>
        <?php foreach ($alerts as $a): ?>
          <?php
            $live      = $a['confirmed_at'] !== null && $a['unsubscribed_at'] === null;
            $converted = $a['became_pro_id'] !== null;
          ?>
          <tr>
            <td>
              <strong><?= e(trim((string) $a['business_name']) !== ''
                  ? $a['business_name']
                  : (trim((string) $a['first_name']) !== '' ? $a['first_name'] : $a['email'])) ?></strong>
              <div class="tiny muted"><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></div>
              <?php if ($converted): ?>
                <div class="tiny muted">
                  now listed as
                  <a href="<?= e(url('/pros/' . $a['pro_slug'])) ?>" target="_blank" rel="noopener">
                    <?= e($a['pro_business'] ?: $a['pro_slug']) ?>
                  </a>
                </div>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($a['trades'] ?? '—') ?></td>
            <td class="small"><?= e($a['counties'] ?? '—') ?></td>
            <td>
              <?php if ($converted): ?>
                <span class="badge b-verified">Listed</span>
              <?php elseif ($a['unsubscribed_at'] !== null): ?>
                <span class="badge b-flat">Unsubscribed</span>
              <?php elseif ($live): ?>
                <span class="badge b-live">Active</span>
                <?php if ((int) $a['sent_count'] > 0): ?>
                  <div class="tiny muted" style="margin-top:4px"><?= (int) $a['sent_count'] ?> sent</div>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge b-pending">Unconfirmed</span>
              <?php endif; ?>
            </td>
            <td class="tiny muted"><?= e(ago((string) $a['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
