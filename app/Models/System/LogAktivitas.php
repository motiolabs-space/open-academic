<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Traits\DapatDicari;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One audit-trail entry, written by RecordActivityLogJob.
 *
 * Append-only: rows are never updated, hence no updated_at column and no
 * public setter path. This is the record an auditor reads when asking who
 * changed a grade and when.
 */
class LogAktivitas extends Model
{
    use DapatDicari;
    use HasUuid;

    protected $table = 'log_aktivitas';

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function aktorLabel(): string
    {
        return $this->causer_name ?? 'Sistem';
    }

    /** @param Builder<self> $query */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }
}
