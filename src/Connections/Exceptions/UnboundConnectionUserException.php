<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Exceptions;

use InvalidArgumentException;

final class UnboundConnectionUserException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self("Connection [{$name}] requires a bound user. Call ->for(\$user) before using it.");
    }
}
