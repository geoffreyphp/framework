<?php

declare(strict_types=1);

namespace Geoffrey\Connections;

use Geoffrey\Connections\Events\ConnectionTokenInvalidated;
use Geoffrey\Models\ConnectionToken;
use Geoffrey\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Client\OAuth\TokenSet;
use Throwable;

final readonly class ConnectionTokenStore
{
    public function __construct(
        private ?TokenRefresher $refresher = null,
    ) {
        //
    }

    public function store(ConnectionDefinition $definition, TokenSet $token, ?User $user): ConnectionToken
    {
        $userId = $this->resolveUserId($definition, $user);

        return ConnectionToken::updateOrCreate(
            ['connection' => $definition->name, 'user_id' => $userId],
            $this->attributesFromTokenSet($token),
        );
    }

    public function retrieve(ConnectionDefinition $definition, ?User $user): ?string
    {
        $token = $this->find($definition, $user);

        if (! $token instanceof ConnectionToken) {
            return null;
        }

        if ($token->expired()) {
            return $this->refresh($definition, $token, $user);
        }

        return $token->access_token;
    }

    private function refresh(ConnectionDefinition $definition, ConnectionToken $token, ?User $user): ?string
    {
        if (! $this->refresher instanceof TokenRefresher || $token->refresh_token === null) {
            return null;
        }

        try {
            $fresh = $this->refresher->refresh($definition, $token);
        } catch (Throwable) {
            $token->delete();

            Event::dispatch(new ConnectionTokenInvalidated($definition->name, $user));

            return null;
        }

        $token->update($this->attributesFromTokenSet($fresh));

        return $fresh->accessToken;
    }

    /** @return array<string, mixed> */
    private function attributesFromTokenSet(TokenSet $token): array
    {
        return [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_at' => $token->expiresAt !== null ? Carbon::createFromTimestamp($token->expiresAt) : null,
            'token_type' => $token->tokenType,
            'scope' => $token->scope,
            'client_id' => $token->clientId,
            'client_secret' => $token->clientSecret,
        ];
    }

    private function find(ConnectionDefinition $definition, ?User $user): ?ConnectionToken
    {
        $userId = $this->resolveUserId($definition, $user);

        return ConnectionToken::query()
            ->where('connection', $definition->name)
            ->where('user_id', $userId)
            ->first();
    }

    private function resolveUserId(ConnectionDefinition $definition, ?User $user): ?int
    {
        return $definition->isShared() ? null : $user?->id;
    }
}
