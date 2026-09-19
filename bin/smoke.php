<?php
declare(strict_types=1);

/**
 * Foundation smoke test.
 *
 * Proves the stack actually works against a real database, and — more
 * importantly — proves the tenant boundary holds. Run after any change to
 * Repository, TenantScope or a repository query:
 *
 *   php bin/smoke.php
 */

require __DIR__ . '/../app/bootstrap.php';

use FixListed\Core\Auth;
use FixListed\Core\Database;
use FixListed\Core\Repository;
use FixListed\Core\TenantScope;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\MarketRepository;
use FixListed\Repositories\ProRepository;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%s  %s%s\n", $ok ? ' PASS' : '*FAIL', $label, $detail !== '' ? "  ({$detail})" : '');
}

$db      = Database::fromConfig();
$markets = new MarketRepository($db);

// --- connection and tenants -------------------------------------------------
$live = $markets->live();
check('connects and reads markets', count($live) === 3, count($live) . ' live markets');

$austin    = $markets->findBySlug('austin');
$roundRock = $markets->findBySlug('round-rock');
$sanMarcos = $markets->findBySlug('san-marcos');
check('resolves a market by slug', $austin !== null && $austin['code'] === 'ATX');
check('per-market pricing is honoured',
    $markets->listingFeeCents((int) $austin['id']) === 1000
    && $markets->listingFeeCents((int) $sanMarcos['id']) === 0,
    'ATX $10, SMT free');

// --- directory: scoping and ad ordering ------------------------------------
$atxPros = new ProRepository($db, TenantScope::market((int) $austin['id']));
$rrkPros = new ProRepository($db, TenantScope::market((int) $roundRock['id']));

$atx = $atxPros->directory();
$rrk = $rrkPros->directory();

// RRK is 3, not 2: Curtis and Priya live there, and Ray Okafor serves it as a
// second market. That third row is the join table doing its job, which the
// next-but-one assertion checks directly.
check('directory is scoped to its market', count($atx) === 7 && count($rrk) === 3,
    'ATX ' . count($atx) . ', RRK ' . count($rrk));

check('a market sees only its own pros',
    !in_array('alma-reyes', array_column($rrk, 'slug'), true)
    && !in_array('hal-brenner', array_column($atx, 'slug'), true),
    'Austin-only and San Marcos-only pros stay put');

$slugs = array_column($atx, 'slug');
check('paid placement sorts first',
    $slugs[0] === 'ray-okafor' && $slugs[1] === 'teresa-vance' && $slugs[2] === 'dmitri-sokolov',
    implode(', ', array_slice($slugs, 0, 3)));

check('paid rows are flagged for labelling',
    (int) $atx[0]['is_ad'] === 1 && (int) $atx[6]['is_ad'] === 0);

// Ray serves Austin and Round Rock; the join table, not the home market, decides.
check('a pro serving two markets appears in both',
    in_array('ray-okafor', array_column($rrk, 'slug'), true));

check('reads utf8mb4 without mangling',
    str_contains((string) $atxPros->findBySlug('dmitri-sokolov')['headline'], '—'),
    'em-dash survives the round trip');

// --- jobs board: pending_payment must never surface ------------------------
$atxJobs = new JobRepository($db, TenantScope::market((int) $austin['id']));
$board   = $atxJobs->board();
$refs    = array_column($board, 'reference');

check('jobs board is scoped and active-only', count($board) === 5, count($board) . ' jobs');
check('a job awaiting payment stays invisible',
    !in_array('ATX-1D6G3Z', $refs, true),
    'ATX-1D6G3Z absent');
check('a paid job is visible', in_array('ATX-4K2P9M', $refs, true));
check('another market\'s job is not leaked', !in_array('RRK-6C4N8V', $refs, true));

// --- the boundary itself ----------------------------------------------------
final class UnscopedRepo extends Repository
{
    protected function table(): string { return 'jobs'; }
    public function forgetful(): array { return $this->scopedAll('SELECT id FROM jobs'); }
    public function meddling(): array  { return $this->scopedAll('SELECT id FROM jobs WHERE market_id = :market_id', ['market_id' => 999]); }
}
$guard = new UnscopedRepo($db, TenantScope::market(1));

try {
    $guard->forgetful();
    check('a query missing its scope is refused', false, 'it ran');
} catch (LogicException $e) {
    check('a query missing its scope is refused', str_contains($e->getMessage(), 'missing its :market_id'));
}

try {
    $guard->meddling();
    check('hand-passing market_id is refused', false, 'it ran');
} catch (LogicException $e) {
    check('hand-passing market_id is refused', str_contains($e->getMessage(), 'Do not pass market_id'));
}

try {
    TenantScope::crossMarket('');
    check('cross-market scope demands a reason', false, 'it was allowed');
} catch (InvalidArgumentException) {
    check('cross-market scope demands a reason', true);
}

$superadminView = TenantScope::crossMarket('superadmin revenue rollup spans every market');
check('cross-market scope is available deliberately',
    $superadminView->crossMarket && !$superadminView->isBound());

// --- authentication ---------------------------------------------------------
$auth = new Auth($db);
check('correct password authenticates',
    $auth->attempt('owner@fixlisted.com', 'demo-password')['ok'] === true);
check('wrong password is rejected',
    $auth->attempt('owner@fixlisted.com', 'wrong')['ok'] === false);
check('unknown email is rejected',
    $auth->attempt('nobody@example.com', 'demo-password')['reason'] === 'no_such_user');

$auth2 = new Auth($db);
$auth2->attempt('dana@fixlisted.com', 'demo-password');
check('market admin administers only their own market',
    $auth2->canAdminister((int) $austin['id']) && !$auth2->canAdminister((int) $roundRock['id']));

$auth3 = new Auth($db);
$auth3->attempt('owner@fixlisted.com', 'demo-password');
check('superadmin administers every market',
    $auth3->canAdminister((int) $austin['id']) && $auth3->canAdminister((int) $roundRock['id']));

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
