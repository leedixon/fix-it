<?php
/**
 * Email shell.
 *
 * Email is not the web. Tables, not flex or grid. Inline styles, because Gmail
 * strips most of <style>. No webfonts — Marcellus will not load, so the serif
 * falls back to Georgia, which is on virtually every machine and shares enough
 * of the brand's character to carry it.
 *
 * @var string $content    the HTML body, already escaped by its template
 * @var string $preheader  the grey line shown next to the subject in the inbox
 * @var string $title
 */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title><?= $e($title) ?></title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style>
  /* Clients that honour <style> get these; the rest fall back to the inline
     styles on every element, which is why those are duplicated below. */
  body { margin:0 !important; padding:0 !important; width:100% !important; }
  table { border-collapse:collapse !important; }
  img { border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; }
  a { color:#8A6115; }
  @media screen and (max-width:620px) {
    .sm-full { width:100% !important; }
    .sm-pad  { padding-left:22px !important; padding-right:22px !important; }
    .sm-h1   { font-size:26px !important; line-height:1.2 !important; }
    .sm-btn  { display:block !important; width:auto !important; }
  }
  @media (prefers-color-scheme: dark) {
    .dk-bg   { background-color:#0D1412 !important; }
    .dk-card { background-color:#18221F !important; }
    .dk-text { color:#E4EAE5 !important; }
    .dk-mute { color:#A2AFA9 !important; }
    .dk-line { border-color:#2C3B36 !important; }
  }
</style>
</head>
<body class="dk-bg" style="margin:0;padding:0;background-color:#EDEFE9;">

<!-- Inbox preview line. The zero-width characters stop Gmail pulling the
     first words of the message in after it. -->
<div style="display:none;font-size:1px;color:#EDEFE9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
  <?= $e($preheader) ?>
  <?= str_repeat('&#847;&zwnj;&nbsp;', 40) ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="dk-bg" style="background-color:#EDEFE9;">
  <tr>
    <td align="center" style="padding:28px 12px 40px 12px;">

      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="sm-full" style="width:600px;max-width:600px;">

        <!-- wordmark -->
        <tr>
          <td align="left" style="padding:0 0 18px 4px;font-family:Georgia,'Times New Roman',serif;font-size:20px;letter-spacing:.3px;color:#16201D;" class="dk-text">
            Fix <span style="color:#8A6115;">Listed</span>
          </td>
        </tr>

        <!-- card -->
        <tr>
          <td class="dk-card" style="background-color:#FFFFFF;border-radius:6px;overflow:hidden;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td style="height:4px;background-color:#C79A3E;font-size:0;line-height:0;">&nbsp;</td></tr>
              <tr>
                <td class="sm-pad" style="padding:34px 40px 36px 40px;">
                  <?= $content ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- footer -->
        <tr>
          <td class="sm-pad dk-mute" style="padding:20px 8px 0 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:12px;line-height:1.6;color:#78857F;">
            Fix Listed &middot; a trades directory for Northwest Illinois<br>
            Questions? Just reply to this email &mdash; it reaches a person.
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
