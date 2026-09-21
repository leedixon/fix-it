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

// Counted, not hard-coded. An assertion against a literal 10 fails the moment
// a real tradesperson is approved, which is the system working — and a test
// that cries wolf on success gets ignored on the day it is right.
$activeInMarket = (int) $db->value(
    "SELECT COUNT(*) FROM pro_profiles p
      WHERE p.status = 'active'
        AND EXISTS (SELECT 1 FROM pro_county_areas a
                     WHERE a.pro_id = p.id AND a.market_id = :m)",
    ['m' => $marketId],
);
check('directory lists every active pro',
    count($all) === $activeInMarket,
    count($all) . ' listed, ' . $activeInMarket . ' active in this market');

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
// Relationships, not a literal count: the filtered set must be a strict
// subset of the unfiltered one and must contain the pro who covers that
// county. Asserting "exactly 3" makes the test fail whenever a real
// tradesperson is approved, which is the system working.
check('filtering by county narrows the directory',
    count($joDaviess) < count($slugs)
        && $joDaviess === array_values(array_intersect($slugs, $joDaviess))
        && in_array('hal-brenner', $joDaviess, true),
    'Jo Daviess: ' . implode(', ', $joDaviess));

check('a pro who does not cover a county is absent from it',
    !in_array('priya-raman', $joDaviess, true),
    'Priya covers Ogle only');

$winnebago = $pros->directory(null, $counties['winnebago-il']);
check('the dense county carries the most pros',
    count($winnebago) >= count($joDaviess),
    count($winnebago) . ' in Winnebago vs ' . count($joDaviess) . ' in Jo Daviess');

$roofers = $pros->directory(7);
check('trade filter works',
    count($roofers) > 0 && count($roofers) < count($slugs),
    count($roofers) . ' roofer(s) out of ' . count($slugs));
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

// --- the listing application, end to end ------------------------------------
//
// Creates a real application, checks it is invisible, approves it, checks it
// is visible, then removes it. The rows are cleaned up in a finally block so a
// failed assertion cannot leave a fake tradesperson in the directory.
$applications = new \FixListed\Repositories\ProApplicationRepository($db, TenantScope::market($marketId));
$probeEmail   = 'smoke-' . bin2hex(random_bytes(4)) . '@example.invalid';
$created      = null;

try {
    $created = $applications->create([
        'first_name' => 'Smoke', 'last_name' => 'Test', 'email' => $probeEmail,
        'phone' => '(815) 555-0000', 'business_name' => 'Smoke Test Trades',
        'headline' => 'Automated check', 'bio' => str_repeat('Checking the application path. ', 4),
        'hourly_rate_cents' => 5000, 'years_experience' => 1,
        'home_county_id' => $counties['winnebago-il'], 'zip' => '61103',
        'license_number' => '', 'license_state' => 'IL', 'insurance_carrier' => '',
        'trade_ids' => [1], 'county_ids' => [$counties['winnebago-il']],
    ]);

    check('an application lands as pending_review',
        ($applications->find($created['pro_id'])['status'] ?? '') === 'pending_review');

    check('a pending application is invisible in the directory',
        !in_array($created['slug'], array_column($pros->directory(), 'slug'), true));

    check('a pending application has no public profile page',
        $pros->findBySlug($created['slug']) === null);

    check('it is queued for review',
        in_array($created['pro_id'], array_map('intval', array_column($applications->pending(), 'id')), true));

    $applications->approve($created['pro_id'], 1, true, true);

    check('approving publishes it',
        in_array($created['slug'], array_column($pros->directory(), 'slug'), true));

    $approved = $pros->findBySlug($created['slug']);
    check('approving records who verified what',
        $approved !== null && $approved['license_verified_at'] !== null
            && $approved['insurance_verified_at'] !== null);

    check('approving clears it from the queue',
        !in_array($created['pro_id'], array_map('intval', array_column($applications->pending(), 'id')), true));

    $applications->reject($created['pro_id'], 1, 'smoke test');
    check('suspending takes it back off the directory',
        !in_array($created['slug'], array_column($pros->directory(), 'slug'), true));
} finally {
    if ($created !== null) {
        // pro_trades, pro_county_areas and moderation_items cascade from these.
        $db->affected('DELETE FROM moderation_items WHERE subject_type = :t AND subject_id = :i',
            ['t' => 'pro_profile', 'i' => $created['pro_id']]);
        $db->affected('DELETE FROM pro_profiles WHERE id = :i', ['i' => $created['pro_id']]);
        $db->affected('DELETE FROM users WHERE email = :e', ['e' => $probeEmail]);
    }
}

// --- passwords and quoting --------------------------------------------------
$resets = new \FixListed\Core\PasswordReset($db);
$probeUser = (int) $db->insert(
    "INSERT INTO users (market_id, role, email, first_name, last_name, status)
     VALUES (:m, 'pro', :e, 'Smoke', 'Probe', 'active')",
    ['m' => $marketId, 'e' => 'smoke-pw-' . bin2hex(random_bytes(4)) . '@example.invalid'],
);

try {
    $token = $resets->issue($probeUser);
    check('a reset token resolves to its user',
        ($resets->resolve($token)['id'] ?? 0) === $probeUser);

    check('a token that was never issued resolves to nothing',
        $resets->resolve(str_repeat('a', 64)) === null);

    check('a malformed token is rejected without touching the database',
        $resets->resolve('not-hex-at-all') === null);

    $reset = $resets->resolve($token);
    $resets->complete((int) $reset['reset_id'], $probeUser, 'a-long-enough-password');

    check('completing a token sets the password',
        (new Auth($db))->attempt((string) $reset['email'], 'a-long-enough-password')['ok'] === true);

    check('a spent token cannot be used again', $resets->resolve($token) === null);

    // Issuing a second link must kill the first, or an older intercepted
    // email stays usable.
    $first  = $resets->issue($probeUser);
    $second = $resets->issue($probeUser);
    check('issuing a new link invalidates the previous one',
        $resets->resolve($first) === null && $resets->resolve($second) !== null);
} finally {
    $db->affected('DELETE FROM users WHERE id = :id', ['id' => $probeUser]);
}

// --- one quote per pro per job, and coverage is enforced at the write -------
$quotes  = new \FixListed\Repositories\QuoteRepository($db, TenantScope::market($marketId));
// A job-and-pro pair where the pro covers the county and has not already
// quoted it. The seed ships quotes, so picking the first of each collides.
$pair = $db->one(
    "SELECT j.id AS job_id, j.county_id, a.pro_id
       FROM jobs j
       JOIN pro_county_areas a ON a.county_id = j.county_id AND a.market_id = j.market_id
      WHERE j.market_id = :m AND j.status = 'active'
        AND NOT EXISTS (SELECT 1 FROM quotes q WHERE q.job_id = j.id AND q.pro_id = a.pro_id)
      LIMIT 1",
    ['m' => $marketId],
);
$openJob    = ['id' => $pair['job_id'], 'county_id' => $pair['county_id']];
$quotingPro = (int) $pair['pro_id'];

$quoteId = null;
try {
    $quoteId = $quotes->create((int) $openJob['id'], $quotingPro, [
        'amount_cents' => 12345, 'amount_type' => 'fixed', 'amount_max_cents' => null,
        'message' => 'Smoke test quote.', 'can_start_on' => null,
    ]);
    check('a quote is written and counted', $quoteId > 0);
    check('the job records that it has one', $quotes->hasQuoted((int) $openJob['id'], $quotingPro));

    try {
        $quotes->create((int) $openJob['id'], $quotingPro, [
            'amount_cents' => 999, 'amount_type' => 'fixed', 'amount_max_cents' => null,
            'message' => 'again', 'can_start_on' => null,
        ]);
        check('a second quote on the same job is refused', false, 'it was allowed');
    } catch (RuntimeException $e) {
        check('a second quote on the same job is refused', $e->getMessage() === 'already_quoted');
    }

    // A pro who does not cover the county must be refused at the write, not
    // merely hidden from the list they browse.
    $outsider = (int) $db->value(
        'SELECT p.id FROM pro_profiles p
          WHERE p.market_id = :m
            AND NOT EXISTS (SELECT 1 FROM pro_county_areas a
                             WHERE a.pro_id = p.id AND a.county_id = :c)
          LIMIT 1',
        ['m' => $marketId, 'c' => $openJob['county_id']],
    );
    try {
        $quotes->create((int) $openJob['id'], $outsider, [
            'amount_cents' => 100, 'amount_type' => 'fixed', 'amount_max_cents' => null,
            'message' => 'outside my area', 'can_start_on' => null,
        ]);
        check('a pro cannot quote outside their counties', false, 'it was allowed');
    } catch (RuntimeException $e) {
        check('a pro cannot quote outside their counties', $e->getMessage() === 'not_covered');
    }
} finally {
    if ($quoteId !== null) {
        $db->affected('DELETE FROM quotes WHERE id = :id', ['id' => $quoteId]);
        $db->affected('UPDATE jobs SET quote_count = quote_count - 1 WHERE id = :id', ['id' => $openJob['id']]);
    }
}

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
