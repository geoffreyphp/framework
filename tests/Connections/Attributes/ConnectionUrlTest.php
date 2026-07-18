<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\ConnectionUrl;

it('exposes the connection url via the connection url attribute', function (): void {
    $attribute = new ConnectionUrl('https://api.clickup.com');

    expect($attribute->value)->toBe('https://api.clickup.com');
});
