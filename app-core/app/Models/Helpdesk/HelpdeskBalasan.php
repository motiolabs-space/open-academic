<?php

declare(strict_types=1);

namespace App\Models\Helpdesk;

use App\Models\Sdm\Staff;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One message on a ticket — a public reply or an internal note.
 */
class HelpdeskBalasan extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'helpdesk_balasan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = [
        'is_internal' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTiket::class, 'helpdesk_tiket_id');
    }

    /** Staff, Dosen, or Mahasiswa. */
    public function penulis(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePublik(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    public function dariStaf(): bool
    {
        return $this->penulis_type === (new Staff)->getMorphClass();
    }

    public function adaLampiran(): bool
    {
        return filled($this->lampiran_path);
    }
}
