/*
 * Fix Listed service worker.
 *
 * This file is the most dangerous thing on the site. A service worker that
 * caches the wrong thing serves a stale page to a returning visitor for as
 * long as the cache lives, and there is nothing they can do about it short
 * of clearing site data — which nobody knows how to do and nobody should
 * have to. Everything below is shaped by that.
 *
 * The rules it follows:
 *
 *  1. HTML is NEVER served from the cache. Every page is fetched from the
 *     network. If the network is gone, the visitor gets an offline page —
 *     never a copy of something they read last week, which would show an old
 *     price, an old job, or a listing that has since been suspended.
 *
 *  2. Nothing private is touched. /admin, /my, /review and /img are passed
 *     straight through. A cached admin page on a shared phone is somebody
 *     else's data sitting in a browser, and a cached photo is one moderation
 *     cannot withdraw.
 *
 *  3. Only same-origin GET requests are considered at all. A POST is never
 *     intercepted, so a payment, a quote or a review cannot be replayed or
 *     swallowed by anything in here.
 *
 *  4. The cache name carries a version. Changing it retires everything from
 *     the previous one on the next activate, which is the kill switch: bump
 *     VERSION and the old cache is gone from every device that visits.
 */

const VERSION = 'fixlisted-v1';
const OFFLINE_URL = '/offline';

/*
 * Just the offline page.
 *
 * The stylesheet was in here too, and it was dead weight: every page asks
 * for it as /assets/css/site.css?v=<mtime>, and a cache entry stored without
 * that query string can never match one. It sat in the cache being a second
 * copy of a file the site had already cached properly through the asset
 * path below.
 *
 * Short on purpose for a second reason. Everything listed here must be
 * fetched before the worker will install, so a long list is a worker that
 * fails to install on a bad connection — and a site that quietly never gets
 * one at all.
 */
const PRECACHE = [OFFLINE_URL];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION)
      .then((cache) => cache.addAll(PRECACHE))
      // A failed precache must not leave a half-installed worker behind.
      .catch(() => undefined)
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(
        names.filter((n) => n !== VERSION).map((n) => caches.delete(n)),
      ))
      .then(() => self.clients.claim()),
  );
});

/**
 * Anything the worker must not come near.
 *
 * Private areas, the payment webhook, and served images — whose whole point
 * is that a moderator can stop them being reachable.
 */
function isOffLimits(url) {
  return /^\/(admin|my|review|img|webhooks|sign-in|set-password|forgot-password)(\/|$)/.test(url.pathname);
}

/** A hashed or versioned static asset: safe to keep, cheap to re-fetch. */
function isStaticAsset(url) {
  return url.pathname.startsWith('/assets/');
}

self.addEventListener('fetch', (event) => {
  const req = event.request;

  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);
  if (url.origin !== self.location.origin || isOffLimits(url)) {
    return;
  }

  // --- pages -------------------------------------------------------------
  //
  // Network first, always. The cached copy is never a page — it is the
  // offline notice, and only when the network genuinely failed.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL).then(
        (r) => r || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } }),
      )),
    );
    return;
  }

  // --- static assets -----------------------------------------------------
  //
  // Cache first for speed, then revalidate in the background so a change
  // ships on the visit after the one that noticed it. Safe here because
  // nothing under /assets is personal and a stale stylesheet for one page
  // load is not a stale price.
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(req).then((hit) => {
        const live = fetch(req).then((res) => {
          if (res && res.status === 200 && res.type === 'basic') {
            const copy = res.clone();
            caches.open(VERSION).then((c) => c.put(req, copy));
          }
          return res;
        }).catch(() => hit);

        return hit || live;
      }),
    );
  }

  // Everything else falls through to the network untouched.
});
