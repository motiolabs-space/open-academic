<?php

declare(strict_types=1);

namespace App\Models\Kinerja;

use App\Enums\StatusPeriodeKinerja;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One planning cycle: a year or a term, with its objectives beneath it. */
class PeriodeKinerja extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'periode_kinerja';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /** Column defaults mirrored so a fresh model agrees with its row. */
    protected $attributes = ['status' => StatusPeriodeKinerja::Draf->value];

    protected function casts(): array
    {
        return [
            'status' => StatusPeriodeKinerja::class,
            'mulai' => 'date',
            'selesai' => 'date',
            'dikunci_at' => 'datetime',
        ];
    }

    public function sasaran(): HasMany
    {
        return $this->hasMany(SasaranKinerja::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function dikunciOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dikunci_by_staff_id');
    }
}
