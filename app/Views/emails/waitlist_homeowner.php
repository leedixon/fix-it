<?php
/** @var string $name @var string $town */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:30px;line-height:1.15;color:#16201D;">
  You're on the list, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  We'll email you once &mdash; the day Fix Listed opens in <?= $e($town) ?>. No newsletter, no drip campaign.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 6px 0;background-color:#F7F8F3;border-radius:4px;" class="dk-card">
  <tr>
    <td style="padding:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.75;color:#4E5C57;" class="dk-mute">
      <span style="font-family:'SFMono-Regular',Consolas,monospace;font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:#78857F;">How it will work</span><br><br>
      <strong style="color:#16201D;" class="dk-text">$10 flat to post a job.</strong> That's the only thing you ever pay us.<br><br>
      <strong style="color:#16201D;" class="dk-text">No commission.</strong> Whatever you agree with your pro is what you pay them. We take nothing out of it.<br><br>
      <strong style="color:#16201D;" class="dk-text">Your details stay here.</strong> Your job goes to local tradespeople, not to four call centres who'll ring you for a fortnight.
    </td>
  </tr>
</table>

<p style="margin:22px 0 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#4E5C57;" class="dk-mute">
  Know a good handyman, plumber or electrician around here? Reply and tell me who &mdash; getting the right trades listed is the whole job right now, and a name from someone who's used them is worth more than any advert.
</p>
