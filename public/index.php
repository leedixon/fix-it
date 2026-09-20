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

use FixListed\Controllers\CityController;
use FixListed\Controllers\DirectoryController;
use FixListed\Controllers\HomeController;
use FixListed\Controllers\JobsController;
use FixListed\Controllers\PageController;
use FixListed\Core\Config;
use FixListed\Core\Database;
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

    $make = static fn (string $class): object => new $class($db, $scope, $market, $view, $request);

    $router = new Router();
    $router->get('/',               static fn () => $make(HomeController::class)->index());
    $router->get('/pros',           static fn () => $make(DirectoryController::class)->index());
    $router->get('/pros/{slug}',    static fn (array $p) => $make(DirectoryController::class)->show($p['slug']));
    $router->get('/jobs',           static fn () => $make(JobsController::class)->index());
    $router->get('/jobs/{reference}', static fn (array $p) => $make(JobsController::class)->show($p['reference']));
    $router->get('/in/{slug}',      static fn (array $p) => $make(CityController::class)->show($p['slug']));
    $router->get('/pricing',        static fn () => $make(PageController::class)->pricing());
    $router->get('/for-pros',       static fn () => $make(PageController::class)->forPros());
    $router->get('/terms',          static fn () => $make(PageController::class)->legal('terms'));
    $router->get('/privacy',        static fn () => $make(PageController::class)->legal('privacy'));
    $router->get('/contact',        static fn () => $make(PageController::class)->contact());

    $matched = $router->match($request);
    if ($matched === null) {
        throw new NotFound($request->path);
    }

    $response = ($matched['handler'])($matched['params']);
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

    if (Config::get('app.env') !== 'production') {
        throw $e;
    }
    $response = Response::html($view->render('site/error', [], null), 500);
}

$response->send();
