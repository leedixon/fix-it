<?php
declare(strict_types=1);

namespace FixListed\Core;

/** One CSRF token per session, verified on every state-changing request. */
final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function check(?string $submitted): bool
    {
        $token = Session::get(self::KEY);
        return is_string($token)
            && is_string($submitted)
            && $token !== ''
            && hash_equals($token, $submitted);
    }
}
