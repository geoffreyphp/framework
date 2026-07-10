<?php

declare(strict_types=1);

namespace Geoffrey\Channels;

use Closure;
use Geoffrey\Contracts\Channel;
use InvalidArgumentException;

class ChannelManager
{
    /** @var array<string, string|Closure> */
    private array $drivers = [];

    public function extend(string $driver, string|Closure $creator): void
    {
        $this->drivers[$driver] = $creator;
    }

    /** @param array<string, array<string, mixed>> $channels */
    public function boot(array $channels): void
    {
        foreach ($channels as $name => $config) {
            $driver = $config['driver'];

            if (! is_string($driver)) {
                throw new InvalidArgumentException("Channel [{$name}] driver must be a string.");
            }

            if (! isset($this->drivers[$driver])) {
                throw new InvalidArgumentException("Driver [{$driver}] is not registered.");
            }

            $creator = $this->drivers[$driver];

            $channel = $creator instanceof Closure ? $creator() : new $creator;

            /** @var Channel $channel */
            $channel->register($name, $config);
        }
    }
}
