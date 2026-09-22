<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Config;
use FixListed\Core\Controller;
use FixListed\Core\Response;
use FixListed\Core\Seo;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\JobRepository;
use FixListed\Repositories\ProRepository;
use FixListed\Repositories\TradeRepository;

/**
 * The two files written for crawlers rather than people.
 *
 * Both are generated rather than kept on disk, and both hang off the same
 * switch — app.noindex — as the meta tag in the layout. One flag, three
 * behaviours that must agree: a site whose pages say noindex while its
 * robots.txt invites the world in is telling two stories, and whichever one
 * gets believed, somebody spends an afternoon working out why.
 *
 * Note on mounting: at /preview these answer at /preview/robots.txt, which no
 * crawler looks for — robots.txt is only read from the domain root. That is
 * correct and harmless. It starts mattering on the day the app moves to /,
 * which is the day it is supposed to matter.
 */
final class SeoController extends Controller
{
    public function robots(): Response
    {
        $sitemap = abs_url('/sitemap.xml');

        /*
         * Before launch: refuse everything, and do not advertise a sitemap.
         *
         * The nuance worth knowing is that Disallow and noindex do different
         * jobs, and Disallow is the blunter one — a blocked URL can still be
         * listed, bare, if something links to it, because the crawler is not
         * allowed to fetch the page and read the noindex. Both are set here
         * on purpose: while the directory is part sample data and the URLs
         * are still moving, "do not come in at all" is the answer, and there
         * is nothing indexed yet for the subtlety to damage.
         */
        if ((bool) Config::get('app.noindex', true)) {
            return Response::text(implode("\n", [
                '# Fix Listed is not open yet.',
                '# This file and the noindex tag on every page come from one setting,',
                '# app.noindex — see docs/launch.md. Both change together, or neither does.',
                'User-agent: *',
                'Disallow: /',
                '',
            ]));
        }

        return Response::text(implode("\n", [
            'User-agent: *',
            '',
            '# Signed-in areas. Nothing here is public, none of it is useful in a',
            '# search result, and a crawler that wanders in just burns crawl budget',
            '# on redirects to the sign-in page.',
            'Disallow: /admin',
            'Disallow: /my',
            'Disallow: /sign-in',
            'Disallow: /forgot-password',
            'Disallow: /set-password',
            'Disallow: /post-a-job/thanks',
            'Disallow: /post-a-job/resume',
            'Disallow: /list-your-business/received',
            '',
            '# Paid-placement click tracking. These are redirects, not pages.',
            'Disallow: /go/',
            '',
            'Sitemap: ' . $sitemap,
            '',
        ]));
    }

    /**
     * Every public URL worth indexing, with an honest lastmod.
     *
     * Built from the database each time rather than written out nightly. A
     * sitemap's one job is to be true right now, and a generated file is a
     * list of what used to exist plus a cron job that will eventually stop
     * running without telling anyone.
     *
     * The tenant scope does the filtering that matters: every query here goes
     * through Repository, so a second market cannot leak into this one's
     * sitemap.
     */
    public function sitemap(): Response
    {
        /*
         * Before launch the sitemap is empty rather than absent.
         *
         * An empty <urlset> is valid, and it answers 200 — so the URL can be
         * submitted to Search Console and checked now, and the day the site
         * opens it fills itself. A 404 here would mean discovering on launch
         * day that the route was never right.
         */
        if ((bool) Config::get('app.noindex', true)) {
            return Response::xml(self::wrap(''));
        }

        $geo    = new GeographyRepository($this->db, $this->scope);
        $trades = (new TradeRepository($this->db))->all();

        $entries = [];

        // The pages that do not change unless somebody edits a template.
        // No lastmod on these: a made-up date is worse than none, and
        // "today, every day" is the signal that gets lastmod ignored
        // site-wide.
        foreach (['/', '/pros', '/jobs', '/post-a-job', '/list-your-business',
                  '/pricing', '/for-pros', '/contact', '/terms', '/privacy'] as $path) {
            $entries[] = ['loc' => abs_url($path)];
        }

        foreach ($trades as $trade) {
            $entries[] = ['loc' => abs_url(Seo::servicePath((string) $trade['slug']))];
        }

        foreach ($geo->pageCities() as $city) {
            $entries[] = ['loc' => abs_url(Seo::cityPath($city))];
        }

        foreach ((new ProRepository($this->db, $this->scope))->sitemap() as $pro) {
            $entries[] = [
                'loc'     => abs_url('/pros/' . $pro['slug']),
                'lastmod' => self::w3c((string) $pro['updated_at']),
            ];
        }

        foreach ((new JobRepository($this->db, $this->scope))->sitemap() as $job) {
            $entries[] = [
                'loc'     => abs_url('/jobs/' . $job['reference']),
                'lastmod' => self::w3c((string) $job['updated_at']),
            ];
        }

        $xml = '';
        foreach ($entries as $entry) {
            $xml .= "  <url>\n    <loc>" . self::esc($entry['loc']) . "</loc>\n";
            if (($entry['lastmod'] ?? '') !== '') {
                $xml .= '    <lastmod>' . self::esc($entry['lastmod']) . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }

        return Response::xml(self::wrap($xml));
    }

    private static function wrap(string $urls): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $urls
            . '</urlset>' . "\n";
    }

    /**
     * A database datetime as the W3C form a sitemap wants.
     *
     * Stored times are in the app's configured zone, so the offset is
     * attached rather than assumed — a bare '2026-09-22T14:00:00' is read as
     * UTC and quietly moves every lastmod by five or six hours.
     */
    private static function w3c(string $datetime): string
    {
        if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($datetime))->format(\DateTimeInterface::W3C);
        } catch (\Exception) {
            return '';
        }
    }

    /** XML escaping. Slugs and references are tame, but nothing is assumed. */
    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
