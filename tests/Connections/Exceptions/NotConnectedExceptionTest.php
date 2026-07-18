<?php

declare(strict_types=1);

use Geoffrey\Connections\Exceptions\NotConnectedException;

it('exposes the connection name on the not connected exception', function (): void {
    $exception = NotConnectedException::forName('clickup', 'https://example.com/connect/clickup');

    expect($exception->connection)->toBe('clickup')
        ->and($exception->connectUrl)->toBe('https://example.com/connect/clickup');
});
