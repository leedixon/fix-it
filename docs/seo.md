# Search

What Fix Listed tells search engines, where each piece of it lives, and the
rules it will not break to rank better.

Most of this is machine-checked. `php bin/smoke.php` asserts the parts that
can be asserted; `php bin/check.php --live` catches the settings.

---

## The one setting that controls everything

`app.url` in `config/config.php`.

Every canonical link, every `og:url`, every `@id` in the structured data,
every `<loc>` in the sitemap and the `Sitemap:` line in `robots.txt` is built
from it. Wrong, and the entire site tells search engines it lives somewhere
else — which fails silently, looks perfect in a browser, and is noticed weeks
later when nothing has been indexed.

`php bin/check.php` prints it on every run for that reason, and `--live`
fails if it still has `/preview` on the end.

The second one is `app.noindex`. It drives three things at once, deliberately:

| `app.noindex` | Every page's `<meta name="robots">` | `/robots.txt` | `/sitemap.xml` |
|---|---|---|---|
| `true` (preview) | `noindex, nofollow` | `Disallow: /` | valid, but empty |
| `false` (live) | absent | full allow, with the sitemap | every public URL |

One flag, because a site whose pages say `noindex` while its `robots.txt`
invites the world in is telling two stories, and whichever gets believed,
somebody loses an afternoon working out why.

The empty sitemap is on purpose. It answers `200`, so the URL can be
submitted to Search Console now and checked; the day the site opens it fills
itself. A `404` would mean finding out on launch day that the route was never
right.

---

## URLs

| Page | Address | Built by |
|---|---|---|
| Town landing page | `/handyman/freeport-il` | `Seo::cityPath()`, via `city_url()` |
| Trade page | `/services/plumbing` | `Seo::servicePath()`, via `service_url()` |
| Profile | `/pros/{slug}` | — |
| Job | `/jobs/{reference}` | — |

Town pages used to live at `/in/freeport`. That address said nothing about
what the page was for; `/handyman/freeport-il` contains the two words people
actually type, and the state suffix separates this Freeport from the six
others in the United States.

`/in/{slug}` still answers, with a **301**, and always will. It resolves the
town first, so a slug that never existed 404s rather than redirecting to a
page that is not there — a redirect chain ending in a 404 is worse than the
404 on its own. `/handyman/freeport` without the suffix 301s to the canonical
form too, so one town cannot end up with two pages competing for one search.

**Templates never build these by hand.** `city_url($row)` and
`service_url($slug)` exist so that the shape lives in one place; a link
written as `'/in/' . $city['slug']` in a view is how a site ends up with half
its internal links pointing at a redirect.

The state comes off the `cities` row rather than being hard-coded. Rockton
and South Beloit sit on the Wisconsin line, and the first market that crosses
it must not produce `/handyman/beloit-il`.

---

## Which towns get a page

`cities.has_page`. Nineteen of forty-six today — the ten Tier 1 towns and the
larger places around them.

The villages stay off, and the reason is in the seed file: forty-six pages
for forty-six towns, most with nobody covering them, reads as a content farm.
A page is turned on or off with one `UPDATE`; migration `007` is the worked
example, and the footer, the sitemap and the "nearby towns" chips all follow
without being edited.

`sort_order` is how those lists read, and the cheapest available signal about
which pages matter. Freeport is first.

### A town is offered to Google only once it has something

`has_page` decides whether a town has a page at all. A second gate decides
whether that page is submitted for indexing, and it is automatic.

`GeographyRepository::pageCitiesWithContent()` returns the towns that have a
**real** tradesperson covering their county, or a **real** job open in them.
Only those go in `sitemap.xml`. A town with neither still serves its page —
somebody who followed a link gets a straight answer and the sign-up form —
but the page carries `noindex` and stays out of the sitemap until there is
something on it.

Sample rows do not count, in either place. A crawler cannot see the SAMPLE
badge, and a page of invented businesses is worse to have indexed than an
empty one.

Nothing has to be switched on by hand. The first real tradesperson to cover
a county puts every town in that county into the next sitemap read; the
first job posted in a town does it for that town alone. Tested both ways in
`bin/smoke.php`.

**Why this exists.** Strip the sample listings and a town page is a heading,
one sentence and a row of filter links — the same shape nineteen times over.
That is what "thin content" and "doorway pages" describe. Submitting them
anyway is asking to be judged on the worst version of the site, and that
judgement is far harder to reverse than it is to avoid.

**Why the answer was not to write more words.** There is nothing true to say
about Stockton that is not equally true of Lena. The `population` column is
empty, there are no coordinates, and anything written per town would be the
town name dropped into a template — which is the thing the guidelines are
actually about, not a workaround for it.

### The duplication that is still there

Coverage is stored per **county**. Every town in Stephenson County therefore
shows the same list of tradespeople, and always will, however much supply
arrives. Seven towns, one list, different headings.

Open jobs are the only genuinely town-level content on those pages.

Three honest options, in rough order of preference:

1. **Leave it and watch.** Near-duplicates are not a penalty; Google picks a
   canonical among them and the others go quiet. The risk is spending crawl
   budget and looking thinner than the site is.
2. **Cut `has_page` back to the towns worth defending** — the Tier 1 ten —
   and let the rest be served by the county filter on `/pros`. One `UPDATE`,
   and the footer, sitemap and chips follow.
3. **Make coverage town-level** rather than county-level. The honest fix,
   and much the largest: it changes what a tradesperson fills in when they
   apply, which is a real cost to the people you are trying to recruit.

Do not do 3 to solve an SEO problem. Do it if homeowners in Galena start
complaining that they get quotes from people an hour away.

---

## Structured data

Assembled in `app/Core/Seo.php`, rendered by `app/Views/partials/jsonld.php`,
one `@graph` per page.

| Node | Where | Notes |
|---|---|---|
| `Organization` | every page | stable `@id`, so page nodes reference it rather than repeating it |
| `WebSite` | every page | — |
| `Service` | town and trade pages | `provider` points at the Organization |
| `LocalBusiness` | profiles | **never on a sample profile** |
| `BreadcrumbList` | anywhere with a trail | built from the same array the trail is drawn from |
| `FAQPage` | trade pages, `/for-pros` | only questions the page visibly answers |

There is deliberately **no `WebSite`/`SearchAction`**. It tells Google there
is a search box whose results live at a URL pattern; this site has filters
(`?trade=`, `?county=`) and no search. Declaring one describes a feature that
does not exist.

There is deliberately **no `Organization` claim to be a local business in
each town**. Fix Listed does not do the plumbing — it is the directory where
you find whoever does. A `LocalBusiness` node per town is both false and
exactly the doorway-page pattern the guidelines name.

### The four things it will not do

These are not style preferences. Each one is a way of telling a search engine
something untrue about businesses that do not exist, and each is covered by a
test in `bin/smoke.php`.

**1. It never marks up a sample listing.** The seed data is ten tradespeople
who do not exist, with licence numbers, ratings and phone numbers. On the
page they carry a Sample badge and a banner, so a person cannot be fooled.
Structured data has no badge, so a seeded profile gets no `LocalBusiness`
node at all.

**2. It never publishes a fabricated rating.** `aggregateRating` is computed
at request time from published, non-demo reviews — never from
`pro_profiles.rating_avg`, which is a nightly rollup that counts seeded ones.
No reviews means no rating node, rather than a rating of zero.

**3. It never counts invented listings.** A town page shows every listing it
has, samples included and labelled. The `Service` description on that same
page uses `ProRepository::countReal()`, which excludes them. The markup
understates; it does not overstate.

**4. It never marks up an answer that is not on the page.** The FAQ on
`/for-pros` is repeated in `PageController` word for word. If the two ever
disagree, the template is right and the controller is the bug.

### Escaping

`Seo::json()` encodes with `JSON_HEX_TAG`, which is not optional. Structured
data is assembled from what tradespeople typed into their own profiles and
written into a `<script>` element — a business name containing `</script>`
would otherwise close the block and turn the rest of the JSON into markup.
`bin/smoke.php` fires a hostile name at it on every run.

---

## The link backbone

The footer carries every trade page and every town page, on every page of the
site.

That is what makes them reachable: a page only the sitemap knows about is a
page nothing links to, and search engines crawl what is linked to. Less
abstractly, it is how somebody reading the Freeport page finds the Lena one.

Both lists come from the database through `Controller::pageCities()` and
`Controller::trades()`, so they follow `has_page` without being edited.

---

## Content

Trade pages are driven by two sources, and neither is filler:

- `app/Core/TradeCopy.php` — what the trade covers, the calls that actually
  come in, and the one question worth asking before hiring. Written per
  trade. `TradeCopy::for()` returns `null` for a trade nobody has written up,
  and the page shows less rather than showing boilerplate pretending to be
  specific. `bin/smoke.php` fails if any active trade is missing copy.
- `licence_authorities` — the real Illinois licensing position for that
  trade, from the table the admin screen edits. Plumbers are licensed by
  Public Health, not IDFPR. Electricians have no state licence at all.
  Roofers are on the IDFPR register and you can check the number yourself.

This is the part that makes a trade page worth opening before a single
plumber has listed, and it is why the "launching soon" empty state is not an
embarrassment. A page that only works once the directory is full is a page
that cannot help fill it.

---

## Link previews while the site is closed

Facebook's crawler obeys `robots.txt`. A closed site that says `Disallow: /`
to everybody therefore refuses `facebookexternalhit`, which never reaches the
page and never reads the `og:` tags — so a shared link comes out as a bare
card with no image. It looks exactly like a broken image and is not one.

`SeoController::PREVIEW_CRAWLERS` names the crawlers that build preview cards
rather than search indexes, and the pre-launch `robots.txt` gives each one a
group of its own with an empty `Disallow`, above the blanket refusal. A group
is chosen by the most specific matching name and only that group applies, so
the named ones ignore the `Disallow: /` below them and everything else does
not.

This does not put a closed site into a search index. The `noindex` meta tag
on every page is what does that job, and it stays on. These crawlers do not
index.

After launch they are not named at all: `User-agent: *` already allows them,
and a second copy of the rules is a second thing to keep in step.

**Facebook caches what it scraped.** Fixing the file does not fix a card that
was already fetched — paste the URL into the
[Sharing Debugger](https://developers.facebook.com/tools/debug/) and press
**Scrape Again**. `php bin/socialcheck.php https://fixlisted.com/` checks
everything else first, and prints the debugger link for you.

---

## Turning it on, in order

The switch is one line. The sequencing around it is what decides whether the
first crawl helps or hurts.

### Before you flip it

- [ ] **Sample data purged** — `php bin/demo.php purge`, `app.demo_data` set
      to `'hide'`. An indexed page of invented businesses is the worst
      outcome available here.
- [ ] **Enough real supply that the pages are not empty.** There is no magic
      number, but the question to ask is: *if a stranger lands on `/pros`
      from a search, does this look like a directory or like a plan for one?*
      One listing is a plan.
- [ ] **Check what the sitemap actually offers.** `curl` it and count. Towns
      with nothing on them are already excluded, so a small number is the
      system working, not a fault. What matters is that the URLs in it are
      ones you would be happy to be judged on.
- [ ] **A lawyer has read `/terms` and `/privacy`** — and, now, the two
      academy lessons that describe Illinois law: *What a home repair
      contract has to say in Illinois* and *Deposits, payment schedules, and
      not paying twice*. They name the Home Repair and Remodeling Act and the
      Mechanics Lien Act and deliberately state no thresholds or deadlines,
      but they are still statements about the law on a commercial site.
- [ ] **`app.url` has no `/preview` on it.** Every canonical, `og:url`,
      JSON-LD `@id` and sitemap `<loc>` is built from it.

### Flipping it

`php bin/configure.php --launch`. One flag moves three things together: the
`robots` meta on every page, what `/robots.txt` says, and whether the sitemap
has anything in it.

Then delete any `robots.txt` sitting in the document root. A real file wins
over the router, so a leftover holding-page one keeps saying `Disallow: /`
after launch — silently, with nothing in the app to show it.

### The first fortnight

Verify the domain in Search Console and submit
`https://fixlisted.com/sitemap.xml`. Then leave it alone. Indexing a new
site takes weeks, and there is no lever that makes it faster.

What to read, when it arrives:

- **Pages → Indexed.** The number should climb toward the sitemap count.
- **"Crawled — currently not indexed"** on a town page is the system telling
  you that page had nothing on it. That is information about supply, not a
  bug to fix in code.
- **"Duplicate, Google chose a different canonical"** across towns in one
  county is the duplication described above, behaving exactly as expected.
  Act on it only if it spreads to pages that are genuinely distinct.
- **Queries.** Expect the academy first. Lessons answer a question; a
  directory page answers a need, and needs are harder to rank for cold.

### If pages get judged thin

Do not add words. Adding text to a page Google has already decided is thin
is the pattern that made it thin.

Take the page out of the sitemap, let it go `noindex` until it has
something, and put the effort into supply. The gate above already does this
for towns automatically. For anything else — a trade page, a service page —
the same logic applies by hand: a page nobody should land on is a page that
should not be offered.

---

## What is still worth doing

- **Google Search Console** — verify the domain, submit
  `https://fixlisted.com/sitemap.xml`, and read the Coverage report a week
  later. Nothing in this repository can do this for you.
- **A Google Business Profile** for Fix Listed itself, if it ever has a real
  address to attach.
- **Reviews.** The single biggest thing on this list, and it is not a code
  change. `aggregateRating` stays absent until real homeowners leave real
  reviews, and that is the honest order to do it in.
- **Task pages** (`/services/plumbing/water-heater-replacement` and the like)
  — deliberately deferred. Ten trade pages that are genuinely useful beat
  ninety that are not, and the trade pages should be earning traffic before
  anything is built on top of them.
