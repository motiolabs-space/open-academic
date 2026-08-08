<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A row that would be rejected by Feeder, found by the pre-flight validator.
 *
 * The point of this table is that an operator sees the whole list of problems
 * before a sync starts, instead of discovering them one failure at a time
 * halfway through a run.
 */
class FeederValidationIssue extends Model
{
    protected $table = 'feeder_validation_issues';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeBatch(Builder $query, string $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    /** @param Builder<self> $query */
    public function scopeError(Builder $query): Builder
    {
        return $query->where('severity', 'error');
    }
}
