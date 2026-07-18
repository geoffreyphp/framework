<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Http;

use Geoffrey\Connections\ConnectionManager;
use Geoffrey\Connections\ConnectionTokenStore;
use Geoffrey\Connections\Http\Middleware\ValidateSignedConnectRequest;
use Geoffrey\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Client\OAuth\TokenSet;

final readonly class OAuthCallbackHandler
{
    public function __construct(
        private ConnectionManager $connections,
        private ConnectionTokenStore $tokens,
        private Request $request,
    ) {
        //
    }

    public function __invoke(string $client, TokenSet $token): Response
    {
        $definition = $this->connections->definition($client);

        $user = $this->sessionUser($client);

        if (! $definition->isShared() && ! $user instanceof User) {
            abort(403);
        }

        $this->tokens->store($definition, $token, $user);

        return response('Connected — you can close this tab.');
    }

    private function sessionUser(string $client): ?User
    {
        $userId = $this->request->session()->get(ValidateSignedConnectRequest::sessionKey($client));

        if (! is_int($userId)) {
            return null;
        }

        return User::query()->find($userId);
    }
}
