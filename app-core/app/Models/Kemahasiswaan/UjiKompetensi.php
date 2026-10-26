<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\HasilUjiKompetensi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One recorded sitting of the national competency examination.
 *
 * A passing row is what turns "Menunggu Uji Kompetensi" into Lulus; see
 * UjiKompetensiService. Never edited after the fact: a mistyped result is
 * corrected by an audited soft delete and a new row.
 */
class UjiKompetensi extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'uji_kompetensi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_ujian' => 'date',
            'hasil' => HasilUjiKompetensi::class,
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_by_staff_id');
    }

    /** @param Builder<self> $query */
    public function scopeLulus(Builder $query): Builder
    {
        return $query->where('hasil', HasilUjiKompetensi::Lulus->value);
    }
}
