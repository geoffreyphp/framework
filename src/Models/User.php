<?php

declare(strict_types=1);

namespace Geoffrey\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 *
 * @method static static firstOrCreate(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 */
class User extends Model
{
    protected $fillable = [
        'channel',
        'channel_account_id',
        'external_id',
        'name',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
    ];

    public static function findOrCreateByIdentity(
        string $channel,
        string $channelAccountId,
        string $externalId
    ): static {
        return static::firstOrCreate(
            [
                'channel' => $channel,
                'channel_account_id' => $channelAccountId,
                'external_id' => $externalId,
            ]
        );
    }
}
