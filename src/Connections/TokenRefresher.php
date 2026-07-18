<?php

declare(strict_types=1);

namespace Geoffrey\Connections;

use Geoffrey\Models\ConnectionToken;
use Laravel\Mcp\Client\OAuth\TokenSet;

interface TokenRefresher
{
    public function refresh(ConnectionDefinition $definition, ConnectionToken $token): TokenSet;
}
