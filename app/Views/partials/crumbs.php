<?php
/**
 * The breadcrumb trail.
 *
 * One array, drawn once here and described once as BreadcrumbList in the head
 * — see partials/jsonld.php. Google's structured-data guidance is that the
 * markup must match what a visitor can see, and the reliable way to hold two
 * things in step is to give them one source rather than two lists that have
 * to be remembered together.
 *
 * Each crumb is ['label' => string, 'href' => string], and the last one — the
 * page you are on — carries no href, because a link to the page you are
 * already reading is a link nobody wants. An optional 'class' styles one
 * crumb: a job reference is set in the mono face wherever else it appears.
 *
 * href is a site-relative path and may carry a query string; url() puts the
 * mount point on the front so it stays right at /preview and at /.
 *
 * @var array<int,array{label:string,href?:string}> $crumbs
 */
if (empty($crumbs)) {
    return;
}
?>
<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <?php foreach (array_values($crumbs) as $i => $crumb): ?>
      <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
      <?php if (isset($crumb['href'])): ?>
        <a href="<?= e(url($crumb['href'])) ?>"><?= e($crumb['label']) ?></a>
      <?php else: ?>
        <span<?= isset($crumb['class']) ? ' class="' . e($crumb['class']) . '"' : '' ?>><?= e($crumb['label']) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</div>
