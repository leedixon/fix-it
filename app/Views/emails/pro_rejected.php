<?php
/** @var string $name @var string $note @var string $replyTo */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  About your application, <?= $e($first) ?>.
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  We have not been able to list you yet.
</p>

<?php if (trim($note) !== ''): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px 0;background-color:#F7F8F3;border-radius:4px;" class="dk-card">
  <tr><td style="padding:18px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#16201D;" class="dk-text">
    <?= $e($note) ?>
  </td></tr>
</table>
<?php endif; ?>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  This is not usually the end of it. Most of what stops an application is a document we could not
  read or a licence number that did not match the register &mdash; both of which are fixable in an
  email. <strong style="color:#16201D;" class="dk-text">Reply to this one</strong> and we will go
  through it with you.
</p>
