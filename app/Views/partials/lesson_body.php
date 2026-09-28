<?php
/**
 * Renders a lesson's blocks.
 *
 * Blocks rather than HTML, so a lesson cannot introduce a style, a script or
 * an unclosed tag, and every lesson looks like every other one without
 * anybody maintaining that. Everything goes through e() — the content is
 * ours, but "it is our own content" is exactly the assumption that stops
 * being true the day somebody pastes something in.
 *
 * @var array $body
 */
?>
<?php foreach ($body as $block): ?>
  <?php if (isset($block['h'])): ?>
    <h2 class="lesson-h"><?= e($block['h']) ?></h2>

  <?php elseif (isset($block['p'])): ?>
    <p class="lesson-p"><?= e($block['p']) ?></p>

  <?php elseif (isset($block['ul'])): ?>
    <ul class="lesson-ul">
      <?php foreach ($block['ul'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
    </ul>

  <?php elseif (isset($block['steps'])): ?>
    <ol class="lesson-ol">
      <?php foreach ($block['steps'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
    </ol>

  <?php elseif (isset($block['note'])): ?>
    <div class="lesson-note"><?= icon('info', 16) ?><p><?= e($block['note']) ?></p></div>

  <?php elseif (isset($block['warn'])): ?>
    <div class="lesson-note warn"><?= icon('alert', 16) ?><p><?= e($block['warn']) ?></p></div>
  <?php endif; ?>
<?php endforeach; ?>
