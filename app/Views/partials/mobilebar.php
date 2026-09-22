<?php
/**
 * The fixed action bar on a phone.
 *
 * At 700px and below the stylesheet hides the "Post a job — $10" button from
 * the header, on the assumption that this bar is carrying it. This file did
 * not exist, so the assumption was wrong and the site's primary call to
 * action simply disappeared on every phone. The stylesheet has described this
 * bar since the prototype; only the markup was missing.
 *
 * Two buttons, one for each side of the marketplace, because the people being
 * sent here right now are tradespeople and "List your business" is what they
 * came for.
 *
 * It is left out on the pages it would be absurd on — a "Post a job" button
 * fixed to the bottom of the post-a-job form, or over a checkout — and in the
 * admin and account areas, which use their own layouts and never include it.
 *
 * @var string $path
 */
$hideOn = ['/post-a-job', '/list-your-business', '/sign-in', '/forgot-password', '/set-password'];

foreach ($hideOn as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        return;
    }
}
?>
<div class="mobilebar">
  <a class="btn btn-ghost btn-block" href="<?= e(url('/list-your-business')) ?>">List your business</a>
  <a class="btn btn-primary btn-block" href="<?= e(url('/post-a-job')) ?>">Post a job — $10</a>
</div>
