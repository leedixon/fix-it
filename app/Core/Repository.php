<?php
declare(strict_types=1);

namespace FixListed\Core;

use LogicException;

/**
 * Base class for every repository that reads a tenant-owned table.
 *
 * The rule this class exists to enforce: no query against a tenant table runs
 * without a market_id predicate. Rather than trusting 200 call sites to
 * remember, every query goes through scopedAll()/scopedOne()/scopedValue(),
 * which bind :market_id themselves and refuse SQL that does not use it.
 *
 * The refusal is a LogicException, not a silent filter. A query that forgot
 * its scope is a bug in the code, and the useful behaviour is to fail in
 * development rather than quietly serve one market's jobs to another's
 * visitors in production.
 */
abstract class Repository
{
    public function __construct(
        protected readonly Database $db,
        protected readonly TenantScope $scope,
    ) {
    }

    /** The tenant table this repository owns, for error messages. */
    abstract protected function table(): string;

    /** @return array<int,array<string,mixed>> */
    protected function scopedAll(string $sql, array $params = []): array
    {
        [$sql, $params] = $this->applyScope($sql, $params);
        return $this->db->all($sql, $params);
    }

    /** @return array<string,mixed>|null */
    protected function scopedOne(string $sql, array $params = []): ?array
    {
        [$sql, $params] = $this->applyScope($sql, $params);
        return $this->db->one($sql, $params);
    }

    protected function scopedValue(string $sql, array $params = []): mixed
    {
        [$sql, $params] = $this->applyScope($sql, $params);
        return $this->db->value($sql, $params);
    }

    protected function scopedAffected(string $sql, array $params = []): int
    {
        [$sql, $params] = $this->applyScope($sql, $params);
        return $this->db->affected($sql, $params);
    }

    /**
     * Verifies the query is scoped, and supplies :market_id so callers never
     * have to pass it (and so they cannot pass the wrong one).
     *
     * A query often needs the market in more than one place — the directory
     * filters pros by service area AND joins ad placements for the same
     * market. PDO with native prepared statements cannot reuse one named
     * placeholder, so each :market_id is rewritten to its own numbered
     * placeholder here. Repository authors write :market_id as many times as
     * the query needs and never see this.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function applyScope(string $sql, array $params): array
    {
        if ($this->scope->crossMarket) {
            // Deliberately unscoped. The reason lives on the TenantScope and
            // was required at construction.
            return [$sql, $params];
        }

        if (!str_contains($sql, ':market_id')) {
            throw new LogicException(sprintf(
                'Query against %s is missing its :market_id predicate. Add "market_id = :market_id" '
                . 'to the WHERE clause, or construct this repository with '
                . 'TenantScope::crossMarket($reason) if it genuinely spans markets. SQL: %s',
                $this->table(),
                preg_replace('/\s+/', ' ', trim($sql)),
            ));
        }

        if (array_key_exists('market_id', $params)) {
            throw new LogicException(
                'Do not pass market_id yourself — the scope supplies it. Passing it by hand is how '
                . 'a query ends up reading a market the current user is not in.'
            );
        }

        $n = 0;
        $sql = preg_replace_callback(
            '/:market_id(?![A-Za-z0-9_])/',
            static function () use (&$n): string {
                return ':market_id_' . (++$n);
            },
            $sql,
        );

        for ($i = 1; $i <= $n; $i++) {
            $params['market_id_' . $i] = $this->scope->marketId;
        }

        return [$sql, $params];
    }

    public function scope(): TenantScope
    {
        return $this->scope;
    }
}
