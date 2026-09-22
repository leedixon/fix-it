# Launch checklist

Things that must be true before fixlisted.com stops being a holding page.
Roughly in order.

**Most of this is machine-checkable. Run it rather than reading it:**

```bash
php bin/check.php --live
```

Everything it reports as FAIL under *live readiness* is a setting that is
correct for a preview and wrong for a site taking money from strangers. The
list below explains why each one matters; the command tells you where you
actually are.

> **The first failure is not a launch item.** `database/seed.sql` publishes the
> sample accounts' password, and this repository is public. A seeded admin that
> can still sign in is an open door today, not at launch — and maintenance mode
> does not close it, because `/admin/login` stays reachable by design. Fix that
> before anything else on this page.

## Content

- [ ] **Real tradespeople signed up.** A directory of ten invented businesses
      is a demo. Aim for enough that a county page does not read as empty.
- [ ] **Purge the sample data** — `php bin/demo.php purge` — and set
      `app.demo_data` to `'hide'` in `config/config.php`.
- [ ] Check every page again afterwards. Empty states are designed, but they
      should be read once with real eyes.

## Legal and payments

- [ ] **A lawyer reads `/terms` and `/privacy`.** They are written to describe
      what the site actually does, in plain English, and they have not been
      reviewed by anyone qualified. Stripe's underwriting reads them, and so do
      people deciding whether to hand over a card.
- [ ] Stripe account out of sandbox, live keys in via
      `php bin/configure.php --stripe`. The plain run rebuilds the whole file
      and would want your database and mail credentials again.
- [ ] **The live key is a restricted key** (`rk_live_…`), with its permission
      set proven in a sandbox first — run the whole money path, then read
      Developers → Logs for 403s. See [payments.md](payments.md).
- [ ] **Live-mode webhook endpoint added in Stripe** — test and live have
      separate endpoints and separate signing secrets, and a job cannot go live
      without one. See [payments.md](payments.md).
- [ ] **All six events subscribed on that endpoint**:
      `checkout.session.completed`, `charge.refunded`, `invoice.paid`,
      `invoice.payment_failed`, `customer.subscription.updated`,
      `customer.subscription.deleted`. Without the last four a placement goes
      up and never comes down — including one nobody is paying for any more.
- [ ] **Stripe's customer portal switched on**, with cancellation allowed
      (Settings → Billing → Customer portal). It is the only way a pro can
      cancel a placement or change a card, and the link fails without it.
- [ ] **You have taken the site down and put it back up once, in anger.**
      `php bin/maintenance.php on` then `off`, and check that a signed-out
      browser really does get the maintenance page while yours does not. The
      day you need it is a bad day to find out it does not work. See
      [maintenance.md](maintenance.md).
- [ ] **The sweep is on cron.** The automatic refund is a written promise; it
      does not happen on its own.
      `7 6 * * * cd ~/fixlisted && php bin/sweep.php >> storage/logs/sweep.log 2>&1`
- [ ] `stripe.api_base` is absent from the config.
- [ ] The refund promise on `/pricing` and in `/terms` matches what the code
      actually does — the auto-refund sweep must exist before the promise ships.
- [ ] A real business address and contact route, if Stripe asks for one.
- [ ] **Placement caps set per market** — `markets.boost_slots` and
      `markets.spotlight_slots`, editable at `/admin/markets`. Scarcity is the
      product; a cap of `0` takes a plan off sale.

## Administration

- [ ] **The team is real, not sample.** `php bin/admin.php --list` should show
      no accounts marked SAMPLE. Invite real colleagues from **Admin → Team**;
      see [team.md](team.md) for what each role can reach.
- [ ] **A second superadmin exists**, or you have tested `php bin/admin.php`
      and know it works. One owner who can sign in is one lost password away
      from needing SSH to administer your own platform.
- [ ] **Make a real superadmin** — `php bin/admin.php`. The seeded one
      (`owner@fixlisted.com`) is sample data and its password is published in
      `database/seed.sql`, so anyone who has read this repository can sign in as
      it. `bin/demo.php purge` refuses to run until a real admin exists, so this
      is not optional.
- [ ] Sign in at `/admin` and approve one application end to end, to confirm
      the emails arrive.

## Licensing

- [ ] Guidance entered for every state you operate in, at **/admin/licensing**.
      A state with no rows shows reviewers "no guidance yet" — which is the
      correct thing to show, but it means every application there needs
      research before it can be approved. See [licensing.md](licensing.md).

## Technical

- [ ] **Move the mount to the document root.** The app derives its base path
      from where `index.php` sits, so this is a file move and not a code change.
- [ ] **Google Tag Manager switched on** — `php bin/configure.php --analytics`.
      Do not hand-edit `config/config.php`; a stray comma there takes the
      whole site down. Empty until then, so no
      preview traffic reports into the live property. Signed-in staff are
      never counted, and the admin and account areas carry no tag at all.
      `/privacy` already describes the cookies this sets. Signed-in staff see
      no tag, so **check it signed out or in a private window** — Tag
      Assistant opens the site in your own browser, where you are signed in.
      Its `gtm_debug` parameter is honoured, so Preview mode works either way.
- [ ] **Go live: `php bin/configure.php --launch`.** Flips `app.noindex` off
      and `app.demo_data` to `'hide'` in one step, and refuses if sample rows,
      a sample admin, or an empty directory would make that a mistake. Do not
      hand-edit those two — launch day is the worst moment to put a parse
      error into a file holding live credentials.
- [ ] **Remove `noindex`.** Set `app.noindex` to `false`. Until then every page
      carries `<meta name="robots" content="noindex, nofollow">`, deliberately.
- [ ] Remove the `Disallow: /` from the holding page's `robots.txt`.
- [ ] **`www` A record** pointing at the same IP, and reissue the certificate
      to cover `www.fixlisted.com`. Right now typing `www.` gives a warning.
- [ ] `php bin/check.php` passes on the server.
- [ ] `php bin/mailtest.php you@example.com` — a real message arrives.
- [ ] Database backups exist and have been restored once, to prove they work.

## Before the first paid listing

- [ ] Post a job end to end with a real card, and confirm the money arrives.
- [ ] Confirm a job in `pending_payment` is invisible everywhere — the jobs
      board, the city pages, search.
- [ ] Confirm the receipt email arrives and reads correctly.
- [ ] Confirm the refund path works, not just the charge path.

## Worth doing early, not required

- [ ] Import Census ZCTA→county data into `zip_counties`, so a ZIP code entered
      when posting resolves to a county without asking. It must come from the
      Census file — hand-typed ZIP boundaries are wrong in ways nobody notices
      until a job is invisible to the pros who cover it.
- [ ] Google Search Console and a sitemap, once `noindex` is off.
- [ ] Re-scrape the link preview in Facebook's sharing debugger after the site
      moves to the root, so the cached card picks up the new URL. The card
      itself is `assets/social/` — re-render it there if the wording changes.
