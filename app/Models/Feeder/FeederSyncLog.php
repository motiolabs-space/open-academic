<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use App\Enums\FeederSyncStatus;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in the Neo Feeder sync ledger.
 *
 * Idempotency lives here: `payload_hash` fingerprints what Feeder last
 * accepted for a given entity row. A re-run compares hashes and records
 * Skipped rather than pushing a duplicate, so an interrupted sync can always
 * be restarted safely — the single most important property of this module.
 */
class FeederSyncLog extends Model
{
    use HasUuid;

    protected $table = 'feeder_sync_logs';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => FeederSyncStatus::class,
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function local(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'local_type', 'local_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    /** Fingerprint used to decide whether a row still needs pushing. */
    public static function hashPayload(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @param Builder<self> $query */
    public function scopeEntity(Builder $query, string $entity): Builder
    {
        return $query->where('entity', $entity);
    }

    /** @param Builder<self> $query */
    public function scopeGagal(Builder $query): Builder
    {
        return $query->where('status', FeederSyncStatus::Failed->value);
    }
}
