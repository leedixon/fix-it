<?php
declare(strict_types=1);

namespace FixListed\Core;

use RuntimeException;

/** Read-only access to config/config.php, addressed with dot notation. */
final class Config
{
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(array $values): void
    {
        self::$values = $values;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            throw new RuntimeException('Config::load() must run before Config::get().');
        }
        $value = self::$values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** For values the app cannot run without — fails loudly at boot, not mid-request. */
    public static function require(string $key): mixed
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException("Required config value '{$key}' is missing from config/config.php.");
        }
        return $value;
    }
}
