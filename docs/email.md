# Email

Fix Listed sends transactional mail — signup confirmations, and later quote
alerts and payment receipts. These must reach an inbox, not a spam folder, so
the sending domain has to be properly authenticated.

## Why not just use the web server

PHP's `mail()` hands the message to the hosting account's mail system, which
sends it from the web server. That server is not authorised to send as your
domain. Modern inbox providers check three things:

| Check | What it asks |
| --- | --- |
| **SPF** | Is this server allowed to send for this domain? |
| **DKIM** | Is the message cryptographically signed by the domain? |
| **DMARC** | If SPF and DKIM fail, what should I do with it? |

A domain publishing a DMARC policy of `reject` gets exactly that: the message
is discarded. **No bounce, nothing in spam, and nothing visible from the
sending end** — the code appears to work and the mail simply never exists. This
is not a theoretical risk; it is what happened here, and it cost most of a day
to identify because every layer reported success.

Sending through a provider that has verified your domain makes the message
genuinely authentic rather than claiming to be.

## Setting up a provider

Any SMTP provider works — `app/Core/Smtp.php` is provider-agnostic. Resend,
Brevo, MailerSend and Postmark all have free tiers well beyond what a launching
directory sends.

### 1. Create an account and add the domain

Add **fixlisted.com** as a sending domain. The provider gives you two or three
DNS records to prove you own it — typically a TXT record for SPF and one or two
CNAME records for DKIM.

### 2. Add those records at the registrar

**fixlisted.com's DNS lives at the registrar, not in cPanel** — the domain is
pointed at A2 with an A record, so cPanel's Zone Editor has no effect on it.
Add the provider's records wherever you manage the domain (GoDaddy, Namecheap,
Cloudflare).

Then wait for the provider to show the domain as **Verified**. Usually minutes.

### 3. Get the SMTP credentials

From the provider's dashboard. Note the username is not always your email
address — Resend uses the literal string `resend` as the username, with the API
key as the password. Getting that wrong produces an authentication failure that
reads like a wrong password.

### 4. Configure and test

```bash
cd ~/fixlisted
php bin/configure.php
```

It asks for the provider, the credentials, and three addresses, then **sends a
test message and tells you whether it worked** before you find out from a
failed signup.

## The three addresses, and why they differ

| Setting | Example | Purpose |
| --- | --- | --- |
| `from_address` | `hello@fixlisted.com` | What recipients see. Must be on the **verified** domain. |
| `reply_to` | `lee@leedixon.com` | Where replies land. Must be an inbox someone reads. |
| `alert_to` | `lee@leedixon.com` | Where signup alerts go. |

They are separate because the brand sends from one place and a person reads
from another. The tradesperson confirmation email's entire call to action is
*"reply to this email"* — if `reply_to` points somewhere unmonitored, the most
valuable signal the site produces is lost silently.

Sending from a domain the provider has **not** verified fails the same way as
using the web server, so `from_address` must be on the verified domain.

## Diagnosing a missing email

```bash
php bin/mailtest.php you@example.com
```

It separates the three independent failures: the form never saved, the message
never sent, or the message sent and was filtered. For mail sent through the
hosting account, cPanel → **Email** → **Track Delivery** shows what actually
happened to each message — including `:blackhole:`, which means the address is
an alias configured to discard, not a mailbox.

## Known traps on this install

- **`lee@leedixon.com` is Google Workspace**, not a cPanel mailbox. cPanel
  initially treated leedixon.com as a local domain and delivered mail to it
  internally, where no such mailbox existed, so it was discarded. Fixed by
  setting cPanel → **Email Routing** → leedixon.com → **Remote Mail Exchanger**.
- **Google App Passwords are unavailable** on this Workspace: 2-Step
  Verification is disabled by admin policy, and App Passwords require it. That
  route is closed unless 2SV is enabled organisation-wide.
- **Volume limits.** Gmail caps around 2,000 messages a day and gives no
  delivery reporting. A dedicated provider gives both headroom and logs.
