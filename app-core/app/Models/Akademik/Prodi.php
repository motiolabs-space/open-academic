<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\EducationLevel;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Carbon\CarbonInterface;
use Database\Factories\ProdiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A study programme.
 *
 * `kode_pddikti` holds the id_sms assigned by PDDIKTI. Without it no Feeder
 * push for this programme can succeed, which is why the pre-flight validator
 * checks it before a sync run rather than failing halfway through one.
 */
class Prodi extends Model
{
    /** @use HasFactory<ProdiFactory> */
    use HasFactory;

    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'prodi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenjang' => EducationLevel::class,
            'is_active' => 'boolean',
            'wajib_ukom' => 'boolean',
            'wajib_proposal_ta' => 'boolean',
        ];
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function kaprodi(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'kaprodi_dosen_id');
    }

    public function kurikulum(): HasMany
    {
        return $this->hasMany(Kurikulum::class);
    }

    public function kurikulumAktif(): HasOne
    {
        return $this->hasOne(Kurikulum::class)->where('is_active', true);
    }

    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class);
    }

    /** The grading scale PDDIKTI holds for this programme, one row per letter. */
    public function skalaNilai(): HasMany
    {
        return $this->hasMany(SkalaNilaiProdi::class)->orderByDesc('bobot_minimum');
    }

    /** This programme's teaching period per term, as reported to PDDIKTI. */
    public function periodePerkuliahan(): HasMany
    {
        return $this->hasMany(PeriodePerkuliahan::class);
    }

    /**
     * Programme learning outcomes, stated on every diploma supplement.
     *
     * Belongs to the programme rather than to the graduate: written once, so the
     * English half is not left as a translation job on the morning of a
     * ceremony — which is when it stops happening.
     */
    public function cpl(): HasMany
    {
        return $this->hasMany(ProdiCpl::class)->orderBy('urutan')->orderBy('kode');
    }

    /**
     * Accreditation decrees, newest first. A new decree is a new row; the
     * one before it stays, because PISN asks which was in force on a
     * graduate's graduation day (ADR-041 §3).
     */
    public function riwayatAkreditasi(): HasMany
    {
        return $this->hasMany(AkreditasiProdi::class)
            ->orderByDesc('tanggal_berlaku')
            ->orderByDesc('id');
    }

    /**
     * The decree in force on that day, read from the loaded history. When two
     * overlap — a re-accreditation issued before the old one ran out — the
     * one that took effect later wins.
     */
    public function akreditasiPada(CarbonInterface $tanggal): ?AkreditasiProdi
    {
        return $this->riwayatAkreditasi->first(fn (AkreditasiProdi $a): bool => $a->berlakuPada($tanggal));
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class);
    }

    /** Lecturers whose homebase is this programme. */
    public function dosen(): HasMany
    {
        return $this->hasMany(Dosen::class);
    }

    public function namaLengkap(): string
    {
        return $this->jenjang->label().' '.$this->nama;
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): ProdiFactory
    {
        return ProdiFactory::new();
    }
}
