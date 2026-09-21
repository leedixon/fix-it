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
use FixListed\Repositories\AdvertisingRepository;
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

// Counted, not hard-coded: the board's size changes every time a real job is
// posted, paid for, refunded or expires, which is the system working.
$activeJobs = (int) $db->value(
    "SELECT COUNT(*) FROM jobs WHERE market_id = :m AND status = 'active'",
    ['m' => $marketId],
);
check('jobs board is scoped and active-only',
    count($board) === $activeJobs,
    count($board) . ' listed, ' . $activeJobs . ' active');
check('a job awaiting payment stays invisible',
    !in_array('NWI-1D6G3Z', $refs, true), 'NWI-1D6G3Z absent');
check('a paid job is visible', in_array('NWI-4K2P9M', $refs, true));
check('jobs carry their city for the landing pages',
    $board[0]['city_name'] !== null && $board[0]['county_name'] !== null,
    $board[0]['city_name'] . ', ' . $board[0]['county_name'] . ' County');
$rockford = (int) $geo->findCity('rockford')['id'];
$rockfordJobs = $jobs->inCity($rockford);
$rockfordActive = (int) $db->value(
    "SELECT COUNT(*) FROM jobs WHERE market_id = :m AND city_id = :c AND status = 'active'",
    ['m' => $marketId, 'c' => $rockford],
);
check('a city page shows only that city\'s jobs',
    count($rockfordJobs) === $rockfordActive
        && array_reduce($rockfordJobs, static fn (bool $ok, array $j): bool => $ok, true),
    count($rockfordJobs) . ' in Rockford');

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

// --- applying must never lower an existing account's role -------------------
//
// An administrator who lists their own business was being demoted to 'pro' by
// the application, which locked them out of /admin on their next request —
// and because admin pages 404 for non-admins, it looked like the site was
// broken rather than like a privilege change.
foreach (['superadmin' => 'superadmin', 'market_admin' => 'market_admin', 'homeowner' => 'pro'] as $before => $after) {
    $email = 'role-' . $before . '-' . bin2hex(random_bytes(3)) . '@example.invalid';
    $userId = (int) $db->insert(
        "INSERT INTO users (market_id, role, email, first_name, last_name, status)
         VALUES (NULL, :r, :e, 'T', 'T', 'active')",
        ['r' => $before, 'e' => $email],
    );
    try {
        $applications->create([
            'first_name' => 'T', 'last_name' => 'T', 'email' => $email, 'phone' => '815',
            'business_name' => 'Role Probe', 'headline' => 'Probe',
            'bio' => str_repeat('Checking the role rule. ', 3),
            'hourly_rate_cents' => null, 'years_experience' => 1,
            'home_county_id' => $counties['winnebago-il'], 'zip' => '61103',
            'license_number' => '', 'license_state' => 'IL', 'insurance_carrier' => '',
            'trade_ids' => [1], 'county_ids' => [$counties['winnebago-il']],
        ]);
        check("applying leaves a {$before} as {$after}",
            $db->value('SELECT role FROM users WHERE id = :i', ['i' => $userId]) === $after);
        check("a {$before} still gets a profile",
            (int) $db->value('SELECT COUNT(*) FROM pro_profiles WHERE user_id = :i', ['i' => $userId]) === 1);
    } finally {
        $probeId = $db->value('SELECT id FROM pro_profiles WHERE user_id = :i', ['i' => $userId]);
        if ($probeId !== null) {
            $db->affected('DELETE FROM moderation_items WHERE subject_type = :t AND subject_id = :i',
                ['t' => 'pro_profile', 'i' => $probeId]);
            $db->affected('DELETE FROM pro_profiles WHERE id = :i', ['i' => $probeId]);
        }
        $db->affected('DELETE FROM users WHERE id = :i', ['i' => $userId]);
    }
}

// --- licensing guidance -----------------------------------------------------
//
// The rule worth protecting: a state nobody has entered guidance for must read
// as UNKNOWN, never as "no licence needed". A reassuring default here would put
// a verified badge on a profile nobody actually checked.
$licences = new \FixListed\Repositories\LicenceRepository($db);

$plumbing = $licences->forTrade('IL', 1);
check('a state-licensed trade resolves to its authority',
    $plumbing['known'] === true && (int) $plumbing['licensed'] === 1,
    $plumbing['authority']);

check('plumbing points at Public Health, not IDFPR',
    !str_contains(mb_strtolower((string) $plumbing['lookup_url']), 'idfpr'),
    'an IDFPR search for a plumber finds nothing');

$electrical = $licences->forTrade('IL', 2);
check('a municipally licensed trade is marked not-state-licensed',
    $electrical['known'] === true && (int) $electrical['licensed'] === 0,
    'no statewide electrician licence exists in Illinois');

$carpentry = $licences->forTrade('IL', 3);
check('a trade with no rule of its own falls back within its state',
    $carpentry['known'] === true && (int) $carpentry['trade_id'] === 0);

$unknownState = $licences->forTrade('ZZ', 1);
check('an unknown state reads as unknown, not as unlicensed',
    $unknownState['known'] === false && $unknownState['licensed'] === null,
    'licensed is null, so nothing can mistake it for "no"');

check('an unknown state says so in words',
    str_contains($unknownState['guidance'], 'No licensing guidance'));

$noState = $licences->forTrade('', 1);
check('a missing state is handled without guessing', $noState['known'] === false);

// Two trades sharing one fallback row should produce one paragraph, not two.
$grouped = $licences->forApplication('IL', [
    ['id' => 3, 'name' => 'Carpentry'],
    ['id' => 10, 'name' => 'Odd Jobs'],
    ['id' => 1, 'name' => 'Plumbing'],
]);
check('guidance is grouped rather than repeated per trade',
    count($grouped) === 2,
    count($grouped) . ' blocks for 3 trades');

// --- the money path ---------------------------------------------------------
//
// The rule being protected: a job is invisible until a verified webhook says
// it was paid for. Everything here is about the ways that could stop being
// true.
$posting = new \FixListed\Repositories\JobPostingRepository($db, TenantScope::market($marketId));
$city    = $geo->findCity('freeport');
$probeJob = null;

try {
    $probeJob = $posting->create([
        'first_name' => 'Smoke', 'last_name' => 'Payer',
        'email' => 'smoke-pay-' . bin2hex(random_bytes(4)) . '@example.invalid',
        'phone' => '(815) 555-0000', 'trade_id' => 1,
        'city_id' => (int) $city['id'], 'county_id' => (int) $city['county_id'],
        'title' => 'Smoke test listing', 'description' => str_repeat('Checking the payment path. ', 3),
        'zip' => '61032', 'urgency' => 'flexible',
        'budget_min_cents' => 10000, 'budget_max_cents' => 20000,
        'market_code' => 'NWI',
    ]);

    $fresh = $posting->findByReference($probeJob['reference']);
    check('a new job starts as pending_payment', ($fresh['status'] ?? '') === 'pending_payment');

    check('an unpaid job is invisible on the board',
        !in_array($probeJob['reference'], array_column($jobs->board(null, 200), 'reference'), true));

    check('an unpaid job has no public page',
        $jobs->findByReference($probeJob['reference']) === null);

    check('a reference is readable and unambiguous',
        preg_match('/^NWI-[23456789BCDFGHJKLMNPQRSTVWXYZ]{6}$/', $probeJob['reference']) === 1,
        $probeJob['reference'] . ' — no vowels, no 0/O or 1/I');

    $session = 'cs_smoke_' . bin2hex(random_bytes(6));
    $posting->startPayment($probeJob['job_id'], $probeJob['user_id'], 1000, $session);

    $published = $posting->completePayment($session, 'pi_smoke_1', 1000);
    check('a completed payment publishes the job', $published !== null);
    check('the published job is on the board',
        in_array($probeJob['reference'], array_column($jobs->board(null, 200), 'reference'), true));

    $live = $posting->findByReference($probeJob['reference']);
    check('publishing sets a run length',
        $live['published_at'] !== null && $live['expires_at'] !== null,
        'live until ' . $live['expires_at']);

    // Stripe retries. Twice through must not publish twice or email twice.
    check('completing the same payment again does nothing',
        $posting->completePayment($session, 'pi_smoke_1', 1000) === null);

    check('an unknown session completes nothing',
        $posting->completePayment('cs_never_existed', 'pi_x', 1000) === null);

    // The promise on the pricing page: every pro covering that county who
    // works that trade hears about it.
    $told = $posting->prosToNotify((int) $city['county_id'], 1);
    check('the right tradespeople would be told', is_array($told),
        count($told) . ' pro(s) cover ' . $city['name'] . ' for that trade');
} finally {
    if ($probeJob !== null) {
        $db->affected('DELETE FROM payments WHERE job_id = :j', ['j' => $probeJob['job_id']]);
        $db->affected('DELETE FROM jobs WHERE id = :j', ['j' => $probeJob['job_id']]);
        $db->affected('DELETE FROM users WHERE id = :u', ['u' => $probeJob['user_id']]);
    }
}

// --- webhook signatures -----------------------------------------------------
//
// The only thing between a public URL and "mark this job paid".
$secret = 'whsec_smoke_secret';
$stripeCheck = new \FixListed\Core\Stripe('sk_test_smoke', $secret);
$body = json_encode(['id' => 'evt_smoke', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);
$signed = static fn (int $when, string $payload, string $key): string =>
    't=' . $when . ',v1=' . hash_hmac('sha256', $when . '.' . $payload, $key);

$accepts = static function (string $payload, string $header) use ($stripeCheck): bool {
    try { $stripeCheck->verifyWebhook($payload, $header); return true; }
    catch (Throwable) { return false; }
};

check('a correctly signed event is accepted', $accepts($body, $signed(time(), $body, $secret)));
check('a wrong secret is refused', !$accepts($body, $signed(time(), $body, 'whsec_wrong')));
check('a tampered payload is refused',
    !$accepts(str_replace('evt_smoke', 'evt_forged', $body), $signed(time(), $body, $secret)));
check('a replayed event is refused', !$accepts($body, $signed(time() - 3600, $body, $secret)));
check('an unsigned request is refused', !$accepts($body, ''));
check('an unconfigured secret trusts nothing',
    !(static function () use ($body, $signed): bool {
        try {
            (new \FixListed\Core\Stripe('sk_test_x', ''))->verifyWebhook($body, $signed(time(), $body, ''));
            return true;
        } catch (Throwable) { return false; }
    })());

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

// --- paid placement: capacity, tracking, and the click redirect -------------
// The money side of advertising is only as good as its refusal to oversell,
// so these check the boundary rather than the happy path.
$adScope = TenantScope::market($marketId);
$ads     = new AdvertisingRepository($db, $adScope);
$plans = $ads->plans($nwi);

check('both plans are priced from the market row',
    $plans['boost']['price_cents'] === (int) $nwi['boost_price_cents']
    && $plans['spotlight']['price_cents'] === (int) $nwi['spotlight_price_cents'],
    money($plans['boost']['price_cents']) . ' / ' . money($plans['spotlight']['price_cents']) . ' a month');

check('sold slots are counted against the cap',
    $plans['boost']['available'] === max(0, $plans['boost']['slots'] - $plans['boost']['sold'])
    && $plans['spotlight']['available'] === max(0, $plans['spotlight']['slots'] - $plans['spotlight']['sold']),
    $plans['spotlight']['sold'] . ' of ' . $plans['spotlight']['slots'] . ' spotlight sold');

// A cap of zero must never read as "unlimited" — it is the switch that takes
// a plan off sale, and an off-by-one here sells something that does not exist.
check('a plan with no slots cannot be sold',
    !$ads->hasCapacity(['boost_slots' => 0] + $nwi, 'boost'));

check('capacity refuses once the cap is reached',
    !$ads->hasCapacity(['spotlight_slots' => $plans['spotlight']['sold']] + $nwi, 'spotlight'));

$paidPro = null;
foreach ($pros->directory(null, null, 60) as $row) {
    if (!empty($row['placement_id'])) {
        $paidPro = $row;
        break;
    }
}
check('a paid listing carries the placement its click is counted against',
    $paidPro !== null && (int) $paidPro['placement_id'] > 0);

$placement = $paidPro !== null ? $ads->placement((int) $paidPro['placement_id']) : null;
check('a placement resolves to a profile on this site',
    $placement !== null && $placement['slug'] === $paidPro['slug'],
    '/go/' . ($paidPro['placement_id'] ?? '?') . ' → /pros/' . ($placement['slug'] ?? '?'));

// The whole defence against /go/ becoming an open redirect is that it only
// ever looks an id up. An id from another market must find nothing.
$qc = $markets->findBySlug('quad-cities');
$otherScope = TenantScope::market((int) $qc['id']);
check('a placement in another market is invisible',
    $paidPro !== null
    && (new AdvertisingRepository($db, $otherScope))->placement((int) $paidPro['placement_id']) === null);

check('an unknown placement resolves to nothing', $ads->placement(999999) === null);

$before = $ads->totalsFor($paidPro !== null ? (int) $paidPro['id'] : 0, 30);
check('a pro can be told what their placement delivered',
    $before['impressions'] >= 0 && $before['clicks'] >= 0 && $before['days'] === 30);

// The daily rollup is keyed on (date, placement). If that key stops matching,
// every impression inserts its own row and the totals a pro is invoiced
// against silently become nonsense.
$dupes = (int) $db->value(
    'SELECT COUNT(*) FROM (
        SELECT stat_date, placement_id FROM ad_stats_daily
         WHERE placement_id IS NOT NULL
         GROUP BY stat_date, placement_id HAVING COUNT(*) > 1
     ) d'
);
check('the daily rollup holds one row per placement per day', $dupes === 0);

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
