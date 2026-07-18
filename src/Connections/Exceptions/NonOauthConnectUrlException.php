<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Exceptions;

use InvalidArgumentException;

final class NonOauthConnectUrlException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self("Connection [{$name}] does not use OAuth and has no connect url.");
    }
}
