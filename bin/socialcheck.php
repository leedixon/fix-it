<?php
declare(strict_types=1);

/**
 * Checks a URL the way a link-preview crawler does.
 *
 *   php bin/socialcheck.php https://fixlisted.com/
 *
 * Facebook's debugger says very little when something is wrong, and "no image"
 * has at least five different causes that look identical from the outside:
 * robots.txt, a meta tag, a 403 on the image, a relative og:image, or an
 * index.html that was never updated. This walks them in order and names the
 * one that is actually biting.
 *
 * It sends facebookexternalhit's user agent, so what it sees is what Facebook
 * sees — including anything the host does differently for bots.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

const UA_FACEBOOK = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
const UA_BROWSER  = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

$url = $argv[1] ?? 'https://fixlisted.com/';
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    fwrite(STDERR, "Usage: php bin/socialcheck.php https://fixlisted.com/\n");
    exit(1);
}

$problems = 0;
$say = static function (bool $ok, string $label, string $detail = '') use (&$problems): void {
    if (!$ok) { $problems++; }
    printf(" %-5s %s%s\n", $ok ? 'OK' : 'FAIL', $label, $detail !== '' ? '  — ' . $detail : '');
};

/** @return array{status:int,body:string,type:string,len:int} */
function fetch(string $url, string $agent = UA_FACEBOOK): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => $agent,
    ]);
    $body = curl_exec($ch);
    $out = [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'body'   => $body === false ? '' : (string) $body,
        'type'   => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        'len'    => (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
        'error'  => curl_error($ch),
        'final'  => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
    ];
    curl_close($ch);
    return $out;
}

/**
 * Is $agent allowed to fetch $path?
 *
 * Simplified but faithful on the point that matters: a crawler named in its
 * own group follows that group and ignores 'User-agent: *' entirely. Getting
 * that backwards is why a blanket Disallow silently kills link previews.
 */
function robotsAllows(string $robots, string $agent, string $path = '/'): ?bool
{
    $groups = [];
    $current = [];
    foreach (preg_split('/\R/', $robots) ?: [] as $line) {
        $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
        if ($line === '') { continue; }
        if (!str_contains($line, ':')) { continue; }
        [$field, $value] = array_map('trim', explode(':', $line, 2));
        $field = strtolower($field);

        if ($field === 'user-agent') {
            $current = [];
            $groups[strtolower($value)] ??= [];
            $current[] = strtolower($value);
            continue;
        }
        foreach ($current as $name) {
            $groups[$name][] = [$field, $value];
        }
    }

    $key = strtolower($agent);
    $rules = $groups[$key] ?? $groups['*'] ?? null;
    if ($rules === null) { return null; }

    // Longest matching rule wins; Allow beats Disallow on a tie.
    $best = null;
    foreach ($rules as [$field, $value]) {
        if ($field !== 'allow' && $field !== 'disallow') { continue; }
        if ($value === '') { continue; }
        if (!str_starts_with($path, rtrim($value, '*'))) { continue; }
        $len = strlen($value);
        if ($best === null || $len > $best[1] || ($len === $best[1] && $field === 'allow')) {
            $best = [$field, $len];
        }
    }
    return $best === null ? true : $best[0] === 'allow';
}

$parts = parse_url($url);
$origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
$path   = $parts['path'] ?? '/';

echo "\nChecking {$url}\n";
echo "as facebookexternalhit\n\n";

// --- 1. robots.txt ----------------------------------------------------------
echo "1. robots.txt\n";
$robots = fetch($origin . '/robots.txt');
if ($robots['status'] === 404) {
    $say(true, 'no robots.txt', 'nothing is blocked');
} elseif ($robots['status'] !== 200) {
    $say(false, 'robots.txt returned ' . $robots['status']);
} else {
    $named = str_contains(strtolower($robots['body']), 'facebookexternalhit');
    $say($named, 'robots.txt names facebookexternalhit',
        $named ? '' : 'this is the OLD file — the new one was not copied to the document root');

    $fb = robotsAllows($robots['body'], 'facebookexternalhit', $path);
    $say($fb !== false, 'facebookexternalhit may fetch ' . $path,
        $fb === false
            ? "blocked by robots.txt — Facebook will not read the page at all, so the og: tags never load"
            : '');
    foreach (['Twitterbot', 'LinkedInBot', 'Slackbot'] as $bot) {
        $allowed = robotsAllows($robots['body'], $bot, $path);
        $say($allowed !== false, $bot . ' may fetch ' . $path, $allowed === false ? 'blocked' : '');
    }
}

// --- 2. the page, as Facebook and as a browser ------------------------------
//
// Fetched twice on purpose. Facebook's debugger reports a robots.txt block and
// a server-side refusal identically, as "Response Code 403" with a boilerplate
// line suggesting robots.txt — so the only way to tell them apart from outside
// is to ask the server the same question twice and change only the user agent.
//
//   both 200          nothing is refusing the crawler
//   both 403          the server refuses everyone (permissions, or an .htaccess)
//   browser 200, FB 403   the host is blocking the crawler by user agent,
//                         which on a shared cPanel host is mod_security or
//                         Imunify360, and no robots.txt edit will touch it
echo "\n2. the page\n";
$page    = fetch($url);
$browser = fetch($url, UA_BROWSER);

$say($page['status'] === 200, 'page returns 200 to facebookexternalhit',
    $page['status'] !== 200 ? 'got ' . $page['status'] : '');

if ($page['status'] !== $browser['status']) {
    echo "\n";
    echo "       A browser gets {$browser['status']} and Facebook gets {$page['status']}.\n";
    echo "       The server is treating the crawler differently by user agent.\n";
    echo "       That is not robots.txt — it is a security rule on the host.\n";
    echo "       On A2 this is mod_security or Imunify360. Ask support to\n";
    echo "       allow facebookexternalhit for fixlisted.com, or find the\n";
    echo "       triggered rule id in cPanel > Security > ModSecurity Tools.\n\n";
} elseif ($page['status'] === 403) {
    echo "\n";
    echo "       Both a browser and the crawler get 403, so nothing is\n";
    echo "       crawler-specific. Check that index.html is readable\n";
    echo "       (chmod 644) and that .htaccess in the document root parses.\n\n";
}

if ($page['final'] !== $url) {
    echo "       redirected to {$page['final']}\n";
}

// Facebook ignores meta robots when scraping, but Twitter and LinkedIn are
// less predictable, so a noindex is worth knowing about.
if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)/i', $browser['body'] ?: $page['body'], $m) === 1) {
    echo "       meta robots: {$m[1]}  (expected before launch; Facebook ignores it when scraping)\n";
}

// Read the tags from whichever fetch actually returned a page. If the host is
// refusing the crawler, the browser's copy is the only one with any markup in
// it — and the image still needs checking, which is the whole point.
$markup = $page['status'] === 200 ? $page['body'] : $browser['body'];
if ($page['status'] !== 200 && $browser['status'] === 200) {
    echo "       (reading the tags from the browser's copy, since the crawler was refused)\n";
}

preg_match_all(
    '/<meta[^>]+(?:property|name)=["\'](og:[a-z:]+|twitter:[a-z:]+)["\'][^>]+content=["\']([^"\']*)/i',
    $markup,
    $tags,
    PREG_SET_ORDER,
);
$og = [];
foreach ($tags as $t) { $og[strtolower($t[1])] = $t[2]; }

foreach (['og:title', 'og:description', 'og:url', 'og:image'] as $required) {
    $say(isset($og[$required]) && $og[$required] !== '', $required . ' is present',
        isset($og[$required]) ? '' : 'missing — is this the rebuilt index.html?');
}

// --- 2b. every crawler, side by side ----------------------------------------
//
// The question this answers: when one platform previews correctly and another
// does not, is the difference in the page or in who is asking? Same URL, same
// moment, only the user agent changes. A column of 200s with one 403 in it is
// a host security rule, and it is the evidence an A2 ticket needs.
echo "\n2b. who the server will talk to\n";
$agents = [
    'a browser'            => UA_BROWSER,
    'facebookexternalhit'  => UA_FACEBOOK,
    'facebookcatalog'      => 'facebookcatalog/1.0',
    'Twitterbot'           => 'Twitterbot/1.0',
    'LinkedInBot'          => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; +http://www.linkedin.com)',
    'Slackbot'             => 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
    'WhatsApp'             => 'WhatsApp/2.23',
    'no user agent'        => '',
];

$pageCodes = [];
foreach ($agents as $name => $agent) {
    $r = fetch($url, $agent);
    $i = isset($og['og:image']) && $og['og:image'] !== '' ? fetch($og['og:image'], $agent) : null;
    $pageCodes[$name] = $r['status'];
    printf("       %-22s page %-4s image %s\n",
        $name,
        $r['status'] === 0 ? 'ERR' : (string) $r['status'],
        $i === null ? '—' : ($i['status'] === 0 ? 'ERR' : (string) $i['status']));
}

$ok  = array_keys(array_filter($pageCodes, static fn (int $c): bool => $c === 200));
$bad = array_keys(array_filter($pageCodes, static fn (int $c): bool => $c !== 200));
if ($bad !== [] && $ok !== []) {
    echo "\n";
    echo "       Accepted: " . implode(', ', $ok) . "\n";
    echo "       Refused:  " . implode(', ', $bad) . "\n\n";
    echo "       Same page, same second — only the user agent differs, so the\n";
    echo "       page is fine and the host is refusing those crawlers by name.\n";
    echo "       That is mod_security or Imunify360, not robots.txt and not\n";
    echo "       anything in the markup. cPanel > Security > ModSecurity Tools\n";
    echo "       > Hits List will show the rule id. Disable that id for\n";
    echo "       fixlisted.com, or send A2 support the two lines above.\n";
    $problems++;
}

// --- 3. the image -----------------------------------------------------------
echo "\n3. the image\n";
if (!isset($og['og:image']) || $og['og:image'] === '') {
    $say(false, 'nothing to check', 'no og:image on the page');
} else {
    $img = $og['og:image'];
    echo "       {$img}\n";
    $say(str_starts_with($img, 'http://') || str_starts_with($img, 'https://'),
        'og:image is an absolute URL',
        str_starts_with($img, 'http') ? '' : 'relative URLs are ignored silently by every scraper');

    $res = fetch($img);
    $say($res['status'] === 200, 'image returns 200',
        match (true) {
            $res['status'] === 403 => 'forbidden — usually file permissions: chmod 644 the file',
            $res['status'] === 404 => 'not found — the file is not at that path on the server',
            $res['status'] === 0   => 'could not connect: ' . $res['error'],
            $res['status'] !== 200 => 'got ' . $res['status'],
            default                => '',
        });

    if ($res['status'] === 200) {
        $say(str_starts_with($res['type'], 'image/'), 'served as an image', 'Content-Type: ' . $res['type']);
        $say($res['len'] > 0 && $res['len'] < 8_000_000, 'size is sane',
            number_format($res['len'] / 1024, 0) . ' KB');

        $info = @getimagesizefromstring($res['body']);
        if ($info === false) {
            $say(false, 'file is a readable image', 'the bytes are not a valid image');
        } else {
            [$w, $h] = $info;
            $say($w >= 200 && $h >= 200, "dimensions {$w}x{$h}",
                $w >= 200 && $h >= 200 ? '' : 'Facebook needs at least 200x200');
            if ($w !== 1200 || $h !== 630) {
                echo "       note: {$w}x{$h} — 1200x630 is what og:image:width/height claim\n";
            }
        }
    }
}

echo "\n";
if ($problems === 0) {
    echo "Nothing is blocking the preview.\n\n";
    echo "Facebook caches hard, so it will still show the old result until you\n";
    echo "force a refresh. Open the URL in the sharing debugger and press\n";
    echo "Scrape Again:\n";
    echo "  https://developers.facebook.com/tools/debug/?q=" . urlencode($url) . "\n\n";
    exit(0);
}

echo "{$problems} problem(s) above. Fix those, then Scrape Again in:\n";
echo "  https://developers.facebook.com/tools/debug/?q=" . urlencode($url) . "\n\n";
exit(1);
