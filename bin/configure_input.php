<?php
declare(strict_types=1);

/**
 * Input cleaning shared by bin/configure.php and bin/smoke.php.
 *
 * Its own file so the tests exercise the code that actually runs, rather
 * than a copy of it that can drift.
 */

/**
 * Strips what a terminal adds to a paste.
 *
 * A terminal with bracketed paste enabled — which includes cPanel's own
 * Terminal, and most modern emulators — wraps pasted text in the escape
 * sequences \e[200~ and \e[201~. They are invisible, and at a prompt with the
 * echo turned off they are invisible *and* unguessable: a perfectly good
 * Stripe key fails its format check and the only visible fact is that the key
 * was rejected.
 *
 * Every control character goes, not just those two. Nothing this script asks
 * for may legitimately contain one, and a password silently stored with a
 * stray \r in it fails to authenticate with no clue as to why.
 */
function cleanInput(string $value): string
{
    $value = preg_replace('/\e\[20[01]~/', '', $value) ?? $value;
    $value = preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $value) ?? $value;
    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? $value;

    return trim($value);
}

/** A description of a rejected value that names the problem without printing it. */
function describeInput(string $raw, string $cleaned): string
{
    $bits = [strlen($cleaned) . ' characters'];
    if ($cleaned !== '') {
        // The first three are the type marker — rk_, sk_, pk_, whs — which is
        // the whole diagnosis and none of the secret.
        $bits[] = 'starting "' . substr($cleaned, 0, 3) . '"';
    }
    if ($raw !== $cleaned && strlen($raw) !== strlen($cleaned) + 1) {
        $bits[] = 'with terminal escape characters stripped';
    }
    return implode(', ', $bits);
}
