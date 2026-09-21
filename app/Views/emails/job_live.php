<?php
/** @var string $name @var string $jobTitle @var string $reference @var string $jobUrl @var string $expires */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  Your job is live, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  Every tradesperson covering your county can see it now. Quotes come straight to you, and you deal with whoever you pick directly &mdash; we take nothing from the work itself.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;background-color:#F7F8F3;border-radius:4px;" class="dk-card">
  <tr><td style="padding:18px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.7;color:#16201D;" class="dk-text">
    <span style="font-family:'SFMono-Regular',Consolas,monospace;font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:#78857F;">Your reference</span><br>
    <strong style="font-family:'SFMono-Regular',Consolas,monospace;font-size:18px;color:#16201D;" class="dk-text"><?= $e($reference) ?></strong><br>
    <span class="dk-mute" style="color:#4E5C57;"><?= $e($jobTitle) ?></span>
    <?php if (trim($expires) !== ''): ?>
      <br><span class="dk-mute" style="color:#78857F;font-size:13px;">Live until <?= $e(date('j F Y', strtotime($expires))) ?></span>
    <?php endif; ?>
  </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
  <tr><td style="background-color:#C79A3E;border-radius:3px;">
    <a href="<?= $e($jobUrl) ?>" style="display:inline-block;padding:14px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;color:#0E1513;text-decoration:none;">
      See your job
    </a>
  </td></tr>
</table>

<p class="dk-mute" style="margin:0 0 20px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text">Give it a day or two.</strong> Most jobs collect a few quotes, and comparing three beats jumping at the first. If nobody quotes at all, the fee comes back to your card automatically &mdash; you do not have to ask.
</p>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#4E5C57;">
  This email is your receipt. Keep the reference &mdash; it is how we find your job if you need us.
</p>
