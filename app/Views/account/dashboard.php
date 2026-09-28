<?php
/**
 * @var array $jobs @var array $myQuotes @var array $stats
 * @var array|null $profile @var bool $isLive
 * @var array $reviews @var float $rating @var int $reviewN
 */
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

<div class="adm-stats">
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
  <?php
  /*
   * Your rating, in the place you sign in to.
   *
   * It was on the public profile and nowhere here, so the only way for a
   * tradesperson to see what people had said about them was to go and look
   * at their own listing like a stranger.
   */
  ?>
  <div class="stat">
    <div class="k">Your rating</div>
    <?php if ($reviewN > 0): ?>
      <div class="v"><?= e(number_format($rating, 1)) ?></div>
      <div class="d"><?= rating_marks($rating, 13) ?> <?= (int) $reviewN ?>
        review<?= $reviewN === 1 ? '' : 's' ?></div>
    <?php else: ?>
      <div class="v">&mdash;</div>
      <div class="d down">no reviews yet</div>
    <?php endif; ?>
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

<?php
/*
 * What people said, and the right of reply.
 *
 * Published reviews only. A tradesperson who could see one while it was
 * still in the moderation queue could work out who wrote it — this is a town
 * of 23,000 — and lean on them before anyone else read it. They see it when
 * the public does.
 *
 * The reply posts straight to the profile rather than joining the queue, and
 * the asymmetry is on purpose: a review comes from somebody who typed an
 * email into a form, a reply comes from a named business with a licence on
 * file whose listing can be suspended in a click. Making a verified trader
 * wait a day to answer a bad review in public is its own kind of unfair.
 */
?>
<?php if ($reviews !== []): ?>
  <div class="panel" style="margin-top:26px">
    <div class="panel-h">
      <h3>What people said</h3>
      <span class="tiny muted" style="margin-left:auto">
        You can answer a review. You cannot remove one, and neither can we.
      </span>
    </div>

    <?php foreach ($reviews as $r): ?>
      <?php $replied = trim((string) $r['pro_reply']) !== ''; ?>
      <div style="padding:18px 20px;border-top:1px solid var(--line)">
        <div class="stars">
          <?= rating_marks((float) $r['rating'], 15) ?>
          <b class="mono"><?= (int) $r['rating'] ?></b>
          <span class="tiny muted">
            · <?= e($r['first_name']) ?> <?= e(mb_substr((string) $r['last_name'], 0, 1)) ?>.
            · <?= e(ago((string) $r['created_at'])) ?>
          </span>
        </div>

        <?php if (!empty($r['job_title'])): ?>
          <p class="tiny muted" style="margin-top:6px"><?= e($r['job_title']) ?></p>
        <?php endif; ?>

        <?php if (trim((string) $r['body']) !== ''): ?>
          <p style="margin-top:10px;font-size:14.5px;line-height:1.65;white-space:pre-line"><?= e($r['body']) ?></p>
        <?php else: ?>
          <p class="tiny muted" style="margin-top:10px"><em>A rating, with nothing written.</em></p>
        <?php endif; ?>

        <?php if ($replied): ?>
          <div style="margin-top:12px;padding:12px 14px;background:var(--paper-2);
                      border-left:2px solid var(--brass-2);border-radius:2px">
            <div class="tiny muted" style="font-weight:600">Your answer
              <?php if (!empty($r['pro_replied_at'])): ?>
                · <?= e(ago((string) $r['pro_replied_at'])) ?>
              <?php endif; ?>
            </div>
            <p style="margin-top:6px;font-size:14px;line-height:1.6;white-space:pre-line"><?= e($r['pro_reply']) ?></p>
          </div>
        <?php endif; ?>

        <div class="rename" style="margin-top:12px">
          <input type="checkbox" id="rep-<?= (int) $r['id'] ?>" class="rename-state"
                 aria-label="Answer this review">
          <label class="btn btn-ghost btn-sm" for="rep-<?= (int) $r['id'] ?>">
            <?= $replied ? 'Edit your answer' : 'Answer this' ?>
          </label>

          <form class="rename-form" method="post" style="max-width:100%"
                action="<?= e(url('/my/reviews/' . (int) $r['id'] . '/reply')) ?>">
            <?= \FixListed\Core\Csrf::field() ?>
            <label class="field" for="rep-t-<?= (int) $r['id'] ?>">
              <span>Your answer — shown publicly under this review</span>
              <textarea id="rep-t-<?= (int) $r['id'] ?>" name="reply" rows="4" maxlength="1500"
                        placeholder="Thanks for having us out. On the point about…"><?= e((string) $r['pro_reply']) ?></textarea>
            </label>
            <div class="rename-act">
              <button class="btn btn-primary btn-sm" type="submit">
                <?= $replied ? 'Save' : 'Post it' ?>
              </button>
              <label class="btn btn-ghost btn-sm" for="rep-<?= (int) $r['id'] ?>">Cancel</label>
            </div>
            <p class="tiny muted" style="margin-top:8px">
              It goes up straight away. Answer the point, not the person — a calm reply to a bad
              review persuades more people than the review put off.
              <?php if ($replied): ?>Clearing the box takes your answer down.<?php endif; ?>
            </p>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
