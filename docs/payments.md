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

## Setting it up

```bash
php bin/configure.php          # asks for the keys, validates their shape
php bin/check.php              # confirms all three are present
```

In the Stripe dashboard, **Developers → Webhooks → Add endpoint**:

| | |
| --- | --- |
| URL | `https://fixlisted.com/webhooks/stripe` |
| Events | `checkout.session.completed`, `charge.refunded` |

Copy the signing secret it shows you into `bin/configure.php`. **Without it no
payment can ever be confirmed and no job will ever go live** — everything else
can be correct and nothing will work. `bin/check.php` fails loudly if it is
missing.

While the site is at `/preview`, the endpoint is
`https://fixlisted.com/preview/webhooks/stripe`. It moves with the site.

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

## Refunds

The promise on the pricing page and in the terms: no quotes within the refund
window and the fee comes back automatically. A promise that only happens when
somebody remembers to run something is not a promise, so:

```
7 6 * * *  cd ~/fixlisted && php bin/sweep.php >> storage/logs/sweep.log 2>&1
```

`bin/sweep.php --dry-run` says what it would do and changes nothing. It also
expires listings past their run and tidies away checkouts abandoned over a week
ago.

The refund call is keyed on the payment id, so a second run cannot refund
twice even if the first died between the Stripe call and the database update. A
refund that fails leaves the listing up for the next run: a listing that stays
up one extra day is a much smaller problem than one quietly dropped.

Refunds issued by hand in the Stripe dashboard come back through
`charge.refunded` and are recorded the same way. A refund that never reaches
the database is how the books stop matching.

## Going live

- Swap test keys for live ones with `bin/configure.php`.
- Add a **live-mode** webhook endpoint in Stripe; test and live have separate
  endpoints and separate signing secrets.
- Make sure `stripe.api_base` is **not** in the config. It exists so the flow
  can be tested against a local stub, and `bin/check.php` fails if it is set
  alongside live keys.
- Post a real job with a real card and confirm the money arrives, the job goes
  live, and the receipt reads correctly.
- Then test a refund, not just a charge.
