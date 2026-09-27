<?php
/**
 * "How did it go?" — sent once, a fortnight after a job was posted, and only
 * to homeowners whose job actually got quotes.
 *
 * The link carries the only credential involved. It is not the job reference,
 * which is public on the jobs board.
 *
 * @var string $name @var string $jobTitle @var string $reference @var string $url
 */
use FixListed\Core\View;
$e = static fn($v) => View::e($v);
$first = explode(' ', trim($name))[0] ?: 'there';
?>
<h1 class="sm-h1 dk-text" style="margin:0 0 16px 0;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.15;color:#16201D;">
  How did it go, <?= $e($first) ?>?
</h1>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  A couple of weeks ago you posted &ldquo;<?= $e($jobTitle) ?>&rdquo; and some tradespeople quoted for it. If one of them did the work, would you tell us how they got on?
</p>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  It takes about a minute. Two questions: which one you hired, and how the work was. You can add a few words if you want to, or not.
</p>

<p style="margin:0 0 26px 0;">
  <a href="<?= $e($url) ?>" style="display:inline-block;background:#C79A3E;color:#16201D;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:600;text-decoration:none;padding:13px 24px;border-radius:3px;">
    Rate the work
  </a>
</p>

<p class="dk-mute" style="margin:0 0 22px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#4E5C57;">
  <strong style="color:#16201D;" class="dk-text">If you did not hire anybody, just ignore this.</strong> We will not ask again either way &mdash; this is the only one of these you will get for job <span style="font-family:'SFMono-Regular',Consolas,monospace;color:#4E5C57;"><?= $e($reference) ?></span>.
</p>

<p class="dk-mute" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#78857F;">
  Reviews are read before they are published. Your first name and last initial go up with it; your email address never does. The link above is yours alone &mdash; please do not forward it.
</p>
