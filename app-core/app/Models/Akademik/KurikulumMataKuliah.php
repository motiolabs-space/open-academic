<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One course's place in one curriculum — the `kurikulum_mata_kuliah` row.
 *
 * Reached everywhere else as a pivot; it gets a model because PDDIKTI records
 * it as an entity of its own (Matkul Kurikulum), and the sync ledger tracks a
 * model with a key.
 */
class KurikulumMataKuliah extends Model
{
    protected $table = 'kurikulum_mata_kuliah';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }
}
