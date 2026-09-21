<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\AdTracker;
use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Repositories\AdvertisingRepository;

/**
 * Where a click on a paid listing goes: /go/{placement}.
 *
 * It takes an id and looks the destination up. It never takes a URL. A
 * redirector that forwards to whatever is in the query string is an open
 * redirect — a link that starts on fixlisted.com and lands somewhere else,
 * which is exactly the shape a phishing link wants. The only thing this can
 * send anyone to is a profile page on this site.
 *
 * A placement that has expired still redirects. The pro's profile is still
 * there, the link may be in somebody's history, and a 404 for a listing that
 * exists is worse than an uncounted click.
 */
final class AdController extends Controller
{
    public function go(string $id): Response
    {
        $placementId = (int) $id;
        if ($placementId < 1) {
            throw new NotFound('go');
        }

        $ads       = new AdvertisingRepository($this->db, $this->scope);
        $placement = $ads->placement($placementId);

        if ($placement === null || (string) $placement['pro_status'] !== 'active') {
            throw new NotFound('go/' . $placementId);
        }

        // Only a live placement counts. Otherwise an expired one keeps
        // accruing clicks against a pro who is no longer paying, and the
        // numbers stop meaning what the invoice says they mean.
        if ((string) $placement['status'] === 'active') {
            AdTracker::click(
                $this->db,
                $this->request,
                $this->scope->marketId,
                $placementId,
                (int) $placement['pro_id'],
            );
        }

        // 302, not 303: this is a GET that stands in for another GET, and the
        // redirect is about routing rather than the result of a submission.
        return Response::redirect('/pros/' . $placement['slug'], 302);
    }
}
