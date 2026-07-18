<?php

declare(strict_types=1);

namespace Geoffrey\Connections;

use Closure;
use Geoffrey\Connections\Exceptions\UnknownConnectionException;
use Geoffrey\Connections\Http\Middleware\ValidateSignedConnectRequest;
use Geoffrey\Connections\Http\OAuthCallbackHandler;
use Geoffrey\Models\User;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Laravel\Mcp\Client;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\WebClient;

class ConnectionManager
{
    /** @var array<string, ConnectionDefinition> */
    private array $definitions = [];

    /**
     * The token store lands in a later task; injecting a resolver here keeps
     * this class decoupled from its concrete implementation.
     *
     * @param  (Closure(ConnectionDefinition): string)|null  $oauthTokenResolver
     */
    public function __construct(
        private readonly ?Closure $oauthTokenResolver = null,
    ) {
        //
    }

    /** @param array<int, class-string> $connections */
    public function boot(array $connections): void
    {
        foreach ($connections as $class) {
            $definition = ConnectionDefinition::fromClass($class);

            if (isset($this->definitions[$definition->name])) {
                throw new InvalidArgumentException("Connection [{$definition->name}] is already registered.");
            }

            $this->definitions[$definition->name] = $definition;

            Mcp::registerClient($definition->name, fn (): WebClient => $this->makeClient($definition));

            if ($definition->isOauth()) {
                $this->registerOauthRoutes($definition);
            }
        }
    }

    private function registerOauthRoutes(ConnectionDefinition $definition): void
    {
        Mcp::oAuthRoutesFor(
            client: $definition->name,
            handler: [OAuthCallbackHandler::class, '__invoke'],
            middleware: ['web', ValidateSignedConnectRequest::class],
            connectUri: "connections/{$definition->name}/connect",
            callbackUri: "connections/{$definition->name}/callback",
        );
    }

    /** @return array<string, ConnectionDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function definition(string $name): ConnectionDefinition
    {
        return $this->definitions[$name] ?? throw UnknownConnectionException::forName($name);
    }

    public function connectUrl(ConnectionDefinition $definition, ?User $user = null): string
    {
        /** @var int $ttl */
        $ttl = config('geoffrey.connect_url_ttl', 15);

        return URL::temporarySignedRoute(
            "mcp.oauth.{$definition->name}.connect",
            now()->addMinutes($ttl),
            $user instanceof User ? ['user' => $user->id] : [],
        );
    }

    public function makeClient(ConnectionDefinition $definition): WebClient
    {
        $client = Client::web($definition->url);

        if ($definition->isOauth()) {
            return $client->withOAuth()
                ->withToken(fn (): string => $this->resolveOauthToken($definition));
        }

        $tokenEnv = $definition->tokenFromEnv();

        if ($tokenEnv !== null) {
            return $client->withToken(fn (): string => (string) getenv($tokenEnv));
        }

        return $client;
    }

    private function resolveOauthToken(ConnectionDefinition $definition): string
    {
        if (! $this->oauthTokenResolver instanceof Closure) {
            return '';
        }

        return ($this->oauthTokenResolver)($definition);
    }
}
