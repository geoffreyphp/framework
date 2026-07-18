<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\ConnectionManager;
use Geoffrey\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

#[ConnectionName('oauth-routes-clickup')]
#[ConnectionUrl('https://api.clickup.com')]
#[WithOauth]
class ConnectionManagerOAuthRoutesClickUpFixture
{
    //
}

#[ConnectionName('oauth-routes-plain')]
#[ConnectionUrl('https://api.plain.example')]
class ConnectionManagerOAuthRoutesPlainFixture
{
    //
}

it('registers connect and callback routes for each oauth connection', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerOAuthRoutesClickUpFixture::class]);

    expect(Route::has('mcp.oauth.oauth-routes-clickup.connect'))->toBeTrue();
    expect(Route::has('mcp.oauth.oauth-routes-clickup.callback'))->toBeTrue();
});

it('does not register routes for non oauth connections', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerOAuthRoutesPlainFixture::class]);

    expect(Route::has('mcp.oauth.oauth-routes-plain.connect'))->toBeFalse();
    expect(Route::has('mcp.oauth.oauth-routes-plain.callback'))->toBeFalse();
});

it('rejects a connect request with an invalid signature', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerOAuthRoutesClickUpFixture::class]);

    $response = $this->get('/connections/oauth-routes-clickup/connect?signature=invalid');

    $response->assertForbidden();
});

it('rejects a connect request with an expired signature', function (): void {
    $manager = new ConnectionManager;

    $manager->boot([ConnectionManagerOAuthRoutesClickUpFixture::class]);

    $signed = URL::temporarySignedRoute('mcp.oauth.oauth-routes-clickup.connect', now()->subMinute());

    $response = $this->get($signed);

    $response->assertForbidden();
});

it('uses the configured connect url ttl when generating signed urls', function (): void {
    config(['geoffrey.connect_url_ttl' => 5]);

    $manager = new ConnectionManager;
    $manager->boot([ConnectionManagerOAuthRoutesClickUpFixture::class]);

    $definition = $manager->definition('oauth-routes-clickup');

    Carbon\Carbon::setTestNow('2026-07-17 12:00:00');

    $url = $manager->connectUrl($definition);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('expires');
    expect((int) $query['expires'])->toBe(Carbon\Carbon::parse('2026-07-17 12:05:00')->getTimestamp());

    Carbon\Carbon::setTestNow();
});

it('stashes the user id in the session on a validly signed connect request', function (): void {
    $manager = new ConnectionManager;
    $manager->boot([ConnectionManagerOAuthRoutesClickUpFixture::class]);

    $user = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    $signed = URL::temporarySignedRoute(
        'mcp.oauth.oauth-routes-clickup.connect',
        now()->addMinutes(15),
        ['user' => $user->id],
    );

    $response = $this->get($signed);

    $response->assertSessionHas(Geoffrey\Connections\Http\Middleware\ValidateSignedConnectRequest::sessionKey('oauth-routes-clickup'), $user->id);
});
