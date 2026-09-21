<?php
/** @var string $name @var string $jobTitle @var string $reference @var string $amount */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  We have refunded you, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  Nobody quoted &ldquo;<?= $e($jobTitle) ?>&rdquo;, so we have sent your <?= $e($amount) ?> back to the card that paid it. You did not have to ask, and there is nothing for you to do.
</p>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#78857F;">
  It usually shows on a statement within five working days, depending on the bank. Reference <span style="font-family:'SFMono-Regular',Consolas,monospace;color:#4E5C57;"><?= $e($reference) ?></span>.
</p>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text">This one is on us, not on you.</strong> It usually means we do not yet have enough tradespeople in your county for that kind of work &mdash; which is our problem to fix.
</p>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  Reply to this email and tell us what you need doing. We will go and find somebody, and let you know when we have.
</p>
