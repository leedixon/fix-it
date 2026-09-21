<?php
/**
 * What to check for this applicant's trades, in their state.
 *
 * Everything here comes from the licence_authorities table. Illinois does not
 * license trades uniformly and no two states agree, so the guidance is data an
 * administrator can extend rather than branches in a template.
 *
 * @var array<int,array<string,mixed>> $guides  from LicenceRepository::forApplication()
 * @var string $licenceNumber
 * @var string $licenceState
 */
require_once __DIR__ . '/icons.php';
?>
<div style="padding:16px 20px;border-top:1px solid var(--line);background:var(--paper-2)">
  <div class="eyebrow" style="margin-bottom:10px">How to check this</div>

  <?php foreach ($guides as $g): ?>
    <div style="margin-bottom:16px">
      <div class="small" style="font-weight:600;display:flex;align-items:center;gap:7px;flex-wrap:wrap">
        <?= e(implode(', ', $g['trades'])) ?>
        <?php if ($g['known'] === false): ?>
          <span class="badge b-pending">No guidance yet</span>
        <?php elseif ((int) $g['licensed'] === 1): ?>
          <span class="badge b-verified">State licensed</span>
        <?php else: ?>
          <span class="badge b-flat">Not state licensed</span>
        <?php endif; ?>
      </div>

      <?php if (!empty($g['authority'])): ?>
        <p class="tiny muted" style="margin-top:5px"><?= e($g['authority']) ?><?php
          if (!empty($g['number_format'])): ?> · numbers look like
          <span class="mono"><?= e($g['number_format']) ?></span><?php endif; ?></p>
      <?php endif; ?>

      <p class="tiny muted" style="margin-top:6px;line-height:1.55"><?= e($g['guidance']) ?></p>

      <?php if (!empty($g['lookup_url'])): ?>
        <p style="margin-top:9px">
          <a class="btn btn-ghost btn-sm btn-block" target="_blank" rel="noopener noreferrer"
             href="<?= e($g['lookup_url']) ?>">Open the register <?= icon('arrow', 13) ?></a>
        </p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php
  // A number supplied where no state register exists is worth a question
  // before it earns a badge.
  $anyStateLicensed = false;
  foreach ($guides as $g) {
      if ($g['known'] !== false && (int) $g['licensed'] === 1) { $anyStateLicensed = true; }
  }
  ?>
  <?php if (!$anyStateLicensed && $licenceNumber !== ''): ?>
    <p class="tiny muted" style="border-top:1px solid var(--line);padding-top:12px">
      They gave a number anyway
      (<span class="mono"><?= e(trim($licenceState . ' ' . $licenceNumber)) ?></span>) —
      ask what issued it before ticking the licence box.
    </p>
  <?php endif; ?>

  <p class="tiny muted" style="border-top:1px solid var(--line);padding-top:12px;margin-top:4px">
    <strong style="color:var(--text)">Insurance is the one to insist on.</strong> A certificate of
    liability insurance works the same in every state: check the expiry and that the business name
    matches. That is what protects a homeowner when something goes wrong.
  </p>

  <p class="tiny" style="margin-top:10px">
    <a href="<?= e(url('/admin/licensing')) ?>">Edit licensing guidance →</a>
  </p>
</div>
