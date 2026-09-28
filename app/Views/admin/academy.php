<?php
/**
 * Managing the academy: a video for each lesson, and a switch to pull one.
 *
 * The words are not editable here on purpose. A lesson about a screen ought
 * to change in the same commit as the screen — put the text in a box and it
 * describes last month's interface within the year, and nobody finds out
 * until somebody follows it.
 *
 * @var array $tracks
 */
require_once __DIR__ . '/../partials/icons.php';
?>
<div class="adm-head">
  <div>
    <h1>Academy</h1>
    <p class="muted small">
      Add a video to a lesson when you record one, or take a lesson down. The text lives in the
      code so it ships with the thing it describes — ask for a change and it goes out with the
      next release.
    </p>
  </div>
</div>

<?php foreach ($tracks as $name => $lessons): ?>
  <div class="panel" style="margin-bottom:20px">
    <div class="panel-h">
      <h3><?= e($name) ?></h3>
      <span class="tiny muted" style="margin-left:auto"><?= count($lessons) ?> lessons</span>
    </div>

    <?php foreach ($lessons as $slug => $l): ?>
      <form method="post" action="<?= e(url('/admin/academy/' . $slug)) ?>"
            style="padding:16px 20px;border-top:1px solid var(--line)">
        <?= \FixListed\Core\Csrf::field() ?>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
          <div style="flex:1;min-width:220px">
            <b style="font-size:15px"><?= e($l['title']) ?></b>
            <div class="tiny muted" style="margin-top:3px">
              <span class="mono"><?= e($slug) ?></span>
              <?php if ($name !== 'Handbook'): ?>
                · <a href="<?= e(url('/academy/' . $slug)) ?>" target="_blank" rel="noopener">view</a>
              <?php else: ?>
                · <a href="<?= e(url('/admin/handbook/' . $slug)) ?>">read</a>
              <?php endif; ?>
              <?php if ($l['video'] !== ''): ?>
                · <span class="badge b-live">has video</span>
              <?php endif; ?>
            </div>
          </div>

          <label class="field" style="flex:2;min-width:260px;margin:0">
            <span>Video link</span>
            <input type="url" name="video_url" placeholder="Paste a YouTube or Vimeo link"
                   value="<?= e($l['video'] !== ''
                       ? (string) ($l['video'])
                       : '') ?>">
          </label>

          <label class="pick-row" style="align-self:flex-end;margin:0">
            <input type="checkbox" name="published" value="1" <?= $l['published'] ? 'checked' : '' ?>>
            <span>Published</span>
          </label>

          <button class="btn btn-ghost btn-sm" type="submit" style="align-self:flex-end">Save</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
