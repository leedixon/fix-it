<?php
declare(strict_types=1);

namespace FixListed\Core;

/** Routes with {placeholders}, matched in registration order. */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        /*
         * Placeholders become named groups; every other character is matched
         * literally.
         *
         * preg_quote first, and it is not cosmetic. Without it the dot in
         * '/robots.txt' is a regex wildcard, so '/robotsXtxt' answers 200
         * with the same body — the site would serve one file at an unbounded
         * number of addresses, which is a duplicate-content problem on the
         * one route whose entire job is telling crawlers what to trust.
         */
        $regex = preg_replace('#\\\{([a-z_]+)\\\}#', '(?P<$1>[^/]+)', preg_quote($pattern, '#'));
        $this->routes[] = [
            'method' => $method,
            'regex'  => '#^' . $regex . '$#',
            'handler'=> $handler,
        ];
    }

    /** @return array{handler:callable,params:array<string,string>}|null */
    public function match(Request $request): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (preg_match($route['regex'], $request->path, $matches) === 1) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }
        return null;
    }
}
