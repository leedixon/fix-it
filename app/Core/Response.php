<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * What a controller hands back.
 *
 * A value rather than a pile of header()/echo calls, so a controller can be
 * called from a test, and so nothing is sent until the front controller
 * decides to send it — which is what makes a redirect after a header is
 * already out impossible.
 */
final class Response
{
    /** @param array<string,string> $headers */
    private function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    /**
     * 303 by default, not 302: after a POST it tells the browser to follow up
     * with a GET, which is what stops a refresh from re-submitting the form.
     */
    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => Url::to($location)]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
