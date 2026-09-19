#!/usr/bin/env python3
"""
Wrap the Artifact-format prototype into a standalone web page for hosting.

prototype/index.html has no <!doctype>, <html> or <head> — the Artifact viewer
supplies those. A real web server does not, so this splits the file at the
first body element, puts the title/links/styles in a proper <head>, and writes
the result to dist/ ready to upload to public_html.

    python3 prototype/build.py
"""
import pathlib, re, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC  = ROOT / "prototype" / "index.html"
DIST = ROOT / "dist"

SPLIT = '<div class="rolebar">'

src = SRC.read_text(encoding="utf-8")
if SPLIT not in src:
    sys.exit(f"build failed: could not find the head/body split marker {SPLIT!r}")

head_part, body_part = src.split(SPLIT, 1)
body_part = SPLIT + body_part

# A tab icon with no extra HTTP request. Swap for a real favicon.ico at launch.
FAVICON = (
    "data:image/svg+xml,"
    "%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E"
    "%3Ctext y='.9em' font-size='90'%3E%F0%9F%94%A7%3C/text%3E%3C/svg%3E"
)

DESCRIPTION = ("Post a handyman job for a flat $10 and hire direct. "
               "No commission, no lead resale. Vetted local trades in Austin, TX.")

head_extra = f"""<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<!-- PRE-LAUNCH: this is the design prototype, with placeholder pros and reviews.
     Delete the next two lines the day the real site goes live, or Google will
     never index flexhandy.com. -->
<meta name="robots" content="noindex, nofollow">
<meta name="googlebot" content="noindex, nofollow">

<meta name="description" content="{DESCRIPTION}">
<meta name="theme-color" content="#0E1513">
<link rel="icon" href="{FAVICON}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="FlexHandy">
<meta property="og:title" content="FlexHandy — the local trades directory">
<meta property="og:description" content="{DESCRIPTION}">
<meta property="og:url" content="https://flexhandy.com/">
<style>
  /* The Artifact viewer supplies these; a plain web server does not. */
  html {{ color-scheme: light dark; }}
  :root {{
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }}
  body {{ margin: 0; }}
  img {{ max-width: 100%; }}
  [hidden] {{ display: none !important; }}
</style>
"""

page = (
    "<!doctype html>\n<html lang=\"en\">\n<head>\n"
    + head_extra
    + head_part.rstrip()
    + "\n</head>\n<body>\n"
    + body_part.rstrip()
    + "\n</body>\n</html>\n"
)

DIST.mkdir(exist_ok=True)
(DIST / "index.html").write_text(page, encoding="utf-8")

(DIST / "robots.txt").write_text(
    "# Pre-launch. The site is a design prototype with placeholder content.\n"
    "# Replace this file the day the real site ships.\n"
    "User-agent: *\n"
    "Disallow: /\n",
    encoding="utf-8")

(DIST / ".htaccess").write_text("""# FlexHandy — prototype hosting rules.
# Replaced by the application's own .htaccess when the PHP app ships.

# Force HTTPS. Only effective once AutoSSL has issued a certificate.
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# The prototype is one file; send every path to it so deep links survive.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml
</IfModule>

# No caching while the prototype is still changing daily.
<IfModule mod_headers.c>
  <FilesMatch "\\.(html)$">
    Header set Cache-Control "no-cache, must-revalidate"
  </FilesMatch>
</IfModule>

ServerSignature Off
Options -Indexes
""", encoding="utf-8")

kb = (DIST / "index.html").stat().st_size / 1024
print(f"wrote dist/index.html ({kb:.0f} KB), dist/robots.txt, dist/.htaccess")
