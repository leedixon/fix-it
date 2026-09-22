<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Whether invented seed listings are on display, and how they are shown.
 *
 * The site has to be look-at-able before real tradespeople have signed up, and
 * ten invented businesses are what makes that possible. They are also, plainly,
 * fabricated listings: "Ojo Plumbing" has a licence number, a rating and a
 * phone number, and none of it is real. A visitor cannot tell that from the
 * markup unless the markup says so.
 *
 * So there are two modes and no third:
 *
 *   label  every seeded row is shown with a Sample badge on the card and a
 *          banner on the page. Nothing pretends to be a real business.
 *   hide   seeded rows are filtered out of every query. This is launch.
 *
 * There is deliberately no "show them unlabelled" mode. It would be one config
 * value between a demo and a directory of businesses that do not exist, and
 * the failure mode is a real homeowner phoning a number nobody answers.
 */
final class Demo
{
    public const LABEL = 'label';
    public const HIDE  = 'hide';

    public static function mode(): string
    {
        return Config::get('app.demo_data', self::LABEL) === self::HIDE ? self::HIDE : self::LABEL;
    }

    public static function isVisible(): bool
    {
        return self::mode() === self::LABEL;
    }

    /**
     * Whether any invented rows are actually on display.
     *
     * The mode says whether seeded rows would be shown; this says whether
     * there are any. They come apart the moment somebody purges the sample
     * data without also switching the mode — and then the banner announces
     * that the listings are invented while every listing on the page is real.
     *
     * That is worse than the failure the banner exists to prevent. A visitor
     * told the directory is fake does not ring the one genuine plumber in it.
     *
     * Asked once per request, and not at all once the mode is 'hide', because
     * the caller short-circuits.
     */
    public static function hasRows(Database $db): bool
    {
        static $has = null;

        if ($has === null) {
            try {
                $has = (bool) $db->value(
                    'SELECT EXISTS(SELECT 1 FROM pro_profiles WHERE is_demo = 1)
                          OR EXISTS(SELECT 1 FROM jobs WHERE is_demo = 1)'
                );
            } catch (\Throwable $e) {
                // If it cannot be answered, say yes. An unnecessary banner is
                // a small embarrassment; a missing one is an undisclosed
                // fabricated business listing.
                $has = true;
            }
        }

        return $has;
    }

    /**
     * The SQL predicate that hides seeded rows, or an empty string.
     *
     * Returns SQL rather than taking a bound parameter because it is glued
     * into queries that already carry their own placeholders. It interpolates
     * nothing a caller supplies — $alias is a table alias written in the
     * query's own source, never anything from a request.
     */
    public static function filter(string $alias): string
    {
        return self::mode() === self::HIDE ? " AND {$alias}.is_demo = 0" : '';
    }
}
