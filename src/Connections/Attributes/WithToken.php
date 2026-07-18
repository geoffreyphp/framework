<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class WithToken
{
    public function __construct(public string $env)
    {
        //
    }
}
