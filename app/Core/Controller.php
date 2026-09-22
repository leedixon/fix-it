<?php
declare(strict_types=1);

namespace FixListed\Core;

use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\TradeRepository;

/**
 * Shared ground for the public controllers.
 *
 * It carries the one market the request resolved to, and assembles the handful
 * of things every page's chrome needs — the county selector, the trade list,
 * whether sample data is on display. Those are queried once per request and
 * only when something asks, so a page that does not use them costs nothing.
 */
abstract class Controller
{
    private ?array $counties = null;
    private ?array $trades   = null;
    private ?array $cities   = null;

    public function __construct(
        protected readonly Database $db,
        protected readonly TenantScope $scope,
        /** @var array<string,mixed> The market row: id, name, slug, listing_fee_cents. */
        protected readonly array $market,
        protected readonly View $view,
        protected readonly Request $request,
        // Public pages are readable signed out, but the chrome changes when
        // somebody is signed in — and the sign-in page itself has to know.
        protected readonly Auth $auth,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    protected function counties(): array
    {
        return $this->counties ??= (new GeographyRepository($this->db, $this->scope))->counties();
    }

    /** @return array<int,array<string,mixed>> */
    protected function trades(): array
    {
        return $this->trades ??= (new TradeRepository($this->db))->all();
    }

    /**
     * The towns with a landing page, for the footer.
     *
     * In the chrome rather than on individual pages because that is what
     * makes it a link backbone: every city and service page is one click from
     * every other, from anywhere on the site. A landing page reachable only
     * from the sitemap is a page nothing links to, and nothing that links to
     * nothing gets crawled often.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function pageCities(): array
    {
        return $this->cities ??= (new GeographyRepository($this->db, $this->scope))->pageCities();
    }

    /**
     * Renders a page, supplying what the layout needs.
     *
     * $data wins over the defaults, so a page can set its own title without
     * the layout having to know about it.
     *
     * @param array<string,mixed> $data
     */
    protected function page(string $template, array $data = [], int $status = 200): Response
    {
        $defaults = [
            'title'       => 'Fix Listed',
            'description' => '',
            'market'      => $this->market,
            'counties'    => $this->counties(),
            'navTrades'   => $this->trades(),
            'navCities'   => $this->pageCities(),
            'path'        => $this->request->path,
            // Both: the mode must allow it AND there must be something to
            // disclose. Otherwise a purged site tells visitors its real
            // listings are invented.
            'showDemo'    => Demo::isVisible() && Demo::hasRows($this->db),
            'me'          => $this->auth->user(),
            'flashes'     => Session::takeFlashes(),
            'bodyClass'   => '',
            // Every public page is noindex until launch. Removing this is a
            // deliberate step in the launch checklist, not something that
            // should happen by accident because a template forgot to set it.
            'noindex'     => (bool) Config::get('app.noindex', true),
            // The breadcrumb trail, drawn by partials/crumbs.php and described
            // as BreadcrumbList by partials/jsonld.php — one array, so the
            // markup cannot drift from what is on the screen.
            'crumbs'      => [],
            // Extra structured-data nodes for this page. The organisation and
            // the site itself are added for every page by the partial; this is
            // what makes *this* page a Service, a LocalBusiness or an FAQ.
            'jsonLd'      => [],
            /*
             * The analytics dataLayer, filled on the server — see
             * partials/gtm.php and docs/analytics.md.
             *
             * page_type is derived from the template rather than set by hand
             * in twenty places: 'site/city' is a city page and always will
             * be, and a derived value cannot be forgotten on the one new
             * page nobody remembers to annotate. A controller adds the rest
             * (which town, which trade, which conversion) by passing its own
             * 'analytics' array, which replaces this one — so anything that
             * does must include page_type itself, and Controller::analytics()
             * is the helper that makes that hard to get wrong.
             */
            'analytics'   => ['page_type' => $this->pageType($template)],
        ];

        return Response::html($this->view->render($template, $data + $defaults), $status);
    }

    /**
     * The page's analytics context: the derived page_type, plus whatever this
     * page knows about itself.
     *
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    protected function analytics(string $template, array $extra = []): array
    {
        return ['page_type' => $this->pageType($template)] + $extra;
    }

    /** 'site/list_business' becomes 'list_business'. */
    private function pageType(string $template): string
    {
        $parts = explode('.', str_replace('/', '.', $template));
        return (string) end($parts);
    }

    /** The county the visitor has selected, or null for the whole market. */
    protected function selectedCounty(): ?array
    {
        $slug = $this->request->input('county');
        if ($slug === null || $slug === '') {
            return null;
        }
        foreach ($this->counties() as $county) {
            if ($county['slug'] === $slug) {
                return $county;
            }
        }
        return null;
    }

    /** The trade filter from ?trade=, or null. */
    protected function selectedTrade(): ?array
    {
        $slug = $this->request->input('trade');
        if ($slug === null || $slug === '') {
            return null;
        }
        return (new TradeRepository($this->db))->findBySlug($slug);
    }
}
