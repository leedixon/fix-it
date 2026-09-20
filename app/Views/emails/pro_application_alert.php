<?php
/** @var string $business @var string $person @var string $email @var string $phone
 *  @var string $licence @var string $reviewUrl */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$row = static function (string $k, string $v) use ($e): string {
    if (trim($v) === '') { return ''; }
    return '<tr><td style="padding:5px 0;font-family:-apple-system,Arial,sans-serif;font-size:13px;color:#78857F;width:110px;">'
         . $e($k) . '</td><td style="padding:5px 0;font-family:-apple-system,Arial,sans-serif;font-size:14px;color:#16201D;" class="dk-text">'
         . $e($v) . '</td></tr>';
};
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  <?= $e($business) ?> applied to be listed.
</h1>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;background-color:#F7F8F3;border-radius:4px;" class="dk-card">
  <tr><td style="padding:16px 20px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
      <?= $row('Person', $person) ?>
      <?= $row('Email', $email) ?>
      <?= $row('Phone', $phone) ?>
      <?= $row('Licence', $licence) ?>
    </table>
  </td></tr>
</table>

<p class="dk-mute" style="margin:0 0 24px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#4E5C57;">
  It is sitting in the review queue and is not visible anywhere public until you approve it.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px 0;">
  <tr><td style="background-color:#C79A3E;border-radius:3px;">
    <a href="<?= $e($reviewUrl) ?>" style="display:inline-block;padding:13px 24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:600;color:#0E1513;text-decoration:none;">
      Review this application
    </a>
  </td></tr>
</table>
