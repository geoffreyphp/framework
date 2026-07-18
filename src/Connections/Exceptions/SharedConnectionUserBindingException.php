<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Exceptions;

use InvalidArgumentException;

final class SharedConnectionUserBindingException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self("Connection [{$name}] is shared and cannot be bound to a user.");
    }
}
