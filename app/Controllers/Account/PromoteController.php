<?php
declare(strict_types=1);

namespace FixListed\Controllers\Account;

use FixListed\Core\AccountController;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\Stripe;
use FixListed\Repositories\AdvertisingRepository;

/**
 * Where a tradesperson buys position, and sees what it bought them.
 *
 * Two decisions shape this screen.
 *
 * **It shows the numbers before it shows the price.** Impressions and clicks
 * are counted for every listing, paid or not, so a pro who has never spent a
 * penny can see what their listing already does and judge whether paying for
 * more of it is worth it. A placement sold on a promise is a placement
 * cancelled in month two.
 *
 * **Billing lives in Stripe.** Cancelling, changing a card and downloading
 * invoices all happen in Stripe's portal. Rebuilding those screens here would
 * mean handling card details and writing a second, worse version of something
 * that already exists — and a pro who cannot find the cancel button does not
 * quietly keep paying, they call their bank.
 */
final class PromoteController extends AccountController
{
    public function index(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }

        $ads = new AdvertisingRepository($this->db, $this->scope);
        $proId = (int) $profile['id'];

        return $this->page('account/promote', [
            'title'        => 'Get seen first — Fix Listed',
            'plans'        => $ads->plans($this->market),
            'subscription' => $ads->forPro($proId),
            'totals'       => $ads->totalsFor($proId, 30),
            'daily'        => $ads->statsFor($proId, 30),
            'canPay'       => Stripe::fromConfig() !== null,
        ]);
    }

    public function subscribe(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was charged.');
            return Response::redirect('/my/promote');
        }

        // A listing still being checked cannot buy the top of the page. The
        // badge beside a promoted card says verified; selling position before
        // the verification is done makes that badge a lie.
        if ((string) $profile['status'] !== 'active') {
            Session::flash('bad', 'Your listing has to be live before you can promote it.');
            return Response::redirect('/my/promote');
        }

        $plan = (string) $this->request->input('plan', '');
        if (!in_array($plan, ['boost', 'spotlight'], true)) {
            return Response::redirect('/my/promote');
        }

        $ads   = new AdvertisingRepository($this->db, $this->scope);
        $proId = (int) $profile['id'];

        $current = $ads->forPro($proId);
        if ($current !== null && in_array((string) $current['status'], ['active', 'trialing', 'past_due'], true)) {
            Session::flash('bad', 'You already have a plan. Change or cancel it in the billing portal first.');
            return Response::redirect('/my/promote');
        }

        if (!$ads->hasCapacity($this->market, $plan)) {
            Session::flash('bad', 'That one just sold out. We will hold your place — email us and we '
                . 'will tell you the moment a slot frees up.');
            return Response::redirect('/my/promote');
        }

        $stripe = Stripe::fromConfig();
        if ($stripe === null) {
            Session::flash('bad', 'Payments are not switched on yet. Nothing was charged.');
            return Response::redirect('/my/promote');
        }

        $plans = $ads->plans($this->market);
        $price = (int) $plans[$plan]['price_cents'];
        if ($price < 100) {
            Session::flash('bad', 'That plan has no price set yet. Nothing was charged.');
            return Response::redirect('/my/promote');
        }

        // Written before the redirect, so the webhook has a row to find when
        // Stripe calls back — the same order the job posting uses, and for
        // the same reason.
        $localId = $ads->startCheckout(
            $proId,
            $plan,
            $price,
            (string) ($current['stripe_customer_id'] ?? ''),
        );

        $user = $this->auth->user() ?? [];

        try {
            $session = $stripe->createSubscriptionSession(
                $price,
                $plan === 'spotlight' ? 'Fix Listed Spotlight' : 'Fix Listed Boost',
                $plan === 'spotlight'
                    ? 'Top of the directory in ' . (string) $this->market['name'] . ', with a Featured badge.'
                    : 'Above the standard listings in ' . (string) $this->market['name'] . ', with a Promoted badge.',
                abs_url('/my/promote/done') . '?sub=' . $localId,
                abs_url('/my/promote'),
                (string) ($user['email'] ?? ''),
                [
                    'kind'            => 'placement',
                    'subscription_id' => (string) $localId,
                    'pro_id'          => (string) $proId,
                    'market_id'       => (string) $this->scope->marketId,
                    'plan'            => $plan,
                ],
                // Keyed on the local row, so a double-click produces one
                // Stripe session rather than two subscriptions.
                'sub-' . $localId,
                (string) ($current['stripe_customer_id'] ?? ''),
            );
        } catch (\Throwable $e) {
            error_log('Placement checkout failed for pro ' . $proId . ': ' . $e->getMessage());
            Session::flash('bad', 'We could not reach the payment page just then. Nothing was charged.');
            return Response::redirect('/my/promote');
        }

        $url = (string) ($session['url'] ?? '');
        if ($url === '') {
            Session::flash('bad', 'Stripe did not return a payment page. Nothing was charged.');
            return Response::redirect('/my/promote');
        }

        return Response::redirect($url);
    }

    /**
     * Where Stripe sends them back.
     *
     * Deliberately does not mark anything paid. Only the webhook does that —
     * this URL is one a pro could simply type. All it does is say thank you
     * and explain the short wait if the webhook has not landed yet.
     */
    public function done(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }

        $ads  = new AdvertisingRepository($this->db, $this->scope);
        $sub  = $ads->forPro((int) $profile['id']);
        $live = $sub !== null && in_array((string) $sub['status'], ['active', 'trialing'], true);

        Session::flash(
            $live ? 'good' : 'note',
            $live
                ? 'You are live. Your listing is now lifted everywhere it appears.'
                : 'Payment received. Your placement goes live within a minute or two — refresh this page.',
        );

        return Response::redirect('/my/promote');
    }

    /** Hands the pro over to Stripe's billing portal. */
    public function billing(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }
        if (!$this->checkCsrf()) {
            return Response::redirect('/my/promote');
        }

        $sub      = (new AdvertisingRepository($this->db, $this->scope))->forPro((int) $profile['id']);
        $customer = (string) ($sub['stripe_customer_id'] ?? '');
        $stripe   = Stripe::fromConfig();

        if ($customer === '' || $stripe === null) {
            Session::flash('bad', 'There is no billing account to open yet.');
            return Response::redirect('/my/promote');
        }

        try {
            $portal = $stripe->billingPortalSession($customer, abs_url('/my/promote'));
        } catch (\Throwable $e) {
            error_log('Billing portal failed for pro ' . $profile['id'] . ': ' . $e->getMessage());
            Session::flash('bad', 'We could not open the billing portal just then. Try again in a moment.');
            return Response::redirect('/my/promote');
        }

        $url = (string) ($portal['url'] ?? '');
        if ($url === '') {
            Session::flash('bad', 'Stripe did not return a billing page.');
            return Response::redirect('/my/promote');
        }

        return Response::redirect($url);
    }
}
