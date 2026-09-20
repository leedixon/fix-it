<?php
/** @var string $name @var string $business @var string $profileUrl @var string $jobsUrl @var string $market */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:30px;line-height:1.15;color:#16201D;">
  You're live, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 24px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <?= $e($business) ?> is published across <?= $e($market) ?>. Homeowners can find you by trade and by the towns you cover, and you can quote anything on the board.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
  <tr><td style="background-color:#C79A3E;border-radius:3px;">
    <a href="<?= $e($profileUrl) ?>" style="display:inline-block;padding:14px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;color:#0E1513;text-decoration:none;">
      See your profile
    </a>
  </td></tr>
</table>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text">Two things worth doing today.</strong><br>
  Look at your profile and reply to this email with anything you want changed &mdash; wording, rate, the counties you cover. Then send a few photos of recent work. Profiles with photos get noticeably more enquiries, and yours has none yet.
</p>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <a href="<?= $e($jobsUrl) ?>" style="color:#8A6115;">Open jobs in your counties &rarr;</a><br>
  Quoting is free and there is no commission on what you win. What the homeowner pays you is yours.
</p>
