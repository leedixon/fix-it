<?php
/** @var string $name @var string $jobTitle @var string $trade @var string $city
 *  @var string $summary @var string $quoteUrl */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 14px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:26px;line-height:1.2;color:#16201D;">
  <?= $e($jobTitle) ?>
</h1>

<p class="dk-mute" style="margin:0 0 20px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#4E5C57;">
  <?= $e($trade) ?><?= trim($city) !== '' ? ' &middot; ' . $e($city) : '' ?> &mdash; posted just now, <?= $e($first) ?>.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;background-color:#F7F8F3;border-radius:4px;" class="dk-card">
  <tr><td style="padding:18px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14.5px;line-height:1.65;color:#4E5C57;" class="dk-mute">
    <?= $e($summary) ?>
  </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0;">
  <tr><td style="background-color:#C79A3E;border-radius:3px;">
    <a href="<?= $e($quoteUrl) ?>" style="display:inline-block;padding:14px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;color:#0E1513;text-decoration:none;">
      Send a quote
    </a>
  </td></tr>
</table>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;color:#78857F;">
  Free to quote, and no commission on the work &mdash; what the homeowner pays you is yours. The
  ones who answer first tend to win it.
</p>
