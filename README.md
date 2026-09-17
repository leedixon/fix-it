# FlexHandy

Local trades directory for flexhandy.com — homeowners post jobs for a flat $10, handymen
create profiles and quote for free, and revenue comes from listing fees plus advertising.

**Current state: design prototype only.** No backend has been written yet.

## Prototype

`prototype/index.html` — a self-contained clickable prototype. Open it in a browser, or
run `python3 -m http.server` from the repo root. No build step, no dependencies.

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

## Decisions made so far

- **Hosting:** A2 Hosting shared cPanel — so the build target is PHP 8 + MySQL with no
  build step. Upload to `public_html`, import the schema, edit one config file.
- **Multitenancy:** one install, many city markets (Austin, Round Rock, San Marcos…).
  Every table carries a `tenant_id`. A superadmin creates markets and assigns a market
  admin; market admins only ever see their own tenant.
- **Payments:** Stripe Checkout for the $10 job fee, Stripe Billing for the monthly ad
  packages. A job stays in `pending_payment` and is not publicly visible until the
  webhook confirms the charge.
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

## Not built yet

Database schema, authentication, the PHP application, Stripe integration, email and SMS
notification, image uploads, review moderation, and the AdSense wiring.
