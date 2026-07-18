<?php

declare(strict_types=1);

namespace Geoffrey\Connections;

use Geoffrey\Models\ConnectionToken;
use InvalidArgumentException;
use Laravel\Mcp\Client\OAuth\TokenSet;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\WebClient;

final class McpOAuthTokenRefresher implements TokenRefresher
{
    public function refresh(ConnectionDefinition $definition, ConnectionToken $token): TokenSet
    {
        $client = Mcp::client($definition->name);

        if (! $client instanceof WebClient) {
            throw new InvalidArgumentException("Connection [{$definition->name}] does not support OAuth token refresh.");
        }

        return $client->oAuthClient()->refreshCredentials(
            refreshToken: (string) $token->refresh_token,
            clientId: $token->client_id,
            clientSecret: $token->client_secret,
        );
    }
}
