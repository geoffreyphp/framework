<?php

declare(strict_types=1);

use Geoffrey\Bootstrap;
use Geoffrey\Http\AgentController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\Facades\Route;

function cleanupBasePath(string $basePath): void
{
    array_map(unlink(...), glob($basePath.'/.geoffrey/**/*') ?: []);
    array_map(rmdir(...), glob($basePath.'/.geoffrey/*') ?: []);
    @rmdir($basePath.'/.geoffrey');
    @rmdir($basePath);
}

function makeBasePath(): string
{
    $basePath = sys_get_temp_dir().'/geoffrey-test-'.uniqid();
    mkdir($basePath, 0755, true);

    return $basePath;
}

it('creates a laravel application with the given base path', function (): void {
    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    expect($app)->toBeInstanceOf(Application::class);
    expect($app->basePath())->toBe($basePath);

    cleanupBasePath($basePath);
});

it('sets storage path to .geoffrey directory under base path', function (): void {
    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    expect($app->storagePath())->toBe($basePath.'/.geoffrey');

    cleanupBasePath($basePath);
});

it('registers the POST /agent route', function (): void {
    $basePath = makeBasePath();

    Bootstrap::create($basePath);

    // The Bootstrap configures the RouteServiceProvider with the routing callback
    // We verify by invoking the static routing callback with a real Router instance
    $app = new Application($basePath);
    $app->register(RoutingServiceProvider::class);

    /** @var Router $router */
    $router = $app->make('router');

    // Use the facade to bind to our test app's router
    Route::setFacadeApplication($app);

    // Invoke the static routing callback registered by Bootstrap::create()
    $reflection = new ReflectionClass(RouteServiceProvider::class);
    $prop = $reflection->getProperty('alwaysLoadRoutesUsing');
    $callback = $prop->getValue();

    expect($callback)->not->toBeNull();

    $callback();

    $route = $router->getRoutes()->match(
        Request::create('/agent', 'POST')
    );

    expect($route->getActionName())->toContain(AgentController::class);

    cleanupBasePath($basePath);
});

it('disables CSRF for agent and webhook paths', function (): void {
    PreventRequestForgery::flushState();

    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    // Resolving the HTTP kernel triggers the withMiddleware callback
    // which calls validateCsrfTokens and sets the static except list
    $app->make(Kernel::class);

    $reflection = new ReflectionClass(PreventRequestForgery::class);
    $prop = $reflection->getProperty('neverVerify');
    /** @var array<string> $neverVerify */
    $neverVerify = $prop->getValue();

    expect($neverVerify)->toContain('agent');
    expect($neverVerify)->toContain('webhooks/*');

    cleanupBasePath($basePath);
});

it('returns a configured Application instance', function (): void {
    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    expect($app)->toBeInstanceOf(Application::class);
    expect($app->basePath())->toBe($basePath);
    expect($app->storagePath())->toBe($basePath.'/.geoffrey');

    cleanupBasePath($basePath);
});
