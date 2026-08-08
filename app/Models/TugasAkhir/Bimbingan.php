<?php

declare(strict_types=1);

namespace App\Models\TugasAkhir;

use App\Models\Sdm\Dosen;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded consultation between a student and a supervisor.
 *
 * The student writes it; the supervisor signs it. Until signed it does not
 * count towards the minimum a defence is scheduled against — otherwise the
 * requirement is certified by the person it constrains.
 */
class Bimbingan extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'tugas_akhir_bimbingan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'disetujui' => 'boolean',
            'disetujui_at' => 'datetime',
        ];
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function scopeDisetujui(Builder $query): Builder
    {
        return $query->where('disetujui', true);
    }
}
