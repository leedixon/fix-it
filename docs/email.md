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

## Why not SMTP either, on this host

**A2 intercepts outbound SMTP.** Connecting to `smtp.resend.com:587` from this
server does not reach Resend: it reaches A2's own mail filter, which answers
the `STARTTLS` upgrade with A2's certificate.

```
STARTTLS negotiation failed: Peer certificate CN=`az1-ts106.a2hosting.com'
did not match expected CN=`smtp.resend.com'
```

That message is conclusive. A proxy has no way to present someone else's
certificate, so the handshake cannot be made to succeed — not with a different
password, not with a fresh CA bundle, not on port 465. It is also consistent
with what Track Delivery showed earlier: outbound mail routed through
`send_via_mailchannels`, A2's own relay.

So this install sends over **Resend's HTTPS API on port 443**, which no host
proxies — doing so would break every outbound HTTPS request the server makes.
Same provider, same account, same API key, a route the host does not sit in the
middle of. That is `app/Core/MailApi.php`, and `'transport' => 'api'`.

`app/Core/Smtp.php` stays in the tree and still works. It is the right
transport on a host that leaves port 587 alone, and this one may not always
intercept it.

## Setting up a provider

Resend is what this install uses, because of the API route above. Brevo,
MailerSend and Postmark are all fine over SMTP on a host that permits it —
`app/Core/Smtp.php` is provider-agnostic. All have free tiers well beyond what
a launching directory sends.

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

### 3. Get the API key

Resend → **API Keys** → create one with **Sending access**. It starts with
`re_`. It is shown once; if you lose it, make a new one rather than hunting for
it.

Over SMTP the same key is the password, and the username is the literal string
`resend` — not your email address. Getting that wrong produces an
authentication failure that reads like a wrong password.

### 4. Configure and test

```bash
cd ~/fixlisted
php bin/configure.php
```

Choose **1 — Resend, over its HTTPS API** at the transport prompt. It then asks
for the key and the three addresses, and **sends a test message and tells you
whether it worked** before you find out from a failed signup.

Nothing is typed into a config file by hand, and the key is never echoed to the
screen or written to shell history.

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

- **Outbound SMTP is proxied** (above). Port 587 and 465 both terminate at
  A2's filter, so any SMTP provider fails the TLS handshake here. Use the API
  transport. `bin/configure.php` and `Smtp::startTls()` both name this
  specifically when they see A2's certificate, so the failure does not read as
  a password problem.
- **`lee@leedixon.com` is Google Workspace**, not a cPanel mailbox. cPanel
  initially treated leedixon.com as a local domain and delivered mail to it
  internally, where no such mailbox existed, so it was discarded. Fixed by
  setting cPanel → **Email Routing** → leedixon.com → **Remote Mail Exchanger**.
- **Google App Passwords are unavailable** on this Workspace: 2-Step
  Verification is disabled by admin policy, and App Passwords require it. That
  route is closed unless 2SV is enabled organisation-wide.
- **Volume limits.** Gmail caps around 2,000 messages a day and gives no
  delivery reporting. A dedicated provider gives both headroom and logs.
