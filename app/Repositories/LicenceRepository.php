<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Database;

/**
 * Who licenses which trade, in which state.
 *
 * Global reference data like counties and trades — Illinois licensing law
 * does not vary by which market is reading it — so this is not a tenant
 * Repository.
 *
 * The rule that matters here: **no row means unknown, never means fine**. A
 * state nobody has entered guidance for must produce an explicit "we do not
 * know yet, find out before you tick the box". Falling back to a reassuring
 * default would put a verified badge on a profile nobody checked, which is
 * the one thing the badge is supposed to rule out.
 */
final class LicenceRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Guidance for one trade in one state.
     *
     * Looks for a row for that exact trade, then the state's fallback row,
     * then gives up honestly.
     *
     * @return array<string,mixed>
     */
    public function forTrade(string $state, int $tradeId): array
    {
        $state = mb_strtoupper(trim($state));

        if ($state !== '') {
            $row = $this->db->one(
                'SELECT * FROM licence_authorities
                  WHERE state = :s AND trade_id IN (:t, 0)
                  ORDER BY trade_id DESC
                  LIMIT 1',
                ['s' => $state, 't' => $tradeId],
            );
            if ($row !== null) {
                return $row + ['known' => true];
            }
        }

        return [
            'state' => $state,
            'trade_id' => $tradeId,
            'licensed' => null,          // genuinely unknown, not "no"
            'authority' => '',
            'lookup_url' => '',
            'number_format' => '',
            'guidance' => $state === ''
                ? 'No state was given on this application, so we cannot say who licenses this trade. '
                . 'Ask before ticking anything.'
                : 'No licensing guidance has been entered for ' . $state . ' yet. Find out who licenses '
                . 'this trade there before ticking the licence box, then add it under Licensing so the '
                . 'next application is quicker.',
            'known' => false,
        ];
    }

    /**
     * Everything for one application: one entry per trade, de-duplicated.
     *
     * Two trades that share a fallback row produce one entry, not two
     * identical paragraphs.
     *
     * @param array<int,array{id:int,name:string}> $trades
     * @return array<int,array<string,mixed>>
     */
    public function forApplication(string $state, array $trades): array
    {
        $out = [];
        foreach ($trades as $trade) {
            $guide = $this->forTrade($state, (int) $trade['id']);
            $key = ($guide['id'] ?? 'unknown') . '';

            if (isset($out[$key])) {
                $out[$key]['trades'][] = $trade['name'];
                continue;
            }
            $guide['trades'] = [$trade['name']];
            $out[$key] = $guide;
        }
        return array_values($out);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->all(
            "SELECT l.*, COALESCE(t.name, 'Every other trade') AS trade_name
               FROM licence_authorities l
               LEFT JOIN trades t ON t.id = l.trade_id
              ORDER BY l.state, (l.trade_id = 0), t.sort_order, t.name"
        );
    }

    /** @return array<int,string> the states guidance exists for */
    public function states(): array
    {
        return array_column($this->db->all('SELECT DISTINCT state FROM licence_authorities ORDER BY state'), 'state');
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM licence_authorities WHERE id = :id', ['id' => $id]);
    }

    /** @param array<string,mixed> $data */
    public function save(?int $id, array $data): void
    {
        if ($id !== null) {
            $this->db->affected(
                'UPDATE licence_authorities
                    SET state = :state, trade_id = :trade, licensed = :licensed, authority = :authority,
                        lookup_url = :url, number_format = :format, guidance = :guidance
                  WHERE id = :id',
                $data + ['id' => $id],
            );
            return;
        }

        // Re-entering a state and trade that already exists updates it rather
        // than failing on the unique index — from the admin's point of view
        // they are editing that combination either way.
        $this->db->affected(
            'INSERT INTO licence_authorities
                (state, trade_id, licensed, authority, lookup_url, number_format, guidance)
             VALUES (:state, :trade, :licensed, :authority, :url, :format, :guidance)
             ON DUPLICATE KEY UPDATE
                licensed = VALUES(licensed), authority = VALUES(authority),
                lookup_url = VALUES(lookup_url), number_format = VALUES(number_format),
                guidance = VALUES(guidance)',
            $data,
        );
    }

    public function delete(int $id): void
    {
        $this->db->affected('DELETE FROM licence_authorities WHERE id = :id', ['id' => $id]);
    }
}
