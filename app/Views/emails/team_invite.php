<?php
/** @var string $name @var string $inviter @var string $roleLabel @var string $roleBlurb
 *  @var string $setUpUrl @var string $market @var int $days */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:30px;line-height:1.15;color:#16201D;">
  <?= $e($first) ?>, you've been added to Fix Listed.
</h1>

<p class="dk-mute" style="margin:0 0 20px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <?= $e($inviter) ?> has given you a <strong style="color:#16201D;"><?= $e($roleLabel) ?></strong>
  account for <?= $e($market) ?>. <?= $e($roleBlurb) ?>
</p>

<!-- The button sets a password. There is no temporary one to send, so there
     is nothing in this email that works without the person acting on it. -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px 0;">
  <tr><td style="background-color:#C79A3E;border-radius:3px;">
    <a href="<?= $e($setUpUrl) ?>" style="display:inline-block;padding:14px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;color:#0E1513;text-decoration:none;">
      Set your password
    </a>
  </td></tr>
</table>

<p class="dk-mute" style="margin:0 0 24px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;color:#78857F;">
  This link works once and expires in <?= (int) $days ?> days. If it has run out, ask
  <?= $e($inviter) ?> to send another — it takes them one click.
</p>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;color:#78857F;">
  If you weren't expecting this, you can ignore it. Nothing happens until you set a password,
  and the link stops working on its own.
</p>
