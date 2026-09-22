<?php
/**
 * Structured data — what this page says to a machine.
 *
 * Every page gets the two site-wide nodes (who publishes this, and what the
 * site is) plus whatever the controller added in $jsonLd, plus a
 * BreadcrumbList built from the very same array the visible trail is drawn
 * from.
 *
 * The site-wide nodes carry stable @ids so a page node can point at them
 * with 'publisher' => ['@id' => ...] instead of repeating the whole
 * organisation on every page. That is the one thing the @graph form is for.
 *
 * There is deliberately no WebSite/SearchAction here. It tells Google there
 * is a search box whose results live at a URL pattern, and this site has
 * filters (?trade=, ?county=) rather than a search — declaring one would be
 * describing a feature that does not exist.
 *
 * @var array  $market
 * @var string $path
 * @var array  $crumbs
 * @var array  $jsonLd    extra nodes from the controller
 * @var array  $counties
 */

use FixListed\Core\Config;
use FixListed\Core\Seo;

$orgId  = Seo::organizationId();
$siteId = Seo::websiteId();

$areaServed = [];
foreach ($counties ?? [] as $county) {
    $areaServed[] = [
        '@type' => 'AdministrativeArea',
        'name'  => $county['name'] . ', ' . ($county['state'] ?? 'IL'),
    ];
}

$organization = [
    '@type'       => 'Organization',
    '@id'         => $orgId,
    'name'        => 'Fix Listed',
    'url'         => abs_url('/'),
    // No ?v= cache-buster on these two, unlike the og:image in the layout.
    // This is an entity's identity rather than a scraper's cache, and a logo
    // URL that changes every time the file is touched is a different logo as
    // far as anything reading the graph is concerned.
    'logo'        => abs_url('/assets/social/square.png'),
    'image'       => abs_url('/assets/social/og.png'),
    'description' => 'A flat-fee directory of handymen and trades in '
        . ($market['name'] ?? 'Northwest Illinois')
        . '. Homeowners pay once to post a job; tradespeople quote for free and keep the whole job.',
    'email'       => (string) Config::get('mail.from_address', ''),
    'areaServed'  => $areaServed,
];

$website = [
    '@type'     => 'WebSite',
    '@id'       => $siteId,
    'name'      => 'Fix Listed',
    'url'       => abs_url('/'),
    'publisher' => ['@id' => $orgId],
    'inLanguage'=> 'en-US',
];

$graph = Seo::graph(array_merge(
    [$organization, $website],
    array_values($jsonLd ?? []),
    [Seo::breadcrumbs($crumbs ?? [])],
));

if ($graph === '') {
    return;
}
?>
<script type="application/ld+json"><?= $graph ?></script>
