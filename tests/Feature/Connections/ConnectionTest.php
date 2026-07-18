<?php

declare(strict_types=1);

use Geoffrey\Connection;
use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\Shared;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\Attributes\WithToken;
use Geoffrey\Connections\ConnectionDefinition;
use Geoffrey\Connections\ConnectionManager;
use Geoffrey\Connections\ConnectionTokenStore;
use Geoffrey\Connections\Exceptions\NonOauthConnectUrlException;
use Geoffrey\Connections\Exceptions\NotConnectedException;
use Geoffrey\Connections\Exceptions\SharedConnectionUserBindingException;
use Geoffrey\Connections\Exceptions\UnboundConnectionUserException;
use Geoffrey\Connections\PendingConnection;
use Geoffrey\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\OAuth\TokenSet;

#[ConnectionName('facade-clickup')]
#[ConnectionUrl('https://api.facade-clickup.example')]
class ConnectionFacadeClickUpFixture
{
    //
}

#[ConnectionName('facade-oauth')]
#[ConnectionUrl('https://api.facade-oauth.example')]
#[WithOauth]
class ConnectionFacadeOauthFixture
{
    //
}

#[ConnectionName('facade-token')]
#[ConnectionUrl('https://api.facade-token.example')]
#[WithToken('CONNECTION_FACADE_TEST_TOKEN')]
class ConnectionFacadeTokenFixture
{
    //
}

#[ConnectionName('facade-shared')]
#[ConnectionUrl('https://api.facade-shared.example')]
#[Shared]
class ConnectionFacadeSharedFixture
{
    //
}

function connectionFacadeUser(): User
{
    return User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);
}

/**
 * Fakes the single MCP JSON-RPC endpoint, resolving the `tools/list` result
 * per bearer token via the given map (token => array of tool names).
 *
 * @param  array<string, array<int, string>>  $toolsByToken
 */
function connectionFacadeFakeMcpEndpoint(string $url, array $toolsByToken): void
{
    Http::fake(function (HttpRequest $request) use ($url, $toolsByToken) {
        if ($request->url() !== $url) {
            return Http::response('', 404);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode((string) $request->body(), true);
        $method = $payload['method'] ?? null;
        $id = $payload['id'] ?? null;

        if ($method === 'initialize') {
            return Http::response(json_encode([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => [],
                    'serverInfo' => ['name' => 'fake-server', 'version' => '1.0.0'],
                ],
            ]));
        }

        if ($method === 'notifications/initialized') {
            return Http::response('', 202);
        }

        if ($method === 'tools/list') {
            $token = str($request->header('Authorization')[0] ?? '')->after('Bearer ')->toString();
            $names = $toolsByToken[$token] ?? [];

            return Http::response(json_encode([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'tools' => array_map(
                        fn (string $name): array => ['name' => $name, 'inputSchema' => ['type' => 'object']],
                        $names,
                    ),
                ],
            ]));
        }

        return Http::response('', 404);
    });
}

it('returns a pending connection for a registered name', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeClickUpFixture::class]);

    $pending = Connection::get('facade-clickup');

    expect($pending)->toBeInstanceOf(PendingConnection::class);
});

it('throws for a per user connection used without a bound user', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeClickUpFixture::class]);

    Connection::get('facade-clickup')->connected();
})->throws(UnboundConnectionUserException::class, 'Connection [facade-clickup] requires a bound user. Call ->for($user) before using it.');

it('throws when binding a user to a shared connection', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeSharedFixture::class]);

    $user = connectionFacadeUser();

    Connection::get('facade-shared')->for($user);
})->throws(SharedConnectionUserBindingException::class, 'Connection [facade-shared] is shared and cannot be bound to a user.');

it('reports connected when a stored token exists for the bound user', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeOauthFixture::class]);

    $user = connectionFacadeUser();

    $definition = ConnectionDefinition::fromClass(ConnectionFacadeOauthFixture::class);
    app(ConnectionTokenStore::class)->store($definition, new TokenSet(accessToken: 'the-access-token'), $user);

    $connected = Connection::get('facade-oauth')->for($user)->connected();

    expect($connected)->toBeTrue();
});

it('reports connected for env token connections without a stored token', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeTokenFixture::class]);

    $user = connectionFacadeUser();

    $connected = Connection::get('facade-token')->for($user)->connected();

    expect($connected)->toBeTrue();
});

it('builds a temporary signed connect url for the bound user', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeOauthFixture::class]);

    $user = connectionFacadeUser();

    $url = Connection::get('facade-oauth')->for($user)->connectUrl();

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('user');
    expect((int) $query['user'])->toBe($user->id);
});

it('throws when requesting a connect url for a non oauth connection', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeTokenFixture::class]);

    $user = connectionFacadeUser();

    Connection::get('facade-token')->for($user)->connectUrl();
})->throws(NonOauthConnectUrlException::class, 'Connection [facade-token] does not use OAuth and has no connect url.');

it('returns tools from the mcp client with the bound user token applied', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeOauthFixture::class]);

    $user = connectionFacadeUser();

    $definition = ConnectionDefinition::fromClass(ConnectionFacadeOauthFixture::class);
    app(ConnectionTokenStore::class)->store($definition, new TokenSet(accessToken: 'user-access-token'), $user);

    connectionFacadeFakeMcpEndpoint('https://api.facade-oauth.example', [
        'user-access-token' => ['search'],
    ]);

    $tools = Connection::get('facade-oauth')->for($user)->tools();

    expect($tools)->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect($tools->keys()->all())->toBe(['search']);
});

it('resolves different tokens when switching users on the same connection', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeOauthFixture::class]);

    $userA = connectionFacadeUser();
    $userB = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T654321',
        'external_id' => 'U654321',
    ]);

    $definition = ConnectionDefinition::fromClass(ConnectionFacadeOauthFixture::class);
    $tokenStore = app(ConnectionTokenStore::class);
    $tokenStore->store($definition, new TokenSet(accessToken: 'token-for-a'), $userA);
    $tokenStore->store($definition, new TokenSet(accessToken: 'token-for-b'), $userB);

    connectionFacadeFakeMcpEndpoint('https://api.facade-oauth.example', [
        'token-for-a' => ['search'],
        'token-for-b' => ['write'],
    ]);

    $toolsForA = Connection::get('facade-oauth')->for($userA)->tools();
    $toolsForB = Connection::get('facade-oauth')->for($userB)->tools();

    expect($toolsForA->keys()->all())->toBe(['search']);
    expect($toolsForB->keys()->all())->toBe(['write']);
});

it('resets the bound user context after tools even when the client throws', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeOauthFixture::class]);

    $user = connectionFacadeUser();

    $definition = ConnectionDefinition::fromClass(ConnectionFacadeOauthFixture::class);
    app(ConnectionTokenStore::class)->store($definition, new TokenSet(accessToken: 'user-access-token'), $user);

    Http::fake([
        'https://api.facade-oauth.example' => Http::response('boom', 500),
    ]);

    try {
        Connection::get('facade-oauth')->for($user)->tools();
    } catch (Throwable) {
        //
    }

    $context = app(Geoffrey\Connections\ConnectionContext::class);

    expect($context->user('facade-oauth'))->toBeNull();
});

it('throws a not connected exception carrying the connect url when no token exists', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeOauthFixture::class]);

    $user = connectionFacadeUser();

    try {
        Connection::get('facade-oauth')->for($user)->tools();

        expect(false)->toBeTrue('Expected a NotConnectedException to be thrown.');
    } catch (NotConnectedException $exception) {
        expect($exception->connectUrl)->toBe(Connection::get('facade-oauth')->for($user)->connectUrl())
            ->and($exception->connection)->toBe('facade-oauth');
    }
});

it('translates an authorization required exception from the client into a not connected exception', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([ConnectionFacadeOauthFixture::class]);

    $user = connectionFacadeUser();

    $definition = ConnectionDefinition::fromClass(ConnectionFacadeOauthFixture::class);
    app(ConnectionTokenStore::class)->store($definition, new TokenSet(accessToken: 'revoked-access-token'), $user);

    Http::fake([
        'https://api.facade-oauth.example' => Http::response('', 401),
    ]);

    try {
        Connection::get('facade-oauth')->for($user)->tools();

        expect(false)->toBeTrue('Expected a NotConnectedException to be thrown.');
    } catch (NotConnectedException $exception) {
        expect($exception->connectUrl)->toBe(Connection::get('facade-oauth')->for($user)->connectUrl())
            ->and($exception->connection)->toBe('facade-oauth');
    }
});
