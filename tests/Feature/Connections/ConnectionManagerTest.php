<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\Attributes\WithToken;
use Geoffrey\Connections\ConnectionDefinition;
use Geoffrey\Connections\ConnectionManager;
use Geoffrey\Connections\Exceptions\UnknownConnectionException;
use Geoffrey\GeoffreyServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\WebClient;

#[ConnectionName('clickup')]
#[ConnectionUrl('https://api.clickup.com')]
class ConnectionManagerClickUpFixture
{
    //
}

#[ConnectionName('linear')]
#[ConnectionUrl('https://api.linear.app')]
class ConnectionManagerLinearFixture
{
    //
}

#[ConnectionName('clickup')]
#[ConnectionUrl('https://api.clickup.com/v2')]
class ConnectionManagerDuplicateClickUpFixture
{
    //
}

#[ConnectionName('clickup-oauth')]
#[ConnectionUrl('https://api.clickup.com')]
#[WithOauth]
class ConnectionManagerOauthFixture
{
    //
}

#[ConnectionName('clickup-token')]
#[ConnectionUrl('https://api.clickup.com')]
#[WithToken('CONNECTION_MANAGER_TEST_TOKEN')]
class ConnectionManagerTokenFixture
{
    //
}

it('registers an mcp client for each configured connection', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerClickUpFixture::class]);

    expect(Mcp::client('clickup'))->toBeInstanceOf(Laravel\Mcp\Client::class);
});

it('keys definitions by connection name', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerClickUpFixture::class, ConnectionManagerLinearFixture::class]);

    $definitions = $manager->definitions();

    expect($definitions)->toHaveKeys(['clickup', 'linear']);
    expect($definitions['clickup']->name)->toBe('clickup');
    expect($definitions['linear']->name)->toBe('linear');
});

it('throws for an unknown connection name', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerClickUpFixture::class]);

    $manager->definition('unknown');
})->throws(UnknownConnectionException::class, 'Connection [unknown] is not registered.');

it('throws when two connections declare the same name', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerClickUpFixture::class, ConnectionManagerDuplicateClickUpFixture::class]);
})->throws(InvalidArgumentException::class, 'Connection [clickup] is already registered.');

it('boots connections from the geoffrey connections config', function (): void {
    config(['geoffrey.connections' => [ConnectionManagerClickUpFixture::class]]);

    $provider = $this->app->getProvider(GeoffreyServiceProvider::class);

    expect($provider)->toBeInstanceOf(GeoffreyServiceProvider::class);

    $provider->boot();

    expect(app(ConnectionManager::class)->definition('clickup')->url)->toBe('https://api.clickup.com');
});

it('registers no clients when the connections config is empty', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([]);

    expect($manager->definitions())->toBe([]);
});

it('configures oauth on the mcp client for oauth connections', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerOauthFixture::class]);

    $client = Mcp::client('clickup-oauth');

    expect($client)->toBeInstanceOf(WebClient::class);
    expect(fn () => $client->oAuthClient())->not->toThrow('No OAuth configuration found. Call withOAuth() before oAuthClient().');
});

it('resolves the token from the env variable for token connections', function (): void {
    putenv('CONNECTION_MANAGER_TEST_TOKEN=env-token-value');

    $manager = new ConnectionManager;
    $definition = ConnectionDefinition::fromClass(ConnectionManagerTokenFixture::class);

    $client = $manager->makeClient($definition);

    expect($client->__serialize()['transport']['token'])->toBe('env-token-value');

    putenv('CONNECTION_MANAGER_TEST_TOKEN');
});

it('resolves the oauth token via the injected token resolver', function (): void {
    $manager = new ConnectionManager(fn (ConnectionDefinition $definition): string => "resolved-for-{$definition->name}");
    $definition = ConnectionDefinition::fromClass(ConnectionManagerOauthFixture::class);

    $client = $manager->makeClient($definition);

    expect($client->__serialize()['transport']['token'])->toBe('resolved-for-clickup-oauth');
});

it('resolves an empty oauth token when no resolver is injected', function (): void {
    $manager = new ConnectionManager;
    $definition = ConnectionDefinition::fromClass(ConnectionManagerOauthFixture::class);

    $client = $manager->makeClient($definition);

    expect($client->__serialize()['transport']['token'])->toBe('');
});

it('builds a client without a token when the connection has no auth', function (): void {
    $manager = new ConnectionManager;
    $definition = ConnectionDefinition::fromClass(ConnectionManagerClickUpFixture::class);

    $client = $manager->makeClient($definition);

    expect($client->__serialize()['transport']['token'])->toBeNull();
});
