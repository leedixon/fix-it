<?php
declare(strict_types=1);

namespace FixListed\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $server,
        /**
         * The directory the app is mounted at, '' at the document root.
         *
         * The site runs at /preview while it is being built and at / when it
         * launches. Rather than configure that in two places and forget one,
         * it is derived from where index.php actually is, so moving the mount
         * point is a matter of moving the files.
         */
        public readonly string $basePath = '',
    ) {
    }

    public static function capture(): self
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        // SCRIPT_NAME is '/preview/index.php' when mounted in a subdirectory
        // and '/index.php' at the root, whether the request was rewritten or
        // not.
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . trim($path, '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path === '//' ? '/' : $path,
            $_GET,
            $_POST,
            $_SERVER,
            $base,
        );
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    /** The host, lowercased and without a port — used to resolve the market. */
    public function host(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? '');
        return strtolower(explode(':', $host)[0]);
    }
}
