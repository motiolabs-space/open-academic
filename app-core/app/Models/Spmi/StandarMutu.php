<?php

declare(strict_types=1);

namespace App\Models\Spmi;

use App\Models\Sdm\UnitKerja;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One quality standard, and the PPEPP stage it currently sits in. */
class StandarMutu extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'standar_mutu';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = ['siklus' => 'penetapan', 'is_active' => true, 'melampaui_sndikti' => false];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'melampaui_sndikti' => 'boolean'];
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorStandar::class);
    }

    public function temuan(): HasMany
    {
        return $this->hasMany(TemuanAudit::class);
    }

    public function unitPenanggungJawab(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_penanggung_jawab_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function siklusLabel(): string
    {
        return (string) (config('spmi.ppepp')[$this->siklus] ?? $this->siklus);
    }
}
