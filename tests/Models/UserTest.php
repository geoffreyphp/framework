<?php

declare(strict_types=1);

use Geoffrey\Models\User;
use Illuminate\Database\QueryException;

it('creates a user with channel, channel_account_id, and external_id', function (): void {
    $user = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    expect($user->id)->toBeInt();
    expect($user->channel)->toBe('slack');
    expect($user->channel_account_id)->toBe('T123456');
    expect($user->external_id)->toBe('U123456');
});

it('enforces unique constraint on channel, channel_account_id, and external_id combination', function (): void {
    User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    expect(fn () => User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]))->toThrow(QueryException::class);
});

it('allows the same external_id across different channels', function (): void {
    User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    $user2 = User::create([
        'channel' => 'telegram',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
    ]);

    expect($user2->id)->toBeInt();
    expect($user2->channel)->toBe('telegram');
});

it('allows the same external_id across different channel_account_ids', function (): void {
    User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T111111',
        'external_id' => 'U123456',
    ]);

    $user2 = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T222222',
        'external_id' => 'U123456',
    ]);

    expect($user2->id)->toBeInt();
    expect($user2->channel_account_id)->toBe('T222222');
});

it('finds or creates a user by channel identity', function (): void {
    $user1 = User::findOrCreateByIdentity('slack', 'T123456', 'U123456');
    $user2 = User::findOrCreateByIdentity('slack', 'T123456', 'U123456');

    expect($user1->id)->toBe($user2->id);
});

it('stores optional name and metadata for a user', function (): void {
    $user = User::create([
        'channel' => 'slack',
        'channel_account_id' => 'T123456',
        'external_id' => 'U123456',
        'name' => 'John Doe',
        'metadata' => ['timezone' => 'UTC', 'locale' => 'en-US'],
    ]);

    expect($user->name)->toBe('John Doe');
    expect($user->metadata)->toBeArray();
    expect($user->metadata['timezone'])->toBe('UTC');
    expect($user->metadata['locale'])->toBe('en-US');
});
