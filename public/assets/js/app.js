/*
 * Home screen install, and nothing else.
 *
 * The second of the two scripts this site ships. Both are enhancements in
 * the strict sense: block them and every page, form and link still works.
 * A home screen icon is a convenience, and a convenience is the one kind of
 * thing that may depend on JavaScript.
 */
(function () {
  'use strict';

  /* ---- the service worker ------------------------------------------- */

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {
        // Registration failing is not worth a console error on a site
        // that works identically without it.
      });
    });
  }

  /* ---- iOS: the hint Safari will not give them ----------------------- */

  var nav = window.navigator;

  // Safari on iOS or iPadOS. iPadOS reports as a Mac, so touch points are
  // what separate an iPad from a desktop; and Chrome on iOS uses WebKit but
  // cannot add to the home screen at all, so it is excluded by name.
  var isIOS = (/iPad|iPhone|iPod/.test(nav.userAgent) ||
               (nav.platform === 'MacIntel' && nav.maxTouchPoints > 1));
  var isSafari = /Safari/.test(nav.userAgent) &&
                 !/CriOS|FxiOS|EdgiOS|OPiOS/.test(nav.userAgent);

  // navigator.standalone is the iOS-only way of asking "am I already
  // installed". No point telling somebody to install an app they opened
  // from their home screen.
  var alreadyInstalled = nav.standalone === true ||
                         window.matchMedia('(display-mode: standalone)').matches;

  var KEY = 'fl-a2hs-dismissed';

  function dismissed() {
    try {
      return window.localStorage.getItem(KEY) === '1';
    } catch (e) {
      // Private browsing throws on localStorage. Treat it as dismissed —
      // better to never show a hint than to show it on every page load with
      // no way to make it stop.
      return true;
    }
  }

  function remember() {
    try {
      window.localStorage.setItem(KEY, '1');
    } catch (e) { /* nothing to do */ }
  }

  if (!isIOS || !isSafari || alreadyInstalled || dismissed()) {
    return;
  }

  // Built rather than hidden in the markup, so a visitor who is not on iOS
  // never downloads it and a crawler never reads it.
  var bar = document.createElement('div');
  bar.className = 'a2hs';
  bar.setAttribute('role', 'note');

  var text = document.createElement('p');
  text.textContent = 'Add Fix Listed to your home screen: tap Share, then “Add to Home Screen”.';

  var close = document.createElement('button');
  close.type = 'button';
  close.className = 'a2hs-x';
  close.setAttribute('aria-label', 'Dismiss');
  close.textContent = '×';
  close.addEventListener('click', function () {
    bar.remove();
    remember();
  });

  bar.appendChild(text);
  bar.appendChild(close);

  // After the first paint, and only once the page has settled. A banner that
  // arrives while somebody is reading is a banner they close without seeing.
  window.setTimeout(function () {
    document.body.appendChild(bar);
  }, 2500);
})();
