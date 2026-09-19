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
use FixListed\Repositories\GeographyRepository;
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
check('connects and reads markets', count($live) === 1, count($live) . ' live, ' . (count($markets->all()) - count($live)) . ' staged');

$nwi = $markets->findBySlug('northwest-illinois');
check('resolves a market by slug', $nwi !== null && $nwi['code'] === 'NWI');
$marketId = (int) $nwi['id'];

$geo = new GeographyRepository($db, TenantScope::market($marketId));
check('market owns six counties', count($geo->counties()) === 6,
    implode(', ', array_column($geo->counties(), 'short_name')));
check('cities load, with a subset carrying landing pages',
    count($geo->allCities()) === 46 && count($geo->pageCities()) === 11,
    count($geo->allCities()) . ' cities, ' . count($geo->pageCities()) . ' with pages');
check('a city resolves to its county',
    ($c = $geo->findCity('freeport')) !== null && $c['county'] === 'Stephenson');
check('per-market pricing is honoured', $markets->listingFeeCents($marketId) === 1000);

// --- directory: coverage, no duplicates, ad ordering ------------------------
$pros = new ProRepository($db, TenantScope::market($marketId));
$all  = $pros->directory();
$slugs = array_column($all, 'slug');

check('directory lists every active pro', count($all) === 10, count($all) . ' pros');

// Marcus covers four counties and Karin four. A join would list each of them
// once per county; EXISTS asks the question the query actually means.
check('a multi-county pro is listed once, not once per county',
    count($slugs) === count(array_unique($slugs)),
    'no duplicate rows');

check('paid placement sorts first',
    array_slice($slugs, 0, 4) === ['marcus-ojo', 'karin-halvorsen', 'dwayne-pryor', 'curtis-nwosu'],
    implode(', ', array_slice($slugs, 0, 4)));

check('paid rows are flagged for labelling',
    (int) $all[0]['is_ad'] === 1 && (int) $all[9]['is_ad'] === 0);

// --- county coverage is the whole point of the geography change -------------
$counties = [];
foreach ($geo->counties() as $county) {
    $counties[$county['slug']] = (int) $county['id'];
}

$joDaviess = array_column($pros->directory(null, $counties['jo-daviess-il']), 'slug');
check('filtering by county narrows the directory',
    count($joDaviess) === 3 && in_array('hal-brenner', $joDaviess, true),
    'Jo Daviess: ' . implode(', ', $joDaviess));

check('a pro who does not cover a county is absent from it',
    !in_array('priya-raman', $joDaviess, true),
    'Priya covers Ogle only');

$winnebago = $pros->directory(null, $counties['winnebago-il']);
check('the dense county carries the most pros',
    count($winnebago) === 8, count($winnebago) . ' in Winnebago');

check('trade filter works', count($pros->directory(7)) === 1, 'one roofer');
check('a pro reports the counties they will drive to',
    count($pros->counties(1)) === 4, 'Marcus covers 4');

check('reads utf8mb4 without mangling',
    str_contains((string) $pros->findBySlug('dwayne-pryor')['headline'], '—'),
    'em-dash survives the round trip');

// --- jobs board: pending_payment must never surface ------------------------
$jobs  = new JobRepository($db, TenantScope::market($marketId));
$board = $jobs->board();
$refs  = array_column($board, 'reference');

check('jobs board is scoped and active-only', count($board) === 9, count($board) . ' jobs');
check('a job awaiting payment stays invisible',
    !in_array('NWI-1D6G3Z', $refs, true), 'NWI-1D6G3Z absent');
check('a paid job is visible', in_array('NWI-4K2P9M', $refs, true));
check('jobs carry their city for the landing pages',
    $board[0]['city_name'] !== null && $board[0]['county_name'] !== null,
    $board[0]['city_name'] . ', ' . $board[0]['county_name'] . ' County');
check('a city page shows only that city\'s jobs',
    count($jobs->inCity((int) $geo->findCity('rockford')['id'])) === 2,
    'Rockford has 2 open jobs');

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

$quadCities = $markets->findBySlug('quad-cities');

$auth2 = new Auth($db);
$auth2->attempt('dana@fixlisted.com', 'demo-password');
check('market admin administers only their own market',
    $auth2->canAdminister($marketId) && !$auth2->canAdminister((int) $quadCities['id']));

$auth3 = new Auth($db);
$auth3->attempt('owner@fixlisted.com', 'demo-password');
check('superadmin administers every market',
    $auth3->canAdminister($marketId) && $auth3->canAdminister((int) $quadCities['id']));

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
