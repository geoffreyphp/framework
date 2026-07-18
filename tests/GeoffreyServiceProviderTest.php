<?php

declare(strict_types=1);

use Geoffrey\Channels\ChannelManager;
use Geoffrey\Contracts\Channel;
use Geoffrey\GeoffreyServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;

function makeApp(): Container
{
    $container = new Container;
    $config = new ConfigRepository;
    $container->instance('config', $config);
    $container->instance(Repository::class, $config);

    return $container;
}

it('merges the geoffrey config file', function (): void {
    $app = makeApp();
    $provider = new GeoffreyServiceProvider($app);
    $provider->register();

    $config = $app->make('config');

    expect($config->get('geoffrey'))->toBeArray();
    expect($config->get('geoffrey.channels'))->toBe([]);
});

it('loads migrations from the package migrations directory', function (): void {
    $loadedPaths = [];

    // Subclass to intercept loadMigrationsFrom while fully calling boot
    $provider = new class($loadedPaths) extends GeoffreyServiceProvider
    {
        /** @param array<string> $loadedPaths */
        public function __construct(private array &$loadedPaths)
        {
            // Skip parent constructor - no app needed for this test
        }

        protected function loadMigrationsFrom($paths): void
        {
            $this->loadedPaths = array_merge($this->loadedPaths, (array) $paths);
        }

        protected function publishes(array $paths, $groups = null): void
        {
            // no-op in test to avoid config_path() call
        }

        protected function bootChannels(): void
        {
            // no-op - no app available in this test
        }

        protected function bootConnections(): void
        {
            // no-op - no app available in this test
        }

        public function boot(): void
        {
            parent::boot();
        }
    };

    $provider->boot();

    expect($loadedPaths)->toHaveCount(1);
    expect(str_contains($loadedPaths[0], 'database/migrations'))->toBeTrue();
});

it('boots configured channels via the channel manager', function (): void {
    $bootedChannels = [];

    $app = makeApp();
    $app->make('config')->set('geoffrey.channels', [
        'my_channel' => ['driver' => 'fake'],
    ]);

    $provider = new GeoffreyServiceProvider($app);
    $provider->register();

    // Set up the channel manager with fake driver AFTER register() so it overrides the singleton
    $channelManager = new ChannelManager;
    $channelManager->extend('fake', function () use (&$bootedChannels): Channel {
        return new class($bootedChannels) implements Channel
        {
            /** @param array<string> $bootedChannels */
            public function __construct(private array &$bootedChannels) {}

            /** @param array<string, mixed> $config */
            public function register(string $name, array $config): void
            {
                $this->bootedChannels[] = $name;
            }
        };
    });

    $app->instance(ChannelManager::class, $channelManager);

    $provider->boot();

    expect($bootedChannels)->toContain('my_channel');
});

it('registers the channel manager as a singleton', function (): void {
    $app = makeApp();
    $provider = new GeoffreyServiceProvider($app);
    $provider->register();

    $manager1 = $app->make(ChannelManager::class);
    $manager2 = $app->make(ChannelManager::class);

    expect($manager1)->toBeInstanceOf(ChannelManager::class);
    expect($manager1)->toBe($manager2);
});

it('handles missing orchestrator config gracefully', function (): void {
    $app = makeApp();
    $app->make('config')->set('geoffrey.orchestrator', null);

    $provider = new GeoffreyServiceProvider($app);
    $provider->register();

    $resolved = $app->make('geoffrey.orchestrator');

    expect($resolved)->toBeNull();
});

it('binds the orchestrator from config', function (): void {
    $app = makeApp();

    $fakeOrchestrator = new class
    {
        public function handle(): string
        {
            return 'ok';
        }
    };

    $app->make('config')->set('geoffrey.orchestrator', $fakeOrchestrator::class);

    $provider = new GeoffreyServiceProvider($app);
    $provider->register();

    $resolved = $app->make('geoffrey.orchestrator');

    expect($resolved)->toBeInstanceOf($fakeOrchestrator::class);
});
