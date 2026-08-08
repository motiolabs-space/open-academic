<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\StatusPenerima;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One student's award under one scheme.
 *
 * @property StatusPenerima $status
 */
class BeasiswaPenerima extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'beasiswa_penerima';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusPenerima::class,
            'diputus_at' => 'datetime',
        ];
    }

    public function beasiswa(): BelongsTo
    {
        return $this->belongsTo(Beasiswa::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function mulai(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_mulai_id');
    }

    public function selesai(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_selesai_id');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diputus_by_staff_id');
    }

    /** Every invoice line this award has produced. */
    public function potongan(): HasMany
    {
        return $this->hasMany(TagihanItem::class, 'beasiswa_penerima_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StatusPenerima::Aktif->value);
    }

    /**
     * Whether the award covers a given term.
     *
     * Compared on the PDDIKTI term code rather than on ids, because ids follow
     * insertion order and terms are routinely created out of sequence — a
     * backfilled 2025 term inserted after 2026 would otherwise fall outside
     * every range that should contain it.
     */
    public function mencakupTerm(TahunAkademik $term): bool
    {
        if ($this->status !== StatusPenerima::Aktif) {
            return false;
        }

        $this->loadMissing(['mulai', 'selesai']);

        if ($term->kode < $this->mulai->kode) {
            return false;
        }

        return $this->selesai === null || $term->kode <= $this->selesai->kode;
    }

    /** The value written into kunci_aktif while the award is running. */
    public static function kunci(int $beasiswaId, int $mahasiswaId): string
    {
        return $beasiswaId.':'.$mahasiswaId;
    }
}
