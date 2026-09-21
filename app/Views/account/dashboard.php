<?php
/** @var array $jobs @var array $myQuotes @var array $stats @var array|null $profile @var bool $isLive */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-h">
  <div>
    <h1 style="font-size:28px">Jobs for you</h1>
    <p>Open work in the counties on your listing. Quoting is free and we take nothing from what you win.</p>
  </div>
</div>

<?php if (!$isLive): ?>
  <div class="empty" style="margin-bottom:26px">
    <div class="ic"><?= icon('clock', 24) ?></div>
    <h3>Your listing is still being checked</h3>
    <p>Once a person has confirmed your licence and insurance, jobs in your counties appear here
       and you can start quoting. It is usually a day or two.</p>
    <p class="tiny muted">Want to speed it up? Reply to your application email with a photo of your
       licence and certificate of insurance.</p>
  </div>
<?php endif; ?>

<div class="adm-stats" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat">
    <div class="k">Quotes sent</div>
    <div class="v"><?= (int) $stats['total'] ?></div>
  </div>
  <div class="stat">
    <div class="k">Seen by the homeowner</div>
    <div class="v"><?= (int) $stats['viewed'] ?></div>
  </div>
  <div class="stat">
    <div class="k">Won</div>
    <div class="v"><?= (int) $stats['won'] ?></div>
  </div>
</div>

<?php if ($isLive): ?>
  <?php if ($jobs === []): ?>
    <div class="empty">
      <div class="ic"><?= icon('search', 24) ?></div>
      <h3>Nothing open in your counties right now</h3>
      <p>Jobs appear here the moment a homeowner posts one in a county on your listing.
         Adding more counties widens what you see.</p>
      <a class="btn btn-ghost" href="<?= e(url('/my/listing')) ?>">Edit your counties</a>
    </div>
  <?php else: ?>
    <div class="jobs-grid">
      <?php foreach ($jobs as $job): ?>
        <article class="job">
          <div class="body">
            <h3>
              <a href="<?= e(url('/jobs/' . $job['reference'])) ?>"><?= e($job['title']) ?></a>
              <?php if ($job['is_demo']): ?> <span class="badge b-sample">Sample</span><?php endif; ?>
            </h3>
            <div class="meta">
              <span class="mono"><?= e($job['trade_name']) ?></span>
              <span><?= e(trim(($job['city_name'] ?? '') . ', ' . ($job['county_name'] ?? ''), ', ')) ?></span>
              <span><?= e(urgency_label((string) $job['urgency'])) ?></span>
              <span><?= e(ago($job['published_at'])) ?></span>
              <span><?= (int) $job['quote_count'] ?> quotes</span>
            </div>
            <p class="excerpt"><?= e(excerpt((string) $job['description'], 130)) ?></p>
          </div>
          <div class="job-foot">
            <div class="budget">
              <div class="v"><?= e(budget(
                  $job['budget_min_cents'] !== null ? (int) $job['budget_min_cents'] : null,
                  $job['budget_max_cents'] !== null ? (int) $job['budget_max_cents'] : null)) ?></div>
              <div class="tiny muted">Homeowner budget</div>
            </div>
            <?php if ($job['already_quoted']): ?>
              <span class="badge b-verified">Quoted</span>
            <?php else: ?>
              <a class="btn btn-primary btn-sm" href="<?= e(url('/my/quote/' . $job['reference'])) ?>">Send a quote</a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($myQuotes !== []): ?>
  <div class="panel" style="margin-top:26px">
    <div class="panel-h">
      <h3>Your recent quotes</h3>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('/my/quotes')) ?>">All of them</a>
    </div>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Job</th><th>Your price</th><th>Status</th><th>Sent</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($myQuotes, 0, 5) as $q): ?>
          <tr>
            <td class="wrap-cell"><a href="<?= e(url('/jobs/' . $q['reference'])) ?>"><?= e($q['title']) ?></a></td>
            <td class="num tiny"><?= e(quote_price($q)) ?></td>
            <td><span class="badge <?= $q['status'] === 'accepted' ? 'b-live' : 'b-flat' ?>"><?= e($q['status']) ?></span></td>
            <td class="tiny muted"><?= e(ago($q['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
