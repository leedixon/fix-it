<?php
/**
 * The review moderation queue.
 *
 * Oldest first, because this is a queue and a queue sorted newest-first is
 * one where the oldest item never gets done.
 *
 * Both buttons are shown with equal weight. A screen that makes Publish the
 * obvious green button and Remove a small grey link is a screen that teaches
 * you to publish without reading, which defeats the point of having it.
 *
 * @var array $waiting
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-head">
  <div>
    <h1>Reviews</h1>
    <p class="muted small">
      Everything a homeowner submits waits here. Publishing puts it on the profile and moves the
      rating; removing it leaves no trace on the public site and moves the rating back.
    </p>
  </div>
</div>

<?php if ($waiting === []): ?>
  <div class="card" style="padding:40px;text-align:center">
    <?= icon('check', 24) ?>
    <p style="margin-top:10px;font-size:17px">Nothing waiting.</p>
    <p class="muted small" style="margin-top:6px">
      Invitations go out automatically a fortnight after a job was posted, to homeowners whose job
      got at least one quote.
    </p>
  </div>
<?php else: ?>
  <?php foreach ($waiting as $r): ?>
    <div class="card" style="padding:20px;margin-bottom:14px">
      <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
        <div style="flex:1;min-width:240px">
          <div class="stars">
            <?= rating_marks((float) $r['rating'], 16) ?>
            <b class="mono"><?= (int) $r['rating'] ?></b>
          </div>
          <p style="margin-top:10px;font-size:15px">
            <b><?= e($r['business_name']) ?></b>
            <span class="muted small">
              · from <?= e($r['first_name']) ?> <?= e(mb_substr((string) $r['last_name'], 0, 1)) ?>.
            </span>
          </p>
          <p class="tiny muted" style="margin-top:4px">
            Job <span class="mono"><?= e($r['job_reference']) ?></span> — <?= e($r['job_title']) ?>
            · submitted <?= e(date('j M Y', strtotime((string) $r['created_at']))) ?>
          </p>

          <?php if (trim((string) $r['body']) !== ''): ?>
            <blockquote style="margin:14px 0 0;padding:12px 14px;background:var(--paper-2);
                               border-left:2px solid var(--line-2);border-radius:2px;
                               font-size:14.5px;line-height:1.65;white-space:pre-line"><?= e($r['body']) ?></blockquote>
          <?php else: ?>
            <p class="tiny muted" style="margin-top:12px"><em>No written comment — a rating only.</em></p>
          <?php endif; ?>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;min-width:150px">
          <form method="post" action="<?= e(url('/admin/reviews/' . $r['id'])) ?>">
            <?= \FixListed\Core\Csrf::field() ?>
            <input type="hidden" name="status" value="published">
            <button class="btn btn-primary btn-sm btn-block" type="submit">Publish</button>
          </form>
          <form method="post" action="<?= e(url('/admin/reviews/' . $r['id'])) ?>">
            <?= \FixListed\Core\Csrf::field() ?>
            <input type="hidden" name="status" value="removed">
            <button class="btn btn-ghost btn-sm btn-block" type="submit">Remove</button>
          </form>
          <a class="tiny muted" style="text-align:center"
             href="<?= e(url('/pros/' . $r['pro_slug'])) ?>" target="_blank" rel="noopener">
            See the profile →
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
