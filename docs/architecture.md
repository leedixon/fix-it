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

An ad unit assembles itself from the pro's live profile. `ad_creatives` stores
only the three fields a pro may override — `headline`, `offer_line`,
`hero_photo_id` (plus `cta_label`) — and a moderation state.

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
stored) and rolled up nightly into `ad_stats_daily`, which is what the dashboard
reads. Raw events are pruned at 45 days — shared hosting does not want an
unbounded event log.

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
