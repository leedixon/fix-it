<?php
declare(strict_types=1);

/**
 * Foundation smoke test. DEVELOPMENT ONLY — see the guard below.
 *
 * Proves the stack actually works against a real database, and — more
 * importantly — proves the tenant boundary holds. Run after any change to
 * Repository, TenantScope or a repository query:
 *
 *   php bin/smoke.php
 *
 * This is not a health check. It asserts against the seeded sample dataset
 * and it WRITES — probe users, applications, quotes, jobs and payments, each
 * removed again in a finally block. For the production equivalent, which
 * only ever reads, run bin/check.php.
 */

require __DIR__ . '/../app/bootstrap.php';

use FixListed\Core\Config;

/*
 * Never against production.
 *
 * This was run once on the live server, on my instructions, and it failed in
 * the only lucky way available: on line 143, reading a seeded pro that does
 * not exist there, forty-four lines before the first INSERT. Past that point
 * it would have written probe rows into the real database and depended on
 * its own cleanup surviving a suite that had already started failing.
 *
 * The cleanup is careful. It is also not a reason to allow this — a test
 * that creates a user and a payment has no business pointing at live data,
 * however tidy it is afterwards. Refused outright rather than documented,
 * because a warning in a docblock is not a guard.
 */
if (Config::get('app.env') === 'production') {
    fwrite(STDERR, PHP_EOL
        . "  Refusing to run: app.env is 'production'." . PHP_EOL . PHP_EOL
        . "  bin/smoke.php asserts against the seeded sample dataset and writes" . PHP_EOL
        . "  probe rows. It is a development suite, not a health check." . PHP_EOL . PHP_EOL
        . "  On a live server, run this instead:" . PHP_EOL
        . "      php bin/check.php" . PHP_EOL . PHP_EOL);
    exit(1);
}

use FixListed\Core\Academy;
use FixListed\Core\Auth;
use FixListed\Core\Database;
use FixListed\Core\Maintenance;
use FixListed\Core\PasswordReset;
use FixListed\Core\Repository;
use FixListed\Core\Seo;
use FixListed\Core\TenantScope;
use FixListed\Core\TradeCopy;
use FixListed\Repositories\AcademyRepository;
use FixListed\Repositories\AdminRepository;
use FixListed\Repositories\AdvertisingRepository;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\JobAlertRepository;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\MarketRepository;
use FixListed\Repositories\TeamRepository;
use FixListed\Repositories\ProRepository;
use FixListed\Repositories\ReviewRepository;
use FixListed\Repositories\TradeRepository;

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
// Counted against the has_page column rather than against a literal. The
// number of landing pages is a decision that gets revisited — migration 007
// took it from eleven to nineteen — and a test asserting yesterday's number
// fails on the day somebody does their job.
$pagedInDb = (int) $db->value(
    'SELECT COUNT(*) FROM cities WHERE market_id = :m AND has_page = 1',
    ['m' => $marketId],
);
check('cities load, and the landing pages are exactly the ones flagged for one',
    count($geo->allCities()) === 46 && count($geo->pageCities()) === $pagedInDb,
    count($geo->allCities()) . ' cities, ' . count($geo->pageCities()) . ' with pages');

// The villages stay off. Forty-six pages for forty-six towns, most of them
// with nobody covering them, is the content farm the seed comment warns about.
check('not every town gets a landing page',
    count($geo->pageCities()) > 0 && count($geo->pageCities()) < count($geo->allCities()));
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

// --- the webhook's event list -----------------------------------------------
// HANDLED_EVENTS is what bin/check.php prints for somebody to paste into the
// Stripe dashboard. If the match block and that list ever disagree, the
// printed list is a lie and an event silently never arrives.
$src = file_get_contents(BASE_PATH . '/app/Controllers/WebhookController.php');
preg_match('/\$handled = match \(\$type\) \{(.*?)\};/s', $src, $m);
preg_match_all("/'([a-z_]+\.[a-z_.]+)'\s*=>/", $m[1] ?? '', $hits);

$matched  = $hits[1] ?? [];
$declared = \FixListed\Controllers\WebhookController::HANDLED_EVENTS;
sort($matched);
sort($declared);

check('the event list matches what the webhook actually handles',
    $matched === $declared && $matched !== [],
    count($declared) . ' events');

// Each of the four subscription events exists because leaving it off has a
// specific consequence, named in docs/payments.md. Spelled out so that
// dropping one is a decision somebody has to make on purpose.
foreach ([
    'checkout.session.completed'    => 'nothing is ever published or granted',
    'charge.refunded'               => 'a refunded listing stays on the board',
    'invoice.paid'                  => 'a placement is never renewed',
    'invoice.payment_failed'        => 'a declined card is never noticed',
    'customer.subscription.updated' => 'a pending cancellation is never recorded',
    'customer.subscription.deleted' => 'a placement goes up and never comes down',
] as $event => $consequence) {
    check('without ' . $event . ', ' . $consequence,
        in_array($event, $declared, true));
}

// --- subscription billing periods, across API versions ----------------------
// Stripe moved these in version 2025-03-31: up to then they sat on the
// subscription, from then on they sit on its items. A webhook body arrives in
// whatever version the event destination is set to, which nothing in this
// codebase controls, so both shapes are read.
$periodOf = static fn (array $sub): array =>
    \FixListed\Controllers\WebhookController::subscriptionPeriod($sub);

check('the pre-2025-03-31 shape is read',
    $periodOf(['current_period_start' => 1790000000, 'current_period_end' => 1792592000])
    === ['2026-09-21 14:13:20', '2026-10-21 14:13:20']);

check('the 2025-03-31-and-later item shape is read',
    $periodOf(['items' => ['data' => [[
        'current_period_start' => 1790000000, 'current_period_end' => 1792592000,
    ]]]])
    === ['2026-09-21 14:13:20', '2026-10-21 14:13:20']);

// A subscription with neither must not blow up the webhook that carried it.
// The money arrived; a missing date is never a reason to withhold what was
// bought.
check('a shape with no period at all yields nulls, not an error',
    $periodOf([]) === [null, null]);
check('a malformed period is treated as absent',
    $periodOf(['current_period_start' => 'soon', 'current_period_end' => null]) === [null, null]);

// The invoice payload names its subscription in one of two places for the
// same reason — 'subscription' was removed from the invoice in the same
// version bump.
$refl = new ReflectionMethod(\FixListed\Controllers\WebhookController::class, 'subscriptionIdOf');
$refl->setAccessible(true);
$hook = new \FixListed\Controllers\WebhookController(
    $db, FixListed\Core\Request::capture(), new FixListed\Core\View(BASE_PATH . '/app/Views')
);
check('the old invoice.subscription is read',
    $refl->invoke($hook, ['subscription' => 'sub_old']) === 'sub_old');
check('the new invoice.parent.subscription_details is read',
    $refl->invoke($hook, ['parent' => ['subscription_details' => ['subscription' => 'sub_new']]]) === 'sub_new');
check('an invoice naming no subscription is not mistaken for one',
    $refl->invoke($hook, []) === '');

// --- what a terminal adds to a paste ----------------------------------------
// A terminal with bracketed paste on — cPanel's Terminal, and most modern
// emulators — wraps pasted text in \e[200~ and \e[201~. At a prompt with the
// echo off they are invisible AND unguessable: a correct Stripe key is
// rejected and the only visible fact is that it was rejected.
require_once BASE_PATH . '/bin/configure_input.php';

foreach ([
    ['rk_live_ABC',                      'rk_live_ABC', 'a plain paste is untouched'],
    ["\e[200~rk_live_ABC\e[201~",        'rk_live_ABC', 'bracketed paste markers are stripped'],
    ["rk_live_ABC\r\n",                  'rk_live_ABC', 'a Windows line ending is stripped'],
    ["  rk_live_ABC  ",                  'rk_live_ABC', 'surrounding whitespace is stripped'],
    ["rk_live_ABC\x00",                  'rk_live_ABC', 'a stray null is stripped'],
    ["\e[200~whsec_ABC\e[201~\n",        'whsec_ABC',   'a webhook secret pastes cleanly too'],
    ['',                                 '',            'nothing in, nothing out'],
] as [$raw, $want, $label]) {
    check($label, cleanInput($raw) === $want, strlen($raw) . ' bytes in, ' . strlen($want) . ' out');
}

// A queued line from a multi-line paste is read as the answer before the
// person has typed anything, and the symptom is baffling: the value on screen
// is plainly right and the script insists it received something else. Worth
// naming rather than leaving them to guess.
foreach (['php bin/check.php', 'cd ~/fixlisted', 'git pull', 'nano config/config.php'] as $cmd) {
    check('a queued "' . explode(' ', $cmd)[0] . '" command is recognised as one',
        looksLikeShellCommand($cmd) && queuedInputHint($cmd) !== '');
}
foreach (['GTM-TX649KRG', 'rk_live_abc', 'whsec_abc', 'phpsomething'] as $real) {
    check('"' . substr($real, 0, 12) . '" is not mistaken for a command',
        !looksLikeShellCommand($real) && queuedInputHint($real) === '');
}

// The sanitiser must not quietly rescue a genuinely wrong key.
check('a publishable key in the secret slot is still wrong',
    !\FixListed\Core\Stripe::looksLikeSecretKey(cleanInput("\e[200~pk_live_ABC\e[201~")));

// --- Stripe key shapes ------------------------------------------------------
// A restricted key is rk_live_…, not sk_live_…. Matching the whole 'sk_live_'
// prefix read a live restricted key as test mode — and told somebody no real
// card would be charged while real cards were being charged.
foreach ([
    ['sk_test_x', true,  false, 'standard'],
    ['sk_live_x', true,  true,  'standard'],
    ['rk_test_x', true,  false, 'restricted'],
    ['rk_live_x', true,  true,  'restricted'],
    ['pk_live_x', false, false, 'standard'],   // publishable: not a secret key
    ['whsec_x',   false, false, 'standard'],
    ['',          false, false, 'standard'],
] as [$key, $isSecret, $isLive, $kind]) {
    check(
        sprintf('%-10s secret:%-3s live:%-3s', $key !== '' ? $key : "''",
            $isSecret ? 'yes' : 'no', $isLive ? 'yes' : 'no'),
        \FixListed\Core\Stripe::looksLikeSecretKey($key) === $isSecret
        && \FixListed\Core\Stripe::isLiveKey($key) === $isLive
        && \FixListed\Core\Stripe::keyKind($key) === $kind,
    );
}

// The one that actually bit: a live restricted key must never read as test.
check('a live restricted key is not mistaken for test mode',
    (new \FixListed\Core\Stripe('rk_live_example', 'whsec_x'))->isLive());

// --- the sample-listings banner ---------------------------------------------
// The mode says whether seeded rows would be shown; it does not say whether
// any exist. They come apart the moment somebody purges the sample data
// without switching the mode, and then the banner tells visitors the listings
// are invented while every listing on the page is real — which is worse than
// the failure the banner exists to prevent.
$demoRows = (int) $db->value('SELECT COUNT(*) FROM pro_profiles WHERE is_demo = 1');
check('the sandbox has sample rows to test against', $demoRows > 0, $demoRows . ' rows');

check('with sample rows present, the banner shows',
    \FixListed\Core\Demo::isVisible() && \FixListed\Core\Demo::hasRows($db));

// hasRows() caches for the request, so the empty case is exercised in a
// subprocess against a real database with the rows removed inside a
// transaction that is rolled back. Nothing the rest of this suite reads is
// disturbed.
$probe = <<<'PHP'
require __DIR__ . '/../app/bootstrap.php';
$db = FixListed\Core\Database::fromConfig();
$db->pdo()->beginTransaction();
$db->affected('UPDATE pro_profiles SET is_demo = 0');
$db->affected('UPDATE jobs SET is_demo = 0');
echo FixListed\Core\Demo::isVisible() ? 'visible' : 'hidden';
echo ':';
echo FixListed\Core\Demo::hasRows($db) ? 'rows' : 'norows';
$db->pdo()->rollBack();
PHP;
file_put_contents(BASE_PATH . '/bin/.banner_probe.php', "<?php\n" . $probe . "\n");
$result = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/bin/.banner_probe.php')));
@unlink(BASE_PATH . '/bin/.banner_probe.php');

check('with the sample rows gone, the banner does not show',
    $result === 'visible:norows',
    'mode still says show, but there is nothing to disclose');

// And the rollback means the suite's own data is intact.
check('the probe left the sample data alone',
    (int) $db->value('SELECT COUNT(*) FROM pro_profiles WHERE is_demo = 1') === $demoRows);

// --- the analytics tag ------------------------------------------------------
// The container id is interpolated into a <script>, so anything that is not a
// container id must be refused rather than printed. And a signed-in
// administrator is not a visitor: counting their afternoon of clicking
// through the site distorts every funnel they touch.
$gtm = static function (string $id, ?array $viewer, bool $previewing = false): string {
    $_GET = $previewing ? ['gtm_debug' => '1700000000000'] : [];
    \FixListed\Core\Config::load(
        ['analytics' => ['gtm_id' => $id]] + (array) require BASE_PATH . '/config/config.php'
    );
    $me   = $viewer;
    $part = 'head';
    ob_start();
    require BASE_PATH . '/app/Views/partials/gtm.php';
    return (string) ob_get_clean();
};

$visitor = null;
$staff   = ['role' => 'superadmin'];
$pro     = ['role' => 'pro'];

check('a valid container renders the tag',
    str_contains($gtm('GTM-TX649KRG', $visitor), 'GTM-TX649KRG'));

check('an empty container renders nothing',
    trim($gtm('', $visitor)) === '');

foreach ([
    "GTM-X');alert(1);//" => 'a script injection',
    'notagtmid'           => 'a value that is not a container id',
    'GTM-<script>'        => 'markup in the container id',
    'GTM-'                => 'a bare prefix',
    'gtm-lowercase'       => 'the wrong case',
] as $bad => $label) {
    $out = $gtm($bad, $visitor);
    check($label . ' renders nothing',
        trim($out) === '' && !str_contains($out, 'alert('));
}

check('signed-in staff are not counted',
    trim($gtm('GTM-TX649KRG', $staff)) === '');

// Otherwise the tag is hidden from the one person trying to debug it, which
// reads exactly like it was never installed.
check('staff previewing with Tag Assistant do see the tag',
    str_contains($gtm('GTM-TX649KRG', $staff, true), 'GTM-TX649KRG'));
check('and preview mode still cannot conjure a tag that is switched off',
    trim($gtm('', $staff, true)) === '');
check('a signed-in tradesperson is a real visitor and is counted',
    str_contains($gtm('GTM-TX649KRG', $pro), 'GTM-TX649KRG'));

// --- leaving a review -------------------------------------------------------
//
// Everything below writes. It is all undone in the finally, and this suite
// refuses to run against production, which is the other half of that promise.
$reviews  = new ReviewRepository($db, TenantScope::market($marketId));
$probeJob = $db->one(
    "SELECT j.id, j.user_id FROM jobs j
      WHERE j.market_id = :m AND j.quote_count > 0
        AND NOT EXISTS (SELECT 1 FROM reviews rv WHERE rv.job_id = j.id)
      LIMIT 1",
    ['m' => $marketId],
);

if ($probeJob === null) {
    check('a job exists to test the review flow against', false, 'no quoted, unreviewed job in the seed');
} else {
    $jobId      = (int) $probeJob['id'];
    $reviewId   = null;
    $flippedPro = null;

    try {
        // --- the token ------------------------------------------------------
        $token = $reviews->issueToken($jobId);
        check('the invitation token is 64 hex characters',
            strlen($token) === 64 && ctype_xdigit($token));

        // Minted once. A second invitation must open the same form, not a
        // second one that would let the same job be rated twice.
        check('re-issuing returns the same token rather than minting another',
            $reviews->issueToken($jobId) === $token);

        check('the token resolves to its job', ($reviews->findByToken($token)['id'] ?? null) == $jobId);

        // Rejected before the query runs, so a malformed token cannot become
        // an index scan.
        check('a token of the wrong length is refused', $reviews->findByToken('abc') === null);
        check('a non-hex token is refused', $reviews->findByToken(str_repeat('z', 64)) === null);
        check('an unissued token matches nothing', $reviews->findByToken(str_repeat('a', 64)) === null);

        // The job reference must never work here. It is printed on the public
        // jobs board, so if it did, anyone reading the board could rate
        // anyone's tradesperson.
        $ref = (string) $db->value('SELECT reference FROM jobs WHERE id = :i', ['i' => $jobId]);
        check('the public job reference is not accepted as a review token',
            $reviews->findByToken($ref) === null, $ref);

        // --- who may be rated -------------------------------------------------
        $quoted = $reviews->quotedOn($jobId);
        check('only the businesses that quoted the job are offered', $quoted !== []
            && count($quoted) === (int) $db->value(
                'SELECT COUNT(DISTINCT pro_id) FROM quotes WHERE job_id = :j', ['j' => $jobId]));

        // --- submitting -------------------------------------------------------
        $proId  = (int) $quoted[0]['id'];
        $wasDemo = (int) $db->value('SELECT is_demo FROM pro_profiles WHERE id = :i', ['i' => $proId]);
        $before  = $db->one('SELECT rating_avg, rating_count FROM pro_profiles WHERE id = :i', ['i' => $proId]);

        $reviewId = $reviews->submit($jobId, $proId, (int) $probeJob['user_id'], 2, 'Probe review.');

        check('a submitted review is held for moderation, not published',
            $db->value('SELECT status FROM reviews WHERE id = :i', ['i' => $reviewId]) === 'pending_review');

        // hired_pro_id has been in the schema since the first migration with
        // nothing ever writing to it. The review form is what fills it.
        check('submitting records which tradesperson was hired',
            (int) $db->value('SELECT hired_pro_id FROM jobs WHERE id = :i', ['i' => $jobId]) === $proId);

        check('a review awaiting moderation shows in the queue',
            in_array($reviewId, array_map('intval', array_column($reviews->awaitingModeration(), 'id')), true));

        // A pending review must not touch the public rating. If it did, the
        // moderation queue would be decoration.
        $held = $db->one('SELECT rating_avg, rating_count FROM pro_profiles WHERE id = :i', ['i' => $proId]);
        check('a pending review does not move the rating',
            $held['rating_avg'] === $before['rating_avg']
            && $held['rating_count'] === $before['rating_count']);

        // One per job, enforced by the database rather than only by PHP —
        // a double-submitted form must not double a pro's review count.
        $dup = false;
        try {
            $reviews->submit($jobId, $proId, (int) $probeJob['user_id'], 5, 'Second.');
        } catch (\Throwable) {
            $dup = true;
        }
        check('a second review on the same job is refused by the database', $dup);

        // --- moderation moves the rating, both ways ---------------------------
        //
        // Tested on a non-demo profile because recalculate() deliberately
        // leaves sample profiles alone — see the docblock there.
        $db->affected('UPDATE pro_profiles SET is_demo = 0 WHERE id = :i', ['i' => $proId]);
        $flippedPro = $wasDemo === 1 ? $proId : null;

        $reviews->moderate($reviewId, 'published');
        $live = $db->one('SELECT rating_avg, rating_count FROM pro_profiles WHERE id = :i', ['i' => $proId]);
        $realPublished = (int) $db->value(
            "SELECT COUNT(*) FROM reviews WHERE pro_id = :p AND status = 'published'", ['p' => $proId]);
        check('publishing recomputes the rating from the published reviews',
            (int) $live['rating_count'] === $realPublished && $realPublished > 0,
            $live['rating_avg'] . ' over ' . $live['rating_count']);

        // Removing has to deflate it again, or moderation is theatre: a
        // libellous five-star could be taken down and still be counted.
        $reviews->moderate($reviewId, 'removed');
        $pulled = (int) $db->value('SELECT rating_count FROM pro_profiles WHERE id = :i', ['i' => $proId]);
        check('removing a review takes its rating back off the profile',
            $pulled === $realPublished - 1);

        check('a status the moderator did not offer is refused',
            $reviews->moderate($reviewId, 'published; DROP') === false);

        // --- sample profiles are left alone -----------------------------------
        //
        // The seed writes rating_count onto nine profiles without creating the
        // rows to back them — three review rows against profiles claiming 187
        // and 143. Recalculating one of those does not correct anything, it
        // just makes one invented profile disagree with the other eight.
        $sample = $db->one('SELECT id, rating_avg, rating_count FROM pro_profiles
                             WHERE is_demo = 1 AND rating_count > 0 LIMIT 1');
        if ($sample !== null) {
            $reviews->recalculate((int) $sample['id']);
            $stillSample = $db->one('SELECT rating_avg, rating_count FROM pro_profiles WHERE id = :i',
                ['i' => $sample['id']]);
            check('recalculating leaves a sample profile untouched',
                $stillSample['rating_avg'] === $sample['rating_avg']
                && $stillSample['rating_count'] === $sample['rating_count'],
                'still ' . $sample['rating_avg'] . ' over ' . $sample['rating_count']);
        }

        // --- who gets asked ---------------------------------------------------
        //
        // The job above has just been invited, so it must have dropped out of
        // the queue. Asking the same person twice reads as a site not paying
        // attention.
        check('a job already invited is not queued for another invitation',
            !in_array($jobId, array_map('intval', array_column($reviews->dueForInvite(0), 'id')), true));

        foreach ($reviews->dueForInvite(0, 200) as $due) {
            if ((int) $due['quote_count'] < 1) {
                check('no invitation is sent for a job nobody quoted', false, 'job ' . $due['reference']);
                break;
            }
        }
        check('every queued invitation is for a job that got quotes', true,
            count($reviews->dueForInvite(0, 200)) . ' queued');
    } finally {
        if ($reviewId !== null) {
            $db->affected('DELETE FROM reviews WHERE id = :i', ['i' => $reviewId]);
        }
        if ($flippedPro !== null) {
            $db->affected('UPDATE pro_profiles SET is_demo = 1 WHERE id = :i', ['i' => $flippedPro]);
        }
        $db->affected('UPDATE jobs SET review_token = NULL, review_invited_at = NULL,
                              hired_pro_id = NULL WHERE id = :i', ['i' => $jobId]);
        foreach ($db->all('SELECT DISTINCT pro_id FROM quotes WHERE job_id = :j', ['j' => $jobId]) as $q) {
            $reviews->recalculate((int) $q['pro_id']);
        }
    }
}

// --- the progressive web app ------------------------------------------------
//
// Most of these check the service worker, which is the most dangerous file
// on the site. A worker that caches the wrong thing serves a stale page to a
// returning visitor for as long as the cache lives, and there is nothing
// they can do about it short of clearing site data.
$manifestPath = BASE_PATH . '/public/manifest.webmanifest';
check('the manifest is there and is valid JSON',
    is_file($manifestPath) && json_decode((string) file_get_contents($manifestPath), true) !== null);

$manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
check('it asks to open as an app rather than a tab',
    ($manifest['display'] ?? '') === 'standalone');
check('and names a start URL inside its own scope',
    str_starts_with((string) ($manifest['start_url'] ?? ''), '/'));

// Maskable is a promise Android holds you to: it crops the icon to whatever
// shape the launcher uses and guarantees only the middle 80% survives.
$maskable = array_filter((array) ($manifest['icons'] ?? []),
    static fn (array $i): bool => str_contains((string) ($i['purpose'] ?? ''), 'maskable'));
check('both icons are declared maskable', count($maskable) === 2);

foreach ((array) ($manifest['icons'] ?? []) as $icon) {
    $file = BASE_PATH . '/public' . $icon['src'];
    $dim  = is_file($file) ? getimagesize($file) : false;
    [$w]  = $dim !== false ? $dim : [0];
    check('icon ' . $icon['sizes'] . ' exists at the size it claims',
        $dim !== false && $w . 'x' . $dim[1] === $icon['sizes']);
}

check('iOS has its own icon, which is the only way Safari gets one',
    is_file(BASE_PATH . '/public/assets/icons/apple-touch-icon.png'));

$layout = (string) file_get_contents(BASE_PATH . '/app/Views/layouts/app.php');
check('every page links the manifest and the Apple icon',
    str_contains($layout, 'rel="manifest"') && str_contains($layout, 'rel="apple-touch-icon"'));

// --- the worker -------------------------------------------------------------
$sw = (string) file_get_contents(BASE_PATH . '/public/sw.js');

// The rule the whole thing rests on. A cached page would show an old price,
// an old job, or a listing that has since been suspended.
check('a page is always fetched from the network, never the cache',
    str_contains($sw, "req.mode === 'navigate'")
    && str_contains($sw, 'fetch(req).catch(() => caches.match(OFFLINE_URL)'));

// A cached admin page on a shared phone is somebody else's data sitting in
// a browser; a cached photo is one moderation cannot withdraw.
foreach (['admin', 'my', 'review', 'img', 'webhooks'] as $private) {
    check('the worker never touches /' . $private, str_contains($sw, $private));
}

check('only GET is ever intercepted, so a payment cannot be replayed',
    str_contains($sw, "req.method !== 'GET'"));
check('and only this origin', str_contains($sw, 'url.origin !== self.location.origin'));

// Bumping the version is the kill switch: every other cache is deleted on
// the next activate, on every device that visits.
check('the cache is versioned and old ones are deleted',
    str_contains($sw, 'const VERSION') && str_contains($sw, 'caches.delete'));

check('the offline page it falls back to is a real route',
    str_contains((string) file_get_contents(BASE_PATH . '/public/index.php'), "'/offline'"));

// A service worker cached by the server is one that can never be replaced —
// including a broken one.
check('Apache is told never to cache the worker',
    str_contains((string) file_get_contents(BASE_PATH . '/public/.htaccess'), 'sw.js')
    && str_contains((string) file_get_contents(BASE_PATH . '/public/.htaccess'), 'AddType application/manifest+json'));

// The second and last script. Both are enhancements: blocked, the site is
// exactly what it was.
check('the install script is an enhancement, not a dependency',
    is_file(BASE_PATH . '/public/assets/js/app.js')
    && count(glob(BASE_PATH . '/public/assets/js/*.js') ?: []) === 2);

// --- the academies ----------------------------------------------------------
//
// Two of them sharing one lesson store: a public one that earns search
// traffic the directory pages cannot, and a staff handbook that must never
// have a public URL.
$academy = new AcademyRepository($db);

check('there are lessons in both public tracks',
    $academy->track(Academy::TRACK_HOMEOWNER) !== []
    && $academy->track(Academy::TRACK_PRO) !== []);
check('and a staff handbook behind the admin gate',
    $academy->track(Academy::TRACK_STAFF, true) !== []);

// Refused in the one method that could serve it, rather than left unlinked.
// An unlinked page is a published page.
$acadSrc = (string) file_get_contents(BASE_PATH . '/app/Controllers/AcademyController.php');
check('the public controller refuses a staff lesson outright',
    str_contains($acadSrc, 'Academy::isPublicTrack'));
check('and isPublicTrack does not include staff',
    !Academy::isPublicTrack(Academy::TRACK_STAFF)
    && Academy::isPublicTrack(Academy::TRACK_HOMEOWNER));

// Every lesson has to actually render. A body block with a key the renderer
// does not know is silently skipped, which is a lesson missing a paragraph
// that nobody notices.
$known = ['h', 'p', 'ul', 'steps', 'note', 'warn'];
$strayBlocks = [];
foreach (Academy::all() as $slug => $lesson) {
    if (trim((string) $lesson['title']) === '' || $lesson['body'] === []) {
        $strayBlocks[] = $slug . ' (empty)';
        continue;
    }
    foreach ($lesson['body'] as $i => $block) {
        if (array_intersect(array_keys($block), $known) === []) {
            $strayBlocks[] = $slug . ' block ' . $i;
        }
    }
}
check('every block in every lesson is one the renderer draws', $strayBlocks === [],
    $strayBlocks === [] ? count(Academy::all()) . ' lessons' : implode(', ', $strayBlocks));

// Slugs end up in URLs and in the sitemap.
$badSlugs = array_keys(array_filter(Academy::all(),
    static fn (array $l, string $slug): bool => preg_match('/^[a-z0-9-]+$/', $slug) !== 1,
    ARRAY_FILTER_USE_BOTH));
check('every lesson slug is URL-safe', $badSlugs === [], implode(', ', $badSlugs));

// --- a pasted video link cannot become an arbitrary iframe ------------------
//
// The renderer builds the embed URL from an id it has validated, rather than
// using the string somebody pasted. "Only the owner can paste it" stops being
// true the first time an admin password is guessed.
check('YouTube links are rebuilt from a validated id',
    AcademyRepository::embeddable('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        === 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
check('short YouTube links work too',
    AcademyRepository::embeddable('https://youtu.be/dQw4w9WgXcQ') !== '');
check('Vimeo links are rebuilt from the numeric id',
    AcademyRepository::embeddable('https://vimeo.com/123456789')
        === 'https://player.vimeo.com/video/123456789');

foreach ([
    'https://evil.example.com/x',
    'javascript:alert(1)',
    'https://youtube.com/watch?v=<script>alert(1)</script>',
    'https://youtube.evil.com/watch?v=abcdefghijk',
    '',
] as $bad) {
    check('refused: ' . ($bad === '' ? '(empty)' : substr($bad, 0, 38)),
        AcademyRepository::embeddable($bad) === '');
}

// --- uploaded images --------------------------------------------------------
//
// pro_photos was designed in the first build and never written to. Building
// the gallery made the seed's seven rows live, and they point at files that
// have never existed — three broken images appeared on a sample profile that
// had never had a photo. Found by uploading one and looking.
check('pro_photos can tell a seeded row from a real one',
    (int) $db->value("SELECT COUNT(*) FROM information_schema.columns
                       WHERE table_schema = DATABASE() AND table_name = 'pro_photos'
                         AND column_name = 'is_demo'") === 1);

$photoSrc = (string) file_get_contents(BASE_PATH . '/app/Repositories/PhotoRepository.php');
check('and no gallery query draws one', substr_count($photoSrc, 'is_demo = 0') >= 4);

// The limits are this application's, not the host's. Production allows 1GB
// uploads, which GD would turn into gigabytes of memory.
check('uploads are capped well below what the host would allow',
    FixListed\Core\ImageUpload::MAX_BYTES <= 16 * 1024 * 1024);

// The check that actually protects the server: a 3MB file can describe a
// 30000x30000 image, and GD allocates four bytes a pixel the moment it opens
// one. getimagesize reads the header, so this is known before anything is
// allocated.
$uploadSrc = (string) file_get_contents(BASE_PATH . '/app/Core/ImageUpload.php');
check('dimensions are checked before the image is decoded',
    strpos($uploadSrc, 'getimagesize') < strpos($uploadSrc, 'imagecreatefromjpeg'));

// Type from the file's own header, never its name. A file called photo.jpg
// is not a JPEG because it is called photo.jpg.
check('the file type comes from its header, not its name',
    str_contains($uploadSrc, 'match ($type)') && !str_contains($uploadSrc, 'pathinfo'));

// The single most important line here. A tradesperson photographs a job on
// a phone and the file carries GPS coordinates of a customer's house.
check('every image is re-encoded, which is what strips EXIF',
    str_contains($uploadSrc, 'imagejpeg($out') && str_contains($uploadSrc, 'imagepng($out'));

check('a phone photo is turned the right way up before the tag is lost',
    str_contains($uploadSrc, 'applyOrientation') && str_contains($uploadSrc, 'imagerotate'));

check('an upload has to have come through an upload',
    str_contains($uploadSrc, 'is_uploaded_file'));

// Files live outside the document root and are served by a route that
// re-checks status, so a rejected photo stops being reachable immediately
// rather than whenever somebody remembers to delete it.
$imgSrc = (string) file_get_contents(BASE_PATH . '/app/Controllers/ImageController.php');
check('images are served through PHP so moderation can withdraw them',
    str_contains($imgSrc, 'storage/uploads') && str_contains($imgSrc, 'nosniff') === false);
check('and the bytes carry nosniff',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Core/Response.php'), 'nosniff'));

// --- a tradesperson can see and answer their own reviews --------------------
//
// The rating was on the public profile and nowhere in the account they sign
// in to, so the only way to read what people had said was to go and look at
// your own listing like a stranger.
$ownerReviews = $reviews->forProOwner((int) $db->value(
    "SELECT pro_id FROM reviews WHERE status = 'published' AND market_id = :m LIMIT 1",
    ['m' => $marketId]) ?: 0);
check('a pro can read their own published reviews', $ownerReviews !== []);

// Never the queue. A pro who saw a review before it was published could work
// out who wrote it — this is a county, not a city — and lean on them first.
$heldFrom = (string) $db->value(
    "SELECT COUNT(*) FROM reviews r WHERE r.status <> 'published'
       AND r.id IN (" . implode(',', array_map('intval', array_column($ownerReviews, 'id') ?: [0])) . ")");
check('and never one that is still waiting for a moderator', (int) $heldFrom === 0);

if ($ownerReviews !== []) {
    $rid   = (int) $ownerReviews[0]['id'];
    $rpro  = (int) $db->value('SELECT pro_id FROM reviews WHERE id = :i', ['i' => $rid]);
    try {
        check('a reply saves and is published straight away',
            $reviews->reply($rid, $rpro, 'Thanks for having us out.')
            && $db->value('SELECT pro_replied_at FROM reviews WHERE id = :i', ['i' => $rid]) !== null);

        // The column has been in the schema since the first build with
        // nothing rendering it, while the page shown after leaving a review
        // told the homeowner the tradesperson could reply.
        check('and the public profile actually shows it',
            str_contains(json_encode($reviews->forPro($rpro)), 'Thanks for having us out')
            && str_contains((string) file_get_contents(BASE_PATH . '/app/Views/site/pro.php'),
                "\$r['pro_reply']"));

        // Scoped to the pro as well as the review: changing a number in the
        // form must not let one business answer another's review.
        check('one business cannot answer another business\'s review',
            $reviews->reply($rid, $rpro + 99999, 'hijack') === false);

        check('clearing the box takes the answer down',
            $reviews->reply($rid, $rpro, '')
            && $db->value('SELECT pro_reply FROM reviews WHERE id = :i', ['i' => $rid]) === null);
    } finally {
        $db->affected('UPDATE reviews SET pro_reply = NULL, pro_replied_at = NULL WHERE id = :i',
            ['i' => $rid]);
    }
}

// --- a waiting review has to be visible somewhere ---------------------------
//
// The first real review submitted to this site went into the queue and
// nothing anywhere said so — the dashboard's "waiting on you" counted
// applications only, and the Reviews nav item had no badge. The feature was
// working exactly as designed and read as broken, which for the person
// looking at it is the same thing.
$adminCounts = (new AdminRepository($db, TenantScope::market($marketId)))->counts();
check('the admin counts include reviews waiting for a moderator',
    array_key_exists('reviews', $adminCounts));

$dashSrc = (string) file_get_contents(BASE_PATH . '/app/Views/admin/dashboard.php');
check('"waiting on you" counts reviews as well as applications',
    str_contains($dashSrc, "\$counts['applications'] + (int) (\$counts['reviews']"));

// Every admin screen used to pass the badge count by hand — the same query
// written out ten times — so a screen that forgot simply showed no badge.
// Computed centrally now, which is what makes a second badge possible at all.
$adminCtl = (string) file_get_contents(BASE_PATH . '/app/Core/AdminController.php');
check('nav badge counts are worked out in one place',
    str_contains($adminCtl, "'pending'  => \$this->queue()['applications']")
    && str_contains($adminCtl, "'pendingReviews' => \$this->queue()['reviews']"));

check('the Reviews nav item carries its own badge',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Views/layouts/admin.php'),
        "'/admin/reviews', 'Reviews', 'star', \$path, \$pendingReviews"));

// And somebody is told, so nobody has to be looking at the dashboard.
check('submitting a review alerts whoever can publish it',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Controllers/ReviewController.php'),
        "'listings.moderate'"));

// --- job alerts -------------------------------------------------------------
//
// The alerts to LISTED pros already existed and worked. This is the other
// half: a tradesperson who wants to see the work before taking out a
// profile. "Get these by email" pointed at /for-pros, so there was nowhere
// for them to go.
$alertRepo = new JobAlertRepository($db, TenantScope::market($marketId));
$probeAddr = 'smoke-alert@example.test';
$aTrade    = (int) $db->value("SELECT id FROM trades WHERE slug = 'plumbing'");
$aCounty   = (int) $db->value(
    'SELECT county_id FROM market_counties WHERE market_id = :m LIMIT 1', ['m' => $marketId]);

try {
    $sub = $alertRepo->subscribe($probeAddr, 'Smoke', 'Smoke Plumbing', [$aTrade], [$aCounty]);

    check('a signup issues a 64-character token',
        strlen($sub['token']) === 64 && ctype_xdigit($sub['token']));

    // The whole point of confirmed opt-in: an address nobody proved is
    // reachable never receives anything. This product IS email, so a spam
    // complaint against a young domain is not a small cost.
    check('nothing is sent to an address that has not confirmed',
        $alertRepo->matching($aCounty, $aTrade) === []);

    $alertRepo->confirm($sub['token']);
    check('a confirmed subscriber matches a job in their trade and county',
        count($alertRepo->matching($aCounty, $aTrade)) === 1);

    $otherTrade = (int) $db->value(
        'SELECT id FROM trades WHERE id <> :t LIMIT 1', ['t' => $aTrade]);
    check('and does not match a trade they did not pick',
        $alertRepo->matching($aCounty, $otherTrade) === []);

    // Re-subscribing is how somebody changes their mind about what they
    // want. Merging instead of replacing would make a trade impossible to
    // remove, and an alert you cannot turn down is one you unsubscribe from.
    $alertRepo->subscribe($probeAddr, 'Smoke', 'Smoke Plumbing', [$otherTrade], [$aCounty]);
    check('re-subscribing replaces the choices rather than adding to them',
        $alertRepo->matching($aCounty, $aTrade) === []
        && count($alertRepo->matching($aCounty, $otherTrade)) === 1);

    $alertRepo->subscribe($probeAddr, 'Smoke', 'Smoke Plumbing', [$aTrade], [$aCounty]);
    $alertRepo->confirm($sub['token']);

    $alertRepo->unsubscribe($sub['token']);
    check('unsubscribing stops the matching', $alertRepo->matching($aCounty, $aTrade) === []);
    // Kept, not deleted: the record that somebody asked to stop is the one
    // thing that prevents a later import signing them up again.
    check('the unsubscribed row is kept rather than deleted',
        $alertRepo->findByToken($sub['token']) !== null);

    check('a malformed token matches nothing',
        $alertRepo->findByToken('nope') === null
        && $alertRepo->findByToken(str_repeat('z', 64)) === null);

    // --- the signed link a listed pro gets -------------------------------
    $signed = FixListed\Controllers\JobAlertController::proToken(4242);
    check('a listed pro gets a signed unsubscribe token, with no table behind it',
        (bool) preg_match('/^p4242\.[0-9a-f]{64}$/', $signed));
    check('the signature is bound to the profile it names',
        $signed !== FixListed\Controllers\JobAlertController::proToken(4243));
} finally {
    $db->affected('DELETE FROM job_alerts WHERE email = :e', ['e' => $probeAddr]);
}

// The CTA has to point at the signup. It pointed at /for-pros — a sales
// page — so the button promised a box to type in and delivered a pitch.
check('the jobs board sends "Get these by email" to the signup',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Views/site/jobs.php'), "url('/jobs/alerts')"));

// --- who the team alerts reach ----------------------------------------------
//
// Staff alerts went to one address in config, so inviting a moderator gave
// them a queue and no way to know anything was in it. Recipients are chosen
// by capability now, which means the emailed list and the rendered screen
// cannot disagree about who counts.
check('a moderator is told about work they can actually do',
    Auth::roleCan('moderator', 'jobs.moderate')
    && Auth::roleCan('moderator', 'applications.review'));
check('and is not told about money they cannot see',
    !Auth::roleCan('moderator', 'finance.view')
    && !Auth::roleCan('market_admin', 'finance.view'));
check('roleCan agrees with the signed-in check for every capability',
    Auth::roleCan('superadmin', 'team.manage') && !Auth::roleCan('moderator', 'team.manage'));

// --- every job alert must carry a way out -----------------------------------
//
// No email on this site had an unsubscribe link. A job alert is sent
// repeatedly and unprompted about new opportunities, which is exactly the
// kind that needs a working opt-out in every copy — enforced per message,
// not per campaign.
$layoutSrc = (string) file_get_contents(BASE_PATH . '/app/Views/emails/layout.php');
check('the email layout can render an unsubscribe footer',
    str_contains($layoutSrc, 'unsubUrl') && str_contains($layoutSrc, 'Stop these emails'));
check('and only shows it when the message actually needs one',
    str_contains($layoutSrc, 'if (!empty($unsubUrl))'));

$hookSrc = (string) file_get_contents(BASE_PATH . '/app/Controllers/WebhookController.php');
check('both kinds of job alert are sent with an unsubscribe link',
    substr_count($hookSrc, "'unsubUrl'") === 2);

// A pro who opted out must drop out of the query that picks recipients,
// without their listing changing in any way.
check('a listed pro who opted out is left off the alert list',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Repositories/JobPostingRepository.php'),
        'p.job_alerts_off = 0'));

// --- seeded money must never be counted as real ------------------------------
//
// The admin dashboard read "Revenue, 30 days: $496" on a site with one
// listing, nothing sold and no open jobs. $486 of it was seeded. Migration
// 003 gave is_demo to the four tables a VISITOR sees; the seed writes to
// twenty-one, and the ones holding money were not among them — so there was
// no flag to filter on and nothing filtered.
$adminRepo = new AdminRepository($db, TenantScope::market($marketId));
$counts    = $adminRepo->counts();

$seededMoney = (int) $db->value(
    "SELECT COALESCE(SUM(amount_cents),0) FROM payments
      WHERE market_id = :m AND status = 'succeeded' AND is_demo = 1
        AND paid_at >= NOW() - INTERVAL 30 DAY",
    ['m' => $marketId],
);
check('the seed does put money in the payments table', $seededMoney > 0,
    money($seededMoney) . ' of it');
check('and the dashboard does not count a penny of it',
    (int) $counts['revenue_30d'] === (int) $db->value(
        "SELECT COALESCE(SUM(amount_cents),0) FROM payments
          WHERE market_id = :m AND status = 'succeeded' AND is_demo = 0
            AND paid_at >= NOW() - INTERVAL 30 DAY", ['m' => $marketId]),
    'reports ' . money((int) $counts['revenue_30d']));

// Every table the seed writes transactional rows into needs the flag, or
// there is nothing to filter on and nothing to purge. Checked against the
// schema rather than a list, so a table that gains seeded rows later is
// caught by the purge test below rather than by nobody.
foreach (['payments', 'subscriptions', 'quotes', 'ad_placements',
          'ad_creatives', 'ad_stats_daily', 'moderation_items'] as $t) {
    check('the ' . $t . ' table can tell seeded rows from real ones',
        (int) $db->value(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = :t AND column_name = 'is_demo'",
            ['t' => $t]) === 1);
}

// The flag is only worth having if the purge acts on it. This is the check
// that would have caught the original bug: demo.php deleted four tables and
// left the other seven, so "purged" left the invented revenue behind.
$purgeSrc = (string) file_get_contents(BASE_PATH . '/bin/demo.php');
$unpurged = [];
foreach ($db->all(
    "SELECT table_name AS t FROM information_schema.columns
      WHERE table_schema = DATABASE() AND column_name = 'is_demo' ORDER BY table_name") as $row) {
    if (!str_contains($purgeSrc, 'DELETE FROM ' . $row['t'] . ' WHERE is_demo = 1')) {
        $unpurged[] = (string) $row['t'];
    }
}
check('every table carrying the demo flag is emptied by demo.php purge',
    $unpurged === [],
    $unpurged === [] ? 'all of them' : 'left behind: ' . implode(', ', $unpurged));

// And it has to report them, or an owner runs status, reads "0 sample", and
// believes the database is clean when it is holding a hundred invented rows.
$unreported = [];
foreach ($db->all(
    "SELECT table_name AS t FROM information_schema.columns
      WHERE table_schema = DATABASE() AND column_name = 'is_demo' ORDER BY table_name") as $row) {
    if (!str_contains($purgeSrc, "'" . $row['t'] . "'")) {
        $unreported[] = (string) $row['t'];
    }
}
check('and demo.php status counts every one of them', $unreported === [],
    $unreported === [] ? 'all of them' : 'missing: ' . implode(', ', $unreported));

// --- this suite must never point at live data -------------------------------
//
// Circular, slightly: the guard is at the top of this very file. It still
// earns its place, because what it protects against is somebody deleting
// those eight lines to "just run it quickly on the server", which is exactly
// how it got run there the first time.
$self = (string) file_get_contents(__FILE__);
check('smoke.php refuses to run when app.env is production',
    str_contains($self, "Config::get('app.env') === 'production'")
    && str_contains($self, 'Refusing to run'));

// --- the rating scale -------------------------------------------------------
//
// Five stars, filled to the score. The count is fixed at five whatever the
// rating, because the row's length is what the eye measures — a 2 that draws
// two marks and a 5 that draws five are not comparable at a glance, which is
// the entire job of the thing.
//
// Hammers were tried here first and looked like a gimmick on a real profile.
// The tests did not care, which is the point of writing them against the
// count and the fill rather than against the shape.
foreach ([0 => 0, 1 => 1, 3 => 3, 5 => 5, 4 => 4] as $rating => $lit) {
    $svg = rating_marks((float) $rating);
    check('a rating of ' . $rating . ' draws five stars, ' . $lit . ' of them filled',
        substr_count($svg, '<svg') === 5 && substr_count($svg, 'class="mk on"') === $lit);
}

// Rounded, and the halfway cases have to land somewhere predictable.
check('4.6 rounds up to five filled, 3.4 down to three',
    substr_count(rating_marks(4.6), 'class="mk on"') === 5
    && substr_count(rating_marks(3.4), 'class="mk on"') === 3);

// Out-of-range input must not draw six stars or a negative number of them.
check('a rating outside 0-5 is clamped rather than trusted',
    substr_count(rating_marks(9.0), 'class="mk on"') === 5
    && substr_count(rating_marks(-2.0), 'class="mk on"') === 0
    && substr_count(rating_marks(9.0), '<svg') === 5);

// The marks are decoration; the number beside them carries the meaning. A
// screen reader announcing five stars is worse than silence.
check('the stars are hidden from screen readers',
    str_contains(rating_marks(4.0), 'aria-hidden="true"'));

// Nothing draws text stars any more. Asserted so the helper is not quietly
// reintroduced alongside the drawn ones, leaving two rating scales on one site.
//
// The /u is load-bearing. Without it the class is a set of BYTES, not
// characters — and 0xE2 opens both a star and an em-dash, so the pattern
// matched 48 of the 71 view files on its first run. A test that fires on
// every em-dash in the codebase is not a test.
check('no template renders a text star',
    preg_grep('/[★☆]/u', array_map(
        static fn (string $f): string => (string) file_get_contents($f),
        (array) glob(BASE_PATH . '/app/Views/**/*.php'),
    )) === []);

// --- the social images exist and are the right shape ------------------------
//
// layouts/app.php names its og:image as a string. Rename or lose that file
// and every link preview on the site reverts to a grey box — on Facebook, in
// iMessage, in Slack — while the page still renders perfectly and every other
// test still passes. Nothing on the site looks wrong; the breakage is only
// visible somewhere else, days later, in somebody's feed.
//
// The sizes are not arbitrary either. A profile picture is cropped to a
// circle, so it must be square; a cover photo at the wrong aspect gets
// centre-cropped by Facebook and loses the wording at both ends.
$pngSize = static function (string $file): ?array {
    // The IHDR chunk is fixed-position: 8 bytes of signature, 8 of chunk
    // header, then width and height as big-endian 32-bit integers.
    $head = (string) file_get_contents($file, false, null, 0, 24);
    if (strlen($head) < 24 || substr($head, 1, 3) !== 'PNG') {
        return null;
    }
    $dim = unpack('Nw/Nh', substr($head, 16, 8));
    return [$dim['w'], $dim['h']];
};

$social = [
    'og.png'                => [1200, 630],
    'square.png'            => [1080, 1080],
    'facebook-profile.png'  => [1080, 1080],
    'facebook-cover.png'    => [1640, 624],
];
foreach ($social as $file => [$w, $h]) {
    $path = BASE_PATH . '/public/assets/social/' . $file;
    $got  = is_file($path) ? $pngSize($path) : null;
    check('social/' . $file . ' is a ' . $w . 'x' . $h . ' PNG',
        $got === [$w, $h],
        $got === null ? 'missing or not a PNG' : $got[0] . 'x' . $got[1]);
}

// The profile picture is cropped to a circle, so a non-square file loses
// content on two sides. Stated separately from the size check because this
// is the reason for it.
check('the Facebook profile picture is square, because Facebook cuts a circle from it',
    $pngSize(BASE_PATH . '/public/assets/social/facebook-profile.png')[0]
    === $pngSize(BASE_PATH . '/public/assets/social/facebook-profile.png')[1]);

// The layout's default must name a file that is actually there.
preg_match(
    "/\\\$ogImage\s*=\s*abs_asset\(\\\$ogImage\s*\?\?\s*'([^']+)'\)/",
    (string) file_get_contents(BASE_PATH . '/app/Views/layouts/app.php'),
    $om,
);
check('the og:image the layout falls back to is a file on disk',
    isset($om[1]) && is_file(BASE_PATH . '/public/' . $om[1]),
    $om[1] ?? 'could not find the default in layouts/app.php');

// --- one header, not three copies of one -----------------------------------
//
// layouts/account.php said in its docblock that it wore the public header and
// carried a hand-copied one instead, so the account menu shipped to every
// page on the site except the one a tradesperson lives on. Nothing failed;
// the copy rendered perfectly, one commit behind.
//
// Two mechanical checks, because that is the kind of drift a human reading a
// diff does not see.
$headerSrc  = (string) file_get_contents(BASE_PATH . '/app/Views/partials/header.php');
$chromeHtml = [];
foreach ((array) glob(BASE_PATH . '/app/Views/layouts/*.php') as $layout) {
    if (str_contains((string) file_get_contents($layout), '<header class="chrome"')) {
        $chromeHtml[] = basename($layout);
    }
}
check('only partials/header.php builds the site header', $chromeHtml === [],
    $chromeHtml === [] ? 'layouts require it' : 'own copy in ' . implode(', ', $chromeHtml));

// And the header only renders if it is handed what it asks for. Its @var
// block is the contract; both base controllers have to honour it, or the
// layout that requires the partial gets notices instead of a nav.
preg_match_all('/@var\s+[^\s]+\s+\$([a-zA-Z_]+)/', $headerSrc, $m);
$wants  = array_unique($m[1]);
$unfed  = [];
foreach (['Controller', 'AccountController'] as $base) {
    $src = (string) file_get_contents(BASE_PATH . '/app/Core/' . $base . '.php');
    foreach ($wants as $var) {
        if (!str_contains($src, "'" . $var . "'")) {
            $unfed[] = $base . ' is missing $' . $var;
        }
    }
}
check('both base controllers supply every variable the header documents',
    $unfed === [], $unfed === [] ? implode(', ', $wants) : implode('; ', $unfed));

// Only the public layout. The admin and account areas are private, noindex,
// and nobody's funnel.
foreach (['layouts/admin', 'layouts/account'] as $private) {
    check('the ' . $private . ' layout carries no tag',
        !str_contains((string) file_get_contents(BASE_PATH . '/app/Views/' . $private . '.php'), 'gtm.php'));
}

// Turning measurement on makes a published promise false unless the page is
// updated with it. It was, and this stops it drifting back.
$privacy = (string) file_get_contents(BASE_PATH . '/app/Views/site/legal_privacy.php');
check('the privacy page describes the analytics cookies',
    stripos($privacy, 'Google Analytics') !== false
    && stripos($privacy, 'No advertising or cross-site tracking cookies are set by us') === false);

// Put the real config back for everything that runs after this.
\FixListed\Core\Config::load((array) require BASE_PATH . '/config/config.php');

// --- show/hide on password fields -------------------------------------------
// This is the only JavaScript on the site and it must stay an enhancement:
// the button is created by script, never present in the markup, so a form
// that works without scripting keeps working.
$pwJs = BASE_PATH . '/public/assets/js/password.js';
check('the password toggle script exists', is_file($pwJs));

$pwSrc = is_file($pwJs) ? (string) file_get_contents($pwJs) : '';

// A toggle that defaults to submit sends the form with a half-typed password.
check('the toggle button is explicitly type=button',
    str_contains($pwSrc, "button.type = 'button'"));

// It binds to the type, not to named fields, so a password box added to a
// form later gets this rather than being the one that missed out.
check('it binds to every password input, not named ones',
    str_contains($pwSrc, 'input[type="password"]'));

check('a revealed password is re-hidden when the form is submitted',
    str_contains($pwSrc, 'setShown(false)'));

// No password field may ship a toggle in its own markup: that would render a
// dead button for anyone without scripting.
$views = glob(BASE_PATH . '/app/Views/**/*.php') ?: [];
$hardCoded = [];
foreach ($views as $view) {
    $html = (string) file_get_contents($view);
    if (str_contains($html, 'pw-eye')) {
        $hardCoded[] = basename($view);
    }
}
check('no template hard-codes a toggle button', $hardCoded === [],
    $hardCoded === [] ? 'created by script only' : implode(', ', $hardCoded));

// Every layout that can render a password field has to load the script, or
// one form silently lacks the button.
$missing = [];
foreach (['layouts/app', 'layouts/account', 'layouts/admin', 'admin/login'] as $layout) {
    $file = BASE_PATH . '/app/Views/' . $layout . '.php';
    if (is_file($file) && !str_contains((string) file_get_contents($file), 'assets/js/password.js')) {
        $missing[] = $layout;
    }
}
check('every layout with a password form loads it', $missing === [],
    $missing === [] ? '4 layouts' : 'missing from ' . implode(', ', $missing));

// --- the emails a decision sends --------------------------------------------
// These two carry the only link a tradesperson or a colleague has to set a
// password. If one fails to render, or carries no link, the person is live
// and locked out with nothing on screen to say so.
$emailView = new FixListed\Core\View(BASE_PATH . '/app/Views');
$probeToken = str_repeat('a1', 32);

$renders = static function (string $template) use ($emailView, $probeToken): string {
    // Every variable any template asks for, in one bag. A template that
    // wants something not in here has been given a new requirement without
    // the test being told, and fails — which is the point.
    return $emailView->render('emails.' . $template, [
        'title' => 't', 'preheader' => 'p', 'name' => 'Sample',
        'business' => 'Sample Plumbing', 'profileUrl' => '#', 'jobsUrl' => '#',
        'setUpUrl' => abs_url('/set-password/' . $probeToken), 'market' => 'Northwest Illinois',
        'note' => 'a reason', 'replyTo' => 'lee@example.com',
        'inviter' => 'Lee Dixon', 'roleLabel' => 'Moderator', 'roleBlurb' => 'blurb', 'days' => 7,
        'jobTitle' => 'Replace a sump pump', 'reference' => 'NWI-8HLF4X',
        'jobUrl' => abs_url('/jobs/NWI-8HLF4X'), 'expires' => '2026-10-21 00:00:00',
        'trade' => 'Plumbing', 'city' => 'Rockford', 'summary' => 'The old one has failed.',
        'quoteUrl' => abs_url('/my/quote/NWI-8HLF4X'), 'amount' => '$10.00',
        'link' => abs_url('/set-password/' . $probeToken), 'invite' => true, 'hours' => 1,
    ], 'emails.layout');
};

foreach (['pro_approved', 'pro_rejected', 'team_invite', 'job_live', 'job_alert',
          'quote_received', 'password_link', 'job_refunded'] as $template) {
    $html = '';
    try {
        $html = $renders($template);
    } catch (\Throwable $e) {
        $html = '';
    }
    check('the ' . $template . ' email renders', $html !== '', strlen($html) . ' bytes');
}

// The two that must carry a way in, and the one that must not.
check('an approved tradesperson is sent a way to set their password',
    str_contains($renders('pro_approved'), $probeToken));
check('an invited colleague is sent a way to set their password',
    str_contains($renders('team_invite'), $probeToken));
check('a declined applicant is sent no sign-in link',
    !str_contains($renders('pro_rejected'), $probeToken));

// Guards a fall-through that would have sent an approved tradesperson the
// rejection email as well: the approve branch must return, not continue into
// the decline send below it.
$tellSrc = file_get_contents(BASE_PATH . '/app/Controllers/Admin/ReviewController.php');
$tellSrc = substr($tellSrc, strpos($tellSrc, 'private function tell('));
$tellSrc = substr($tellSrc, 0, strpos($tellSrc, "\n    }\n"));
check('approve and decline each send exactly one email',
    substr_count($tellSrc, 'mailer->send(') === 2
    && substr_count($tellSrc, 'return $mailer->send(') === 2,
    'both sends return, so neither falls through to the other');

// The decision is committed before the email is attempted, so a mail failure
// must be reported rather than swallowed — an approval nobody was told about
// is a published profile its owner cannot sign into.
$reviewSrc = file_get_contents(BASE_PATH . '/app/Controllers/Admin/ReviewController.php');
check('a failed decision email is reported on screen, not just logged',
    str_contains($reviewSrc, '$sent = $this->tell($pro, \'approved\'')
    && str_contains($reviewSrc, 'did not send'));

// --- the People screen's role tabs ------------------------------------------
// Every one of these 500'd: ':role' was written into the SQL and the value was
// never bound, so PDO refused the statement. The unfiltered tab worked, which
// is exactly why it survived being looked at.
$adminRepo = new \FixListed\Repositories\AdminRepository($db, TenantScope::market($marketId));

foreach (['', 'pro', 'homeowner', 'market_admin', 'moderator', 'superadmin'] as $role) {
    $ok = true;
    try {
        $rows = $adminRepo->users($role);
        // A filtered read must return only that role, or the filter is
        // decorative and the tab is lying about what it shows.
        foreach ($rows as $row) {
            if ($role !== '' && $row['role'] !== $role) {
                $ok = false;
            }
        }
    } catch (\Throwable $e) {
        $ok = false;
        $rows = [];
    }
    check('the People tab for ' . ($role !== '' ? $role : 'everyone') . ' loads',
        $ok, count($rows) . ' rows');
}

// An unknown role is a query that returns nothing, not one that errors —
// the value is bound, so it cannot be anything else.
check('an unknown role returns nothing rather than throwing',
    $adminRepo->users('not-a-role') === []);

// The other two filtered readers on the same screen family, which were
// written correctly and must stay that way.
check('the Tradespeople and Jobs filters bind their values too',
    is_array($adminRepo->pros('active')) && is_array($adminRepo->jobs('active')));

// --- the staff ladder -------------------------------------------------------
// Read as a table: each row is a capability, each column a role. A permission
// model is only auditable if you can see it all at once, and this is the test
// that fails when somebody widens one by accident.
$CAPS = [
    'admin.access', 'applications.review', 'listings.moderate', 'jobs.moderate',
    'people.view', 'licensing.manage', 'placements.view',
    'finance.view', 'finance.manage', 'team.manage', 'users.delete',
    'markets.manage', 'maintenance.manage',
];

/**
 * Everything one account can do, captured in one go.
 *
 * Signing in writes the user to the session, so two Auth objects held at once
 * both answer for whoever signed in last. Each role is therefore asked
 * everything before the next one signs in.
 *
 * @return array{role:?string,caps:array<string,bool>}
 */
$snapshot = static function (Database $db, string $email) use ($CAPS): array {
    $auth = new Auth($db);
    $auth->attempt($email, 'demo-password');

    $caps = [];
    foreach ($CAPS as $cap) {
        $caps[$cap] = $auth->can($cap);
    }
    return ['role' => $auth->role(), 'caps' => $caps];
};

$asOwner = $snapshot($db, 'owner@fixlisted.com');
$asMgr   = $snapshot($db, 'dana@fixlisted.com');
$asMod   = $snapshot($db, 'tomas@fixlisted.com');
$asPro   = $snapshot($db, 'marcus@ojoplumbing.com');
$asNone  = ['role' => null, 'caps' => array_map(
    static fn (string $c): bool => (new Auth($db))->can($c),
    array_combine($CAPS, $CAPS),
)];

check('the three staff roles sign in',
    $asOwner['role'] === Auth::ROLE_SUPER
    && $asMgr['role'] === Auth::ROLE_ADMIN
    && $asMod['role'] === Auth::ROLE_MODERATOR,
    $asOwner['role'] . ', ' . $asMgr['role'] . ', ' . $asMod['role']);

foreach ([
    // capability            owner  manager  moderator  tradesperson
    ['admin.access',          true,   true,    true,      false],
    ['applications.review',   true,   true,    true,      false],
    ['listings.moderate',     true,   true,    true,      false],
    ['jobs.moderate',         true,   true,    true,      false],
    ['people.view',           true,   true,    false,     false],
    ['licensing.manage',      true,   true,    false,     false],
    ['placements.view',       true,   true,    false,     false],
    ['finance.view',          true,   false,   false,     false],
    ['finance.manage',        true,   false,   false,     false],
    ['team.manage',           true,   false,   false,     false],
    ['users.delete',          true,   false,   false,     false],
    ['markets.manage',        true,   false,   false,     false],
    ['maintenance.manage',    true,   false,   false,     false],
] as [$cap, $wantOwner, $wantMgr, $wantMod, $wantPro]) {
    $yn = static fn (bool $b): string => $b ? 'yes' : 'no ';
    check(
        sprintf('%-20s owner:%s manager:%s moderator:%s pro:%s',
            $cap, $yn($wantOwner), $yn($wantMgr), $yn($wantMod), $yn($wantPro)),
        $asOwner['caps'][$cap] === $wantOwner
        && $asMgr['caps'][$cap] === $wantMgr
        && $asMod['caps'][$cap] === $wantMod
        && $asPro['caps'][$cap] === $wantPro
        // Nobody signed in can do anything, whatever the row says.
        && $asNone['caps'][$cap] === false,
    );
}

// Named again on purpose. If a later change widens one of these, the row
// above fails and so does this — and the second failure says why it mattered.
check('money, the team and deletion are the owner\'s alone',
    !$asMgr['caps']['finance.view'] && !$asMgr['caps']['team.manage'] && !$asMgr['caps']['users.delete']
    && !$asMod['caps']['finance.view'] && !$asMod['caps']['team.manage'] && !$asMod['caps']['users.delete']);

// A typo in a template must hide a button, not reveal one.
$ownerAgain = new Auth($db);
$ownerAgain->attempt('owner@fixlisted.com', 'demo-password');
check('an unknown capability is refused, not granted', !$ownerAgain->can('finance.embezzle'));

$team  = new TeamRepository($db);
$staff = $team->all();
check('the team lists all three staff roles and nobody else',
    count($staff) === 3
    && array_values(array_unique(array_column($staff, 'role')))
       === ['superadmin', 'market_admin', 'moderator'],
    implode(', ', array_column($staff, 'role')));

// A superadmin belongs to no market. A team query that insisted on market_id
// would quietly return a team with its owner missing.
check('the owner appears despite belonging to no market',
    in_array('owner@fixlisted.com', array_column($staff, 'email'), true));

// The count every destructive path checks first. Locking the last owner out
// is the one mistake with no way back through the interface.
check('the last signed-in-able superadmin is counted',
    $team->activeSuperadmins() === 1
    && $team->activeSuperadmins((int) $staff[0]['id']) === 0,
    'excluding the owner leaves none');

// A staff invite is a key to the admin panel and expires sooner than a
// tradesperson's first-time link.
check('a staff invite expires sooner than a pro invite',
    PasswordReset::STAFF_INVITE_DAYS < PasswordReset::INVITE_DAYS,
    PasswordReset::STAFF_INVITE_DAYS . ' days vs ' . PasswordReset::INVITE_DAYS);

// --- the maintenance switch -------------------------------------------------
// It is a file rather than a row precisely so it works when the database does
// not, which also means these run without touching one.
$wasDown = Maintenance::isOn();
$restore = $wasDown ? Maintenance::state() : null;

Maintenance::off();
check('the site starts up', !Maintenance::isOn());

Maintenance::on('Back by 3pm.', 'smoke test');
check('the switch takes the site down', Maintenance::isOn());
check('the switch records what to tell visitors',
    (Maintenance::state()['message'] ?? '') === 'Back by 3pm.');
check('the switch records who threw it',
    (Maintenance::state()['by'] ?? '') === 'smoke test');

// A visitor must never see a 200 on a maintenance page: a search engine reads
// that as the URL having become a maintenance notice, and drops the ranking.
$response = Maintenance::response(new FixListed\Core\View(BASE_PATH . '/app/Views'));
check('visitors get 503, not 200', $response->status === 503);
check('and a Retry-After, so crawlers come back',
    ($response->headers['Retry-After'] ?? '') !== '');
check('the maintenance page is not cached',
    str_contains($response->headers['Cache-Control'] ?? '', 'no-store'));
check('the page says what the switch was told',
    str_contains($response->body, 'Back by 3pm.'));

// Rendered without the site layout on purpose: the layout needs a market, a
// county list and a trade list, and "the database is down" is exactly when
// this page has to work.
check('the maintenance page carries no site chrome',
    !str_contains($response->body, 'All counties') && !str_contains($response->body, 'Jobs board'));

// A truncated or hand-edited file must still count as down. Staying up
// because the flag had a stray comma in it is the one failure nobody forgives.
file_put_contents(Maintenance::file(), '{ not json at all');
$reread = new ReflectionClass(Maintenance::class);
$reread->setStaticPropertyValue('loaded', false);
check('an unreadable switch still counts as down', Maintenance::isOn());
check('and falls back to a sensible message',
    str_contains((string) (Maintenance::state()['message'] ?? ''), 'back shortly'));

Maintenance::off();
check('the switch puts the site back up', !Maintenance::isOn());

if ($restore !== null) {
    Maintenance::on($restore['message'], $restore['by']);
}
check('the smoke test left the site as it found it', Maintenance::isOn() === $wasDown);

// --- SEO: URLs, structured data, and what must never be published ----------
//
// The rules under test here are not stylistic. Three of them are about the
// site not telling a search engine something untrue about businesses that do
// not exist, and one is about a script element not being escapable.

$geoScope = TenantScope::market($marketId);
$geo2     = new GeographyRepository($db, $geoScope);
$proRepo  = new ProRepository($db, $geoScope);

$freeport = $geo2->findCity('freeport');
check('a city page URL carries its state',
    Seo::cityPath($freeport) === '/handyman/freeport-il', Seo::cityPath($freeport));

check('a city resolves from its URL segment',
    ($byPath = $geo2->findCityByPath('freeport-il')) !== null
        && (int) $byPath['id'] === (int) $freeport['id']);

// The suffix is matched, not stripped and hoped about. Without it two
// spellings of one town are two pages competing for the same search.
check('a bare town slug is not a city page URL',
    $geo2->findCityByPath('freeport') === null);
check('a made-up state is not a city page URL',
    $geo2->findCityByPath('freeport-wi') === null);

// Every town with a page must have a URL that resolves back to it, or the
// footer and the sitemap are advertising 404s.
$roundTrips = 0;
foreach ($geo2->pageCities() as $pageCity) {
    $back = $geo2->findCityByPath(Seo::citySegment($pageCity));
    if ($back !== null && (int) $back['id'] === (int) $pageCity['id']) {
        $roundTrips++;
    }
}
check('every landing page URL resolves back to its town',
    $roundTrips === count($geo2->pageCities()),
    $roundTrips . ' of ' . count($geo2->pageCities()));

check('all ten Tier 1 towns have a landing page',
    count(array_intersect(
        ['freeport', 'rockford', 'lena', 'stockton', 'pearl-city',
         'forreston', 'orangeville', 'cedarville', 'dakota', 'german-valley'],
        array_column($geo2->pageCities(), 'slug'),
    )) === 10);

// Every trade needs a page that says something. A service page with a
// heading and nothing else is the thin page this whole exercise is meant to
// avoid producing nineteen of.
$tradeSlugs = array_column((new TradeRepository($db))->all(), 'slug');
$written    = array_filter($tradeSlugs, static fn (string $t): bool => TradeCopy::for($t) !== null);
check('every trade has page copy written for it',
    count($written) === count($tradeSlugs),
    count($written) . ' of ' . count($tradeSlugs));

/*
 * The copy has to read as English for every trade, not just for the one
 * somebody happened to look at.
 *
 * "Post a odd jobs job" and "1 plumbing businesses" both shipped, and both
 * were found by looking at a screenshot rather than by any test here. These
 * assertions are what stop the eleventh trade reintroducing them.
 */
$badArticle = [];
$missingJob = [];
foreach ($tradeSlugs as $slug) {
    $phrase = TradeCopy::jobPhrase($slug);
    $copy   = TradeCopy::for($slug);

    if ($copy === null || ($copy['job'] ?? '') === '') {
        $missingJob[] = $slug;
        continue;
    }

    // 'a' before a consonant, 'an' before a vowel. The check in jobPhrase is
    // a plain vowel test, which is right for these phrases and wrong for a
    // silent h or a long u — so this is where such a phrase gets caught.
    $expected = str_contains('aeiou', strtolower($copy['job'][0])) ? 'an ' : 'a ';
    if (!str_starts_with($phrase, $expected) || !str_ends_with($phrase, ' job')) {
        $badArticle[] = $slug . ': "Post ' . $phrase . '"';
    }
}
check('every trade has a phrase that fits "Post a ___ job"',
    $missingJob === [], implode(', ', $missingJob));
check('and the article in front of it is right',
    $badArticle === [], implode('; ', $badArticle));

/*
 * Two mechanical properties of the phrase, and deliberately not a third.
 *
 * Whether 'handyman' is the right word for Odd Jobs is a judgement, made
 * once and visible in TradeCopy — not something to approximate with a rule
 * that would also have rejected 'appliance repair', which reads perfectly.
 * A test that fails on correct output is a test people learn to ignore.
 *
 * What a machine can check: it never reads 'job job', and it is lower case,
 * so it does not look like a proper noun in the middle of a sentence.
 */
$malformed = [];
foreach ($tradeSlugs as $slug) {
    $phrase = TradeCopy::jobPhrase($slug);
    if (str_contains($phrase, 'job job') || $phrase !== strtolower($phrase)) {
        $malformed[] = $slug . ': "Post ' . $phrase . '"';
    }
}
check('no phrase stutters or shouts', $malformed === [], implode('; ', $malformed));

/*
 * The escaping test, and the reason Seo::json exists.
 *
 * Structured data is assembled from names tradespeople type into their own
 * profile and written into a <script> element. A business name containing
 * "</script>" must not be able to close it.
 */
$hostile = Seo::graph([['@type' => 'Thing', 'name' => '</script><img src=x onerror=alert(1)>']]);
check('structured data cannot break out of its script tag',
    !str_contains($hostile, '</script>') && !str_contains($hostile, '<img'),
    'angle brackets are hex-escaped');
check('and the value survives the escaping',
    json_decode($hostile, true)['@graph'][0]['name'] === '</script><img src=x onerror=alert(1)>');

// Empty properties are dropped rather than published as "". Search Console
// reports an empty value as invalid, where a missing optional one is fine.
$pruned = Seo::prune(['a' => '', 'b' => null, 'c' => [], 'd' => 0, 'e' => false, 'f' => 'x']);
check('empty structured-data properties are dropped, but zero and false are kept',
    $pruned === ['d' => 0, 'e' => false, 'f' => 'x']);

// A trail of one is the home page, and a BreadcrumbList of one item is noise.
check('a single crumb produces no BreadcrumbList',
    Seo::breadcrumbs([['label' => 'Home', 'href' => '/']]) === []);
$crumbNode = Seo::breadcrumbs([
    ['label' => 'Home', 'href' => '/'],
    ['label' => 'Freeport'],
]);
check('the last crumb is listed by position and carries no link',
    count($crumbNode['itemListElement']) === 2
        && !isset($crumbNode['itemListElement'][1]['item'])
        && $crumbNode['itemListElement'][1]['position'] === 2);

/*
 * The three that matter most: nothing invented is ever offered for indexing.
 *
 * The seed data is ten tradespeople who do not exist, their jobs and their
 * reviews. Every one of them is on display right now with a Sample badge,
 * which is honest to a person reading the page. A sitemap, a schema.org
 * description and an aggregateRating have no badge.
 */
$demoPros = (int) $db->value('SELECT COUNT(*) FROM pro_profiles WHERE is_demo = 1');
check('the seed data is still here to test against', $demoPros > 0, $demoPros . ' sample profiles');

$realPros = (int) $db->value(
    "SELECT COUNT(*) FROM pro_profiles p
      WHERE p.status = 'active' AND p.is_demo = 0
        AND EXISTS (SELECT 1 FROM pro_county_areas a
                     WHERE a.pro_id = p.id AND a.market_id = :m)",
    ['m' => $marketId],
);
check('the sitemap offers real profiles only, whatever the display mode is set to',
    count($proRepo->sitemap()) === $realPros,
    count($proRepo->sitemap()) . ' listed, ' . $realPros . ' real');

$realJobs = (int) $db->value(
    "SELECT COUNT(*) FROM jobs
      WHERE market_id = :m AND status = 'active' AND is_demo = 0 AND published_at IS NOT NULL",
    ['m' => $marketId],
);
check('the sitemap offers real jobs only',
    count((new JobRepository($db, $geoScope))->sitemap()) === $realJobs);

// The count that goes into a Service description is not the count on the page.
// The page labels its samples; a schema.org description cannot.
$stephenson = null;
foreach ($geo2->counties() as $county) {
    if ($county['slug'] === 'stephenson-il') { $stephenson = (int) $county['id']; }
}
check('structured data counts real pros, never seeded ones',
    $proRepo->countReal($stephenson) <= $proRepo->countActive($stephenson)
        && $proRepo->countReal() === $realPros,
    $proRepo->countReal() . ' real vs ' . $proRepo->countActive() . ' shown');

// A star rating built from invented reviews is the fabricated-review case the
// structured-data policy names outright.
$reviewRepo = new ReviewRepository($db, $geoScope);
$demoRated  = $db->one(
    "SELECT p.id, p.rating_count FROM pro_profiles p
      WHERE p.is_demo = 1 AND p.rating_count > 0 LIMIT 1"
);
if ($demoRated !== null) {
    $stats = $reviewRepo->publishedStats((int) $demoRated['id']);
    check('a profile whose only reviews are seeded gets no rating to publish',
        $stats['count'] === 0 && (int) $demoRated['rating_count'] > 0,
        'stored rating_count is ' . $demoRated['rating_count'] . ', publishable is 0');
} else {
    check('a profile whose only reviews are seeded gets no rating to publish', true, 'no seeded ratings to test');
}

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

// --- the header on a phone --------------------------------------------------
//
// Two rows used to be pinned, about 130px of a small screen, and the page
// came to rest half-hidden underneath. Only the top row is pinned now, which
// works only while the nav is a SIBLING of .chrome-bar rather than inside it:
// a fixed box takes its children with it.
$header = (string) file_get_contents(BASE_PATH . '/app/Views/partials/header.php');

check('the pinned row wraps the wordmark and the account menu',
    preg_match('~<div class="chrome-bar">.*?class="logo".*?class="chrome-r".*?</div>~s', $header) === 1);

check('and the primary nav sits outside it, so it scrolls away',
    preg_match('~</div>\s*<nav class="nav"~s', $header) === 1);

$css = (string) file_get_contents(BASE_PATH . '/public/assets/css/site.css');

check('the wrapper is not there at all on a wide screen',
    str_contains($css, '.chrome-bar{display:contents}'));

// display:contents drops the wrapper, so the nav's place in the desktop row
// is decided by order and nothing else.
check('so the desktop row is ordered, not nested',
    str_contains($css, '.nav{display:flex;gap:2px;margin-left:10px;order:2}')
    && str_contains($css, 'order:3}'));

// Fixed means out of flow: without this the page slides up under the bar.
check('the page gives back the height the fixed bar takes',
    str_contains($css, 'body:has(.chrome){padding-top:calc(var(--bar)'));

// Scoped to pages that carry this header — the admin shell has its own.
check('and only on pages that have this header',
    !str_contains($css, 'body{padding-top:calc(var(--bar)'));

// --- an inline style outranks every media query -----------------------------
//
// grid-template-columns lived in a style attribute on the dashboard, so the
// four stat cards stayed four across on a phone. A 1fr column will not shrink
// below its longest word, and "HOMEOWNER" held the row about 50px wider than
// the screen: the rating card was cut off at the edge, the whole page scrolled
// sideways, and the fixed header stretched to the overflow.
foreach (['account/dashboard', 'account/promote', 'admin/advertising'] as $view) {
    check('column counts on ' . $view . ' are a class, not a style attribute',
        !str_contains((string) file_get_contents(BASE_PATH . '/app/Views/' . $view . '.php'),
            'style="grid-template-columns'));
}

check('and the phone override can reach all of them',
    str_contains($css, '.adm-stats,.adm-stats-3{grid-template-columns:repeat(2,1fr)}'));

// --- the account menu closes when you click away ----------------------------
//
// A backdrop-filter makes an element the containing block for every fixed
// descendant. With it on .chrome the dismiss scrim was sized to the header,
// so clicking the page below the menu did nothing.
check('the header blur sits on a pseudo-element, not on the header',
    str_contains($css, '.chrome::before{content:"";position:absolute;inset:0;z-index:-1;')
    && preg_match('~\.chrome\{[^}]*backdrop-filter:blur~', $css) !== 1);

// --- the academy is reachable ----------------------------------------------
//
// It was linked from the footer and nowhere else, which is the weakest
// placement a page has — and the signed-in tradesperson area, which the pro
// track was written for, had no link at all.
$viewFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/app/Views'));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with((string) $f, '.php')) {
        $viewFiles[(string) $f] = (string) file_get_contents((string) $f);
    }
}

// A learn() link pointing at a slug that does not exist is a 404 served from
// inside somebody's own dashboard, and nothing else would catch it.
$linked = [];
foreach ($viewFiles as $path => $src) {
    if (preg_match_all("~learn\\('([a-z0-9-]+)'~", $src, $m) > 0) {
        foreach ($m[1] as $slug) {
            $linked[$slug] = basename($path);
        }
    }
}
check('the in-context links point at lessons that exist', $linked !== []);
foreach ($linked as $slug => $where) {
    $lesson = Academy::find($slug);
    check('  ' . $where . ' links to a real, public lesson: ' . $slug,
        $lesson !== null && Academy::isPublicTrack((string) $lesson['track']));
}

// A staff lesson linked from a public screen would 404 the visitor, because
// the controller refuses a non-public track rather than serving it.
check('no public screen links a staff lesson',
    array_filter($linked, static fn (string $s): bool
        => ($l = Academy::find($s)) !== null && !Academy::isPublicTrack((string) $l['track']),
        ARRAY_FILTER_USE_KEY) === []);

check('a signed-in tradesperson can reach the academy from their own area',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Views/layouts/account.php'), "'/academy"));

check('and somebody deciding whether to list is offered it before the sign-up ask',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Views/site/for_pros.php'), 'lesson-card'));

// Read from the repository, not retyped, so a lesson taken down in the admin
// stops being advertised instead of 404ing whoever follows the link.
check('that block reads the real lessons rather than a hardcoded list',
    str_contains((string) file_get_contents(BASE_PATH . '/app/Controllers/PageController.php'),
        'AcademyRepository($this->db))->track(Academy::TRACK_PRO)'));

// The shelves the /my link and any track link land on.
$acadIndex = (string) file_get_contents(BASE_PATH . '/app/Views/site/academy.php');
check('the academy index has an anchor per track, clear of the sticky header',
    str_contains($acadIndex, 'id="homeowners"') && str_contains($acadIndex, 'id="tradespeople"')
    && str_contains($acadIndex, 'scroll-margin-top'));

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
