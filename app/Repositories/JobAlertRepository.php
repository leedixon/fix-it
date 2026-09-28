<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Repository;

/**
 * Job alerts for tradespeople who have not listed.
 *
 * A listed pro already gets every matching job automatically — see
 * JobPostingRepository::prosToNotify, which the payment webhook drives. This
 * is the other half: somebody who wants to see the work before committing to
 * a profile, which is the warmest prospect this site can have and previously
 * had nowhere to go.
 *
 * Free, deliberately. /for-pros promises "no per-lead charge" in its hero,
 * and a subscription to receive jobs is a per-lead charge with a monthly
 * wrapper.
 */
final class JobAlertRepository extends Repository
{
    protected function table(): string
    {
        return 'job_alerts';
    }

    /**
     * Take a signup, or refresh one that already exists.
     *
     * Re-subscribing an address that is already here updates what they asked
     * for and re-arms the confirmation rather than failing on the unique key.
     * That also makes this the undo for an accidental unsubscribe: sign up
     * again and it clears, with a fresh confirmation to prove the address is
     * still theirs.
     *
     * @param array<int,int> $tradeIds
     * @param array<int,int> $countyIds
     * @return array{id:int,token:string,already:bool}
     */
    public function subscribe(
        string $email,
        string $firstName,
        string $business,
        array $tradeIds,
        array $countyIds,
        ?string $ip = null,
    ): array {
        $marketId = $this->scope->marketId;

        return (array) $this->db->transaction(
            function () use ($email, $firstName, $business, $tradeIds, $countyIds, $ip, $marketId): array {
                $existing = $this->db->one(
                    'SELECT id, token, confirmed_at FROM job_alerts
                      WHERE market_id = :market AND email = :email LIMIT 1',
                    ['market' => $marketId, 'email' => $email],
                );

                if ($existing !== null) {
                    $id    = (int) $existing['id'];
                    $token = (string) $existing['token'];
                    $this->db->affected(
                        "UPDATE job_alerts
                            SET first_name = :first, business_name = :biz,
                                unsubscribed_at = NULL
                          WHERE id = :id",
                        ['first' => $firstName, 'biz' => $business, 'id' => $id],
                    );
                } else {
                    $token = bin2hex(random_bytes(32));
                    $id    = (int) $this->db->insert(
                        'INSERT INTO job_alerts (market_id, email, first_name, business_name, token, ip)
                         VALUES (:market, :email, :first, :biz, :token, :ip)',
                        [
                            'market' => $marketId,
                            'email'  => $email,
                            'first'  => $firstName,
                            'biz'    => $business,
                            'token'  => $token,
                            'ip'     => $ip !== null ? @inet_pton($ip) ?: null : null,
                        ],
                    );
                }

                // Replaced wholesale rather than merged: the form shows every
                // current choice, so what comes back IS the new answer, and a
                // trade they unticked has to actually go.
                $this->db->affected('DELETE FROM job_alert_trades WHERE alert_id = :id', ['id' => $id]);
                $this->db->affected('DELETE FROM job_alert_counties WHERE alert_id = :id', ['id' => $id]);

                foreach (array_unique($tradeIds) as $tradeId) {
                    $this->db->affected(
                        'INSERT IGNORE INTO job_alert_trades (alert_id, trade_id) VALUES (:a, :t)',
                        ['a' => $id, 't' => $tradeId],
                    );
                }
                foreach (array_unique($countyIds) as $countyId) {
                    $this->db->affected(
                        'INSERT IGNORE INTO job_alert_counties (alert_id, county_id) VALUES (:a, :c)',
                        ['a' => $id, 'c' => $countyId],
                    );
                }

                return [
                    'id'      => $id,
                    'token'   => $token,
                    'already' => $existing !== null && $existing['confirmed_at'] !== null,
                ];
            },
        );
    }

    /** @return array<string,mixed>|null */
    public function findByToken(string $token): ?array
    {
        // Shape-checked first, so a truncated or padded token is refused
        // rather than becoming an index scan.
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        return $this->scopedOne(
            'SELECT * FROM job_alerts WHERE token = :token AND market_id = :market_id LIMIT 1',
            ['token' => $token],
        );
    }

    /** Proves the address is reachable. Nothing is sent before this. */
    public function confirm(string $token): ?array
    {
        $row = $this->findByToken($token);
        if ($row === null) {
            return null;
        }
        if ($row['confirmed_at'] === null) {
            $this->scopedAffected(
                'UPDATE job_alerts SET confirmed_at = NOW(), unsubscribed_at = NULL
                  WHERE id = :id AND market_id = :market_id',
                ['id' => (int) $row['id']],
            );
        }

        return $row;
    }

    /**
     * Stop the alerts.
     *
     * The row stays. Deleting it would lose the record that this person once
     * asked and then asked to stop — which is the one thing worth keeping,
     * because it is what stops them being signed up again by a later import.
     */
    public function unsubscribe(string $token): ?array
    {
        $row = $this->findByToken($token);
        if ($row === null) {
            return null;
        }
        $this->scopedAffected(
            'UPDATE job_alerts SET unsubscribed_at = NOW() WHERE id = :id AND market_id = :market_id',
            ['id' => (int) $row['id']],
        );

        return $row;
    }

    /**
     * Everyone who asked to hear about a job in this county, for this trade.
     *
     * Confirmed, not unsubscribed, and not already listed — a pro who has
     * since taken out a profile gets the alert through prosToNotify and would
     * otherwise receive the same job twice from two different systems.
     *
     * @return array<int,array<string,mixed>>
     */
    public function matching(int $countyId, int $tradeId): array
    {
        return $this->scopedAll(
            "SELECT a.id, a.email, a.first_name, a.token
               FROM job_alerts a
               JOIN job_alert_counties c ON c.alert_id = a.id AND c.county_id = :county
               JOIN job_alert_trades t   ON t.alert_id = a.id AND t.trade_id = :trade
              WHERE a.market_id = :market_id
                AND a.confirmed_at IS NOT NULL
                AND a.unsubscribed_at IS NULL
                AND a.became_pro_id IS NULL
                AND NOT EXISTS (
                      SELECT 1 FROM users u
                        JOIN pro_profiles p ON p.user_id = u.id AND p.status = 'active'
                       WHERE u.email = a.email)
              LIMIT 500",
            ['county' => $countyId, 'trade' => $tradeId],
        );
    }

    public function markSent(int $alertId): void
    {
        $this->scopedAffected(
            'UPDATE job_alerts SET last_sent_at = NOW(), sent_count = sent_count + 1
              WHERE id = :id AND market_id = :market_id',
            ['id' => $alertId],
        );
    }

    /**
     * The admin list: who is on it, what they want, and who converted.
     *
     * became_pro_id is filled opportunistically here rather than by a
     * background job, because the only moment it matters is the moment
     * somebody looks at this screen.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(int $limit = 500): array
    {
        $this->scopedAffected(
            "UPDATE job_alerts a
                SET a.became_pro_id = (
                      SELECT p.id FROM users u
                        JOIN pro_profiles p ON p.user_id = u.id
                       WHERE u.email = a.email AND p.market_id = a.market_id
                       LIMIT 1)
              WHERE a.market_id = :market_id AND a.became_pro_id IS NULL",
        );

        return $this->scopedAll(
            "SELECT a.*,
                    (SELECT GROUP_CONCAT(t.name ORDER BY t.sort_order SEPARATOR ', ')
                       FROM job_alert_trades jt JOIN trades t ON t.id = jt.trade_id
                      WHERE jt.alert_id = a.id) AS trades,
                    (SELECT GROUP_CONCAT(co.short_name ORDER BY co.name SEPARATOR ', ')
                       FROM job_alert_counties jc JOIN counties co ON co.id = jc.county_id
                      WHERE jc.alert_id = a.id) AS counties,
                    p.slug AS pro_slug, p.business_name AS pro_business
               FROM job_alerts a
               LEFT JOIN pro_profiles p ON p.id = a.became_pro_id
              WHERE a.market_id = :market_id
              ORDER BY a.created_at DESC
              LIMIT {$limit}",
        );
    }

    /** @return array{live:int,pending:int,gone:int,converted:int} */
    public function counts(): array
    {
        $row = $this->scopedOne(
            "SELECT
               SUM(confirmed_at IS NOT NULL AND unsubscribed_at IS NULL AND became_pro_id IS NULL) AS live,
               SUM(confirmed_at IS NULL AND unsubscribed_at IS NULL) AS pending,
               SUM(unsubscribed_at IS NOT NULL) AS gone,
               SUM(became_pro_id IS NOT NULL) AS converted
             FROM job_alerts WHERE market_id = :market_id",
        ) ?? [];

        return [
            'live'      => (int) ($row['live'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'gone'      => (int) ($row['gone'] ?? 0),
            'converted' => (int) ($row['converted'] ?? 0),
        ];
    }
}
