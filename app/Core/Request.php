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
    ) {
    }

    public static function capture(): self
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path === '//' ? '/' : $path,
            $_GET,
            $_POST,
            $_SERVER,
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
