<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * The conventions that search engines see: where a page lives, and the
 * machine-readable description of what is on it.
 *
 * Two jobs, kept together because they are the same decision seen twice. A
 * city page's URL and the `url` inside its structured data have to agree
 * exactly, and the fastest way to make them disagree is to build them in two
 * places.
 */
final class Seo
{
    /**
     * A city's path: /handyman/freeport-il.
     *
     * The state is in the URL because that is how people search — "handyman
     * Freeport IL", not "handyman Freeport" — and because town names repeat
     * across state lines. Freeport is in Illinois, Maine, New York, Ohio,
     * Pennsylvania, Texas and Florida; the suffix is what says which one this
     * page is about, to a reader and to a crawler.
     *
     * It is taken from the row rather than hard-coded. This market is entirely
     * in Illinois today, but Rockton and South Beloit sit on the Wisconsin
     * line and the first market that crosses it must not produce
     * /handyman/beloit-il.
     *
     * @param array<string,mixed> $city a cities row, with slug and state
     */
    public static function cityPath(array $city): string
    {
        return '/handyman/' . self::citySegment($city);
    }

    /** The '{slug}-{state}' segment on its own, for building and for matching. */
    public static function citySegment(array $city): string
    {
        return (string) $city['slug'] . '-' . strtolower((string) ($city['state'] ?? 'il'));
    }

    /**
     * The @id the site-wide Organization node is published under.
     *
     * Page nodes reference it rather than repeating the organisation, so
     * there is one publisher entity across the site instead of one per page
     * that a crawler has to guess are the same thing.
     */
    public static function organizationId(): string
    {
        return abs_url('/') . '#organization';
    }

    /** The @id of the site-wide WebSite node. */
    public static function websiteId(): string
    {
        return abs_url('/') . '#website';
    }

    /** A trade's path: /services/plumbing. */
    public static function servicePath(string $tradeSlug): string
    {
        return '/services/' . $tradeSlug;
    }

    /**
     * An array encoded for a <script type="application/ld+json"> block.
     *
     * JSON_HEX_TAG is the one that matters and it is not optional. Without it
     * a business name containing "</script>" ends the block early and turns
     * the rest of the JSON into markup — structured data is assembled from
     * whatever tradespeople typed into their profile, so this is user input
     * being written into a script element, and it is escaped like it.
     *
     * The slashes are left alone (JSON_UNESCAPED_SLASHES) so URLs stay
     * readable when someone views source; that is safe precisely because the
     * angle brackets are already gone.
     *
     * @param array<int,array<string,mixed>> $nodes
     */
    public static function graph(array $nodes): string
    {
        $nodes = array_values(array_filter($nodes, static fn ($n): bool => $n !== [] && $n !== null));
        if ($nodes === []) {
            return '';
        }

        return self::json(
            ['@context' => 'https://schema.org', '@graph' => array_map([self::class, 'prune'], $nodes)],
        );
    }

    /**
     * JSON that is safe to write inside a <script> element.
     *
     * The flags are the point. JSON_HEX_TAG turns '<' and '>' into \u003C and
     * \u003E, so a value containing '</script>' cannot end the block early and
     * turn the rest of the data into markup. Everything encoded by this method
     * is or may be user input — business names, job titles, town names typed
     * by an administrator — so it is escaped as if it always is.
     *
     * Used by the structured data above and by the analytics dataLayer, which
     * are the two places this application writes data into JavaScript.
     */
    public static function json(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Drops empty values, recursively.
     *
     * A node with "description": "" or "telephone": null is worse than one
     * without the property: Search Console reports it as an invalid value
     * rather than a missing optional one. Callers can therefore build a node
     * with everything it might have and let the blanks fall out here, instead
     * of writing a conditional around each line.
     *
     * Zero and false are kept — they are answers. Only null, '' and [] go.
     *
     * @param array<string|int,mixed> $node
     * @return array<string|int,mixed>
     */
    public static function prune(array $node): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $value = self::prune($value);
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /**
     * BreadcrumbList from the same array the visible trail is rendered from.
     *
     * Google's guidance is that structured data must describe what is on the
     * page. Building the markup from a second, hand-written list is how it
     * ends up describing a trail that is not there — so the partial that
     * draws the crumbs and this method take one array, and the last crumb
     * (the current page, which has no link) is included by position and
     * without an item URL, which is what the spec asks for.
     *
     * @param array<int,array{label:string,href?:string}> $crumbs
     * @return array<string,mixed>
     */
    public static function breadcrumbs(array $crumbs): array
    {
        if (count($crumbs) < 2) {
            return [];
        }

        $items = [];
        foreach (array_values($crumbs) as $i => $crumb) {
            $items[] = self::prune([
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => (string) $crumb['label'],
                'item'     => isset($crumb['href']) ? abs_url((string) $crumb['href']) : null,
            ]);
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * FAQPage from question => answer pairs.
     *
     * Only for questions that are actually answered in the visible copy of the
     * page it is put on. Marking up an answer a visitor cannot find is the
     * thing the structured-data guidelines single out, and it is also just a
     * lie told to get a bigger search result.
     *
     * @param array<string,string> $pairs
     * @return array<string,mixed>
     */
    public static function faq(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $items = [];
        foreach ($pairs as $question => $answer) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $items];
    }
}
