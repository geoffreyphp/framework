<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Events;

use Geoffrey\Models\User;

final readonly class ConnectionTokenInvalidated
{
    public function __construct(
        public string $connection,
        public ?User $user,
    ) {
        //
    }
}
