<?php

declare(strict_types=1);

namespace Geoffrey\Connections;

use Geoffrey\Models\User;

/**
 * Tracks which user is currently bound to a connection name for the
 * duration of a single operation (e.g. `PendingConnection::tools()`).
 *
 * The MCP client is memoized per connection name, so its `withToken`
 * closure cannot capture a user directly. Instead, the closure asks this
 * scoped singleton "who is bound to connection X right now?" at call time.
 */
class ConnectionContext
{
    /** @var array<string, User> */
    private array $boundUsers = [];

    public function bind(string $connection, User $user): void
    {
        $this->boundUsers[$connection] = $user;
    }

    public function user(string $connection): ?User
    {
        return $this->boundUsers[$connection] ?? null;
    }

    public function reset(string $connection): void
    {
        unset($this->boundUsers[$connection]);
    }
}
