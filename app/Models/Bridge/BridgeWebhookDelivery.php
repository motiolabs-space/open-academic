<?php

declare(strict_types=1);

namespace App\Models\Bridge;

use App\Enums\WebhookDeliveryStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt-tracked webhook delivery.
 *
 * Payloads are HMAC-signed so a consumer can prove an event really came from
 * this installation; failures back off on the schedule in config/bridge.php
 * rather than hammering an endpoint that is already down.
 */
class BridgeWebhookDelivery extends Model
{
    use HasUuid;

    protected $table = 'bridge_webhook_deliveries';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => WebhookDeliveryStatus::class,
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(BridgeConsumer::class, 'bridge_consumer_id');
    }

    /**
     * Seconds to wait before the next attempt, per the configured backoff.
     *
     * The index is clamped rather than looked up blindly: an attempt count past
     * the end of the schedule must fall back to the longest delay, never to a
     * missing key that would read as "retry immediately".
     */
    public function backoffSeconds(): int
    {
        $schedule = array_values((array) config('bridge.webhooks.backoff'));

        if ($schedule === []) {
            return 60;
        }

        $index = max(0, min((int) $this->attempts, count($schedule) - 1));

        return (int) $schedule[$index];
    }

    public function bolehDicobaLagi(): bool
    {
        return $this->attempts < (int) config('bridge.webhooks.max_attempts');
    }

    /** @param Builder<self> $query */
    public function scopeSiapKirim(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                WebhookDeliveryStatus::Pending->value,
                WebhookDeliveryStatus::Failed->value,
            ])
            ->where(fn (Builder $q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
    }
}
