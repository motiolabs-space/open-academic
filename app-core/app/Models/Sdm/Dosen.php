<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\Penguji;
use App\Traits\AuthenticatesWithUuid;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\DosenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A lecturer. Authenticates on the "dosen" guard.
 *
 * The NIDN is the national identifier PDDIKTI keys on; it is nullable here
 * because practitioners brought in from industry (IKU 4) frequently have none,
 * and the Feeder pre-flight validator is what refuses to push such a row.
 */
class Dosen extends Authenticatable implements OAuthenticatable
{
    use AuthenticatesWithUuid;
    use DapatDicari;

    /** @use HasFactory<DosenFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasLogAktivitas;
    use HasRoles;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $hidden = ['password', 'remember_token'];

    protected string $guard_name = 'dosen';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'tanggal_lahir' => 'date',
            'jenis_kelamin' => Gender::class,
            'pendidikan_tertinggi' => EducationLevel::class,
            'is_praktisi' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Homebase study programme. */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /** Classes this lecturer teaches, in any role. */
    public function kelasKuliah(): BelongsToMany
    {
        return $this->belongsToMany(KelasKuliah::class, 'kelas_dosen')
            ->withPivot(['peran', 'porsi_sks', 'praktisi_instansi'])
            ->withTimestamps();
    }

    /** Students for whom this lecturer is the academic advisor (dosen wali). */
    public function mahasiswaBimbingan(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'dosen_wali_id');
    }

    /** External and non-teaching assignments — the IKU 3/4 source. */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanDosen::class);
    }

    /**
     * Final projects this lecturer supervises.
     *
     * Distinct from mahasiswaBimbingan() above, which is academic advising for
     * study plans. A lecturer is typically advisor to a whole cohort and
     * supervisor to a handful — conflating the two would make the supervision
     * quota meaningless.
     */
    public function pembimbingTugasAkhir(): HasMany
    {
        return $this->hasMany(Pembimbing::class);
    }

    public function pengujiTugasAkhir(): HasMany
    {
        return $this->hasMany(Penguji::class);
    }

    /*
     * The SISTER side: histories rather than current values.
     *
     * The flat columns on this table (pendidikan_tertinggi, jabatan_fungsional)
     * stay, because a class list and a signature block need one value and not a
     * ladder. Where the two disagree, these win — they carry dates and decrees.
     */

    public function riwayatPendidikan(): HasMany
    {
        return $this->hasMany(RiwayatPendidikanDosen::class)->orderByDesc('tahun_lulus');
    }

    public function riwayatJabatan(): HasMany
    {
        return $this->hasMany(JabatanFungsionalDosen::class)->orderByDesc('tmt');
    }

    public function sertifikasi(): HasMany
    {
        return $this->hasMany(SertifikasiDosen::class)->orderByDesc('tanggal');
    }

    public function laporanBkd(): HasMany
    {
        return $this->hasMany(BkdLaporan::class);
    }

    /*
     |---------------------------------------------------------------------
     | Kepegawaian mendalam
     |
     | Typed histories rather than one generic "riwayat" table: a generic one
     | cannot be validated, cannot be indexed usefully, and cannot be mapped to
     | a SISTER field without a lookup nobody maintains.
     |---------------------------------------------------------------------
     */

    /**
     * The unit that employs this lecturer.
     *
     * Distinct from `prodi`, which says who they teach for. A lecturer seconded
     * to the library still teaches, and a head count by employer must not
     * confuse the two.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(KeluargaDosen::class);
    }

    public function pangkat(): HasMany
    {
        return $this->hasMany(PangkatDosen::class);
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiDosen::class);
    }

    public function penghargaanSanksi(): HasMany
    {
        return $this->hasMany(PenghargaanSanksiDosen::class);
    }

    public function bahasa(): HasMany
    {
        return $this->hasMany(BahasaDosen::class);
    }

    public function organisasi(): HasMany
    {
        return $this->hasMany(OrganisasiDosen::class);
    }

    /**
     * Whether this lecturer is obliged to report BKD at all.
     *
     * The allowance is the reason the report exists, so a campus that demands
     * one from every lecturer is imposing paperwork the regulation does not.
     */
    public function wajibBkd(): bool
    {
        return $this->sertifikasi()->serdos()->exists();
    }

    public function namaLengkap(): string
    {
        return trim(sprintf(
            '%s %s%s',
            $this->gelar_depan ?? '',
            $this->nama,
            $this->gelar_belakang ? ', '.$this->gelar_belakang : '',
        ));
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopePraktisi(Builder $query): Builder
    {
        return $query->where('is_praktisi', true);
    }

    protected static function newFactory(): DosenFactory
    {
        return DosenFactory::new();
    }
}
