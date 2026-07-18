<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\Shared;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\ConnectionDefinition;
use Geoffrey\Connections\ConnectionTokenStore;
use Geoffrey\Connections\Events\ConnectionTokenInvalidated;
use Geoffrey\Connections\TokenRefresher;
use Geoffrey\Models\ConnectionToken;
use Geoffrey\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Client\OAuth\TokenSet;

#[ConnectionName('clickup')]
#[ConnectionUrl('https://api.clickup.com')]
#[WithOauth]
class ConnectionTokenStoreClickUpFixture
{
    //
}

#[ConnectionName('shared-service')]
#[ConnectionUrl('https://api.shared.example')]
#[WithOauth]
#[Shared]
class ConnectionTokenStoreSharedFixture
{
    //
}

function connectionTokenStoreUser(): User
{
    return User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);
}

final class ConnectionTokenStoreFakeRefresher implements TokenRefresher
{
    /** @param  (callable(ConnectionDefinition, ConnectionToken): TokenSet)|null  $callback */
    public function __construct(
        private $callback = null,
    ) {
        //
    }

    public function refresh(ConnectionDefinition $definition, ConnectionToken $token): TokenSet
    {
        if ($this->callback !== null) {
            return ($this->callback)($definition, $token);
        }

        throw new RuntimeException('Refresh failed.');
    }
}

it('stores a token set for a connection and user', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();
    $store = new ConnectionTokenStore;

    $tokenSet = new TokenSet(
        accessToken: 'access-token-value',
        refreshToken: 'refresh-token-value',
    );

    $token = $store->store($definition, $tokenSet, $user);

    expect($token)->toBeInstanceOf(ConnectionToken::class);
    expect($token->connection)->toBe('clickup');
    expect($token->user_id)->toBe($user->id);
    expect($token->access_token)->toBe('access-token-value');
    expect($token->refresh_token)->toBe('refresh-token-value');
});

it('updates the existing row when storing again for the same connection and user', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();
    $store = new ConnectionTokenStore;

    $store->store($definition, new TokenSet(accessToken: 'first-access-token'), $user);
    $token = $store->store($definition, new TokenSet(accessToken: 'second-access-token'), $user);

    expect(ConnectionToken::count())->toBe(1);
    expect($token->access_token)->toBe('second-access-token');
});

it('stores shared connection tokens without a user', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreSharedFixture::class);
    $user = connectionTokenStoreUser();
    $store = new ConnectionTokenStore;

    $token = $store->store($definition, new TokenSet(accessToken: 'shared-access-token'), $user);

    expect($token->connection)->toBe('shared-service');
    expect($token->user_id)->toBeNull();
    expect($token->access_token)->toBe('shared-access-token');
});

it('updates the existing shared row instead of duplicating when storing again for the same connection', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreSharedFixture::class);
    $store = new ConnectionTokenStore;

    $store->store($definition, new TokenSet(accessToken: 'first-shared-access-token'), null);
    $token = $store->store($definition, new TokenSet(accessToken: 'second-shared-access-token'), null);

    expect(ConnectionToken::count())->toBe(1);
    expect($token->access_token)->toBe('second-shared-access-token');
});

it('retrieves the access token for a connection and user', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();
    $store = new ConnectionTokenStore;

    $store->store($definition, new TokenSet(accessToken: 'access-token-value'), $user);

    expect($store->retrieve($definition, $user))->toBe('access-token-value');
});

it('returns null when no token exists', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();
    $store = new ConnectionTokenStore;

    expect($store->retrieve($definition, $user))->toBeNull();
});

it('refreshes an expired token and persists the new token set', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();

    $stored = ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'expired-access-token',
        'refresh_token' => 'refresh-token-value',
        'expires_at' => now()->subMinute(),
    ]);

    $freshToken = new TokenSet(
        accessToken: 'fresh-access-token',
        refreshToken: 'fresh-refresh-token',
        expiresAt: time() + 3600,
    );

    $refresher = new ConnectionTokenStoreFakeRefresher(
        fn (ConnectionDefinition $definitionArg, ConnectionToken $tokenArg): TokenSet => $freshToken,
    );

    $store = new ConnectionTokenStore($refresher);

    $accessToken = $store->retrieve($definition, $user);

    expect($accessToken)->toBe('fresh-access-token');

    $stored->refresh();

    expect($stored->access_token)->toBe('fresh-access-token');
    expect($stored->refresh_token)->toBe('fresh-refresh-token');
});

it('deletes the token and returns null when refresh fails', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();

    $stored = ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'expired-access-token',
        'refresh_token' => 'refresh-token-value',
        'expires_at' => now()->subMinute(),
    ]);

    $store = new ConnectionTokenStore(new ConnectionTokenStoreFakeRefresher);

    $accessToken = $store->retrieve($definition, $user);

    expect($accessToken)->toBeNull();
    expect(ConnectionToken::find($stored->id))->toBeNull();
});

it('dispatches a connection token invalidated event when refresh fails', function (): void {
    Event::fake();

    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();

    ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'expired-access-token',
        'refresh_token' => 'refresh-token-value',
        'expires_at' => now()->subMinute(),
    ]);

    $store = new ConnectionTokenStore(new ConnectionTokenStoreFakeRefresher);

    $store->retrieve($definition, $user);

    Event::assertDispatched(
        ConnectionTokenInvalidated::class,
        fn (ConnectionTokenInvalidated $event): bool => $event->connection === 'clickup' && $event->user?->id === $user->id,
    );
});

it('does not attempt refresh when the expired token has no refresh token', function (): void {
    $definition = ConnectionDefinition::fromClass(ConnectionTokenStoreClickUpFixture::class);
    $user = connectionTokenStoreUser();

    $stored = ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'expired-access-token',
        'refresh_token' => null,
        'expires_at' => now()->subMinute(),
    ]);

    $refresher = new ConnectionTokenStoreFakeRefresher(
        fn (ConnectionDefinition $definitionArg, ConnectionToken $tokenArg): TokenSet => throw new RuntimeException('Refresh should not be called.'),
    );

    $store = new ConnectionTokenStore($refresher);

    $accessToken = $store->retrieve($definition, $user);

    expect($accessToken)->toBeNull();
    expect(ConnectionToken::find($stored->id))->not->toBeNull();
});
