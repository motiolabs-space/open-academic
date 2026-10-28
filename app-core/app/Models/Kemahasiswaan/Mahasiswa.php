<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\Gender;
use App\Enums\JenisDaftar;
use App\Enums\StudentStatus;
use App\Models\Akademik\Konsentrasi;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\Krs;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederAdoption;
use App\Models\Feeder\FeederChangeRequest;
use App\Models\Feeder\FeederPastStudent;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Models\Surat\Surat;
use App\Models\TugasAkhir\TugasAkhir;
use App\Traits\AuthenticatesWithUuid;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\PutarTokenIngat;
use Database\Factories\MahasiswaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A student. Authenticates on the "mahasiswa" guard using the NIM.
 *
 * This is the identity source of truth for the whole ecosystem: Campus Bridge
 * SSO issues tokens against these rows, and Open Campus never stores its own
 * copy of the enrolment record.
 */
class Mahasiswa extends Authenticatable implements OAuthenticatable
{
    use AuthenticatesWithUuid;
    use DapatDicari;

    /** @use HasFactory<MahasiswaFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasLogAktivitas;
    use HasRoles;
    use HasUuid;
    use Notifiable;
    use PutarTokenIngat;
    use SoftDeletes;

    protected $table = 'mahasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $hidden = ['password', 'remember_token'];

    protected string $guard_name = 'mahasiswa';

    /** Personal data changes are audited; login bookkeeping is not. */
    protected array $logExcept = ['last_login_at'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'tanggal_lahir' => 'date',
            'jenis_kelamin' => Gender::class,
            'status' => StudentStatus::class,
            'jenis_daftar' => JenisDaftar::class,

            // YEAR columns come back as strings from the driver.
            'angkatan' => 'integer',
            'tahun_lulus_sekolah' => 'integer',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /** The curriculum version the student is bound to for their whole study. */
    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    /**
     * The track within that curriculum, once chosen.
     *
     * Null through most of the first half of a degree, so callers must read it
     * as "not yet" rather than "none exists".
     */
    public function konsentrasi(): BelongsTo
    {
        return $this->belongsTo(Konsentrasi::class);
    }

    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_wali_id');
    }

    /** Per-term enrolment history — also the KHS header per term. */
    public function statusPerSemester(): HasMany
    {
        return $this->hasMany(StatusMahasiswa::class);
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class);
    }

    /** Credit recognised from elsewhere, in every state — filter with diakui(). */
    public function konversiKredit(): HasMany
    {
        return $this->hasMany(KonversiKredit::class);
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    /** MBKM and other off-campus activity — the IKU 2 source. */
    public function aktivitas(): HasMany
    {
        return $this->hasMany(AktivitasMahasiswa::class);
    }

    public function cuti(): HasMany
    {
        return $this->hasMany(CutiMahasiswa::class);
    }

    public function yudisium(): HasOne
    {
        return $this->hasOne(Yudisium::class);
    }

    public function alumni(): HasOne
    {
        return $this->hasOne(Alumni::class);
    }

    /** Competency-examination sittings, failed ones included. */
    public function ujiKompetensi(): HasMany
    {
        return $this->hasMany(UjiKompetensi::class);
    }

    /**
     * The passing sitting, if any. There is at most one: once it is recorded
     * the student is Lulus and no further result is accepted.
     */
    public function ujiKompetensiLulus(): HasOne
    {
        return $this->hasOne(UjiKompetensi::class)->lulus();
    }

    /** Study-evaluation findings and decisions, newest first. */
    public function evaluasiStudi(): HasMany
    {
        return $this->hasMany(EvaluasiStudi::class)->orderByDesc('id');
    }

    /**
     * Changes to columns PDDIKTI locked once this student was reported.
     *
     * While one is open the biodata is not pushed again; see
     * FeederPerubahanService for why that is the honest default.
     */
    public function perubahanFeeder(): HasMany
    {
        return $this->hasMany(FeederChangeRequest::class)->orderByDesc('id');
    }

    /**
     * What adoption runs concluded about this student at PDDIKTI.
     *
     * An ambiguous one means PDDIKTI may already hold them; the biodata is
     * not inserted until a person links or separates it.
     */
    public function adopsiFeeder(): HasMany
    {
        return $this->hasMany(FeederAdoption::class)->orderByDesc('id');
    }

    /**
     * Past-student records at PDDIKTI read or claimed for this student.
     *
     * A claimed one means PDDIKTI holds them; until adoption links it, the
     * biodata is not inserted.
     */
    public function klaimLampauFeeder(): HasMany
    {
        return $this->hasMany(FeederPastStudent::class)->orderByDesc('id');
    }

    /** Official letters requested by or issued to this student. */
    public function surat(): HasMany
    {
        return $this->hasMany(Surat::class)->orderByDesc('id');
    }

    /** Every final project this student has started, including abandoned ones. */
    public function tugasAkhir(): HasMany
    {
        return $this->hasMany(TugasAkhir::class)->orderByDesc('id');
    }

    /**
     * The one currently running, if any.
     *
     * Keyed on mahasiswa_aktif_id rather than filtered by status, so the
     * relation leans on the same unique index that makes "one at a time" true
     * in the first place. See the tugas_akhir migration.
     */
    public function tugasAkhirAktif(): HasOne
    {
        return $this->hasOne(TugasAkhir::class, 'mahasiswa_aktif_id');
    }

    /** Enrolment record for one term, or the current one when omitted. */
    public function statusPada(?TahunAkademik $term = null): ?StatusMahasiswa
    {
        $term ??= TahunAkademik::aktif();

        if ($term === null) {
            return null;
        }

        return $this->statusPerSemester()
            ->where('tahun_akademik_id', $term->id)
            ->first();
    }

    /** Cumulative grade point average from the most recent finalised term. */
    public function ipk(): float
    {
        return (float) ($this->statusPerSemester()
            ->orderByDesc('tahun_akademik_id')
            ->value('ipk') ?? 0.0);
    }

    /**
     * Cumulative credits from the most recent finalised term.
     *
     * Read from the stored enrolment row rather than recomputed, matching
     * ipk() above and keeping IndeksPrestasiCalculator the single place that
     * decides what a credit total means.
     *
     * Counts the best attempt at each course including failed ones, so it runs
     * slightly ahead of credits actually passed. That is fine for the coarse
     * gates it serves — "far enough along to start a final project" — and it is
     * deliberately not the graduation checklist, which recomputes from live
     * grades in YudisiumService because a diploma cannot rest on a stored total.
     */
    public function sksKumulatif(): int
    {
        return (int) ($this->statusPerSemester()
            ->orderByDesc('tahun_akademik_id')
            ->value('sks_kumulatif') ?? 0);
    }

    /**
     * Regular semesters spent at the origin institution before entering here.
     *
     * Counted from the origin's entry term to this campus's entry term, with
     * short terms (kode ending 3) folded into the even semester as PDDIKTI
     * does not count them. Zero when either term is unknown: an unrecorded
     * origin is not a reason to shorten anybody's study period.
     */
    public function semesterDiAsal(): int
    {
        $sah = static fn (?string $kode): bool => $kode !== null && preg_match('/^\d{4}[123]$/', $kode) === 1;

        if (!$sah($this->term_masuk_asal) || !$sah($this->term_masuk)) {
            return 0;
        }

        $urutan = static fn (string $kode): int => (int) substr($kode, 0, 4) * 2 + min(2, (int) substr($kode, 4, 1));

        return max(0, $urutan($this->term_masuk) - $urutan($this->term_masuk_asal));
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Aktif->value);
    }

    /** @param Builder<self> $query */
    public function scopeAngkatan(Builder $query, int $tahun): Builder
    {
        return $query->where('angkatan', $tahun);
    }

    protected static function newFactory(): MahasiswaFactory
    {
        return MahasiswaFactory::new();
    }
}
