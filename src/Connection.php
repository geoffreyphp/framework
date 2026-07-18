<?php

declare(strict_types=1);

namespace Geoffrey;

use Geoffrey\Connections\ConnectionContext;
use Geoffrey\Connections\ConnectionManager;
use Geoffrey\Connections\ConnectionTokenStore;
use Geoffrey\Connections\PendingConnection;

final class Connection
{
    public static function get(string $name): PendingConnection
    {
        $manager = app(ConnectionManager::class);

        return new PendingConnection(
            $manager->definition($name),
            $manager,
            app(ConnectionTokenStore::class),
            app(ConnectionContext::class),
        );
    }
}
