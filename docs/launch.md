# Launch checklist

Things that must be true before fixlisted.com stops being a holding page.
Roughly in order.

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
- [ ] Stripe account out of sandbox, live keys in via `bin/configure.php`.
- [ ] The refund promise on `/pricing` and in `/terms` matches what the code
      actually does — the auto-refund sweep must exist before the promise ships.
- [ ] A real business address and contact route, if Stripe asks for one.

## Technical

- [ ] **Move the mount to the document root.** The app derives its base path
      from where `index.php` sits, so this is a file move and not a code change.
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
