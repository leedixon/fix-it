# Taking the site down, and putting it back up

Two ways to do the same thing. Both write the same switch.

```bash
php bin/maintenance.php on --message="Back by 3pm."
php bin/maintenance.php status
php bin/maintenance.php off
```

Or **Admin → Maintenance** (`/admin/maintenance`), superadmin only.

Use the admin screen day to day. Use the command when the admin screen will
not load — which is exactly the situation you will most want maintenance mode
in, and the reason the command exists.

## What visitors get

A short page saying the site is back shortly, with whatever message you set,
answered **503 Service Unavailable** with a `Retry-After` header.

The status matters more than the page. A search engine reads 503 as
"temporarily unavailable, keep what you have indexed and come back". The same
words served with a 200 tell it this URL *is now* a maintenance notice — which
is how a site comes back up having lost its rankings.

The page is deliberately self-contained: inline styles, no stylesheet, no
script, no image, and not a single database query. It has to render when
nothing else will, so it depends on nothing that could be the reason the site
is down.

## What you get

Nothing changes. Sign in at `/admin/login` and the whole site works exactly as
normal — directory, jobs board, admin, all of it. That is the point: take it
down, deploy, click through your own work, put it back up.

`/admin/login` stays reachable while the site is down. Without that, signing
out during maintenance would lock you out of your own site until you could get
to a terminal.

Every admin page carries a red bar while it is on. The way this feature goes
wrong is not turning it on — it is **forgetting it is on**, because you are
the one person who cannot tell. The bar is the only thing that tells you.
`php bin/check.php` fails loudly for the same reason.

## Who gets through

| | |
| --- | --- |
| Signed-in superadmin or market admin | Full site, as normal |
| Signed-in tradesperson or homeowner | Maintenance page |
| Signed out | Maintenance page |
| `/admin/login` | Always reachable |
| Stripe webhooks | **503** — see below |

A signed-in tradesperson is a visitor, not an operator. Only the two admin
roles pass.

## Stripe is held off too

Payment webhooks get the same 503. That is deliberate, and it is the safer of
two bad options.

Answering **200** during a migration would mean accepting an event the
application then fails to process — and Stripe, having been told the event was
received, never sends it again. That is a payment taken with no job published
and no placement granted.

Answering **503** tells Stripe to come back. It retries with exponential
backoff for roughly **three days**, so nothing is lost by a maintenance window
measured in minutes or hours.

**A maintenance window longer than three days would start dropping payments.**
If you need the site down for longer than that, take the Stripe endpoint out
of the picture properly rather than leaving this on.

## Why the switch is a file

`storage/maintenance.json`. Not a database row, not a line in `config.php`.

The moments you most need to take the site down are the moments something is
broken, and a database mid-migration is top of that list. A flag stored in the
thing that is broken is not a flag. A file also means `bin/maintenance.php`
works over SSH with no database, no session, and no working application.

`config/config.php` was the other candidate and was rejected: it holds live
credentials and is mode 600, and letting the web process rewrite it is a much
bigger thing to allow than a flag is worth.

Consequences worth knowing:

- **The file is per-server, and gitignored.** A `git pull` can never take a
  site down, or bring one up in the middle of a migration.
- **If the file exists but cannot be parsed, the site stays down.** Staying up
  because the flag had a stray comma in it is the failure nobody forgives.
- **Deleting the file brings the site straight back up**, from any shell, with
  no application involved. That is the escape hatch.
- **`storage/` must be writable** for the admin screen's button to work. The
  command works regardless, as long as the file can be removed.

## While the site is at /preview

Maintenance mode covers **the application**. Today that means
`fixlisted.com/preview`; after launch, when the app moves to the root, it means
the whole site.

The static holding page at `fixlisted.com` is a separate thing — its own
`index.html` and `.htaccess` in the docroot — and this does not touch it. It
keeps serving, and its waitlist form keeps working, whatever this switch says.

Static files under `/preview/assets/` are served by Apache directly and never
reach `index.php`, so they stay available while the site is down. The
maintenance page does not use them, so it makes no difference either way.

## If something goes wrong

**The site is down and you cannot get to the admin screen.**

```bash
rm ~/fixlisted/storage/maintenance.json
```

That is the whole recovery. No database, no application, no command needed.

**You cannot turn it on from the admin screen.** `storage/` is probably not
writable. `php bin/check.php` says so. Use the command instead.

**It is on and you do not know who did it.** `php bin/maintenance.php status`
names them, and `/admin/activity` has the `maintenance.on` entry with the time
and the message.
