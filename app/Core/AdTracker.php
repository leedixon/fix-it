<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Counts what paid placement actually delivered.
 *
 * A tradesperson paying monthly for position will ask what they got for it,
 * and the only honest answer is one backed by numbers that were being
 * collected before they asked. So this runs from now, whether or not anything
 * is being sold yet.
 *
 * Two things it is careful about:
 *
 * **It does not slow the page down.** Templates call seen() while rendering,
 * which only adds to an array. The writes happen in flush(), after the
 * response has been sent — a visitor never waits on a counter.
 *
 * **It does not inflate.** Crawlers are skipped, a pro looking at their own
 * listing is skipped, and one session counts at most one impression per
 * placement per day. A number that flatters the product is worse than no
 * number, because it will be quoted back at a price.
 */
final class AdTracker
{
    /** @var array<int,int> placement id => pro id */
    private static array $impressions = [];

    /** Called from a template as a paid card renders. Records nothing yet. */
    public static function seen(int $placementId, int $proId): void
    {
        if ($placementId > 0) {
            self::$impressions[$placementId] = $proId;
        }
    }

    /**
     * Writes what was seen. Call after the response has gone out.
     *
     * Wrapped whole: a counter that throws must never take a page with it,
     * and by this point the visitor already has their HTML anyway.
     */
    public static function flush(Database $db, Request $request, int $marketId, ?int $userId = null): void
    {
        if (self::$impressions === [] || self::isBot($request)) {
            self::$impressions = [];
            return;
        }

        try {
            $session = self::sessionHash($request);
            // Resolved here rather than during the render: for everyone who
            // is not signed in — which is almost every visitor — it is a
            // query that never runs at all.
            $viewerProId = self::proIdOf($db, $marketId, $userId);

            foreach (self::$impressions as $placementId => $proId) {
                if ($viewerProId !== null && $viewerProId === $proId) {
                    continue;   // a pro refreshing their own listing
                }
                self::record($db, $marketId, $placementId, $proId, 'impression', $session, $request);
            }
        } catch (\Throwable $e) {
            error_log('Ad impression tracking failed: ' . $e->getMessage());
        } finally {
            self::$impressions = [];
        }
    }

    /** A click on a paid listing. Called by the redirect endpoint. */
    public static function click(Database $db, Request $request, int $marketId, int $placementId, int $proId): void
    {
        if (self::isBot($request)) {
            return;
        }
        try {
            self::record($db, $marketId, $placementId, $proId, 'click', self::sessionHash($request), $request);
        } catch (\Throwable $e) {
            error_log('Ad click tracking failed: ' . $e->getMessage());
        }
    }

    /** The pro profile behind a signed-in user, if there is one. */
    private static function proIdOf(Database $db, int $marketId, ?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }
        $id = $db->value(
            'SELECT id FROM pro_profiles WHERE user_id = :u AND market_id = :m LIMIT 1',
            ['u' => $userId, 'm' => $marketId],
        );
        return $id === null || $id === false ? null : (int) $id;
    }

    private static function record(
        Database $db,
        int $marketId,
        int $placementId,
        int $proId,
        string $event,
        string $session,
        Request $request,
    ): void {
        // One per session per placement per day. Without this a visitor who
        // reloads the directory ten times has "delivered" ten impressions,
        // and the pro is being charged against a number that means nothing.
        $already = $db->value(
            "SELECT COUNT(*) FROM ad_events
              WHERE placement_id = :p AND session_hash = :s AND event = :e
                AND created_at >= CURDATE()",
            ['p' => $placementId, 's' => $session, 'e' => $event],
        );
        if ((int) $already > 0) {
            return;
        }

        $db->insert(
            'INSERT INTO ad_events (market_id, placement_id, pro_id, event, page, session_hash, ip_hash)
             VALUES (:m, :p, :pro, :e, :page, :s, :ip)',
            [
                'm' => $marketId, 'p' => $placementId, 'pro' => $proId, 'e' => $event,
                'page' => mb_substr($request->path, 0, 80),
                's' => $session,
                // Hashed, not stored: enough to spot one address hammering a
                // placement, not enough to be a log of who read what.
                'ip' => md5('fixlisted-ip' . $request->ip()),
            ],
        );

        $column = $event === 'click' ? 'clicks' : 'impressions';
        $db->affected(
            "INSERT INTO ad_stats_daily (market_id, pro_id, placement_id, stat_date, {$column})
             VALUES (:m, :pro, :p, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE {$column} = {$column} + 1",
            ['m' => $marketId, 'pro' => $proId, 'p' => $placementId],
        );
    }

    /**
     * A stable per-visitor key for deduplication.
     *
     * The session id when there is one; otherwise the user agent and address,
     * hashed with the app key. Never the raw address — this is a counter, not
     * a record of who looked at whom.
     */
    private static function sessionHash(Request $request): string
    {
        $id = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
        $basis = $id !== ''
            ? $id
            : ($request->server['HTTP_USER_AGENT'] ?? '') . '|' . $request->ip() . '|' . date('Y-m-d');

        return md5((string) Config::get('app.key', 'fixlisted') . $basis);
    }

    /**
     * Crawlers do not buy plumbing.
     *
     * Deliberately a broad substring match rather than a precise list: a bot
     * counted as a person inflates the number a tradesperson is paying
     * against, and the cost of skipping a real visitor now and then is
     * nothing by comparison.
     */
    private static function isBot(Request $request): bool
    {
        $agent = mb_strtolower((string) ($request->server['HTTP_USER_AGENT'] ?? ''));
        if ($agent === '') {
            return true;
        }
        foreach (['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'preview',
                  'headless', 'python-requests', 'curl', 'wget', 'monitor', 'pingdom',
                  'lighthouse', 'gtmetrix', 'semrush', 'ahrefs'] as $needle) {
            if (str_contains($agent, $needle)) {
                return true;
            }
        }
        return false;
    }
}
