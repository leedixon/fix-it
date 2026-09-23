# Social cards

`card.html` is the source for the images that appear when someone pastes a
fixlisted.com link into Facebook, LinkedIn, Slack, iMessage or X.

## Why it is rendered and not generated

Image generators cannot spell. Asked for a card reading "Fix Listed" they
produce something that looks right at a glance and says "Fxi Lsited" at full
size, and you do not always catch it in a thumbnail. Rendering the card from
HTML means the wordmark is set in the real Marcellus, at the real brand
colours, with the same tokens as `public/assets/css/site.css` — so the card
and the site look like one thing, because they are one thing.

## Re-rendering

```bash
node assets/social/render.js
```

Run it on a machine with Playwright installed, not on the shared host. The
PNGs are committed, so the server only ever serves finished files.

| Output | Size | Used by |
| --- | --- | --- |
| `public/assets/social/og.png` | 1200 × 630 | Open Graph, Twitter, LinkedIn, Slack, SMS |
| `public/assets/social/square.png` | 1080 × 1080 | Instagram, square previews |
| `public/assets/social/facebook-profile.png` | 1080 × 1080 | Facebook Page profile picture |
| `public/assets/social/facebook-cover.png` | 1640 × 624 | Facebook Page cover photo |

The first two are served by the site. The Facebook pair is not — nothing links
to them. They are uploaded by hand once to the Page and live here so they are
versioned with the brand they came from rather than only in somebody's
downloads folder.

## The two Facebook rules that are easy to get wrong

**The profile picture is cropped to a circle and shown as small as 36px.**
Both facts shaped it. The corners are thrown away, so nothing lives in them.
And the plumb bob is *filled* here rather than drawn in line art like the
cards: a 2.4px stroke scaled down to a comment-thread avatar is a tenth of a
pixel, and the mark simply disappears. There is no wordmark on it, because no
wordmark is readable at that size and Facebook prints the Page name beside the
avatar anyway.

**The cover is authored at twice its display size.** The file is 1640 wide and
Facebook shows it at 820, so every type size in `.card.fbc` is double what it
looks like it should be. Set at face value, the county line came out around
six pixels on a real page. Nothing in the render says so — you only catch it
by putting the cover next to a browser at the size it is actually shown.

Mobile fills a taller frame and throws away about a third of the width, split
evenly, so everything that has to be read sits in the `--safe` band. The bottom
strip is kept clear because the profile picture overlaps the cover there:
bottom-left on desktop, lower-centre on mobile.

`prototype/build.py` copies `og.png` to `maintenance/dist/og.png`, because the
holding page's preview points at `https://fixlisted.com/og.png` — a path that
has to work while the root is still the holding page.

## Fonts

`fonts/` holds the four woff2 files the card uses, vendored from the
`@fontsource` packages. They are here so the card renders with no network
access and produces identical output every time. They are build-time assets:
the site itself still loads its fonts from Google Fonts.

## Changing the wording

Edit `card.html` and re-render. The text lives in one `<template>` block and
is cloned into both sizes, so the two cards cannot end up saying different
things. The per-size type scale is in the `.card.og` and `.card.sq` rules —
adjust `--claim` and `--claim-w` if new wording breaks the line wrap.

After re-rendering, run `python3 prototype/build.py` so the holding page's copy
is updated too, and re-scrape the URL in
[Facebook's debugger](https://developers.facebook.com/tools/debug/) — it caches
the old image for a long time otherwise.

## When the preview shows no image

```bash
php bin/socialcheck.php https://fixlisted.com/
```

It fetches the URL with Facebook's own user agent and walks the causes in
order, because "no image" looks identical from the outside whether it is
robots.txt, a 403 on the file, a relative `og:image`, or an `index.html` that
was never rebuilt.

The one that bit first: **`robots.txt` said `Disallow: /`**, and
`facebookexternalhit` obeys robots.txt. A disallowed page is never fetched at
all, so the `og:` tags are never read and the file being present on disk makes
no difference. The generated `robots.txt` now names the preview crawlers and
allows them, while still keeping search engines out until launch — robots.txt
resolution picks the most specific matching user-agent group, so a named
crawler ignores the `*` catch-all entirely.

Facebook then caches the bad result. After fixing anything, open the URL in the
[sharing debugger](https://developers.facebook.com/tools/debug/) and press
**Scrape Again**, twice — the first press often just re-reads the cache.
