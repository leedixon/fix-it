<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Takes the site down, and puts it back up.
 *
 * The switch is a **file**, not a row in the database and not a line in the
 * config. That is the whole design decision, and it follows from when this
 * gets used: the moments you most need to take the site down are the moments
 * something is broken, and a database migration that went sideways is top of
 * that list. A flag stored in the thing that is broken is not a flag.
 *
 * A file also means `bin/maintenance.php` works over SSH with no database, no
 * session and no working application — which is the state you will be in when
 * you need it.
 *
 * config/config.php was the other candidate and was rejected: it holds live
 * credentials, it is mode 600, and having the web process able to rewrite it
 * is a much bigger thing to allow than a flag is worth.
 *
 * Visitors get 503 with Retry-After rather than 200. A search engine reads
 * that as "temporarily unavailable, come back" and keeps the pages it has
 * indexed; a 200 on a maintenance page reads as "this page is now a
 * maintenance notice", which is how a site comes back up having lost its
 * rankings.
 */
final class Maintenance
{
    /** How long to tell clients — and Stripe — to wait before retrying. */
    private const RETRY_SECONDS = 1800;

    private static ?array $cached = null;
    private static bool $loaded = false;

    public static function file(): string
    {
        return BASE_PATH . '/storage/maintenance.json';
    }

    public static function isOn(): bool
    {
        return self::state() !== null;
    }

    /**
     * What the switch says, or null when the site is up.
     *
     * A file that exists but cannot be parsed still counts as on. The failure
     * a person will not forgive is the site staying up because the flag file
     * had a stray comma in it.
     *
     * @return array{message:string,started_at:string,by:string}|null
     */
    public static function state(): ?array
    {
        if (self::$loaded) {
            return self::$cached;
        }
        self::$loaded = true;

        $file = self::file();
        if (!is_file($file)) {
            return self::$cached = null;
        }

        $raw    = @file_get_contents($file);
        $parsed = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($parsed)) {
            $parsed = [];
        }

        return self::$cached = [
            'message'    => trim((string) ($parsed['message'] ?? '')) !== ''
                ? (string) $parsed['message']
                : 'We are making a quick change to the site. It will be back shortly.',
            'started_at' => (string) ($parsed['started_at'] ?? ''),
            'by'         => (string) ($parsed['by'] ?? ''),
        ];
    }

    /** Turns it on. Returns false if the file could not be written. */
    public static function on(string $message = '', string $by = ''): bool
    {
        $payload = json_encode([
            'message'    => trim($message),
            'started_at' => date('c'),
            'by'         => trim($by),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $ok = @file_put_contents(self::file(), $payload . "\n", LOCK_EX) !== false;
        self::$loaded = false;
        self::$cached = null;
        return $ok;
    }

    /** Turns it off. Already-off counts as success. */
    public static function off(): bool
    {
        $file = self::file();
        $ok   = !is_file($file) || @unlink($file);
        self::$loaded = false;
        self::$cached = null;
        return $ok;
    }

    /** How long it has been down, in words, for the banner an admin sees. */
    public static function runningFor(): string
    {
        $state = self::state();
        $since = $state['started_at'] ?? '';
        if ($since === '') {
            return '';
        }

        $started = strtotime($since);
        if ($started === false) {
            return '';
        }

        $minutes = max(0, (int) round((time() - $started) / 60));
        if ($minutes < 1) {
            return 'just now';
        }
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return $hours . ' hour' . ($hours === 1 ? '' : 's');
        }

        $days = (int) floor($hours / 24);
        return $days . ' day' . ($days === 1 ? '' : 's');
    }

    /**
     * The page a visitor gets, as a Response.
     *
     * Rendered without the site layout and without a single database query on
     * purpose. The layout needs a market, a county list and a trade list —
     * three queries that would turn "the database is down" into a blank 500
     * at precisely the moment this page is the only thing standing.
     */
    public static function response(View $view): Response
    {
        $state = self::state() ?? ['message' => '', 'started_at' => '', 'by' => ''];

        $body = $view->render('site/maintenance', [
            'message' => $state['message'],
            'email'   => (string) Config::get('mail.reply_to', ''),
        ], null);

        return Response::unavailable($body, self::RETRY_SECONDS);
    }
}
