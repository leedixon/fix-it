<?php
declare(strict_types=1);

/**
 * Boots the application: autoloading, error handling, configuration.
 * Included by public/index.php and by every script in bin/.
 */

define('BASE_PATH', dirname(__DIR__));

// PSR-4 style autoloader for the FixListed\ namespace, mapped onto app/.
spl_autoload_register(static function (string $class): void {
    $prefix = 'FixListed\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use FixListed\Core\Config;

$configFile = BASE_PATH . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit("Configuration missing. Copy config/config.example.php to config/config.php and fill it in.\n");
}
Config::load(require $configFile);

date_default_timezone_set(Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

// Turn every warning and notice into an exception, so a typo surfaces as a
// failure instead of quietly producing a half-rendered page.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$isProduction = Config::get('app.env') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
error_reporting(E_ALL);
