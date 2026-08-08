<?php

declare(strict_types=1);

namespace App\Models\Bridge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lightweight access log behind the Bridge console usage chart.
 * Prune it on a schedule — it is telemetry, not an audit trail.
 */
class BridgeApiRequest extends Model
{
    protected $table = 'bridge_api_requests';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(BridgeConsumer::class, 'bridge_consumer_id');
    }
}
