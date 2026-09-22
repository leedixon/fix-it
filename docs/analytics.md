# Analytics

Google Tag Manager container `analytics.gtm_id`, loading GA4. What the site
sends, what it deliberately does not, and how to set the events up in GA4.

---

## How it works

`app/Views/partials/gtm.php` does three things a pasted snippet would not.

**The container id comes from config**, validated against `GTM-` plus letters
and digits before it reaches the page. It ends up inside a `<script>` tag, so
anything else is dropped rather than executed. An empty id renders nothing at
all, which is the right setting for any copy of this codebase that is not the
live site.

**Staff are not counted.** An administrator clicking through the site all
afternoon is not a visitor and distorts every funnel they touch. Tradespeople
and homeowners are real users and are tracked normally. The exception is
`?gtm_debug` — GTM's own Preview appends it, and suppressing the tag from the
one person trying to debug it is the most confusing possible failure.

**The dataLayer is filled on the server.** What a page is about is known in
the controller and guessed at from the URL anywhere else. `Controller::page()`
derives `page_type` from the template name, so a new page is annotated
whether or not anyone remembers to, and controllers add what only they know.

---

## What arrives in the dataLayer

One push, before the container loads, so every tag and trigger can read it.

### On every page

| Key | Example | Notes |
|---|---|---|
| `page_type` | `city`, `service`, `directory`, `pro`, `job`, `home`, `post_job` | derived from the template |

### Where the page knows more

| Key | On | Example |
|---|---|---|
| `city`, `county` | town pages | `Freeport`, `Stephenson` |
| `trade` | trade pages, filtered directory, filtered board, a job | `plumbing` |
| `pro` | a profile | the slug, never the person's name |
| `listings_shown` | directory and trade pages | `0` on a launching-soon page |

`listings_shown` is there to answer the question worth asking of a page built
before the directory filled up: does a launching-soon page convert anyway? If
it does, build more of them.

### Conversions

| `event` | Fires on | Carries |
|---|---|---|
| `purchase` | `/post-a-job/thanks`, **once the job is live** | `transaction_id`, `value`, `currency`, `items` |
| `sign_up` | `/list-your-business/received` | `method: pro_application` |

Both are GA4's own event names rather than custom ones, so they land in
reports that already exist.

**`purchase` fires on what the database says, not on arrival.** That page is
loaded by the browser on the way back from Stripe and anyone can type its
URL, so landing there is not evidence of payment. While the webhook is still
in flight the page refreshes itself every four seconds, and the event fires
on the reload that finds the job live.

`transaction_id` is the job reference, which is what GA4 deduplicates on. A
homeowner who reloads the page or opens it twice from their email is one
sale, and getting that for free beats getting it wrong.

`sign_up` has no transaction id and so no deduplication — nothing was
charged, so there is nothing to dedupe against. A refresh counts twice. That
is a known, small inaccuracy on a soft conversion, and the number that
actually matters is the count of applications in the admin queue, which is
exact.

---

## What is deliberately not tracked

**Anything identifying.** No names, no email addresses, no phone numbers, no
job descriptions, no ZIP codes. A job reference and a dollar amount are what
a conversion needs; everything else would be sending a homeowner's details to
Google because they happened to be in scope. Profiles report a slug, not a
person's name.

**Subscription revenue.** Boost and Spotlight are sold inside `/my`, which is
behind a login and carries no tag. Stripe is the source of truth for
subscription revenue and reports it exactly; adding client-side tracking
would double-count against it and give worse numbers, not better ones.

**Clicks, scrolls and outbound links.** The site's only JavaScript is the
password toggle, and it is staying that way. GA4's enhanced measurement
covers scroll and outbound clicks from inside the container if you want them
— that is a GTM setting, not a code change.

---

## Setting it up in GA4

Nothing below is in this repository; it is done once in the GTM and GA4 web
interfaces.

1. **GTM → Variables.** Create a Data Layer Variable for each key you want to
   segment by: `page_type`, `city`, `trade`, `listings_shown`, `pro`.
2. **GTM → Tags.** On the GA4 Configuration tag, add those as event
   parameters so they ride along with every page view.
3. **GTM → Triggers.** A Custom Event trigger on `purchase` and one on
   `sign_up`. Fire a GA4 Event tag from each, passing the parameters straight
   through — `transaction_id`, `value`, `currency` and `items` for `purchase`;
   `method` for `sign_up`.
4. **GA4 → Admin → Custom definitions.** Register `page_type`, `city`,
   `trade` and `listings_shown` as custom dimensions. Until you do, they
   arrive but cannot be reported on, which looks exactly like them not
   arriving.
5. **GA4 → Admin → Key events.** Mark `purchase` and `sign_up` as key events.
6. **Check it.** GTM Preview, then GA4 DebugView. Open the site in a signed-
   out window, or append `?gtm_debug` — signed-in staff are not tracked, and
   that is the first thing to rule out when a tag "is not firing".

---

## Privacy

Turning this on means `/privacy` has to say so. GTM exists to load tags that
set cookies. The page currently describes it; if the container starts loading
anything beyond GA4, the page needs updating before the tag does.
