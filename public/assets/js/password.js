/*
 * Show/hide on every password field.
 *
 * This is the only JavaScript on the site, and it is strictly an enhancement:
 * it finds password inputs that already work and adds a button to them. With
 * scripting off, or if this file fails to load, every form behaves exactly as
 * it did before — which is why the button is created here rather than sitting
 * in the markup doing nothing.
 *
 * It binds to every input[type=password] on the page rather than to named
 * fields, so a password box added to a form later gets this for free instead
 * of being the one that quietly missed out.
 */
(function () {
  'use strict';

  var EYE = '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>'
          + '<circle cx="12" cy="12" r="3"/>';
  var EYE_OFF = '<path d="M3 3l18 18"/>'
              + '<path d="M10.6 6.2A9.8 9.8 0 0112 6c6.4 0 10 6 10 6a17 17 0 01-3.4 4"/>'
              + '<path d="M6.6 6.7C3.9 8.3 2 12 2 12s3.6 7 10 7a9.9 9.9 0 004.3-.9"/>'
              + '<path d="M9.9 9.9a3 3 0 004.2 4.2"/>';

  function svg(body) {
    return '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" '
         + 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
         + 'stroke-linejoin="round" aria-hidden="true" focusable="false">' + body + '</svg>';
  }

  function enhance(input) {
    if (input.dataset.pwToggle === 'on') {
      return;
    }
    input.dataset.pwToggle = 'on';

    var wrap = document.createElement('div');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var button = document.createElement('button');
    // Explicitly not a submit button. Left to the default, a click on this
    // would send the form — with the password half typed.
    button.type = 'button';
    button.className = 'pw-eye';
    button.innerHTML = svg(EYE);
    button.setAttribute('aria-label', 'Show password');
    button.setAttribute('aria-pressed', 'false');
    // A password manager offering to fill the toggle is noise at best.
    button.setAttribute('tabindex', '0');
    wrap.appendChild(button);

    function setShown(shown) {
      input.type = shown ? 'text' : 'password';
      button.innerHTML = svg(shown ? EYE_OFF : EYE);
      button.setAttribute('aria-label', shown ? 'Hide password' : 'Show password');
      button.setAttribute('aria-pressed', shown ? 'true' : 'false');
    }

    button.addEventListener('click', function () {
      var shown = input.type === 'text';
      setShown(!shown);
      // Focus goes back to where they were typing, at the end of what they
      // have typed — otherwise revealing the password costs them their place.
      input.focus();
      try {
        var end = input.value.length;
        input.setSelectionRange(end, end);
      } catch (e) {
        // Some browsers refuse setSelectionRange on a field mid-type-change.
        // Losing the caret position is not worth throwing over.
      }
    });

    // Never leave a password on screen after the form has gone. A validation
    // error re-renders the page anyway; this covers the moment in between,
    // and the back button.
    if (input.form) {
      input.form.addEventListener('submit', function () {
        setShown(false);
      });
    }
  }

  function run() {
    var inputs = document.querySelectorAll('input[type="password"]');
    for (var i = 0; i < inputs.length; i++) {
      enhance(inputs[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
