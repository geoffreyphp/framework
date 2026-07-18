<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionName;

it('exposes the connection name via the connection name attribute', function (): void {
    $attribute = new ConnectionName('clickup');

    expect($attribute->value)->toBe('clickup');
});
