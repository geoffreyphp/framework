<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\Shared;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\ConnectionManager;
use Geoffrey\Models\ConnectionToken;
use Geoffrey\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

#[ConnectionName('callback-clickup')]
#[ConnectionUrl('https://api.callback-clickup.example')]
#[WithOauth]
class OAuthCallbackHandlerClickUpFixture
{
    //
}

#[ConnectionName('callback-shared')]
#[ConnectionUrl('https://api.callback-shared.example')]
#[WithOauth]
#[Shared]
class OAuthCallbackHandlerSharedFixture
{
    //
}

function oAuthCallbackHandlerFakeOAuthEndpoints(string $host, string $accessToken): void
{
    Http::fake([
        "https://{$host}/.well-known/oauth-protected-resource" => Http::response([], 404),
        "https://{$host}/.well-known/oauth-authorization-server" => Http::response([
            'issuer' => "https://{$host}",
            'authorization_endpoint' => "https://{$host}/authorize",
            'token_endpoint' => "https://{$host}/token",
            'registration_endpoint' => "https://{$host}/register",
        ]),
        "https://{$host}/register" => Http::response([
            'client_id' => 'dynamic-client-id',
            'client_secret' => 'dynamic-client-secret',
        ]),
        "https://{$host}/token" => Http::response([
            'access_token' => $accessToken,
            'refresh_token' => 'refresh-token-value',
            'token_type' => 'Bearer',
        ]),
    ]);
}

/** @return array{0: Illuminate\Testing\TestResponse, 1: Illuminate\Testing\TestResponse} */
function oAuthCallbackHandlerDance(string $connection, ?User $user): array
{
    $signed = URL::temporarySignedRoute(
        "mcp.oauth.{$connection}.connect",
        now()->addMinutes(15),
        $user instanceof User ? ['user' => $user->id] : [],
    );

    $connectResponse = test()->get($signed);

    $redirectUrl = (string) $connectResponse->headers->get('Location');
    parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);

    $callbackResponse = test()->get("/connections/{$connection}/callback?".http_build_query([
        'code' => 'auth-code',
        'state' => $query['state'],
    ]));

    return [$connectResponse, $callbackResponse];
}

it('persists the token set for the session user on callback', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([OAuthCallbackHandlerClickUpFixture::class]);

    $user = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    oAuthCallbackHandlerFakeOAuthEndpoints('api.callback-clickup.example', 'the-access-token');

    [, $callbackResponse] = oAuthCallbackHandlerDance('callback-clickup', $user);

    $callbackResponse->assertOk();

    $token = ConnectionToken::query()->where('connection', 'callback-clickup')->first();

    expect($token)->not->toBeNull();
    expect($token->user_id)->toBe($user->id);
    expect($token->access_token)->toBe('the-access-token');
});

it('persists a shared token without a user on callback for shared connections', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([OAuthCallbackHandlerSharedFixture::class]);

    oAuthCallbackHandlerFakeOAuthEndpoints('api.callback-shared.example', 'shared-access-token');

    [, $callbackResponse] = oAuthCallbackHandlerDance('callback-shared', null);

    $callbackResponse->assertOk();

    $token = ConnectionToken::query()->where('connection', 'callback-shared')->first();

    expect($token)->not->toBeNull();
    expect($token->user_id)->toBeNull();
    expect($token->access_token)->toBe('shared-access-token');
});

it('rejects the callback when no user is in the session for a per user connection', function (): void {
    $manager = app(ConnectionManager::class);
    $manager->boot([OAuthCallbackHandlerClickUpFixture::class]);

    oAuthCallbackHandlerFakeOAuthEndpoints('api.callback-clickup.example', 'the-access-token');

    [, $callbackResponse] = oAuthCallbackHandlerDance('callback-clickup', null);

    $callbackResponse->assertForbidden();

    expect(ConnectionToken::query()->where('connection', 'callback-clickup')->exists())->toBeFalse();
});
