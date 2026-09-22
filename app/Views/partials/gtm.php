<?php
/**
 * Google Tag Manager.
 *
 * Three things this does that pasting the snippet into the layout would not:
 *
 * **The container id comes from config**, so it can be changed or switched off
 * without a deploy, and so a copy of this codebase running somewhere else does
 * not quietly report into the live property.
 *
 * **The id is validated before it reaches the page.** It is interpolated into
 * JavaScript, and a config value that is not a container id has no business
 * being executed as one. Anything that is not GTM- followed by letters and
 * digits is dropped and nothing is rendered.
 *
 * **Staff are not counted.** An administrator clicking through the site all
 * afternoon is not a visitor, and their sessions distort every funnel they
 * touch. Tradespeople and homeowners are real users and are tracked normally.
 *
 * @var string    $part  'head' or 'body'
 * @var array|null $me   the signed-in user, if any
 */

$gtmId = (string) \FixListed\Core\Config::get('analytics.gtm_id', '');

// Letters and digits only after the prefix. This is the whole injection
// defence, and it is deliberately strict rather than clever.
if ($gtmId === '' || preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtmId) !== 1) {
    return;
}

/*
 * Staff are not counted — except when they are deliberately debugging tags.
 *
 * Tag Assistant and GTM's own Preview open the site in the operator's browser,
 * where they are signed in, so the suppression below hides the tag from the
 * one person trying to look at it. That reads as "the tag is not installed"
 * and is the most confusing possible failure.
 *
 * GTM appends gtm_debug to the URL when previewing, and hits made in debug
 * mode go to DebugView rather than the ordinary reports — so honouring it
 * costs nothing in polluted data and makes the tool usable.
 */
$isPreviewing = isset($_GET['gtm_debug']);
$isStaff      = in_array($me['role'] ?? '', ['superadmin', 'market_admin', 'moderator'], true);

if ($isStaff && !$isPreviewing) {
    return;
}
?>
<?php if (($part ?? 'head') === 'head'): ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?= $gtmId ?>');</script>
<!-- End Google Tag Manager -->
<?php else: ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $gtmId ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php endif; ?>
