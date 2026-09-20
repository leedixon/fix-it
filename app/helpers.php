<?php
declare(strict_types=1);

/**
 * Template helpers, global on purpose.
 *
 * The views are plain PHP. Writing FixListed\Core\View::e() around every value
 * is what makes people stop writing it, and one unescaped value is an XSS
 * hole. A three-character function gets used.
 */

use FixListed\Core\Url;
use FixListed\Core\View;

/** Escape for HTML. Every value interpolated into a template goes through it. */
function e(mixed $value): string
{
    return View::e($value);
}

/** A site-relative URL, correct whether the site is at / or /preview. */
function url(string $path = '/'): string
{
    return Url::to($path);
}

/** @param array<string,string|int|null> $query */
function url_q(string $path, array $query): string
{
    return Url::withQuery($path, $query);
}

/**
 * A static asset's URL, with a cache-buster taken from the file's mtime.
 *
 * Shared hosting has no build step and no asset pipeline, so a stylesheet
 * change would otherwise sit behind a week of browser cache.
 */
function asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = BASE_PATH . '/public' . $path;
    $stamp = is_file($file) ? (string) filemtime($file) : '';
    return Url::to($path) . ($stamp !== '' ? '?v=' . $stamp : '');
}

/**
 * Integer cents to '$75', '$10.50', '$1,234'. All money in this app is cents.
 *
 * Cents appear only when they are not zero. Always showing them makes a rate
 * card read like an invoice ('$75.00/hr'); never showing them rounds $10.50
 * to $11, which is a different number from the one the pro typed.
 */
function money(?int $cents, ?bool $withCents = null): string
{
    return View::money($cents, $withCents ?? ($cents !== null && $cents % 100 !== 0));
}

/**
 * A budget range as one string: '$200–$600', '$200+', 'Open to quotes'.
 *
 * Homeowners frequently give one end of the range or neither, and a card
 * showing '$0 – $0' reads as a mistake by the site rather than a blank left
 * by the person posting.
 */
function budget(?int $min, ?int $max): string
{
    if ($min === null && $max === null) {
        return 'Open to quotes';
    }
    if ($min !== null && $max !== null) {
        return $min === $max ? money($min) : money($min) . '–' . money($max);
    }
    return $min !== null ? money($min) . '+' : 'Up to ' . money($max);
}

/**
 * '3 days ago'. Relative time reads as freshness, which is the whole signal a
 * jobs board is selling; an absolute date makes a visitor do the subtraction.
 */
function ago(?string $timestamp): string
{
    if ($timestamp === null || $timestamp === '') {
        return '';
    }
    $then = strtotime($timestamp);
    if ($then === false) {
        return '';
    }
    $seconds = max(0, time() - $then);

    return match (true) {
        $seconds < 90          => 'just now',
        $seconds < 3600        => intdiv($seconds, 60) . ' min ago',
        $seconds < 7200        => 'an hour ago',
        $seconds < 86400       => intdiv($seconds, 3600) . ' hours ago',
        $seconds < 172800      => 'yesterday',
        $seconds < 2592000     => intdiv($seconds, 86400) . ' days ago',
        $seconds < 5184000     => 'last month',
        default                => intdiv($seconds, 2592000) . ' months ago',
    };
}

/** Trim to a word boundary, so a card's text does not end mid-word. */
function excerpt(string $text, int $chars = 155): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if (mb_strlen($text) <= $chars) {
        return $text;
    }
    $cut = mb_substr($text, 0, $chars);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, ' ,.;:') . '…';
}

/**
 * Initials for the avatar tile. Two letters from a business name, one from a
 * single word. Used instead of a photo placeholder, because a grid of
 * identical grey silhouettes looks like a broken page.
 */
function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $words = array_values(array_filter($words, static fn (string $w): bool => $w !== ''));
    if ($words === []) {
        return '—';
    }
    $first = mb_strtoupper(mb_substr($words[0], 0, 1));
    return count($words) === 1 ? $first : $first . mb_strtoupper(mb_substr(end($words), 0, 1));
}

/**
 * A stable background colour for an avatar, derived from the name.
 *
 * Deterministic rather than random so the same pro keeps the same colour
 * across pages and reloads — a tile that changes colour on refresh reads as a
 * glitch.
 */
function avatar_tint(string $seed): string
{
    $tints = ['#DCB25C', '#C79A3E', '#9FBFA8', '#C9B79C', '#B9C6C1', '#E0C98A'];
    return $tints[abs(crc32($seed)) % count($tints)];
}

/** Star glyphs for a rating, e.g. 4.8 → '★★★★★'. Rounded to the nearest half. */
function stars(float $rating): string
{
    $full = (int) round($rating);
    return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full));
}

/**
 * Minutes to '40 min', 'about 2 hours', 'a day'.
 *
 * A response time is a promise, so it is rounded honestly: 95 minutes reads as
 * 'about 2 hours', never as '1 hour'.
 */
function response_time(int $minutes): string
{
    return match (true) {
        $minutes <= 0    => 'minutes',
        $minutes < 60    => $minutes . ' min',
        $minutes < 90    => 'about an hour',
        $minutes < 1440  => 'about ' . (int) round($minutes / 60) . ' hours',
        $minutes < 2880  => 'a day',
        default          => (int) round($minutes / 1440) . ' days',
    };
}

/** The urgency enum as something a person would say. */
function urgency_label(string $urgency): string
{
    return match ($urgency) {
        'asap'       => 'ASAP',
        'this_week'  => 'This week',
        'this_month' => 'This month',
        default      => 'Flexible',
    };
}

/**
 * An absolute URL for a site path.
 *
 * Open Graph and Twitter both require absolute URLs — a relative og:image is
 * silently ignored by every scraper, which looks exactly like no image at all.
 * Built from app.url so it stays right whether the site is at /preview or at
 * the root.
 */
function abs_url(string $path = '/'): string
{
    $base = rtrim((string) \FixListed\Core\Config::get('app.url', ''), '/');
    return $base . Url::to($path);
}

/** The same, for a static file, carrying asset()'s cache-busting stamp. */
function abs_asset(string $path): string
{
    $base = rtrim((string) \FixListed\Core\Config::get('app.url', ''), '/');
    return $base . asset($path);
}
