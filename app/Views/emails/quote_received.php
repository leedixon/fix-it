<?php
/** @var string $name @var string $business @var string $jobTitle @var string $jobUrl */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  You have a quote, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text"><?= $e($business) ?></strong> has quoted
  &ldquo;<?= $e($jobTitle) ?>&rdquo;.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
  <tr><td style="background-color:#C79A3E;border-radius:3px;">
    <a href="<?= $e($jobUrl) ?>" style="display:inline-block;padding:14px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;color:#0E1513;text-decoration:none;">
      Read the quote
    </a>
  </td></tr>
</table>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#4E5C57;">
  You deal with them directly and pay them directly &mdash; Fix Listed takes nothing from the job.
  Give it a day or two before deciding; most jobs get a few quotes.
</p>
