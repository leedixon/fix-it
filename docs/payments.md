# Payments

Money moves in one direction and through one gate: a job is invisible until a
**verified webhook** says it was paid for. Nothing else publishes a listing.

## Why the webhook and not the return page

After Checkout, Stripe sends the browser to a success URL. That is the browser
making a claim, and a browser can be pointed at any URL by anybody — including
someone who has read the URL once and never paid again. So the return page
reports what the database says and nothing more. It has three states:

- **Live** — the webhook arrived and the job is published.
- **Confirming** — the money is taken and the webhook is a second behind. The
  page refreshes itself and says *there is no need to pay again*, because the
  alternative is a second charge.
- **Not paid** — they never completed Checkout. The job is saved and they are
  offered the way back.

## Products: there are none, on purpose

**Do not create a Product or a Price in the Stripe dashboard.** Nothing in the
code looks one up, and one created there would simply sit unused.

The checkout session builds its line item inline instead:

```php
'line_items' => [[
    'quantity'   => 1,
    'price_data' => [
        'currency'     => 'usd',
        'unit_amount'  => $amountCents,          // from markets.listing_fee_cents
        'product_data' => ['name' => 'Fix Listed job posting', 'description' => …],
    ],
]],
```

The reason is the admin. The listing fee is a per-market column that a
superadmin can change at **/admin/markets** without a deploy, and a market can
run at $0 to fill its board. A Stripe Price is a fixed object with its own id;
wiring one in would mean the fee shown on the site and the fee actually charged
could drift apart, silently, the first time somebody edited it. Reading the
amount from the same row the page reads makes that impossible.

The cost is that Stripe's Products list stays empty and per-product reporting
is not available. Payments are still fully reported, and the application's own
`payments` table is the better record anyway — it knows which *job* each charge
belongs to, which Stripe never will.

Subscriptions for Boost and Spotlight work the same way — inline `price_data`
with `recurring: {interval: month}` and no Product object. The argument for
real Products was that a subscription is fixed-price and identical every month;
the argument against won, and it is the same one: the price lives in the market
row, a market can change it, and a Stripe Price that quietly disagrees with the
page is worse than an empty Products list. Existing subscribers are unaffected
by a price change either way, because `subscriptions.price_cents` captures what
they agreed to.

## Which API key, and which permissions

Use a **restricted key** (`rk_live_…`), not a standard secret key
(`sk_live_…`). This application calls six endpoints and needs nothing else, so
a key scoped to those six is worth very little to whoever ends up with it.
`bin/configure.php` and `bin/check.php` accept both and say which you pasted.

### Every Stripe call this application makes

That is the whole list — it is the permission set, derived from the code
rather than guessed at.

| Endpoint | Method | Why |
| --- | --- | --- |
| `checkout/sessions` | POST | The listing fee, and starting a placement subscription |
| `checkout/sessions/{id}` | GET | Reading a session back |
| `refunds` | POST | The no-quote auto-refund, and undoing an oversold placement |
| `subscriptions/{id}` | GET | What a placement is paid up through |
| `subscriptions/{id}` | DELETE | Cancelling a placement that could not be granted |
| `invoices/{id}` | GET | Finding the payment intent behind a subscription invoice |
| `billing_portal/sessions` | POST | The pro's "manage or cancel" link |

Webhooks need **no** permission — they are verified with the signing secret
and make no API call.

### Setting the permissions

In **Custom permissions**, grant *write* on Checkout Sessions, Refunds,
Subscriptions, Customers and the Customer portal, and *read* on Invoices and
PaymentIntents. Write implies read, so nothing needs both ticked.

**Then confirm it against the request log rather than trusting that list.**
Inline `price_data` creates Product and Price objects behind the scenes, and
whether that draws on the Products and Prices permissions is the sort of
detail that is easy to be wrong about and expensive to discover at the moment
a customer presses Pay. Stripe's own advice is to derive the permissions from
what the key actually did:

1. Make the restricted key **in a sandbox first**, with the same permissions
   you intend to use live.
2. Run the whole money path against it — post a job and pay for it, let the
   sweep refund one, buy a placement, open the billing portal, cancel it.
3. **Developers → Logs**, filtered to that key. Any 403 names the permission
   it wanted; tick it and go again.
4. Once the sandbox run is clean, create the live key with the permissions
   you ended up with.

This costs half an hour and replaces a guess with a fact.

### If you would rather not

**Full access — except sensitive operations** works and is defensible. It
blocks payouts, issuing cards, fund transfers and Connect account creation —
the operations that actually drain an account. What it leaves open is reading
every customer record you hold, which is a real cost and the reason it is the
second choice rather than the first.

**Full access** is not needed by anything here. Its permissions cannot be
edited after creation, so a key made that way can only be replaced.

## Setting it up

```bash
php bin/configure.php          # first run: asks for everything
php bin/configure.php --stripe # after that: the payment keys and nothing else
php bin/check.php              # confirms all three are present
```

Use `--stripe` for every change after the first, including swapping test keys
for live ones. The full run rebuilds the file from its answers, which is right
once and wrong afterwards — it would mean retyping a database password and a
mail provider key nobody should have to have to hand. It shows what is already
set without printing any of it, keeps anything you skip with Enter, refuses a
key pasted into the wrong slot, and removes `stripe.api_base` when the key you
give it is live.


In the Stripe dashboard, **Developers → Webhooks → Add endpoint**:

| | |
| --- | --- |
| URL | `https://fixlisted.com/webhooks/stripe` |
| Events | `checkout.session.completed`, `charge.refunded`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted` |

The first two carry job postings. The last four carry monthly placement — a
subscription that renews, fails, is changed in the billing portal, or ends.
Leaving them off the endpoint does not break checkout; it means a placement
goes up and never comes down again.

Copy the signing secret it shows you into `bin/configure.php`. **Without it no
payment can ever be confirmed and no job will ever go live** — everything else
can be correct and nothing will work. `bin/check.php` fails loudly if it is
missing.

While the site is at `/preview`, the endpoint is
`https://fixlisted.com/preview/webhooks/stripe`. It moves with the site.

### A note on API versions

Leave `stripe.api_version` empty. API responses then come back in the same
version your webhooks arrive in — whatever the account's default is — and
there is one shape of each object in the codebase rather than two.

This is not hypothetical. Stripe moved subscription billing periods in version
`2025-03-31`: `current_period_start` and `current_period_end` came off the
subscription and onto its **items**. A request pinned to an older version
would return the old shape while the webhook for the same subscription arrived
in the new one, and the placement would go live with no period on it — no
error, just an empty renewal date on the admin screen.

The readers handle both shapes anyway, which is the actual defence: the
version an event arrives in is set in the Stripe dashboard, by somebody who
has never seen this code.

Where the shapes differ, and both are read:

| Field | Up to 2025-03-31 | From 2025-03-31 |
| --- | --- | --- |
| Subscription period | `current_period_start` / `_end` | `items.data[0].current_period_start` / `_end` |
| Invoice's subscription | `invoice.subscription` | `invoice.parent.subscription_details.subscription` |

## What the webhook does

1. **Verifies the signature before reading the body.** Without this the
   endpoint is an unauthenticated "mark this job paid" URL. Rejected events are
   answered 400, not 500, so Stripe does not retry something that will fail
   identically forever.
2. **Records the event before processing it**, keyed on Stripe's event id with
   a unique index. Stripe retries anything it did not get a 200 for, and will
   cheerfully deliver the same event several times — a duplicate inserts
   nothing and is acknowledged.
3. **Completes the payment inside a transaction**, locking the payment row. A
   second delivery finds it already succeeded and changes nothing, so no job is
   published twice and no receipt is sent twice.
4. **Answers 200 in milliseconds, then sends the email.** Stripe times out at
   twenty seconds; notifying a county's worth of tradespeople is not something
   to do while it waits.

Every event is kept in `webhook_events` with its payload and outcome —
`processed`, `ignored` or `failed`. A failure is recorded and answered 200,
because a retry would hit the duplicate check and do nothing; what is useful is
a row someone can look at.

## Monthly placement

A tradesperson buys Boost or Spotlight from **Get seen first** in their own
account (`/my/promote`). The flow mirrors job posting deliberately:

1. A row is written to `subscriptions` as `incomplete` **before** the redirect,
   so the webhook has something to find. It holds no slot and grants nothing.
2. Stripe Checkout runs in `mode=subscription`.
3. `checkout.session.completed` grants the placement; `invoice.paid` keeps it.

### Inventory, and what happens when it runs out

Each market caps each plan (`markets.boost_slots`, `markets.spotlight_slots`).
The cap is counted in **subscriptions**, not placements, because that is the
unit a pro buys.

Capacity is checked twice: on the page that sells the plan, and again inside
the transaction that grants it, with the count taken `FOR UPDATE`. The second
check is the one that matters — two pros can reach Stripe for the last slot
within the same second and both will be charged. The loser's subscription is
cancelled and their payment refunded automatically, and `mail.alert_to` gets an
email so a person can reach them first. A cap of `0` takes a plan off sale, and
is refused by both checks rather than read as "unlimited".

### What each event does

| Event | Effect |
| --- | --- |
| `checkout.session.completed` (mode `subscription`) | Grants the placement, or refunds if the slot went |
| `invoice.paid` | Renews the period; brings a `past_due` placement back |
| `invoice.payment_failed` | Marks `past_due`. **The listing stays up** |
| `customer.subscription.updated` | Records `cancel_at_period_end`; ends it if Stripe says `canceled` or `unpaid` |
| `customer.subscription.deleted` | Ends the placement, frees the slot |

A declined card does not pull a tradesperson off the page. Stripe retries for
about two weeks and usually wins; removing a paying customer over an expired
card loses the customer instead of collecting the payment. Only
`customer.subscription.deleted` — or Stripe giving up — ends a placement.

### Cancelling, cards and invoices

All in **Stripe's billing portal**, reached from the same screen. Nothing here
holds card details or reimplements what Stripe already does, and a pro who
cannot find a cancel button calls their bank rather than quietly keeps paying.

### One placement, every page

A subscription grants exactly one `ad_placements` row in the `directory_top`
slot. The directory, the home page and every town page all select from that
slot, so buying once lifts the listing everywhere it appears — and there is one
row to revoke when payment stops. `position` is assigned once and kept, so a
pro who has paid since January does not slide down because somebody joined in
March.

### Labelling

Every paid lift is labelled: Spotlight shows **Featured**, Boost shows
**Promoted**, and the directory says so above the results. An earlier version
lifted Boost with no badge at all, which reads to a visitor as an earned
ranking — the exact undisclosed paid placement this directory's credibility
depends on not doing. A placement whose subscription is not paying does not
move anyone at all: position is only read when a plan came with it.

## What placement actually delivered

`AdTracker` counts impressions and clicks for every paid listing, and has done
since before anything was for sale — a tradesperson paying monthly will ask
what they got, and the only honest answer is one backed by numbers that were
already being collected.

- Templates call `AdTracker::seen()` while rendering, which only appends to an
  array. The writes happen after `$response->send()` and
  `fastcgi_finish_request()`, so a visitor never waits on a counter.
- A click goes through `/go/{placement}`, which takes an **id** and looks the
  destination up. It never takes a URL — a redirector that forwards to whatever
  is in the query string is an open redirect, and the only thing this one can
  reach is a profile on this site.
- One session counts at most one impression per placement per day. Crawlers are
  skipped by user agent, and a pro refreshing their own listing is skipped. A
  number that flatters the product is worse than no number, because it will be
  quoted back at a price.
- `ad_stats_daily` is keyed on `(stat_date, placement_id)`. It must not include
  `creative_id`: ads are assembled from the pro's own profile so that column is
  always NULL, MySQL treats NULLs as distinct in a unique index, and the key
  would never match — the rollup would insert a row per impression instead of
  incrementing one. Migration `005` fixed exactly that.

## Refunds

The promise on the pricing page and in the terms: no quotes within the refund
window and the fee comes back automatically. A promise that only happens when
somebody remembers to run something is not a promise, so:

```
7 6 * * *  cd ~/fixlisted && php bin/sweep.php >> storage/logs/sweep.log 2>&1
```

`bin/sweep.php --dry-run` says what it would do and changes nothing. It also
expires listings past their run, tidies away checkouts abandoned over a week
ago, prunes raw ad events past 45 days, and takes down any placement that
outlived its subscription — the webhooks do that already, and this catches the
one whose event never arrived.

The refund call is keyed on the payment id, so a second run cannot refund
twice even if the first died between the Stripe call and the database update. A
refund that fails leaves the listing up for the next run: a listing that stays
up one extra day is a much smaller problem than one quietly dropped.

Refunds issued by hand in the Stripe dashboard come back through
`charge.refunded` and are recorded the same way. A refund that never reaches
the database is how the books stop matching.

## Testing it

Use Stripe's test cards. The only one worth memorising:

| Card | What it does |
| --- | --- |
| `4242 4242 4242 4242` | Succeeds |
| `4000 0000 0000 9995` | Declined for insufficient funds |
| `4000 0025 0000 3155` | Requires 3-D Secure authentication |

Any future expiry, any CVC, any postcode.

**The webhook will not reach a local machine**, only a public URL. On the
server it works as-is. Locally, either use the Stripe CLI
(`stripe listen --forward-to localhost/webhooks/stripe`, which prints its own
signing secret to use instead) or test against the deployed preview.

After a test payment, check three places:

1. **Stripe → Developers → Webhooks → your endpoint** shows a 200.
2. `SELECT status FROM jobs WHERE reference = '…'` reads `active`.
3. The job appears on `/jobs`.

If Stripe shows a non-200, the message there says why — and the same event is
in the `webhook_events` table with its payload and outcome.

## Going live

- Swap test keys for live ones with `bin/configure.php`. Prefer a restricted
  key; see **Which API key** above, and prove the permission set in a sandbox
  before making the live one.
- Add a **live-mode** webhook endpoint in Stripe; test and live have separate
  endpoints and separate signing secrets.
- Make sure `stripe.api_base` is **not** in the config. It exists so the flow
  can be tested against a local stub, and `bin/check.php` fails if it is set
  alongside live keys.
- Post a real job with a real card and confirm the money arrives, the job goes
  live, and the receipt reads correctly.
- Then test a refund, not just a charge.
- Buy a placement with a real card, confirm the badge appears and the billing
  portal opens, then cancel it in the portal and confirm the listing drops back
  and the slot frees up.
- Enable the Stripe **customer portal** (Settings → Billing → Customer portal)
  and allow cancellation there. The link is generated per pro at request time;
  nothing needs configuring in the code.
