<?php
/** @var string $name @var string $business @var string $market */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:30px;line-height:1.15;color:#16201D;">
  We have your application, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  A person is checking your licence and insurance now &mdash; not a script. You will hear back within two working days, and usually a lot sooner than that.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;background-color:#F7F8F3;border-radius:4px;" class="dk-card">
  <tr>
    <td style="padding:18px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.7;color:#16201D;" class="dk-text">
      <span style="font-family:'SFMono-Regular',Consolas,monospace;font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:#78857F;">Applying as</span><br>
      <strong style="color:#16201D;" class="dk-text"><?= $e($business) ?></strong><br>
      <span class="dk-mute" style="color:#4E5C57;">In <?= $e($market) ?></span>
    </td>
  </tr>
</table>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text">Want to speed this up?</strong> Reply to this email with a photo of your licence and your certificate of insurance. That is the whole hold-up, and it usually turns two days into an afternoon.
</p>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  Photos of recent work help too. Profiles with photos get noticeably more enquiries, and we will put them up before you go live.
</p>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  Nothing to pay, now or later. No commission on the work you win.
</p>
