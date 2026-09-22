# Deploying Fix Listed to A2 Hosting

Written for: **SSH access available**, **prototype going on the main domain**.
The domain is pointed at A2 with an A record at the registrar (Route A in Phase 1).

Phases 1–5 are what you can do now. Phase 6 onward is the real application, which
does not exist yet — it's here so the sequence is clear and nothing surprises you later.

> **cPanel menu names move between versions.** Where a step names a cPanel tool, look
> for it in the search box at the top of cPanel rather than hunting the category tiles.

---

## Phase 1 — Point the domain at A2  *(do this first, it's the long pole)*

DNS propagation takes anywhere from 20 minutes to 24 hours, and **SSL cannot be issued
until it finishes**. Start it before anything else.

There are two ways to do it. **Either works** — pick one, don't do both.

### Route A — A record at your registrar  *(this is what we did)*

Leaves DNS control at the registrar and just points the web traffic at A2.

1. Get your server's IP from cPanel → sidebar → **General Information** →
   **Shared IP Address**. It's also in your A2 welcome email. **Use that number** —
   an A record pointing at the wrong IP propagates perfectly cleanly and still fails.
2. At your registrar, add:

   | Type | Host | Value |
   | --- | --- | --- |
   | A | `@` | your A2 shared IP |
   | A *(or CNAME to `@`)* | `www` | your A2 shared IP |

   **Don't skip `www`.** Miss it and the bare domain works while `www.fixlisted.com`
   dies, which you won't notice until a customer does.
3. Make sure the domain is actually set up in cPanel — as the primary domain, or under
   **Domains** → **Create A New Domain**. If the server doesn't know the hostname it
   will serve A2's default page no matter how correct the DNS is.

**What this route changes later:** your registrar stays the source of truth for DNS, not
cPanel. Every future record — MX for email, SPF/DKIM, Stripe or Google verification —
gets added at the registrar. Editing cPanel's zone file will do nothing at all.

### Route B — Point nameservers at A2

Hands all DNS to cPanel. Simpler long-term if you want A2 to handle email too.

1. Get your nameservers from the A2 welcome email or the A2 customer portal. They
   usually look like `ns1.a2hosting.com` through `ns4.a2hosting.com`, but **use the ones
   A2 gave you** — they differ by server.
2. At your registrar, replace the default nameservers with A2's.
3. Records are then managed in cPanel → **Zone Editor**.

### Verifying either route

```bash
dig +short fixlisted.com              # should return your A2 shared IP
dig +short NS fixlisted.com           # Route B: should return A2's nameservers
```

Or use [dnschecker.org](https://dnschecker.org) for a worldwide view — green across the
map means propagation is complete.

Propagation confirms the record is *set*, not that it's *right*. Confirm the server
itself answers for your hostname, which works even before DNS has spread:

```bash
curl -sI --resolve fixlisted.com:80:YOUR.A2.IP.HERE http://fixlisted.com/ | head -5
```

| Result | Meaning |
| --- | --- |
| `HTTP/1.1 200` plus your content | Server and IP both correct |
| A2 default or "coming soon" page | IP correct; domain not set up in cPanel, or nothing uploaded |
| Someone else's site | Wrong IP |
| Timeout or connection refused | Wrong IP, or the domain isn't added in cPanel |

Everything below can be done while DNS propagates, except issuing SSL.

---

## Phase 2 — Connect over SSH

A2 shared hosting uses **port 7822**, not the default 22, and requires key-based auth.

### Generate the key on your machine, not on the server

cPanel's **SSH Access → Generate a Public Key** form creates the private key *on the
server*, so it exists somewhere you don't control and has to be downloaded back to you.
That form also only offers RSA and DSA — DSA is obsolete and should never be used.

Generate locally instead. `ssh-keygen` ships with macOS, Linux and Windows 10/11.

**macOS / Linux:**

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "fixlisted-a2" -f ~/.ssh/fixlisted_a2
```

**Windows PowerShell** — note the full path. PowerShell does *not* expand `~` for
native commands like `ssh-keygen`, and the `.ssh` folder does not exist on a fresh
profile, so the short Unix form fails with
`Saving key "~/.ssh/fixlisted_a2" failed: No such file or directory`:

```powershell
New-Item -ItemType Directory -Force -Path "$env:USERPROFILE\.ssh"
ssh-keygen -t ed25519 -C "fixlisted-a2" -f "$env:USERPROFILE\.ssh\fixlisted_a2"
```

Set a passphrase when prompted. That produces two files — `fixlisted_a2` (private, never
leaves your machine, never goes in git) and `fixlisted_a2.pub` (public, safe to paste
anywhere).

### Install the public key

```bash
cat ~/.ssh/fixlisted_a2.pub
```

```powershell
# Windows — straight to the clipboard
Get-Content "$env:USERPROFILE\.ssh\fixlisted_a2.pub" | Set-Clipboard
```

cPanel → **SSH Access** → **Manage SSH Keys** → **Import Key**. Paste into the **public
key** box, leave the private key box empty, name it `fixlisted_a2`.

**Then click Authorize.** Importing a key does not authorize it, and an unauthorized key
is refused with the same "Permission denied (publickey)" as a wrong key — this is the
single most common reason A2 SSH appears broken.

### Connect

```bash
ssh -p 7822 -i ~/.ssh/fixlisted_a2 YOURCPANELUSER@fixlisted.com
```

```powershell
# Windows
ssh -p 7822 -i "$env:USERPROFILE\.ssh\fixlisted_a2" YOURCPANELUSER@fixlisted.com
```

`YOURCPANELUSER` is the cPanel account name from cPanel's **General Information**
sidebar — not your local Windows or Mac username.

Worth setting up once so every later deploy is just `ssh fixlisted`. Put this in
`~/.ssh/config`, or `%USERPROFILE%\.ssh\config` on Windows (create the file with no
extension):

```
Host fixlisted
    HostName fixlisted.com
    User YOURCPANELUSER
    Port 7822
    IdentityFile ~/.ssh/fixlisted_a2
```

The `~` inside this config file *is* understood by ssh on Windows — it's only
PowerShell's command line that doesn't expand it.

### If it still refuses

- **Check the username first.** `Permission denied` after three correct-looking
  password attempts is more often the wrong username than the wrong password.
  cPanel → **MySQL Databases** shows your account prefix; whatever is before the
  underscore is your cPanel username. cPanel's **General Information** sidebar
  states it outright.
- **You may already be on the server.** cPanel → **Terminal** opens a shell on
  the account with no SSH, no key and no password prompt. Everything in this
  document works there. If SSH is fighting you and you need to deploy now, use
  that and come back to the key later.
- SSH is off by default on some A2 plans — enable it in the **A2 customer portal**
  (not cPanel).
- The key was imported but never **Authorized**.
- Before DNS propagates, connect to the server hostname from your A2 welcome email
  instead: `ssh -p 7822 YOURCPANELUSER@a2ss123.a2hosting.com`.
- `ssh -vvv` prints which key it actually offered, which usually ends the argument.
- Windows only: if ssh refuses the key as "unprotected" or "too open", reset its
  permissions with
  `icacls "$env:USERPROFILE\.ssh\fixlisted_a2" /inheritance:r /grant:r "$env:USERNAME:R"`.

### If you use the cPanel generator anyway

It does work. Set **Key Type: RSA** (never DSA) and **Key Size: 4096**, set a real Key
Password, then Go Back → **Manage SSH Keys** → **Authorize** the public key →
**Download** the private key into `~/.ssh/` and `chmod 600` it.

---

## Phase 3 — Get the code onto the server

Clone **outside** `public_html`. Application code, config and the `.git` directory must
never sit inside the web root — anything in there is fetchable over HTTP.

```bash
cd ~
git clone https://github.com/leedixon/fix-it.git fixlisted
cd fixlisted
git checkout claude/laughing-knuth-mpnpav
```

Your home directory ends up like this:

```
/home/YOURCPANELUSER/
├── fixlisted/          ← the repo. NOT web-accessible.
│   ├── database/
│   ├── dist/
│   └── docs/
└── public_html/        ← the only web-accessible directory
```

---

## Phase 4 — Create the database

**In cPanel → MySQL® Databases:**

1. **Create a database.** Name it `fixlisted`. cPanel prefixes it with your cPanel
   account name, so the real name becomes `<cpaneluser>_fixlisted` — note the full
   name, you'll need it.

   > **This prefix tells you your cPanel username.** It is not necessarily your
   > name, your email, or the local username on the machine you are sitting at,
   > and cPanel truncates long ones. Whatever appears before the underscore here
   > is exactly what goes in front of the `@` when you SSH. Getting it wrong
   > produces `Permission denied` with a correct password, which reads as a
   > password problem and is not one.

2. **Create a user.** Use cPanel's password generator and save the password somewhere
   safe. The username gets the same prefix: `<cpaneluser>_fixapp`.
3. **Add the user to the database** with **ALL PRIVILEGES**. Easy to skip; nothing
   works without it.

**Then import over SSH** — far more reliable than phpMyAdmin, which times out on large
imports and silently truncates:

```bash
cd ~/fixlisted
mysql -u <cpaneluser>_fixapp -p <cpaneluser>_fixlisted < database/schema.sql
mysql -u <cpaneluser>_fixapp -p <cpaneluser>_fixlisted < database/seed.sql
```

### Upgrading a database that already exists

`schema.sql` builds a new database. It will not upgrade one that is already
installed — it has no `IF NOT EXISTS`, so re-running it stops at the first table that
exists. Schema changes ship as numbered files in `database/migrations/` instead:

```bash
mysql -u USER -p DBNAME < database/migrations/001_waitlist.sql
mysql -u USER -p DBNAME < database/migrations/002_geography.sql
```

`php bin/check.php` reports the table count, so it tells you whether you are behind.
A fresh install gets everything from `schema.sql` and needs only `001_waitlist.sql`.

`schema.sql` is intentionally **one-shot** — no `CREATE TABLE IF NOT EXISTS`. Running it
a second time fails with `ERROR 1050: Table 'markets' already exists`, which means the
first run worked, not that anything is wrong. A silent run is a successful one; MySQL
prints nothing on success. `seed.sql` truncates first, so that one is safe to re-run.

**Verify the import before moving on:**

```bash
mysql -u <cpaneluser>_fixapp -p --default-character-set=utf8mb4 --table \
      <cpaneluser>_fixlisted < database/verify.sql
```

Check the output against the expectations listed at the top of `database/verify.sql`.
The ones that matter most: 25 tables exist, job `ATX-1D6G3Z` does **not** appear on the
jobs board, and all four tenant-isolation counts are `0`.

### Skip the seed data for a real launch

`seed.sql` creates demo pros, fake reviews and accounts with the password
`demo-password`. That's for development. For a production database, import
`schema.sql` only, then create your superadmin by hand:

```sql
INSERT INTO markets (slug,name,code,city,state,status,listing_fee_cents,launched_at)
VALUES ('austin','Austin, TX','ATX','Austin','TX','live',1000,NOW());

-- generate the hash first:  php -r 'echo password_hash("YOUR-PASSWORD", PASSWORD_BCRYPT);'
INSERT INTO users (market_id,role,email,password_hash,first_name,last_name,email_verified_at)
VALUES (NULL,'superadmin','you@fixlisted.com','$2y$12$PASTE_THE_HASH_HERE','Lee','Dixon',NOW());
```

The trades list is in `seed.sql` and is not demo data — copy that one `INSERT INTO
trades` block across regardless.

---

## Phase 5 — Put the prototype live

> ### Find the document root first — do not assume `~/public_html`
>
> `~/public_html` is the document root of the account's **primary domain**. On this
> account the primary domain is `leedixon.com`, and fixlisted.com is one of 38 **addon
> domains**, so it has its own separate directory. Copying into `~/public_html` would
> overwrite the primary domain's live site.
>
> Get the real path from cPanel → **Domains** → find `fixlisted.com` → read its
> **Document Root** column. It will be something like
> `/home/leedixon/fixlisted.com` or `/home/leedixon/public_html/fixlisted.com`.
>
> Or from the shell:
>
> ```bash
> grep -A2 -i "fixlisted.com" ~/.cpanel/userdata/main 2>/dev/null
> ls -d ~/fixlisted.com ~/public_html/fixlisted.com 2>/dev/null
> ```
>
> Set it once and reuse it:
>
> ```bash
> DOCROOT=/home/leedixon/fixlisted.com     # replace with the real path
> ```

```bash
cd ~/fixlisted
git pull
cp -r dist/. "$DOCROOT"/
ls -la "$DOCROOT"/             # expect index.html, robots.txt, .htaccess
```

`dist/` is generated from `prototype/index.html` by `python3 prototype/build.py`. Edit
the prototype, re-run the build, commit, then `git pull && cp -r dist/. "$DOCROOT"/` on the server.

### Set the PHP version now, while you're here

cPanel → **MultiPHP Manager** (or **Select PHP Version**): set fixlisted.com to
**PHP 8.2 or 8.3**. Then confirm these extensions are enabled: `pdo_mysql`, `mbstring`,
`curl`, `openssl`, `json`, and `gd` or `imagick` for photo uploads. The prototype
doesn't need any of them; the application needs all of them, and finding out now beats
finding out mid-deploy.

### Issue SSL — once DNS has propagated

cPanel → **SSL/TLS Status** → select fixlisted.com → **Run AutoSSL**. Wait for the
green padlock. The HTTPS redirect is already in `dist/.htaccess` and starts working the
moment the certificate lands.

### One thing to know about putting the prototype on the main domain

You chose the main domain over a staging subdomain, which is fine — but the prototype
contains invented handymen, invented licence numbers and invented reviews. So the build
ships a `noindex` meta tag and a `Disallow: /` robots.txt, and Google won't index it.
The domain is brand new with nothing to lose, and it's two lines to undo:

- delete the `robots` and `googlebot` meta tags from `prototype/build.py`
- replace `dist/robots.txt` with a real one

If you'd rather be indexed immediately, say so and I'll strip them — but I'd wait until
the content is real.

---

## Phase 6 — The application  *(not built yet)*

When the PHP app ships, three things change.

**1. The web root moves.** Only `public/` should be web-accessible:

```
~/fixlisted/
├── app/        ← controllers, models, views — NOT web-accessible
├── config/     ← config.php with your live credentials — NOT web-accessible
├── database/
├── storage/    ← uploads and logs — NOT web-accessible
└── public/     ← index.php, assets — THIS is the web root
```

Preferred: cPanel → **Domains** → fixlisted.com → set the document root to
`/home/leedixon/fixlisted/public`. On an addon domain this is usually editable
directly, which is easier than it is on a primary domain.

If cPanel won't let you change it, symlink the addon domain's own directory —
**never `~/public_html`**, which belongs to the primary domain:

```bash
mv "$DOCROOT" "$DOCROOT.bak"
ln -s ~/fixlisted/public "$DOCROOT"
```

**2. Config carries live secrets.** Don't hand-edit it — run the builder:

```bash
cd ~/fixlisted
php bin/configure.php
```

It asks for the database name, user and password, the site URL and the email
addresses, then writes `config/config.php`, generates the app key, sets mode 600, and
tests the database connection before it finishes. The password is not echoed as you
type and never reaches your shell history.

Editing the file by hand in a terminal editor is where this step goes wrong: a paste
lands in the wrong place, a line gets clipped, and you get a parse error or a silently
missing setting. `config/config.example.php` documents what each value is for; the
builder is how the real file gets written. Re-running it backs up the existing file
first.

Then confirm it, rather than finding out from a blank page in a browser:

```bash
php bin/check.php
```

It checks the config values, the file permissions, the database connection, whether the
schema imported, that utf8mb4 survives a round trip, the PHP version and extensions, and
that the storage directories are writable. Everything must read `OK` before you point a
domain at the application.

It's already in `.gitignore`, so a `git pull` will never overwrite it and your Stripe
keys will never reach GitHub. The DSN **must** include `charset=utf8mb4` — without it
PHP negotiates latin1 and mangles every em-dash and accented name on the way out, even
though the columns are correct.

**3. Cron jobs.** cPanel → **Cron Jobs**. Find your PHP binary with `which php`
(usually `/usr/local/bin/php` on A2) and add:

| Schedule | Command |
| --- | --- |
| `* * * * *` | `/usr/local/bin/php ~/fixlisted/bin/notifications.php` |
| `0 * * * *` | `/usr/local/bin/php ~/fixlisted/bin/hourly.php` |
| `15 3 * * *` | `/usr/local/bin/php ~/fixlisted/bin/daily.php` |

What each one does is in [architecture.md](architecture.md#shared-hosting-constraints).

**4. Stripe.** Needs a working HTTPS certificate first, because Stripe refuses to send
webhooks to plain HTTP. Point the webhook at `https://fixlisted.com/webhooks/stripe`,
copy the signing secret into `config/config.php`, and test with Stripe's CLI in test
mode before touching live keys.

---

## Routine deploys, after all that

```bash
ssh -p 7822 YOURCPANELUSER@fixlisted.com
cd ~/fixlisted && git pull
```

That's it, once the document root points at `public/`. Schema changes get their own
migration file; run it the same way you ran the import.

### A deploy that changes the schema

Put the site down first. A migration that runs while visitors are mid-request
is how half-applied data happens.

```bash
php bin/maintenance.php on --message="Back in ten minutes."
git pull
php bin/migrate.php
php bin/check.php            # everything should pass
                             # then click through the site — you still see it
php bin/maintenance.php off
```

You keep full access to the site while it is down, so the click-through is a
real check rather than a hope. If the migration goes wrong, the site stays
down until you say otherwise, and visitors get a 503 rather than a broken page
— see [maintenance.md](maintenance.md).

---

## If something breaks

| Symptom | Cause |
| --- | --- |
| 500 error, blank page | PHP version too old, or a missing extension. Check cPanel → **Errors**. |
| Every page says "Back shortly" | Maintenance mode is on. `php bin/maintenance.php off`, or delete `storage/maintenance.json`. |
| "Access denied for user" | The DB user was created but never **added to the database** with ALL PRIVILEGES. |
| Em-dashes show as `?` or `â€"` | The connection isn't `utf8mb4`. Check the DSN, not the tables. |
| AutoSSL fails | DNS hasn't propagated. `dig +short NS fixlisted.com` and wait. |
| Site shows A2's default page | The document root still points at the old directory. |
| Changes appear on the wrong domain | You wrote into `~/public_html`, which is the **primary** domain (leedixon.com), not fixlisted.com's addon directory. |
| `.htaccess` rules ignored | `AllowOverride` is off for that directory — open a ticket with A2. |
| `ERROR 1050: Table 'markets' already exists` | `schema.sql` already ran successfully. Skip to `seed.sql`. |
