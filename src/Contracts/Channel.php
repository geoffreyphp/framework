<?php

declare(strict_types=1);

namespace Geoffrey\Contracts;

interface Channel
{
    /** @param array<string, mixed> $config */
    public function register(string $name, array $config): void;
}
