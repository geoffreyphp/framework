<?php

declare(strict_types=1);

namespace Tests;

use Geoffrey\GeoffreyServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;

class TestCase extends Testbench
{
    use RefreshDatabase;

    private ?ConnectionResolverInterface $previousConnectionResolver = null;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            GeoffreyServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Testbench binds a global container instance, a model event
     * dispatcher, and an Eloquent connection resolver for the lifetime of
     * the test. All are static/global state that would otherwise leak into
     * the package's other test suite (a bare Capsule/Container harness with
     * no Laravel app). Capture/restore them here so that harness stays
     * isolated from this one.
     */
    protected function setUp(): void
    {
        $this->previousConnectionResolver = Model::getConnectionResolver();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Model::unsetEventDispatcher();
        Container::setInstance();

        if ($this->previousConnectionResolver instanceof ConnectionResolverInterface) {
            Model::setConnectionResolver($this->previousConnectionResolver);
        }
    }
}
