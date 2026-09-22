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
