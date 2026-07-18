<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\Shared;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\Attributes\WithToken;

it('targets classes only for all connection attributes', function (string $attribute): void {
    $reflection = new ReflectionClass($attribute);
    $attributeInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

    expect($attributeInstance->flags)->toBe(Attribute::TARGET_CLASS);
})->with([
    ConnectionName::class,
    ConnectionUrl::class,
    WithOauth::class,
    WithToken::class,
    Shared::class,
]);
