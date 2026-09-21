# Architecture

Decisions that are expensive to change later. Everything here is implemented in
`database/schema.sql` and proven by `database/verify.sql`.

## Geography: markets, counties, cities

Three layers, each doing a different job:

| Layer | What it is | Who uses it |
| --- | --- | --- |
| **Market** | The tenant. A named region — "Northwest Illinois" | URLs, branding, admin |
| **County** | The service-area unit | Pros choose which ones they cover |
| **City** | What people type and search for | Homeowners posting; SEO landing pages |

**Counties are the structural unit** because they are finite and permanent —
Illinois has 102 and always will — whereas "places" number in the thousands and
blur into townships and unincorporated areas. Rural tradespeople already think
this way: "I cover Stephenson and Jo Daviess." Counties are keyed on **FIPS
code, not name**: Boone County exists in both Illinois and Iowa, and Winnebago
in three states.

**A county cannot be the tenant.** Stephenson County alone is ~44,000 people.
Market 1 covers six counties (~470,000) because Winnebago County is Rockford,
and a directory without that density has no liquidity to offer anyone.

**Cities are not markets.** They are landing pages inside one, because people
search "handyman rockford il", not "handyman winnebago county". Only cities
with `has_page = 1` get an indexed page; the long tail of villages is
selectable when posting a job but has no page of its own, since forty
near-empty pages read as a content farm.

**A pro's coverage lives in `pro_county_areas`**, and the directory tests it
with `EXISTS`, not a join — a pro covering four counties matches four rows, and
a join would list them four times.

**`zip_counties` ships empty on purpose.** It must be imported from the Census
ZCTA-to-county relationship file, never hand-typed, because a wrong ZIP
silently routes a paid job post into the wrong market. Until it is loaded,
homeowners pick their city from a list, which needs no ZIP data at all.

## Multitenancy: one install, many city markets

Every tenant-owned row carries `market_id`, and every composite index leads with
it. A market is a city (Austin, Round Rock, San Marcos), not a customer — you
run all of them, and a market admin is staff who sees exactly one.

**The rule: no query touches a tenant table without a `market_id` predicate.**
That gets enforced in one place — a scoped-query helper that every repository
goes through — rather than trusted to discipline in 200 call sites. Superadmin
is the only role that may drop the scope, through an explicitly named method so
it is greppable.

Global tables, deliberately outside the scope: `users`, `trades`,
`webhook_events`, `password_resets`. A user's identity is global (one email, one
login) while their role and their data are scoped.

A pro's market membership lives in `pro_service_areas`, not in
`pro_profiles.market_id`. The directory reads the join table, so a pro can grow
into a second city without a migration. `pro_profiles.market_id` is only their
home market.

## Pricing is per market, in the database

`markets.listing_fee_cents` is the $10 fee, and it is per market. A new market
can launch at `0` to fill the jobs board, then switch to `1000` from the
superadmin panel with no deploy. San Marcos is seeded that way.

Inventory caps live alongside it: `boost_slots` and `spotlight_slots`. Scarcity
is the product — if every pro can buy the top slot, nobody's top slot is worth
anything. The app checks remaining capacity before letting a subscription start.

`subscriptions.price_cents` captures the price at signup, so raising a market's
prices never silently repriced an existing advertiser.

## Money

All money is `INT` cents. No floats, no `DECIMAL` rounding surprises.

`payments` is a single ledger — one row per Stripe charge, whatever it paid for.
The superadmin revenue screen reads this table and never sums the Stripe API at
runtime.

A job sits in `pending_payment` and is invisible to everyone until the Stripe
webhook flips it to `active`. Nothing in the public directory reads a row in
that state. The seed includes one such job precisely so the test can prove it
stays hidden.

Stripe delivers webhooks more than once. `webhook_events.stripe_event_id` is
unique — insert first, and a duplicate hits the key and the handler exits
without re-applying the charge.

## Advertising: profile-derived, not an ad builder

An ad unit assembles itself from the pro's live profile. **As built, an ad *is*
the pro's listing card, lifted** — there is no separate ad unit to render and
nothing for an advertiser to write. `ad_creatives` exists for the day a pro
wants to override the three fields that are theirs to override — `headline`,
`offer_line`, `hero_photo_id` (plus `cta_label`) — and carries a moderation
state for them. Nothing writes to it yet, and a placement does not need it.

**A NULL override means "inherit from the profile."** A row with every override
NULL is still a complete, live ad, so every paying advertiser has working
creative the moment they subscribe without touching this table. Teresa Vance is
seeded that way to prove it.

The reasoning: the trust signals that actually drive clicks — rating, review
count, verified licence, years in trade — are rendered by the template from live
profile data and **cannot be authored by the advertiser**. That keeps ads
honest, keeps the inventory looking premium enough to justify $149/mo, collapses
moderation to reviewing three short text fields, and means placement can be A/B
tested centrally across all advertisers instead of being 200 uncontrolled
variables. `ad_creatives.variant` supports two live variants per pro for exactly
that.

Ad events are written raw to `ad_events` (with IP and session hashed, not
stored) and counted into `ad_stats_daily` in the same write, which is what both
dashboards read. There is no nightly rollup: without a queue worker, the
alternative to counting inline is a cron job that can silently stop, and a
counter a tradesperson is invoiced against should not be able to fall behind
without anyone noticing. Both writes happen after the response has been sent
(`fastcgi_finish_request`), so they cost a visitor nothing.

The dedupe key is `(stat_date, placement_id)` and deliberately excludes
`creative_id`: it is always NULL here, MySQL treats NULLs as distinct in a
unique index, so including it means the key never matches and every impression
inserts its own row. Migration `005` repaired that.

Raw events are pruned at 45 days by `bin/sweep.php` — shared hosting does not
want an unbounded event log, and the daily totals are the permanent record, so
a tradesperson's history survives the pruning of the rows that produced it. The
same sweep takes down any placement that outlived its subscription: the
webhooks do that already, and this catches the event that never arrived.

Clicks are routed through `/go/{placement}`, which resolves the destination
from the database. It never accepts a URL: a redirector that forwards to
whatever is in the query string is an open redirect, and the only thing this
one can reach is a profile on this site.

## Shared-hosting constraints

A2 shared cPanel has no queue worker and no long-running processes, so:

- **Mail and SMS go through the `notifications` table**, drained by a one-minute
  cron. A slow SMTP call never blocks a checkout, and a failed send retries
  instead of vanishing.
- **Transactional mail is sent through the domain's real provider**, not
  through the web server. Mail sent from this host claiming to come from a
  domain hosted on Google Workspace fails SPF and DKIM; a domain with a DMARC
  policy then has those messages **rejected silently** — no bounce, nothing in
  the spam folder, and nothing on this end to indicate it happened. `Mailer`
  therefore speaks authenticated SMTP (`app/Core/Smtp.php`) when
  `mail.transport` is `smtp`, and falls back to `mail()` only where the
  from-address is a mailbox on this same server.
- **Counters are denormalised** (`rating_avg`, `quote_count`, `jobs_completed`)
  and rebuilt nightly. Reads stay cheap.
- **The PDO connection must set `charset=utf8mb4`.** Without it the client
  negotiates latin1 and mangles every em-dash and accented name on the way out,
  even though the columns are correct.

Cron jobs the install needs:

| Schedule | Job |
| --- | --- |
| every minute | drain `notifications` |
| hourly | expire jobs past `expires_at`; pause placements whose subscription is `past_due` |
| daily | roll `ad_events` into `ad_stats_daily`; prune events past 45 days; rebuild pro counters |
| daily | refund sweep — jobs live past `markets.refund_window_hours` with zero quotes |

## The 72-hour no-quote refund

`markets.refund_window_hours` (default 72) drives an automatic refund for any
job that goes that long with `quote_count = 0`. It is a real financial
commitment, made per market so it can be tightened in a thin market. Block 8 of
`verify.sql` is the exact sweep query the cron runs.


## The back end

`/admin`, gated in one place: `AdminController::guard()`. Every admin
controller extends it and calls it first, so "is this person allowed" has one
implementation rather than a check each new screen has to remember to make.

Roles run narrowest to widest — homeowner, pro, market_admin, superadmin. A
market admin sees their own market; only a superadmin may change pricing or
see the market list. A signed-out visitor gets the login page; a signed-in
tradesperson poking at `/admin` gets a 404, which does not confirm the URL
exists.

Screens: dashboard, the application queue, tradespeople, jobs, people (with
the pre-launch waiting list), advertising, markets, and the activity log.

**Reads for the back end live in `AdminRepository`, away from the public
ones.** The admin deliberately ignores the filters the public repositories
enforce — it exists to look at suspended profiles and jobs awaiting payment,
which nothing public may ever show. Keeping both sets of queries in one class
is how a `status` filter goes missing from a public page.

**Every administrative write is recorded.** `AuditLog` is append-only:
approvals, suspensions, removals, pricing changes, sign-ins. Nothing in the
application updates or deletes a row in it. The point is answering "who took
this listing down, when, and why" months later.

### Applications

A tradesperson applies at `/list-your-business` — no password, no account to
set up, no email verification loop before they have seen whether it is worth
it. The write spans four tables and runs as one transaction: a user with no
profile is an account nobody can use, and a profile with no counties is
invisible to the directory meant to show it.

Profiles are created `pending_review` and are invisible everywhere public
until an administrator approves them. Approval is two ticks, not one button:
the profile carries a licence badge and an insurance badge, each a claim the
site makes on the administrator's behalf, so each is confirmed separately.
That is what makes "licence and insurance checked" true rather than
decorative.


## The signed-in area for tradespeople

`/my`, gated by `AccountController::guard()` the same way the admin is. It
resolves the signed-in user to their profile, deliberately reading it *without*
the public status filter — the point of this area is that a pro can see and
edit their own listing while it is pending or after it has been suspended.

Screens: jobs in their counties, their quotes, and their listing.

### Getting in

A tradesperson never chooses a password when they apply. Asking for one before
they know whether they will be accepted is friction for nothing, and it leaves
dead accounts behind for every application that is declined. Instead, approval
issues a one-time link (`PasswordReset`, invite lifetime) and the approval email
leads with it. The token is emailed in the clear and stored only as a SHA-256
hash, is single-use, and issuing a new one invalidates any outstanding link.

### Quoting

One quote per pro per job, enforced by a unique index and checked again inside
the write. The job is re-read `FOR UPDATE` inside the transaction rather than
trusted from the form, because between rendering the page and submitting it a
job can close, be removed, or turn out never to have been paid for. County
coverage is checked at the write too, not only when building the list the pro
browses.

A quote carries the *shape* of a price — fixed, a range, hourly, or "I need to
see it first" — because most trades genuinely cannot give one number from a
paragraph of text, and forcing them to produces a board full of invented
figures nobody honours.

## Clocks

`Database` sets MySQL's session time zone to PHP's current UTC offset on every
connection. Without it the two disagree by that offset and any comparison
mixing them is wrong — a reset link stored as `date(..., time() + 3600)` in
America/Chicago and checked against a UTC `NOW()` is born four hours expired,
so nobody can ever use one. That is a silent failure that looks like broken
email, and it was real until it was caught by a test.

A numeric offset rather than a named zone, because named zones need MySQL's
time zone tables loaded and shared hosts usually have not. It is recalculated
per connection, so daylight saving is PHP's problem rather than ours.
