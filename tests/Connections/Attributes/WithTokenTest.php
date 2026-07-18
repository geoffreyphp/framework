<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\WithToken;

it('exposes the env variable name via the with token attribute', function (): void {
    $attribute = new WithToken('CLICKUP_API_TOKEN');

    expect($attribute->env)->toBe('CLICKUP_API_TOKEN');
});
