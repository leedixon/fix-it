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
