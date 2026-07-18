<?php

declare(strict_types=1);

use Geoffrey\Models\ConnectionToken;
use Geoffrey\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores a token for a connection and user', function (): void {
    $user = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    $token = ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'access-token-value',
    ]);

    expect($token->id)->toBeInt();
    expect($token->connection)->toBe('clickup');
    expect($token->user_id)->toBe($user->id);
    expect($token->access_token)->toBe('access-token-value');
});

it('stores a shared token with a null user id', function (): void {
    $token = ConnectionToken::create([
        'connection' => 'clickup',
        'access_token' => 'shared-access-token-value',
    ]);

    expect($token->id)->toBeInt();
    expect($token->connection)->toBe('clickup');
    expect($token->user_id)->toBeNull();
    expect($token->access_token)->toBe('shared-access-token-value');
});

it('encrypts the access token refresh token and client secret at rest', function (): void {
    $token = ConnectionToken::create([
        'connection' => 'clickup',
        'access_token' => 'plain-access-token',
        'refresh_token' => 'plain-refresh-token',
        'client_secret' => 'plain-client-secret',
    ]);

    $raw = DB::table('connection_tokens')->where('id', $token->id)->first();

    expect($raw->access_token)->not->toBe('plain-access-token');
    expect($raw->refresh_token)->not->toBe('plain-refresh-token');
    expect($raw->client_secret)->not->toBe('plain-client-secret');
});

it('enforces uniqueness per connection and user', function (): void {
    $user = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'access-token-value',
    ]);

    expect(fn () => ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'another-access-token-value',
    ]))->toThrow(QueryException::class);
});

it('reports expired when expires at is in the past', function (): void {
    $token = ConnectionToken::create([
        'connection' => 'clickup',
        'access_token' => 'access-token-value',
        'expires_at' => now()->subMinute(),
    ]);

    expect($token->expired())->toBeTrue();
});

it('reports not expired when expires at is null or in the future', function (): void {
    $tokenWithNullExpiry = ConnectionToken::create([
        'connection' => 'clickup',
        'access_token' => 'access-token-value',
    ]);

    $tokenWithFutureExpiry = ConnectionToken::create([
        'connection' => 'linear',
        'access_token' => 'access-token-value',
        'expires_at' => now()->addMinute(),
    ]);

    expect($tokenWithNullExpiry->expired())->toBeFalse();
    expect($tokenWithFutureExpiry->expired())->toBeFalse();
});

it('deletes tokens when the owning user is deleted', function (): void {
    $user = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    $token = ConnectionToken::create([
        'connection' => 'clickup',
        'user_id' => $user->id,
        'access_token' => 'access-token-value',
    ]);

    $user->delete();

    expect(ConnectionToken::find($token->id))->toBeNull();
});
