<?php
/**
 * The designed empty state.
 *
 * A launching directory shows this more often than it shows results, so it
 * gets a real design and an action rather than a blank column. Each caller
 * supplies the sentence that fits its own page.
 *
 * @var string $emptyTitle
 * @var string $emptyBody
 * @var string $emptyCta       link text, optional
 * @var string $emptyCtaHref
 */
require_once __DIR__ . '/icons.php';
?>
<div class="empty">
  <div class="ic"><?= icon('search', 24) ?></div>
  <h3><?= e($emptyTitle) ?></h3>
  <p><?= e($emptyBody) ?></p>
  <?php if (!empty($emptyCta)): ?>
    <a class="btn btn-primary" href="<?= e($emptyCtaHref) ?>"><?= e($emptyCta) ?></a>
  <?php endif; ?>
</div>
