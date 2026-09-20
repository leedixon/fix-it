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

    public function __construct(
        protected readonly Database $db,
        protected readonly TenantScope $scope,
        /** @var array<string,mixed> The market row: id, name, slug, listing_fee_cents. */
        protected readonly array $market,
        protected readonly View $view,
        protected readonly Request $request,
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
            'path'        => $this->request->path,
            'showDemo'    => Demo::isVisible(),
            'bodyClass'   => '',
            // Every public page is noindex until launch. Removing this is a
            // deliberate step in the launch checklist, not something that
            // should happen by accident because a template forgot to set it.
            'noindex'     => (bool) Config::get('app.noindex', true),
        ];

        return Response::html($this->view->render($template, $data + $defaults), $status);
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
