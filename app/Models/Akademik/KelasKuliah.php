<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Sdm\Dosen;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\KelasKuliahFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A course offering for one term — the unit a student actually enrols in.
 *
 * Two of its columns exist purely to serve indicators Open Campus computes:
 * `is_case_method` and `is_team_based_project` are the IKU 7 evidence, and a
 * kelas_dosen row with peran = "praktisi" is the IKU 4 evidence. Open Academic
 * records them; it never scores them.
 */
class KelasKuliah extends Model
{
    use DapatDicari;

    /** @use HasFactory<KelasKuliahFactory> */
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kelas_kuliah';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'is_case_method' => 'boolean',
            'is_team_based_project' => 'boolean',
            'finalized_at' => 'datetime',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function dosen(): BelongsToMany
    {
        return $this->belongsToMany(Dosen::class, 'kelas_dosen')
            ->withPivot(['peran', 'porsi_sks', 'praktisi_instansi'])
            ->withTimestamps();
    }

    /** The lecturer of record, used wherever a single name is shown. */
    public function dosenPengampu(): BelongsToMany
    {
        return $this->dosen()->wherePivot('peran', 'pengampu');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class);
    }

    public function pertemuan(): HasMany
    {
        return $this->hasMany(PertemuanKelas::class)->orderBy('pertemuan_ke');
    }

    public function komponenNilai(): HasMany
    {
        return $this->hasMany(KomponenNilai::class)->orderBy('urutan');
    }

    public function krsDetail(): HasMany
    {
        return $this->hasMany(KrsDetail::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class);
    }

    public function namaLengkap(): string
    {
        return $this->mataKuliah->nama.' — Kelas '.$this->kode;
    }

    public function sisaKuota(): int
    {
        return max(0, $this->kuota - $this->terisi);
    }

    public function penuh(): bool
    {
        return $this->terisi >= $this->kuota;
    }

    /** True when the class carries at least one IKU 7 teaching method. */
    public function kelasKolaboratif(): bool
    {
        return $this->is_case_method || $this->is_team_based_project;
    }

    /** @param Builder<self> $query */
    public function scopeTerm(Builder $query, int $tahunAkademikId): Builder
    {
        return $query->where('tahun_akademik_id', $tahunAkademikId);
    }

    protected static function newFactory(): KelasKuliahFactory
    {
        return KelasKuliahFactory::new();
    }
}
