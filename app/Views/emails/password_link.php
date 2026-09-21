<?php
/** @var string $name @var string $link @var bool $invite @var int $hours @var int $days */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  <?= $invite ? 'Set your password, ' . $e($first) . '.' : 'Reset your password, ' . $e($first) . '.' ?>
</h1>

<p class="dk-mute" style="margin:0 0 24px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <?= $invite
    ? 'One link and you are in. After that you can quote jobs and edit your listing whenever you like.'
    : 'Click below to choose a new one.' ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
  <tr><td style="background-color:#C79A3E;border-radius:3px;">
    <a href="<?= $e($link) ?>" style="display:inline-block;padding:14px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;color:#0E1513;text-decoration:none;">
      <?= $invite ? 'Set my password' : 'Choose a new password' ?>
    </a>
  </td></tr>
</table>

<p class="dk-mute" style="margin:0 0 18px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;color:#78857F;">
  This link works once and expires in <?= $invite ? (int) $days . ' days' : (int) $hours . ' hour' ?>.
  If the button does not work, copy this into your browser:<br>
  <span style="word-break:break-all;color:#4E5C57;"><?= $e($link) ?></span>
</p>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;color:#78857F;">
  If you did not ask for this, ignore it &mdash; nothing changes until the link is used.
</p>
