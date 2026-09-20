<?php
/**
 * Inline SVG icons.
 *
 * Inline rather than a sprite file or an icon font: there are a dozen of them,
 * they inherit currentColor, and they cost no extra request on a shared host
 * where every round trip is slow.
 */
function icon(string $name, int $size = 16): string
{
    $paths = [
        'check'   => '<path d="M20 6L9 17l-5-5"/>',
        'shield'  => '<path d="M12 3l8 3v6c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'lock'    => '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 118 0v3"/>',
        'bolt'    => '<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/>',
        'pin'     => '<path d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/>',
        'arrow'   => '<path d="M5 12h14"/><path d="M13 5l7 7-7 7"/>',
        'back'    => '<path d="M19 12H5"/><path d="M11 19l-7-7 7-7"/>',
        'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.6v.9"/>',
        'alert'   => '<path d="M12 4l9 16H3z"/><path d="M12 10v4"/><path d="M12 17.2v.6"/>',
        'tools'   => '<path d="M14.5 5.5a3.5 3.5 0 004.8 4.5l-8 8a2.3 2.3 0 11-3.3-3.3l8-8a3.5 3.5 0 01-1.5-1.2z"/>',
        'user'    => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c1.4-3.6 4-5.4 7-5.4s5.6 1.8 7 5.4"/>',
        'phone'   => '<path d="M5 4h4l2 5-2.4 1.6a12 12 0 005.4 5.4L15.6 14l5 2v4a1 1 0 01-1.1 1A16.5 16.5 0 014 5.1 1 1 0 015 4z"/>',
        'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.4 6.2L12 13l8.6-6.8"/>',
        'star'    => '<path d="M12 3.6l2.6 5.4 5.9.8-4.3 4.1 1.1 5.9L12 17l-5.3 2.8 1.1-5.9L3.5 9.8l5.9-.8z"/>',

        // The trades. trades.icon holds these names, so a new trade gets an
        // icon by adding one row here and one in the table — and an unknown
        // name falls through to the generic tool rather than rendering blank.
        'pipe'      => '<path d="M4 8h6a3 3 0 013 3v2a3 3 0 003 3h4"/><path d="M4 5.5v5"/><path d="M20 13.5v5"/>',
        'saw'       => '<path d="M3 15l10-10 4 4L7 19z"/><path d="M15.5 4.5l4 4"/><path d="M6 12l1.6 1.6M9 9l1.6 1.6M12 6l1.6 1.6"/>',
        'roller'    => '<rect x="3" y="5" width="12" height="5" rx="1.4"/><path d="M15 7.5h3.5a1.5 1.5 0 011.5 1.5v2a1.5 1.5 0 01-1.5 1.5H12"/><rect x="10" y="14" width="4" height="7" rx="1.2"/>',
        'appliance' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M5 9h14"/><circle cx="12" cy="15" r="3"/>',
        'fan'       => '<circle cx="12" cy="12" r="2"/><path d="M12 10c0-4 1-6 3-6s2.4 3 0 5"/><path d="M14 12c4 0 6 1 6 3s-3 2.4-5 0"/><path d="M12 14c0 4-1 6-3 6s-2.4-3 0-5"/><path d="M10 12c-4 0-6-1-6-3s3-2.4 5 0"/>',
        'roof'      => '<path d="M2.5 12L12 4l9.5 8"/><path d="M5 12v7h14v-7"/><path d="M2.5 19h19"/>',
        'fence'     => '<path d="M5 20V8l2.5-3L10 8v12"/><path d="M14 20V8l2.5-3L19 8v12"/><path d="M3 11h18"/><path d="M3 15h18"/>',
        'leaf'      => '<path d="M20 4C10 4 4 9 4 16c0 2 .6 3.3 1.4 4C13 20 19 14 20 4z"/><path d="M5.4 20C9 15 13 12 18 10"/>',
    ];

    $body = $paths[$name] ?? $paths['info'];

    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true" focusable="false">' . $body . '</svg>';
}
