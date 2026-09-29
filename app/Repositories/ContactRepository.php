<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Repository;

/**
 * Messages sent from /contact.
 *
 * Written here before the notification email is attempted, so the email is
 * allowed to fail. Two mailto: links used to be the whole contact page, which
 * meant every message lived in one inbox and a bounce or a spam filter lost it
 * without trace. A row is the record; the email is a convenience on top.
 *
 * Nothing here deletes. A message is marked read, never removed — somebody
 * asking for help is a thing that happened, and the admin screen is not the
 * place to decide it did not.
 */
final class ContactRepository extends Repository
{
    /** The tabs on the form. Anything else is stored as 'other'. */
    public const AUDIENCES = ['homeowner', 'pro', 'other'];

    protected function table(): string
    {
        return 'contact_messages';
    }

    /**
     * @param array<string,string> $data
     */
    public function add(array $data, ?string $ip, string $userAgent): int
    {
        $audience = in_array($data['audience'] ?? '', self::AUDIENCES, true)
            ? (string) $data['audience']
            : 'other';

        /*
         * The house pattern for an insert that needs its id back: the market
         * is passed by hand, because applyScope() rewrites reads and updates
         * and an INSERT has no WHERE for it to guard.
         */
        return $this->db->insert(
            "INSERT INTO contact_messages
                 (market_id, audience, name, email, phone, job_reference,
                  business_name, subject, message, ip, user_agent)
             VALUES
                 (:market_id, :audience, :name, :email, :phone, :job_reference,
                  :business_name, :subject, :message, :ip, :user_agent)",
            [
                'market_id'     => $this->scope->marketId,
                'audience'      => $audience,
                'name'          => (string) ($data['name'] ?? ''),
                'email'         => (string) ($data['email'] ?? ''),
                'phone'         => (string) ($data['phone'] ?? ''),
                'job_reference' => (string) ($data['job_reference'] ?? ''),
                'business_name' => (string) ($data['business_name'] ?? ''),
                'subject'       => (string) ($data['subject'] ?? ''),
                'message'       => (string) ($data['message'] ?? ''),
                // Packed, so an IPv6 address fits in the same column and
                // neither form is a string somebody prints into a page.
                'ip'            => $ip !== null && $ip !== ''
                    ? (@inet_pton($ip) ?: null)
                    : null,
                'user_agent'    => mb_substr($userAgent, 0, 255),
            ],
        );
    }

    /** Stamped once the notification actually went, so a null means nobody was told. */
    public function markNotified(int $id): void
    {
        $this->scopedAffected(
            'UPDATE contact_messages SET notified_at = NOW()
              WHERE id = :id AND market_id = :market_id',
            ['id' => $id],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 100): array
    {
        return $this->scopedAll(
            'SELECT * FROM contact_messages
              WHERE market_id = :market_id
              ORDER BY read_at IS NOT NULL, created_at DESC
              LIMIT ' . max(1, min(500, $limit))
        );
    }

    public function find(int $id): ?array
    {
        return $this->scopedOne(
            'SELECT * FROM contact_messages WHERE id = :id AND market_id = :market_id',
            ['id' => $id],
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->scopedAffected(
            "UPDATE contact_messages
                SET read_at = COALESCE(read_at, NOW()), read_by = COALESCE(read_by, :user_id)
              WHERE id = :id AND market_id = :market_id",
            ['id' => $id, 'user_id' => $userId],
        );
    }

    public function markUnread(int $id): void
    {
        $this->scopedAffected(
            'UPDATE contact_messages SET read_at = NULL, read_by = NULL
              WHERE id = :id AND market_id = :market_id',
            ['id' => $id],
        );
    }

    /** Drives the count beside "Messages" in the admin sidebar. */
    public function unreadCount(): int
    {
        return (int) $this->scopedValue(
            'SELECT COUNT(*) FROM contact_messages
              WHERE market_id = :market_id AND read_at IS NULL'
        );
    }

    /**
     * How many this address has sent in the last hour.
     *
     * Not a security boundary — an attacker changes the address. It stops one
     * frustrated person sending the same message nine times while the page
     * looks like it did nothing.
     */
    public function recentlyFrom(string $email): int
    {
        return (int) $this->scopedValue(
            'SELECT COUNT(*) FROM contact_messages
              WHERE market_id = :market_id AND email = :email
                AND created_at > NOW() - INTERVAL 1 HOUR',
            ['email' => $email],
        );
    }
}
