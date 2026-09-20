<?php
/** Internal alert. Built to be actioned from a phone in one tap. */
/** @var string $role @var string $name @var string $email @var string $phone
 *  @var string $trade @var string $counties @var string $town @var string $note */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$isPro = $role === 'pro';
$rows = array_filter([
    'Email'    => $email,
    'Phone'    => $phone,
    'Trade'    => $isPro ? $trade : '',
    'Counties' => $isPro ? $counties : '',
    'Town'     => !$isPro ? $town : '',
    'Needs'    => $note,
]);
?>
<h1 style="margin:0 0 4px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:26px;line-height:1.2;color:#16201D;" class="dk-text">
  <?= $isPro ? 'New founding pro' : 'New homeowner signup' ?>
</h1>
<p style="margin:0 0 20px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:18px;color:#16201D;" class="dk-text">
  <strong><?= $e($name) ?></strong>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:22px;">
  <?php foreach ($rows as $label => $value): ?>
  <tr>
    <td style="padding:7px 0;border-bottom:1px solid #EDEFE9;font-family:'SFMono-Regular',Consolas,monospace;font-size:10px;letter-spacing:1.2px;text-transform:uppercase;color:#78857F;width:92px;vertical-align:top;" class="dk-line"><?= $e($label) ?></td>
    <td style="padding:7px 0;border-bottom:1px solid #EDEFE9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;color:#16201D;" class="dk-text dk-line"><?= $e($value) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if ($isPro): ?>
<p style="margin:0 0 18px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;color:#4E5C57;" class="dk-mute">
  Supply is the bottleneck. A call today, while they still remember signing up, converts far better than one next week.
</p>
<?php endif; ?>

<table role="presentation" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <?php if ($phone !== ''): ?>
    <td align="center" bgcolor="#0E1513" style="border-radius:4px;">
      <a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $phone)) ?>" style="display:inline-block;padding:13px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:700;color:#E9EEE9;text-decoration:none;">Call <?= $e(explode(' ', trim($name))[0]) ?></a>
    </td>
    <td style="width:10px;">&nbsp;</td>
    <?php endif; ?>
    <td align="center" bgcolor="#C79A3E" style="border-radius:4px;">
      <a href="mailto:<?= $e($email) ?>" style="display:inline-block;padding:13px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:700;color:#0E1513;text-decoration:none;">Email them</a>
    </td>
  </tr>
</table>
