<?php
/**
 * Confirmed opt-in. Nothing is sent to an address until this is clicked.
 *
 * @var string $name @var string $market @var string $confirmUrl
 */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0];
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  <?= $first !== '' ? 'One click, ' . $e($first) . '.' : 'One click and you are on.' ?>
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  Confirm this address and we will email you new jobs in <?= $e($market) ?> that match the trades and counties you picked &mdash; usually within a minute of the homeowner posting one.
</p>

<p style="margin:0 0 26px 0;">
  <a href="<?= $e($confirmUrl) ?>" style="display:inline-block;background:#C79A3E;color:#16201D;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;text-decoration:none;padding:13px 24px;border-radius:3px;">
    Start the alerts
  </a>
</p>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text">Did not ask for this?</strong> Ignore it. Nothing is sent until that link is clicked, and we will not write to you again.
</p>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#78857F;">
  Alerts are free, quoting is free, and Fix Listed takes nothing from the work you win. You do not need a listing to receive these.
</p>
