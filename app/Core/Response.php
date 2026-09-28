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

    /**
     * The bytes of a file on disk.
     *
     * Read into the body rather than streamed, which is the right trade at
     * this size: an uploaded image here is capped at well under a megabyte
     * after re-encoding, and holding one in memory for the length of a
     * request costs less than the complexity of a streaming response that
     * has to bypass everything else in this class.
     *
     * The ETag is the file's own fingerprint, so a browser that already has
     * the image gets a 304 and no body at all. It is quoted and weak-free
     * because a strong ETag is what makes a conditional request cheap.
     */
    public static function file(string $path, string $contentType, string $cache = 'private, max-age=86400'): self
    {
        $bytes = (string) @file_get_contents($path);
        $etag  = '"' . substr(sha1($bytes), 0, 20) . '"';

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            return new self('', 304, ['ETag' => $etag, 'Cache-Control' => $cache]);
        }

        return new self($bytes, 200, [
            'Content-Type'   => $contentType,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control'  => $cache,
            'ETag'           => $etag,
            // These are files strangers uploaded. Even re-encoded, telling
            // the browser never to guess at the type is free.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * 301. The URL moved and is not coming back.
     *
     * Used when a page's address changes for good. 301 is what transfers the
     * old URL's accumulated ranking to the new one and what makes browsers,
     * bookmarks and anyone who linked to the old address stop asking. A 302
     * or the 303 above says "look over there for now" and transfers nothing,
     * so a permanent move served as a temporary redirect quietly throws away
     * every link the old URL ever earned.
     */
    public static function movedPermanently(string $location): self
    {
        return new self('', 301, ['Location' => Url::to($location)]);
    }

    /** Plain text, for the files written for robots rather than people. */
    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** XML, for the sitemap. */
    public static function xml(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * 503, with Retry-After.
     *
     * The status matters more than the page. A search engine reads 503 as
     * "temporarily unavailable, keep what you have indexed and come back";
     * serving the same words with a 200 tells it this URL is now a
     * maintenance notice, which is how a site comes back up having lost its
     * rankings. Retry-After says how long to wait, and Stripe honours it too.
     */
    public static function unavailable(string $body, int $retryAfterSeconds = 1800): self
    {
        return new self($body, 503, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Retry-After'   => (string) $retryAfterSeconds,
            // Nothing about a maintenance page should outlive the maintenance.
            'Cache-Control' => 'no-store, must-revalidate',
        ]);
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
