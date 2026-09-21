<?php
declare(strict_types=1);

namespace FixListed\Core;

use FixListed\Repositories\ProRepository;

/**
 * Base for everything a signed-in tradesperson sees.
 *
 * Like AdminController, the gate is here and only here. The extra job this one
 * does is resolving the signed-in user to their profile: almost every screen
 * needs it, and a pro whose profile is still pending review must see a waiting
 * page rather than a broken dashboard.
 */
abstract class AccountController
{
    private ?array $profile = null;
    private bool $profileLoaded = false;

    public function __construct(
        protected readonly Database $db,
        protected readonly TenantScope $scope,
        /** @var array<string,mixed> */
        protected readonly array $market,
        protected readonly View $view,
        protected readonly Request $request,
        protected readonly Auth $auth,
    ) {
    }

    protected function guard(): ?Response
    {
        if (!$this->auth->check()) {
            Session::put('_intended', $this->request->path);
            return Response::redirect('/sign-in');
        }
        if (!$this->auth->is(Auth::ROLE_PRO, Auth::ROLE_ADMIN, Auth::ROLE_SUPER)) {
            throw new NotFound('account');
        }
        return null;
    }

    /**
     * The signed-in tradesperson's profile, whatever its status.
     *
     * Read without the public status filter on purpose: the whole point of
     * this area is that a pro can see and edit their own listing while it is
     * still pending, or after it has been suspended.
     *
     * @return array<string,mixed>|null
     */
    protected function profile(): ?array
    {
        if ($this->profileLoaded) {
            return $this->profile;
        }
        $this->profileLoaded = true;

        $userId = $this->auth->id();
        if ($userId === null) {
            return $this->profile = null;
        }

        return $this->profile = $this->db->one(
            'SELECT p.*, co.short_name AS home_county
               FROM pro_profiles p
               LEFT JOIN counties co ON co.id = p.home_county_id
              WHERE p.user_id = :user AND p.market_id = :market_id
              LIMIT 1',
            ['user' => $userId, 'market_id' => $this->scope->marketId],
        );
    }

    /** @param array<string,mixed> $data */
    protected function page(string $template, array $data = [], int $status = 200): Response
    {
        $profile = $this->profile();

        $defaults = [
            'title'    => 'Your account — Fix Listed',
            'market'   => $this->market,
            'path'     => $this->request->path,
            'me'       => $this->auth->user(),
            'profile'  => $profile,
            'isLive'   => ($profile['status'] ?? '') === 'active',
            'flashes'  => Session::takeFlashes(),
        ];

        return Response::html($this->view->render($template, $data + $defaults, 'layouts/account'), $status);
    }

    protected function checkCsrf(): bool
    {
        return Csrf::check($this->request->input('_csrf'));
    }
}
