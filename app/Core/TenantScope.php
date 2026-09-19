<?php
declare(strict_types=1);

namespace FixListed\Core;

use InvalidArgumentException;

/**
 * Which market's data a repository may see.
 *
 * Every request resolves to exactly one of these. Almost always it is
 * TenantScope::market($id), bound to the market the visitor is browsing or the
 * market the signed-in user belongs to.
 *
 * TenantScope::crossMarket() is the deliberate escape hatch for superadmin
 * screens that legitimately aggregate across every market — the revenue
 * rollup, the market list. It demands a written reason so that
 * `grep -rn crossMarket app/` returns a short, readable list of every place
 * the boundary is crossed, along with why. If that list ever grows long, or
 * contains a reason that does not justify itself, the security review starts
 * there.
 */
final class TenantScope
{
    private function __construct(
        public readonly ?int $marketId,
        public readonly bool $crossMarket,
        public readonly string $reason,
    ) {
    }

    public static function market(int $marketId): self
    {
        if ($marketId < 1) {
            throw new InvalidArgumentException('A market-bound scope needs a positive market id.');
        }
        return new self($marketId, false, '');
    }

    /** Superadmin only. $reason is required and is not optional documentation. */
    public static function crossMarket(string $reason): self
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(
                'TenantScope::crossMarket() requires a reason describing why this query may span markets.'
            );
        }
        return new self(null, true, $reason);
    }

    public function isBound(): bool
    {
        return !$this->crossMarket;
    }

    public function describe(): string
    {
        return $this->crossMarket
            ? 'all markets (' . $this->reason . ')'
            : 'market ' . $this->marketId;
    }
}
