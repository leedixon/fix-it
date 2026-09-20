<?php
/** @var string $name @var string $trade @var string $counties @var string $replyTo */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:30px;line-height:1.15;color:#16201D;">
  You're on the founding list, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  Fix Listed opens in Northwest Illinois shortly, and your listing will be live on day one. Nothing to pay &mdash; now or later &mdash; to be on it.
</p>

<!-- what we have on file: reassures, and invites a correction, which is a reply -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;background-color:#F7F8F3;border-radius:4px;" class="dk-card">
  <tr>
    <td style="padding:18px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.7;color:#16201D;" class="dk-text">
      <span style="font-family:'SFMono-Regular',Consolas,monospace;font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:#78857F;">What we have for you</span><br>
      <strong style="color:#16201D;" class="dk-text"><?= $e($trade) ?></strong><br>
      <span class="dk-mute" style="color:#4E5C57;">Covering <?= $e($counties) ?></span>
    </td>
  </tr>
</table>

<p class="dk-mute" style="margin:0 0 10px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text">Wrong, or missing something?</strong> Reply to this email and I'll fix it. If you send a couple of photos of recent work, I'll put them on your profile before we open &mdash; profiles with photos get roughly a quarter more enquiries.
</p>

<!-- One action. A reply is the cheapest possible conversion and it starts a
     real conversation, which is what actually gets a tradesperson onboard. -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 8px 0;">
  <tr>
    <td align="center" bgcolor="#C79A3E" style="border-radius:4px;">
      <!--[if mso]>
      <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="mailto:<?= $e($replyTo) ?>?subject=My%20Fix%20Listed%20profile" style="height:48px;v-text-anchor:middle;width:280px;" arcsize="9%" strokecolor="#C79A3E" fillcolor="#C79A3E">
        <w:anchorlock/><center style="color:#0E1513;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">Send my work photos</center>
      </v:roundrect>
      <![endif]-->
      <!--[if !mso]><!-- -->
      <a class="sm-btn" href="mailto:<?= $e($replyTo) ?>?subject=My%20Fix%20Listed%20profile"
         style="display:inline-block;padding:15px 34px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:700;color:#0E1513;text-decoration:none;border-radius:4px;background-color:#C79A3E;">
        Send my work photos
      </a>
      <!--<![endif]-->
    </td>
  </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;border-top:1px solid #D6DACF;" class="dk-line">
  <tr>
    <td style="padding-top:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.75;color:#4E5C57;" class="dk-mute">
      <strong style="color:#16201D;" class="dk-text">A reminder of the deal:</strong><br>
      Listing is free. Quoting is free. We take <strong>no commission</strong> on anything you agree with a homeowner, and we don't sell your details on to anyone.
    </td>
  </tr>
</table>
