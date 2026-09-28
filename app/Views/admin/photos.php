<?php
/**
 * Work photos waiting to go on a profile.
 *
 * Shown large, because the whole job here is looking properly. A grid of
 * thumbnails is a screen you approve without seeing what is in the corner of
 * the frame — a child, a house number, a van's plate, somebody's front door.
 *
 * @var array $waiting
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-head">
  <div>
    <h1>Photos</h1>
    <p class="muted small">
      Pictures of other people's houses, uploaded by a tradesperson and published under their
      business name. Nothing appears until it is approved. Location data is already stripped.
    </p>
  </div>
</div>

<?php if ($waiting === []): ?>
  <div class="card" style="padding:40px;text-align:center">
    <?= icon('check', 24) ?>
    <p style="margin-top:10px;font-size:17px">Nothing waiting.</p>
  </div>
<?php else: ?>
  <?php foreach ($waiting as $ph): ?>
    <div class="card" style="padding:18px;margin-bottom:16px">
      <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start">
        <img src="<?= e(url('/img/' . (int) $ph['id'])) ?>" alt="" loading="lazy"
             style="width:min(420px,100%);border-radius:3px;border:1px solid var(--line);display:block">
        <div style="flex:1;min-width:200px">
          <p style="font-size:16px"><b><?= e($ph['business_name'] ?: 'Unnamed business') ?></b></p>
          <p class="tiny muted" style="margin-top:4px">
            uploaded <?= e(ago((string) $ph['created_at'])) ?>
          </p>
          <a class="tiny" style="display:inline-block;margin-top:8px"
             href="<?= e(url('/pros/' . $ph['slug'])) ?>" target="_blank" rel="noopener">
            See the profile →
          </a>

          <p class="tiny muted" style="margin-top:14px;line-height:1.6">
            Look at the whole frame, not the work. A house number, a plate, a child, a
            neighbour's door — those are the reasons this queue exists.
          </p>

          <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
            <form method="post" action="<?= e(url('/admin/photos/' . (int) $ph['id'])) ?>">
              <?= \FixListed\Core\Csrf::field() ?>
              <input type="hidden" name="status" value="approved">
              <button class="btn btn-primary btn-sm" type="submit">Approve</button>
            </form>
            <form method="post" action="<?= e(url('/admin/photos/' . (int) $ph['id'])) ?>">
              <?= \FixListed\Core\Csrf::field() ?>
              <input type="hidden" name="status" value="rejected">
              <button class="btn btn-ghost btn-sm" type="submit">Reject</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
