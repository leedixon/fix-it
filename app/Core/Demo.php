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
