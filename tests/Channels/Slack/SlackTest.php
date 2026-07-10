<?php

declare(strict_types=1);

use Geoffrey\Bootstrap;
use Geoffrey\Channels\ChannelManager;
use Geoffrey\Channels\Slack\Slack;
use Geoffrey\Channels\Slack\VerifySlackRequest;
use Geoffrey\GeoffreyServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

function makeSlackTestContainer(): array
{
    $container = new Container;
    $dispatcher = new Dispatcher($container);
    $router = new Router($dispatcher, $container);
    $container->instance('router', $router);
    Container::setInstance($container);

    return [$container, $router];
}

function makeSlackServiceProviderApp(): Container
{
    $container = new Container;
    $config = new ConfigRepository;
    $container->instance('config', $config);
    $container->instance(Repository::class, $config);

    return $container;
}

it('registers a POST webhook route for the channel', function (): void {
    /** @var array{0: Container, 1: Router} $result */
    $result = makeSlackTestContainer();
    $router = $result[1];

    $slack = new Slack;
    $slack->register('slack_main', ['driver' => 'slack', 'token' => 'tok', 'signing_secret' => 'secret']);

    $route = $router->getRoutes()->match(
        Request::create('/webhooks/slack_main/slack', 'POST')
    );

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('webhooks/slack_main/slack');
    expect($route->methods())->toContain('POST');
});

it('applies the VerifySlackRequest middleware with signing secret to the webhook route', function (): void {
    /** @var array{0: Container, 1: Router} $result */
    $result = makeSlackTestContainer();
    $router = $result[1];

    $slack = new Slack;
    $slack->register('slack_main', ['driver' => 'slack', 'token' => 'tok', 'signing_secret' => 'mysecret']);

    $route = $router->getRoutes()->match(
        Request::create('/webhooks/slack_main/slack', 'POST')
    );

    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain(VerifySlackRequest::class.':mysecret');
});

it('passes the channel name to the controller', function (): void {
    /** @var array{0: Container, 1: Router} $result */
    $result = makeSlackTestContainer();
    $router = $result[1];

    $slack = new Slack;
    $slack->register('slack_main', ['driver' => 'slack', 'token' => 'tok', 'signing_secret' => 'secret']);

    $route = $router->getRoutes()->match(
        Request::create('/webhooks/slack_main/slack', 'POST')
    );

    $action = $route->getAction();
    expect($action['uses'])->toBeInstanceOf(Closure::class);

    // Execute the closure with a mock request to verify it creates a controller with the channel name
    $request = Request::create('/webhooks/slack_main/slack', 'POST', ['type' => 'url_verification', 'challenge' => 'test']);
    $response = ($action['uses'])($request);
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toBe(['challenge' => 'test']);
});

it('registers the slack driver with the channel manager via the service provider', function (): void {
    $app = makeSlackServiceProviderApp();
    $provider = new GeoffreyServiceProvider($app);
    $provider->register();

    $channelManager = $app->make(ChannelManager::class);

    $reflection = new ReflectionClass($channelManager);
    $driversProperty = $reflection->getProperty('drivers');
    $drivers = $driversProperty->getValue($channelManager);

    expect($drivers)->toHaveKey('slack');
});

it('supports multiple slack channel accounts with separate routes', function (): void {
    /** @var array{0: Container, 1: Router} $result */
    $result = makeSlackTestContainer();
    $router = $result[1];

    $slack = new Slack;
    $slack->register('slack_main', ['driver' => 'slack', 'token' => 'tok1', 'signing_secret' => 'secret1']);
    $slack->register('slack_secondary', ['driver' => 'slack', 'token' => 'tok2', 'signing_secret' => 'secret2']);

    $route1 = $router->getRoutes()->match(
        Request::create('/webhooks/slack_main/slack', 'POST')
    );
    $route2 = $router->getRoutes()->match(
        Request::create('/webhooks/slack_secondary/slack', 'POST')
    );

    expect($route1->uri())->toBe('webhooks/slack_main/slack');
    expect($route2->uri())->toBe('webhooks/slack_secondary/slack');

    $middleware1 = $route1->gatherMiddleware();
    $middleware2 = $route2->gatherMiddleware();

    expect($middleware1)->toContain(VerifySlackRequest::class.':secret1');
    expect($middleware2)->toContain(VerifySlackRequest::class.':secret2');
});

it('excludes webhook routes from csrf verification', function (): void {
    // CSRF exclusion for webhooks/* is handled by Bootstrap.php
    // This test verifies the exclusion exists in Bootstrap
    $reflection = new ReflectionClass(Bootstrap::class);
    $source = file_get_contents((string) $reflection->getFileName());

    expect($source)->toContain('webhooks/*');
});
