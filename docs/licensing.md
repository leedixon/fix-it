# Licence verification

There is no national trade-licence registry in the United States, no common
number format, and no agreement about which trades need a licence at all.
Illinois alone splits it three ways: plumbers are licensed by Public Health,
roofers by IDFPR, and electricians by individual cities with no state licence
existing.

So it is data, not code. `licence_authorities` holds a row per **state and
trade**, and the application review screen looks up whoever licenses that
applicant's trade in their state. Opening a market in another state is rows an
administrator types at **/admin/licensing**, not a deploy.

## The rule that matters

**No row means "we do not know". It never means "no licence needed".**

A state with nothing entered shows the reviewer an explicit *No guidance yet*
warning rather than a reassuring default. A verified badge on a public profile
is a claim the site makes on somebody's behalf, and it must never come from a
gap in a table. `licensed` is deliberately nullable in the lookup result for
exactly this reason: `null` is unknown, `0` is "the state does not license
this", and those are different answers.

`0` is a real answer, not a missing one. An Illinois electrician has no state
licence, and treating that as a red flag would be wrong.

## Adding a state

**/admin/licensing**, superadmin only. One row per trade that differs, plus a
fallback row (trade = *Every other trade*) covering the rest of that state.
Re-entering a state and trade updates it rather than creating a duplicate.

Worth writing into the guidance field: what would be *wrong* to assume. The
Illinois plumbing row says searching IDFPR finds nothing, because that is the
mistake somebody would otherwise make.

## Why not an API

There is no universal one. Commercial verification APIs exist — the
background-check providers sell professional-licence screening — but their
coverage is built around healthcare, finance and transport, where licensing is
federal or near-uniform. Trade contractors are patchy. Before paying anyone,
send them the actual list (plumbing in Illinois, roofing in Illinois,
electrical in Rockford) and make them confirm those specifically.

The better automation, when volume justifies it, is bulk data. IDFPR publishes
a **Bulk License Look Up** — a downloadable roster rather than a search box.
Import it monthly into a `licence_records` table and verification becomes a
local query with no vendor and no rate limit. It will not cover plumbers
(IDPH) or municipal electricians, but it covers roofers, and the pattern
extends state by state.

Even then, keep the human tick. The site says *a person checked this*, which is
both more honest and more defensible than "a database said so" — rosters go
stale and names mismatch. An automated pre-check should flag a mismatch for a
person, never decide on its own.

## What to insist on regardless

A certificate of liability insurance works the same in every state and every
trade: check the expiry date and that the business name matches. That is what
protects a homeowner when something goes wrong, whatever the licensing rules
say. Every guidance block on the review screen ends with that, deliberately.
