# Fix Listed

Local trades directory for fixlisted.com, serving Northwest Illinois — homeowners post jobs for a flat $10, handymen
create profiles and quote for free, and revenue comes from listing fees plus advertising.

**Current state:** the prototype is live at https://fixlisted.com on A2 Hosting with
Let's Encrypt SSL. The database schema is written and verified but not yet imported to
production. The PHP application has not been written yet.

| Piece | State |
| --- | --- |
| Design prototype | Live at fixlisted.com (noindex, placeholder content) |
| Hosting, DNS, SSL | Done — A record at registrar, Let's Encrypt issued |
| Database schema | Written, verified on MariaDB 10.11, not yet imported to A2 |
| PHP application | Foundation built and tested (30 checks); pages not yet written |
| Stripe | Not started |

Deployment specifics for this account: cPanel user `leedixon`, document root
`/home/leedixon/fixlisted.com` (an addon domain — **not** `~/public_html`, which belongs
to the primary domain leedixon.com), repo cloned at `~/fixlisted`.

## Deploying

See [docs/deploy.md](docs/deploy.md) — a step-by-step runbook for A2 Hosting shared
cPanel, covering DNS, SSH, the database import, SSL and the prototype upload.

## Prototype

`prototype/index.html` is the source. It's written in Artifact format (no `<!doctype>`,
`<html>` or `<head>` — the viewer supplies those), so it needs a build step before a
real web server can serve it:

```
python3 prototype/build.py    # -> dist/index.html, robots.txt, .htaccess
```

`dist/` is what you upload to `public_html`. It's committed so the server can just
`git pull`. Don't edit it by hand — edit the prototype and rebuild.

Eight views, hash-routed:

| Route | What it shows |
| --- | --- |
| `#/home` | Landing page — hero quote-starter, trust strip, categories, featured pros, open jobs, reviews |
| `#/browse` | Pro directory with filters, sponsored placement and display ad slots |
| `#/pro/:id` | Pro profile — credentials, work gallery, reviews, sticky quote rail |
| `#/post` | 4-step job posting wizard ending in a $10 Stripe-style checkout |
| `#/advertise` | Ad packages (Free / Boost $49 / Spotlight $149) and an inventory map |
| `#/dashboard` | Handyman dashboard — leads, ad performance, profile strength |
| `#/admin` | Superadmin — markets, moderation queue, revenue by line, users |

The market selector in the header really does re-scope the data, so the multi-market
behaviour is visible rather than described. The role chips in the top bar are a prototype
affordance for previewing each dashboard.

## Database

```
mysql -u USER -p DBNAME < database/schema.sql   # 25 tables, 58 foreign keys
mysql -u USER -p DBNAME < database/seed.sql     # demo data mirroring the prototype
mysql -u USER -p --default-character-set=utf8mb4 --table DBNAME < database/verify.sql
```

`verify.sql` is a smoke test — eight queries the application genuinely depends
on, each with its expected answer written at the top of the file. Run it after
importing to a new server before pointing a domain at it.

Verified against MariaDB 10.11. Demo accounts all use the password
`demo-password`; `owner@fixlisted.com` is the superadmin. Delete them before launch.

See [docs/architecture.md](docs/architecture.md) for the decisions behind the schema.

## Decisions made so far

- **Hosting:** A2 Hosting shared cPanel — so the build target is PHP 8 + MySQL with no
  build step. Upload to `public_html`, import the schema, edit one config file.
- **Multitenancy:** one install, many city markets (Austin, Round Rock, San Marcos…).
  Every table carries a `tenant_id`. A superadmin creates markets and assigns a market
  admin; market admins only ever see their own tenant.
- **Payments:** Stripe Checkout for the $10 job fee, Stripe Billing for the monthly ad
  packages. A job stays in `pending_payment` and is not publicly visible until the
  webhook confirms the charge.
- **Ads are derived from the profile**, not built by the advertiser. A pro may override
  a headline, a one-line offer and which photo leads; the rating, review count and
  verified badges are rendered from live profile data and can't be self-authored.
  See [docs/architecture.md](docs/architecture.md#advertising-profile-derived-not-an-ad-builder).
- **Pricing:** $10 flat per job post. Free profiles and free quoting for pros. Revenue
  above that is advertising — Boost and Spotlight placements plus AdSense display slots.

## CRO decisions baked into the design

- One dominant call to action per view; everything secondary is a ghost button.
- Price transparency everywhere the fee appears — the checkout summary names the $0
  commission and $0 lead resale lines explicitly, because that is the differentiator.
- The posting wizard shows the running total at every step and states that nothing is
  charged until the post is reviewed.
- A 72-hour no-quote auto-refund is promised next to the pay button to de-risk the $10.
- Paid placement is always labelled as an ad, so ratings and licence badges stay credible.
- Sticky mobile action bar; the header CTA collapses into it below 700px.

## Application

```
php bin/configure.php    # write config/config.php (don't hand-edit it)
php bin/check.php        # pre-flight: config, database, extensions, permissions
php bin/smoke.php        # 30 checks against a real database
php bin/preview_emails.php   # render the transactional emails to storage/cache/
```

`config/config.php` holds the live credentials and is gitignored.
`config/config.example.php` is the template it is copied from.

`bin/smoke.php` is the regression test for the tenant boundary. Run it after touching
`Repository`, `TenantScope`, or any repository query. It proves, among other things,
that a query which forgets its `market_id` predicate raises instead of silently
returning another market's rows, and that a job in `pending_payment` never reaches the
public board.

Built so far: config, PDO bootstrap, the tenant-scoped repository base, session, CSRF,
auth with lockout, routing, views, and the market/pro/job repositories.

### Not built yet

Controllers and templates for the public site, the job posting flow, Stripe integration
and webhooks, the pro and admin dashboards, image uploads, and the cron jobs listed in
[docs/architecture.md](docs/architecture.md#shared-hosting-constraints).
