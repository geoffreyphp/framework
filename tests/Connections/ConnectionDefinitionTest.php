<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\Shared;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\Attributes\WithToken;
use Geoffrey\Connections\ConnectionDefinition;

it('reflects name and url from connection attributes', function (): void {
    #[ConnectionName('clickup')]
    #[ConnectionUrl('https://api.clickup.com')]
    class ConnectionDefinitionNameAndUrlFixture
    {
        //
    }

    $definition = ConnectionDefinition::fromClass(ConnectionDefinitionNameAndUrlFixture::class);

    expect($definition->name)->toBe('clickup');
    expect($definition->url)->toBe('https://api.clickup.com');
});

it('marks the definition as oauth when the with oauth attribute is present', function (): void {
    #[ConnectionName('clickup')]
    #[ConnectionUrl('https://api.clickup.com')]
    #[WithOauth]
    class ConnectionDefinitionOauthFixture
    {
        //
    }

    $definition = ConnectionDefinition::fromClass(ConnectionDefinitionOauthFixture::class);

    expect($definition->isOauth())->toBeTrue();
});

it('carries the env key when the with token attribute is present', function (): void {
    #[ConnectionName('clickup')]
    #[ConnectionUrl('https://api.clickup.com')]
    #[WithToken('CLICKUP_TOKEN')]
    class ConnectionDefinitionTokenFixture
    {
        //
    }

    $definition = ConnectionDefinition::fromClass(ConnectionDefinitionTokenFixture::class);

    expect($definition->tokenFromEnv())->toBe('CLICKUP_TOKEN');
});

it('marks the definition as shared when the shared attribute is present', function (): void {
    #[ConnectionName('clickup')]
    #[ConnectionUrl('https://api.clickup.com')]
    #[Shared]
    class ConnectionDefinitionSharedFixture
    {
        //
    }

    $definition = ConnectionDefinition::fromClass(ConnectionDefinitionSharedFixture::class);

    expect($definition->isShared())->toBeTrue();
});

it('defaults to per user and no auth when only name and url are present', function (): void {
    #[ConnectionName('clickup')]
    #[ConnectionUrl('https://api.clickup.com')]
    class ConnectionDefinitionDefaultsFixture
    {
        //
    }

    $definition = ConnectionDefinition::fromClass(ConnectionDefinitionDefaultsFixture::class);

    expect($definition->isOauth())->toBeFalse();
    expect($definition->isShared())->toBeFalse();
    expect($definition->tokenFromEnv())->toBeNull();
});

it('throws when the connection name attribute is missing', function (): void {
    #[ConnectionUrl('https://api.clickup.com')]
    class ConnectionDefinitionMissingNameFixture
    {
        //
    }

    ConnectionDefinition::fromClass(ConnectionDefinitionMissingNameFixture::class);
})->throws(
    InvalidArgumentException::class,
    'Connection [ConnectionDefinitionMissingNameFixture] is missing the #[ConnectionName] attribute.',
);

it('throws when the connection url attribute is missing', function (): void {
    #[ConnectionName('clickup')]
    class ConnectionDefinitionMissingUrlFixture
    {
        //
    }

    ConnectionDefinition::fromClass(ConnectionDefinitionMissingUrlFixture::class);
})->throws(
    InvalidArgumentException::class,
    'Connection [ConnectionDefinitionMissingUrlFixture] is missing the #[ConnectionUrl] attribute.',
);

it('throws when both oauth and token attributes are declared', function (): void {
    #[ConnectionName('clickup')]
    #[ConnectionUrl('https://api.clickup.com')]
    #[WithOauth]
    #[WithToken('CLICKUP_TOKEN')]
    class ConnectionDefinitionConflictingAuthFixture
    {
        //
    }

    ConnectionDefinition::fromClass(ConnectionDefinitionConflictingAuthFixture::class);
})->throws(
    InvalidArgumentException::class,
    'Connection [ConnectionDefinitionConflictingAuthFixture] cannot declare both #[WithOauth] and #[WithToken].',
);
