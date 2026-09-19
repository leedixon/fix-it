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

Generate locally instead. `ssh-keygen` ships with macOS, Linux and Windows 10/11:

```bash
ssh-keygen -t ed25519 -C "fixlisted-a2" -f ~/.ssh/fixlisted_a2
```

Set a passphrase when prompted. That produces two files — `fixlisted_a2` (private, never
leaves your machine, never goes in git) and `fixlisted_a2.pub` (public, safe to paste
anywhere).

### Install the public key

```bash
cat ~/.ssh/fixlisted_a2.pub
# Windows PowerShell:  type $env:USERPROFILE\.ssh\fixlisted_a2.pub
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

Worth setting up once, in `~/.ssh/config`, so every later deploy is just `ssh fixlisted`:

```
Host fixlisted
    HostName fixlisted.com
    User YOURCPANELUSER
    Port 7822
    IdentityFile ~/.ssh/fixlisted_a2
```

### If it still refuses

- SSH is off by default on some A2 plans — enable it in the **A2 customer portal**
  (not cPanel).
- The key was imported but never **Authorized**.
- Before DNS propagates, connect to the server hostname from your A2 welcome email
  instead: `ssh -p 7822 YOURCPANELUSER@a2ss123.a2hosting.com`.
- `ssh -vvv` prints which key it actually offered, which usually ends the argument.

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

1. **Create a database.** Name it `fixlisted`. cPanel prefixes it with your account
   name, so the real name becomes something like `leedixo_fixlisted` — note the full
   name, you'll need it.
2. **Create a user.** Use cPanel's password generator and save the password somewhere
   safe. The username gets the same prefix: `leedixo_fixapp`.
3. **Add the user to the database** with **ALL PRIVILEGES**. Easy to skip; nothing
   works without it.

**Then import over SSH** — far more reliable than phpMyAdmin, which times out on large
imports and silently truncates:

```bash
cd ~/fixlisted
mysql -u leedixo_fixapp -p leedixo_fixlisted < database/schema.sql
mysql -u leedixo_fixapp -p leedixo_fixlisted < database/seed.sql
```

**Verify the import before moving on:**

```bash
mysql -u leedixo_fixapp -p --default-character-set=utf8mb4 --table \
      leedixo_fixlisted < database/verify.sql
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

```bash
cd ~/fixlisted
git pull
cp -r dist/. ~/public_html/
ls -la ~/public_html/          # expect index.html, robots.txt, .htaccess
```

`dist/` is generated from `prototype/index.html` by `python3 prototype/build.py`. Edit
the prototype, re-run the build, commit, then `git pull && cp -r dist/. ~/public_html/`
on the server.

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
`/home/YOURCPANELUSER/fixlisted/public`. If your cPanel won't allow it on the primary
domain, symlink instead:

```bash
mv ~/public_html ~/public_html.bak
ln -s ~/fixlisted/public ~/public_html
```

**2. Config carries live secrets.** Copy the example, fill it in, lock it down:

```bash
cp config/config.example.php config/config.php
chmod 600 config/config.php
```

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

---

## If something breaks

| Symptom | Cause |
| --- | --- |
| 500 error, blank page | PHP version too old, or a missing extension. Check cPanel → **Errors**. |
| "Access denied for user" | The DB user was created but never **added to the database** with ALL PRIVILEGES. |
| Em-dashes show as `?` or `â€"` | The connection isn't `utf8mb4`. Check the DSN, not the tables. |
| AutoSSL fails | DNS hasn't propagated. `dig +short NS fixlisted.com` and wait. |
| Site shows A2's default page | The document root still points at `public_html`. |
| `.htaccess` rules ignored | `AllowOverride` is off for that directory — open a ticket with A2. |
