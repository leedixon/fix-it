<?php
declare(strict_types=1);

/**
 * Front controller. Every request that is not a real file arrives here.
 *
 * The app is mounted wherever index.php sits — at /preview while the site is
 * being built, at / when it launches — and Url::mount() is the one line that
 * knows about it.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use FixListed\Controllers\AdController;
use FixListed\Controllers\CityController;
use FixListed\Controllers\DirectoryController;
use FixListed\Controllers\HomeController;
use FixListed\Controllers\JobsController;
use FixListed\Controllers\PageController;
use FixListed\Controllers\PostJobController;
use FixListed\Controllers\ProSignupController;
use FixListed\Controllers\WebhookController;
use FixListed\Controllers\Account\DashboardController as AccountDashboard;
use FixListed\Controllers\Account\PasswordController;
use FixListed\Controllers\Account\ProfileController;
use FixListed\Controllers\Account\PromoteController;
use FixListed\Controllers\Account\QuoteController;
use FixListed\Controllers\Account\SessionController as AccountSession;
use FixListed\Controllers\Admin\DashboardController;
use FixListed\Controllers\Admin\MaintenanceController;
use FixListed\Controllers\Admin\ManageController;
use FixListed\Controllers\Admin\ReviewController;
use FixListed\Controllers\Admin\SessionController;
use FixListed\Controllers\Admin\TeamController;
use FixListed\Core\AdTracker;
use FixListed\Core\Auth;
use FixListed\Core\Config;
use FixListed\Core\Database;
use FixListed\Core\HaltWith;
use FixListed\Core\Maintenance;
use FixListed\Core\NotFound;
use FixListed\Core\Request;
use FixListed\Core\Response;
use FixListed\Core\Router;
use FixListed\Core\Session;
use FixListed\Core\TenantScope;
use FixListed\Core\Url;
use FixListed\Core\View;
use FixListed\Repositories\MarketRepository;

$request = Request::capture();
Url::mount($request->basePath);

$db   = Database::fromConfig();
$view = new View(BASE_PATH . '/app/Views');

try {
    Session::start();

    // Which market this visitor is in. One live market today; when there are
    // several this is where a subdomain or a path segment picks between them,
    // and everything downstream already works in terms of the scope.
    $market = (new MarketRepository($db))->default();
    if ($market === null) {
        throw new RuntimeException('No live market is configured.');
    }
    $scope = TenantScope::market((int) $market['id']);

    // One Auth for the request. Every controller takes it: public pages change
    // their chrome when somebody is signed in, and the account and admin areas
    // are gated on it.
    $auth  = new Auth($db);

    /*
     * Maintenance mode. Everything above this line had to run first, because
     * working out whether *you* are an administrator needs the session and
     * the database. When the database is the thing that is broken, this check
     * is never reached at all — the catch at the bottom covers that case, and
     * it is the case this feature exists for.
     *
     * Admins pass straight through and see the whole site as normal, which is
     * the point: you take it down, deploy, check your work, put it back up.
     * /admin/login stays open so you can become an admin while it is down —
     * without that, signing out during maintenance locks you out of your own
     * site until you SSH in.
     *
     * Everything else, webhooks included, gets 503. A webhook answered 200
     * during a migration is a payment Stripe will never send again; answered
     * 503 it comes back, with backoff, for about three days.
     */
    if (Maintenance::isOn()
        && !$auth->is(Auth::ROLE_ADMIN, Auth::ROLE_SUPER)
        && !str_starts_with($request->path, '/admin/login')) {
        Maintenance::response($view)->send();
        exit;
    }
    $make  = static fn (string $class): object => new $class($db, $scope, $market, $view, $request, $auth);
    $admin = $make;

    $router = new Router();
    $router->get('/',               static fn () => $make(HomeController::class)->index());
    $router->get('/pros',           static fn () => $make(DirectoryController::class)->index());
    $router->get('/pros/{slug}',    static fn (array $p) => $make(DirectoryController::class)->show($p['slug']));
    $router->get('/jobs',           static fn () => $make(JobsController::class)->index());
    $router->get('/jobs/{reference}', static fn (array $p) => $make(JobsController::class)->show($p['reference']));
    $router->get('/in/{slug}',      static fn (array $p) => $make(CityController::class)->show($p['slug']));
    $router->get('/list-your-business',          static fn () => $make(ProSignupController::class)->form());
    $router->post('/list-your-business',         static fn () => $make(ProSignupController::class)->submit());
    $router->get('/list-your-business/received', static fn () => $make(ProSignupController::class)->received());

    // A click on a paid listing, counted and then sent on. It takes an id
    // and resolves the destination itself — see AdController.
    $router->get('/go/{id}', static fn (array $p) => $make(AdController::class)->go($p['id']));

    $router->get('/pricing',        static fn () => $make(PageController::class)->pricing());
    $router->get('/for-pros',       static fn () => $make(PageController::class)->forPros());
    $router->get('/terms',          static fn () => $make(PageController::class)->legal('terms'));
    $router->get('/privacy',        static fn () => $make(PageController::class)->legal('privacy'));
    $router->get('/contact',        static fn () => $make(PageController::class)->contact());

    // --- posting a job, and paying for it --------------------------------
    $router->get('/post-a-job',         static fn () => $make(PostJobController::class)->form());
    $router->post('/post-a-job',        static fn () => $make(PostJobController::class)->submit());
    $router->get('/post-a-job/thanks',  static fn () => $make(PostJobController::class)->thanks());
    $router->get('/post-a-job/resume',  static fn () => $make(PostJobController::class)->resume());

    // Stripe talks to this one. No session, no market from the request, no
    // chrome — see WebhookController for why it stands apart.
    $router->post('/webhooks/stripe', static fn () => (new WebhookController($db, $request, $view))->stripe());

    // --- signing in, and the tradesperson's own area ---------------------
    $router->get('/sign-in',          static fn () => $make(AccountSession::class)->form());
    $router->post('/sign-in',         static fn () => $make(AccountSession::class)->login());
    $router->post('/sign-out',        static fn () => $make(AccountSession::class)->logout());
    $router->get('/forgot-password',  static fn () => $make(AccountSession::class)->forgotForm());
    $router->post('/forgot-password', static fn () => $make(AccountSession::class)->forgot());
    $router->get('/set-password/{token}',  static fn (array $p) => $make(PasswordController::class)->form($p['token']));
    $router->post('/set-password/{token}', static fn (array $p) => $make(PasswordController::class)->submit($p['token']));

    $router->get('/my',            static fn () => $make(AccountDashboard::class)->index());
    $router->get('/my/quotes',     static fn () => $make(AccountDashboard::class)->quotes());
    $router->get('/my/promote',    static fn () => $make(PromoteController::class)->index());
    $router->post('/my/promote',   static fn () => $make(PromoteController::class)->subscribe());
    $router->get('/my/promote/done', static fn () => $make(PromoteController::class)->done());
    $router->post('/my/billing',   static fn () => $make(PromoteController::class)->billing());
    $router->get('/my/listing',    static fn () => $make(ProfileController::class)->edit());
    $router->post('/my/listing',   static fn () => $make(ProfileController::class)->update());
    $router->get('/my/quote/{reference}',  static fn (array $p) => $make(QuoteController::class)->form($p['reference']));
    $router->post('/my/quote/{reference}', static fn (array $p) => $make(QuoteController::class)->submit($p['reference']));

    // --- admin ---------------------------------------------------------
    // Registered after the public routes but before the match, so nothing
    // public can shadow them. /admin/login is the only one outside the gate.
    $router->get('/admin/login',   static fn () => $admin(SessionController::class)->form());
    $router->post('/admin/login',  static fn () => $admin(SessionController::class)->login());
    $router->post('/admin/logout', static fn () => $admin(SessionController::class)->logout());

    $router->get('/admin',          static fn () => $admin(DashboardController::class)->index());
    $router->get('/admin/activity', static fn () => $admin(DashboardController::class)->activity());

    $router->get('/admin/applications',      static fn () => $admin(ReviewController::class)->index());
    $router->get('/admin/applications/{id}', static fn (array $p) => $admin(ReviewController::class)->show($p['id']));
    $router->post('/admin/applications/{id}/approve', static fn (array $p) => $admin(ReviewController::class)->approve($p['id']));
    $router->post('/admin/applications/{id}/reject',  static fn (array $p) => $admin(ReviewController::class)->reject($p['id']));

    $router->get('/admin/pros',                static fn () => $admin(ManageController::class)->pros());
    $router->post('/admin/pros/{id}/status',   static fn (array $p) => $admin(ManageController::class)->setProStatus($p['id']));
    $router->post('/admin/pros/{id}/resend',   static fn (array $p) => $admin(ManageController::class)->resendProLink($p['id']));
    $router->get('/admin/jobs',                static fn () => $admin(ManageController::class)->jobs());
    $router->post('/admin/jobs/{id}/remove',   static fn (array $p) => $admin(ManageController::class)->removeJob($p['id']));
    $router->get('/admin/users',               static fn () => $admin(ManageController::class)->users());
    $router->post('/admin/users/{id}/remove',  static fn (array $p) => $admin(ManageController::class)->removeUser($p['id']));
    $router->get('/admin/advertising',         static fn () => $admin(ManageController::class)->advertising());
    $router->get('/admin/licensing',           static fn () => $admin(ManageController::class)->licensing());
    $router->post('/admin/licensing',          static fn () => $admin(ManageController::class)->saveLicensing());
    $router->post('/admin/licensing/{id}/delete', static fn (array $p) => $admin(ManageController::class)->deleteLicensing($p['id']));
    $router->get('/admin/team',                static fn () => $admin(TeamController::class)->index());
    $router->post('/admin/team',               static fn () => $admin(TeamController::class)->invite());
    $router->post('/admin/team/{id}/role',     static fn (array $p) => $admin(TeamController::class)->setRole($p['id']));
    $router->post('/admin/team/{id}/status',   static fn (array $p) => $admin(TeamController::class)->setStatus($p['id']));
    $router->post('/admin/team/{id}/resend',   static fn (array $p) => $admin(TeamController::class)->resend($p['id']));
    $router->post('/admin/team/{id}/remove',   static fn (array $p) => $admin(TeamController::class)->remove($p['id']));
    $router->get('/admin/maintenance',         static fn () => $admin(MaintenanceController::class)->index());
    $router->post('/admin/maintenance',        static fn () => $admin(MaintenanceController::class)->update());
    $router->get('/admin/markets',             static fn () => $admin(ManageController::class)->markets());
    $router->post('/admin/markets/{id}',       static fn (array $p) => $admin(ManageController::class)->updateMarket($p['id']));

    $matched = $router->match($request);
    if ($matched === null) {
        throw new NotFound($request->path);
    }

    $response = ($matched['handler'])($matched['params']);
} catch (HaltWith $halt) {
    // A controller decided mid-request that the answer is a redirect.
    $response = $halt->response;
} catch (NotFound) {
    $response = Response::html(
        $view->render('site/not_found', [
            'title'    => 'Page not found — Fix Listed',
            'market'   => $market ?? [],
            'counties' => [],
            'navTrades'=> [],
            'path'     => $request->path,
            'showDemo' => false,
            'noindex'  => true,
        ]),
        404,
    );
} catch (Throwable $e) {
    // The message can name the database, a file path or a credential, so it
    // goes to the log and never to the visitor.
    error_log('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    /*
     * The case this whole feature is for.
     *
     * If the site is down for maintenance and something above threw — the
     * database is mid-migration, a table is missing, a connection is refused
     * — then the failure is expected and the honest answer is the maintenance
     * page with its 503, not a 500 that says something is wrong. It is also
     * checked before the development re-throw, so taking the site down means
     * taking it down in every environment.
     *
     * Maintenance::state() reads a file and touches nothing else, which is
     * what makes it safe to call from inside a failure.
     */
    if (Maintenance::isOn()) {
        try {
            Maintenance::response($view)->send();
            exit;
        } catch (Throwable) {
            // Even the template failed. Say the one thing that matters.
            http_response_code(503);
            header('Retry-After: 1800');
            header('Content-Type: text/plain; charset=UTF-8');
            exit("Fix Listed is down for maintenance. Please try again shortly.\n");
        }
    }

    if (Config::get('app.env') !== 'production') {
        throw $e;
    }
    $response = Response::html($view->render('site/error', [], null), 500);
}

$response->send();

/*
 * Everything below runs with the visitor already served.
 *
 * fastcgi_finish_request closes the connection and lets PHP carry on, so
 * counting an impression costs the person reading the page nothing. Without
 * it (CLI server, some FPM setups) the work still happens — it is two small
 * writes — it just is not free.
 */
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (isset($scope, $auth)) {
    AdTracker::flush($db, $request, $scope->marketId, $auth->id());
}
