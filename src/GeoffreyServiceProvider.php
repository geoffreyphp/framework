<?php

declare(strict_types=1);

namespace Geoffrey;

use Geoffrey\Channels\ChannelManager;
use Geoffrey\Channels\Slack\Slack;
use Geoffrey\Database\Console\MigrateCommand;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class GeoffreyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/geoffrey.php',
            'geoffrey',
        );

        $this->app->singleton(ChannelManager::class);

        $this->app->extend(BaseMigrateCommand::class, fn (): MigrateCommand => new MigrateCommand($this->app['migrator'], $this->app[Dispatcher::class]));

        $this->app->make(ChannelManager::class)->extend('slack', Slack::class);

        $this->app->bind('geoffrey.orchestrator', function () {
            /** @var Repository $config */
            $config = $this->app->make('config');
            $orchestratorClass = $config->get('geoffrey.orchestrator');

            if (! is_string($orchestratorClass) || $orchestratorClass === '') {
                return null;
            }

            return $this->app->make($orchestratorClass);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app instanceof Application) {
            $this->publishes([
                __DIR__.'/../config/geoffrey.php' => $this->app->configPath('geoffrey.php'),
            ], 'geoffrey-config');
        }

        $this->bootChannels();
    }

    protected function bootChannels(): void
    {
        /** @var Repository $config */
        $config = $this->app->make('config');

        /** @var array<string, array<string, mixed>> $channels */
        $channels = $config->get('geoffrey.channels', []);

        $this->app->make(ChannelManager::class)->boot($channels);
    }
}
