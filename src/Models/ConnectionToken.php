<?php

declare(strict_types=1);

namespace Geoffrey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stores one token set per (connection, user) pair, or per connection alone
 * when the connection is shared (user_id null).
 *
 * NOTE: the (connection, user_id) unique index on the underlying table does
 * NOT enforce uniqueness for shared rows (user_id = null), because SQL
 * treats each NULL as distinct. Shared-token upsert uniqueness must be
 * enforced by callers via an explicit whereNull('user_id') lookup.
 *
 * @property-read int $id
 * @property string $connection
 * @property int|null $user_id
 * @property string $access_token
 * @property string|null $refresh_token
 * @property-read Carbon|null $expires_at
 * @property string $token_type
 * @property string|null $scope
 * @property string|null $client_id
 * @property string|null $client_secret
 *
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static static|null find(int $id)
 */
class ConnectionToken extends Model
{
    protected $fillable = [
        'connection',
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'token_type',
        'scope',
        'client_id',
        'client_secret',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'client_secret' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function expired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
