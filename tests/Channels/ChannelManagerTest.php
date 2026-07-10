<?php

declare(strict_types=1);

use Geoffrey\Channels\ChannelManager;
use Geoffrey\Contracts\Channel;

it('resolves a channel driver to its implementing class', function (): void {
    $manager = new ChannelManager;

    $fakeChannel = new class implements Channel
    {
        public function register(string $name, array $config): void {}
    };

    $manager->extend('slack', $fakeChannel::class);

    $channels = ['slack_main' => ['driver' => 'slack', 'token' => 'abc']];
    $manager->boot($channels);

    // If no exception is thrown, the driver resolved successfully
    expect(true)->toBeTrue();
});

it('calls register on the resolved channel with name and config', function (): void {
    $manager = new ChannelManager;

    $registered = [];

    $manager->extend('slack', function () use (&$registered): Channel {
        return new class($registered) implements Channel
        {
            /** @param array<mixed> $registered */
            public function __construct(private array &$registered) {}

            /** @param array<string, mixed> $config */
            public function register(string $name, array $config): void
            {
                $this->registered[] = ['name' => $name, 'config' => $config];
            }
        };
    });

    $channels = ['slack_main' => ['driver' => 'slack', 'token' => 'abc']];
    $manager->boot($channels);

    expect($registered)->toHaveCount(1);
    expect($registered[0]['name'])->toBe('slack_main');
    expect($registered[0]['config'])->toBe(['driver' => 'slack', 'token' => 'abc']);
});

it('supports multiple channels with the same driver', function (): void {
    $manager = new ChannelManager;

    $registered = [];

    $manager->extend('slack', function () use (&$registered): Channel {
        return new class($registered) implements Channel
        {
            /** @param array<mixed> $registered */
            public function __construct(private array &$registered) {}

            /** @param array<string, mixed> $config */
            public function register(string $name, array $config): void
            {
                $this->registered[] = ['name' => $name, 'config' => $config];
            }
        };
    });

    $channels = [
        'slack_main' => ['driver' => 'slack', 'token' => 'abc'],
        'slack_secondary' => ['driver' => 'slack', 'token' => 'xyz'],
    ];
    $manager->boot($channels);

    expect($registered)->toHaveCount(2);
    expect($registered[0]['name'])->toBe('slack_main');
    expect($registered[1]['name'])->toBe('slack_secondary');
    expect($registered[1]['config']['token'])->toBe('xyz');
});

it('throws an exception for an unknown driver', function (): void {
    $manager = new ChannelManager;

    $channels = ['telegram_main' => ['driver' => 'telegram', 'bot_token' => 'secret']];

    expect(fn () => $manager->boot($channels))
        ->toThrow(InvalidArgumentException::class, 'Driver [telegram] is not registered.');
});

it('allows registering custom drivers', function (): void {
    $manager = new ChannelManager;

    $registered = [];

    $manager->extend('custom', function () use (&$registered): Channel {
        return new class($registered) implements Channel
        {
            /** @param array<mixed> $registered */
            public function __construct(private array &$registered) {}

            /** @param array<string, mixed> $config */
            public function register(string $name, array $config): void
            {
                $this->registered[] = $name;
            }
        };
    });

    $channels = ['my_custom' => ['driver' => 'custom', 'api_key' => 'secret']];
    $manager->boot($channels);

    expect($registered)->toHaveCount(1);
    expect($registered[0])->toBe('my_custom');
});

it('boots all configured channels from config', function (): void {
    $manager = new ChannelManager;

    $booted = [];

    $makeChannel = function () use (&$booted): Channel {
        return new class($booted) implements Channel
        {
            /** @param array<mixed> $booted */
            public function __construct(private array &$booted) {}

            /** @param array<string, mixed> $config */
            public function register(string $name, array $config): void
            {
                $this->booted[$name] = $config;
            }
        };
    };

    $manager->extend('slack', $makeChannel);
    $manager->extend('telegram', $makeChannel);

    $config = [
        'slack_main' => ['driver' => 'slack', 'token' => 'slack-token-1'],
        'slack_secondary' => ['driver' => 'slack', 'token' => 'slack-token-2'],
        'telegram_bot' => ['driver' => 'telegram', 'bot_token' => 'tg-token'],
    ];

    $manager->boot($config);

    expect($booted)->toHaveCount(3);
    expect($booted)->toHaveKey('slack_main');
    expect($booted)->toHaveKey('slack_secondary');
    expect($booted)->toHaveKey('telegram_bot');
    expect($booted['slack_main']['token'])->toBe('slack-token-1');
    expect($booted['slack_secondary']['token'])->toBe('slack-token-2');
    expect($booted['telegram_bot']['bot_token'])->toBe('tg-token');
});
