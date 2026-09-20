<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * An append-only record of what an administrator did.
 *
 * Every state change an admin makes from the back end goes through here:
 * approving a tradesperson, suspending one, removing a job, changing a fee.
 * The point is being able to answer "who took this listing down, when, and
 * why" three months later, when nobody remembers — and, if there is ever more
 * than one administrator, being able to answer it about someone else.
 *
 * Writes only. Nothing in the application updates or deletes a row here.
 */
final class AuditLog
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string,mixed> $meta */
    public function record(
        string $action,
        ?int $actorUserId,
        string $subjectType = '',
        ?int $subjectId = null,
        array $meta = [],
        ?int $marketId = null,
        string $ip = '',
    ): void {
        $this->db->insert(
            'INSERT INTO audit_log (market_id, actor_user_id, action, subject_type, subject_id, meta, ip)
             VALUES (:market_id, :actor, :action, :subject_type, :subject_id, :meta, :ip)',
            [
                'market_id'    => $marketId,
                'actor'        => $actorUserId,
                'action'       => $action,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'meta'         => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_SLASHES),
                // inet_pton gives the packed form VARBINARY(16) wants, and
                // handles IPv6 without a separate column.
                'ip'           => $ip !== '' ? (inet_pton($ip) ?: null) : null,
            ],
        );
    }
}
