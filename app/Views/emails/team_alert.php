<?php
/**
 * Internal post: something happened that the people running the site should
 * know about without having to go and look.
 *
 * Deliberately plain. This is not marketing, it is a notification to a
 * colleague, and it should read like one.
 *
 * @var string $name @var string $lede @var array $facts
 * @var string $actionUrl @var string $actionLabel
 */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0];
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 14px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:26px;line-height:1.15;color:#16201D;">
  <?= $first !== '' ? $e($first) . ',' : 'Heads up' ?>
</h1>

<p class="dk-mute" style="margin:0 0 20px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <?= $e($lede) ?>
</p>

<?php if ($facts !== []): ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 22px 0;width:100%;">
  <?php foreach ($facts as $label => $value): ?>
    <?php if (trim((string) $value) === '') { continue; } ?>
    <tr>
      <td style="padding:5px 14px 5px 0;font-family:'SFMono-Regular',Consolas,monospace;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#78857F;white-space:nowrap;vertical-align:top;"><?= $e($label) ?></td>
      <td class="dk-text" style="padding:5px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.5;color:#16201D;"><?= $e($value) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($actionUrl !== ''): ?>
<p style="margin:0 0 20px 0;">
  <a href="<?= $e($actionUrl) ?>" style="display:inline-block;background:#16201D;color:#E9EEE9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:600;text-decoration:none;padding:11px 20px;border-radius:3px;">
    <?= $e($actionLabel) ?>
  </a>
</p>
<?php endif; ?>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.6;color:#78857F;">
  You are getting this because you help run Fix Listed. Alerts follow what your role can see &mdash; change somebody's role and their alerts change with it.
</p>
