<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ConnectionUrl
{
    public function __construct(public string $value)
    {
        //
    }
}
