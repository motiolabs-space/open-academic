<?php

declare(strict_types=1);

namespace App\Models\Bridge;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * An external application allowed to read Open Academic data — Open Campus
 * first, but the contract is deliberately generic.
 *
 * A consumer never touches the database. It holds a Sanctum token whose scopes
 * are listed here, and optionally a webhook endpoint we sign deliveries for.
 */
/**
 * Implements Authenticatable because a token holder is a principal: Laravel's
 * rate limiter and anything else that asks "who is making this request" needs
 * an identity to key on. It is not a login — a consumer has no password and no
 * session, only a token.
 */
class BridgeConsumer extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use HasApiTokens;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'bridge_consumers';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'webhook_events' => 'array',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(BridgeWebhookDelivery::class);
    }

    public function apiRequests(): HasMany
    {
        return $this->hasMany(BridgeApiRequest::class);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function subscribesTo(string $event): bool
    {
        return $this->webhook_url !== null
            && in_array($event, $this->webhook_events ?? [], true);
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
