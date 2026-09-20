<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Builds links that survive the site being moved.
 *
 * The site runs at /preview during the build and at / once it launches. Every
 * link in every template goes through here, so that move is one call in the
 * front controller rather than a search-and-replace across the views.
 */
final class Url
{
    private static string $base = '';

    public static function mount(string $basePath): void
    {
        self::$base = rtrim($basePath, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    /** '/pros' becomes '/preview/pros'. Absolute URLs are returned untouched. */
    public static function to(string $path = '/'): string
    {
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            || str_starts_with($path, 'mailto:') || str_starts_with($path, '#')) {
            return $path;
        }
        $path = '/' . ltrim($path, '/');
        return self::$base . ($path === '/' ? '/' : rtrim($path, '/'));
    }

    /**
     * A link with query parameters, dropping the ones that are empty.
     *
     * Filter links are built by handing back the current filters with one
     * changed, and a null or empty value has to disappear from the URL rather
     * than appear as '?trade='. Otherwise 'All trades' produces a different
     * URL from the unfiltered page and splits its ranking.
     *
     * @param array<string,string|int|null> $query
     */
    public static function withQuery(string $path, array $query): string
    {
        $query = array_filter(
            $query,
            static fn ($v): bool => $v !== null && $v !== '' && $v !== 0,
        );
        return self::to($path) . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
