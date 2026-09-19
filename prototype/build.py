#!/usr/bin/env python3
"""
Wrap Artifact-format pages into standalone web pages for hosting.

Sources here have no <!doctype>, <html> or <head> — the Artifact viewer
supplies those and a web server does not. This splits each file at its first
body element, assembles a proper head, and writes the result ready to upload.

    python3 prototype/build.py

Outputs:
    dist/               the clickable prototype
    maintenance/dist/   the pre-launch holding page
"""

import pathlib, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent

FAVICON = (
    "data:image/svg+xml,"
    "%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E"
    "%3Ctext y='.9em' font-size='90'%3E%F0%9F%94%A7%3C/text%3E%3C/svg%3E"
)

RESET = """<style>
  /* The Artifact viewer supplies these; a plain web server does not. */
  html { color-scheme: light dark; }
  :root {
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }
  body { margin: 0; }
  img { max-width: 100%; }
  [hidden] { display: none !important; }
</style>"""

PAGES = [
    {
        "src": "prototype/index.html",
        "out": "dist",
        "split": '<div class="rolebar">',
        "title": "Fix Listed — the local trades directory",
        "description": (
            "Post a handyman job for a flat $10 and hire direct. No commission, "
            "no lead resale. Vetted local trades across Northwest Illinois."
        ),
        "robots": "PRE-LAUNCH: placeholder pros and reviews.",
    },
    {
        "src": "maintenance/src.html",
        "out": "maintenance/dist",
        "split": '<div class="top">',
        "title": "Fix Listed — opening in Northwest Illinois",
        "description": (
            "A trades directory for Northwest Illinois. Homeowners post a job for "
            "a flat $10, local pros quote free, and nobody takes a cut. "
            "Claim a founding listing before we open."
        ),
        # This one gets shared to recruit tradespeople, so it needs to survive
        # a Facebook or SMS link preview — hence the og: tags below.
        "robots": "PRE-LAUNCH: the site is not open yet.",
    },
]


def head(page: str) -> str:
    meta = next(p for p in PAGES if p["out"] == page)
    return f"""<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<!-- {meta['robots']}
     Delete the next two lines the day the real site goes live, or Google will
     never index fixlisted.com. -->
<meta name="robots" content="noindex, nofollow">
<meta name="googlebot" content="noindex, nofollow">

<meta name="description" content="{meta['description']}">
<meta name="theme-color" content="#0E1513">
<link rel="icon" href="{FAVICON}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Fix Listed">
<meta property="og:title" content="{meta['title']}">
<meta property="og:description" content="{meta['description']}">
<meta property="og:url" content="https://fixlisted.com/">
<meta name="twitter:card" content="summary">
{RESET}
"""


HTACCESS_PROTOTYPE = """# Fix Listed — prototype hosting rules.

RewriteEngine On

# Let's Encrypt validates and renews over plain HTTP by fetching a file from
# /.well-known/acme-challenge/. Never redirect or rewrite that path.
RewriteRule ^\\.well-known/ - [L]

RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml
</IfModule>
<IfModule mod_headers.c>
  <FilesMatch "\\.(html)$">
    Header set Cache-Control "no-cache, must-revalidate"
  </FilesMatch>
</IfModule>

ServerSignature Off
Options -Indexes
"""

HTACCESS_MAINTENANCE = """# Fix Listed — pre-launch holding page.

RewriteEngine On

# ACME challenges must never be redirected or rewritten, or certificate
# renewal breaks the moment the certificate lapses.
RewriteRule ^\\.well-known/ - [L]

# The waitlist endpoint is a real script and must run, not be rewritten away.
RewriteRule ^signup\\.php$ - [L]

RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# Every other path shows the holding page. A 200, not a 503: this link gets
# shared to recruit tradespeople, and a 503 breaks link previews and stops
# people reaching a page that is working exactly as intended.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml
</IfModule>
<IfModule mod_headers.c>
  <FilesMatch "\\.(html)$">
    Header set Cache-Control "no-cache, must-revalidate"
  </FilesMatch>
</IfModule>

ServerSignature Off
Options -Indexes
"""

ROBOTS = (
    "# Pre-launch. Replace this file the day the real site ships.\n"
    "User-agent: *\n"
    "Disallow: /\n"
)


def build(meta: dict) -> None:
    src = ROOT / meta["src"]
    out = ROOT / meta["out"]
    text = src.read_text(encoding="utf-8")

    if meta["split"] not in text:
        sys.exit(f"build failed: {meta['src']} has no split marker {meta['split']!r}")

    head_part, body_part = text.split(meta["split"], 1)
    body_part = meta["split"] + body_part

    page = (
        '<!doctype html>\n<html lang="en">\n<head>\n'
        + head(meta["out"])
        + head_part.rstrip()
        + "\n</head>\n<body>\n"
        + body_part.rstrip()
        + "\n</body>\n</html>\n"
    )

    out.mkdir(parents=True, exist_ok=True)
    (out / "index.html").write_text(page, encoding="utf-8")
    (out / "robots.txt").write_text(ROBOTS, encoding="utf-8")
    (out / ".htaccess").write_text(
        HTACCESS_MAINTENANCE if "maintenance" in meta["out"] else HTACCESS_PROTOTYPE,
        encoding="utf-8",
    )
    kb = (out / "index.html").stat().st_size / 1024
    print(f"{meta['out']}/index.html  ({kb:.0f} KB)")


for meta in PAGES:
    build(meta)
