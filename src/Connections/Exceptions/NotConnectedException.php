<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Exceptions;

use RuntimeException;

final class NotConnectedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $connectUrl,
    ) {
        parent::__construct($message);
    }

    public static function forName(string $name, string $connectUrl): self
    {
        return new self("Connection [{$name}] is not connected. Visit the connect url to authorize it.", $connectUrl);
    }
}
