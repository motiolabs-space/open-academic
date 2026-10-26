<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Enums\Agama;
use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Enums\PaymentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An admission applicant.
 *
 * NIK and place/date of birth are collected here rather than after enrolment,
 * because a Feeder biodata push fails without them — catching the gap at
 * registration is far cheaper than chasing a student down a year later.
 */
class PmbPendaftar extends Model
{
    use DapatDicari;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pmb_pendaftar';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => ApplicantStatus::class,
            'jenis_kelamin' => Gender::class,
            'tanggal_lahir' => 'date',
            'nilai_seleksi' => 'decimal:2',
            'agama_kode' => Agama::class,
            'is_calon_kip' => 'boolean',
            'biaya_formulir' => 'integer',
            'diajukan_at' => 'datetime',
        ];
    }

    /** The online account that owns this application, if it came in online. */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(PmbAkun::class, 'pmb_akun_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(PmbPembayaran::class, 'pmb_pendaftar_id')->latest('id');
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(PmbGelombang::class, 'pmb_gelombang_id');
    }

    public function prodiPilihan1(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_pilihan_1_id');
    }

    public function prodiPilihan2(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_pilihan_2_id');
    }

    public function prodiDiterima(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_diterima_id');
    }

    /** Set once the applicant has been converted into a student record. */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function berkas(): HasMany
    {
        return $this->hasMany(PmbBerkas::class);
    }

    public function daring(): bool
    {
        return $this->pmb_akun_id !== null;
    }

    public function milik(PmbAkun $akun): bool
    {
        return $this->pmb_akun_id === $akun->id;
    }

    /** Whether the applicant may still edit the form and replace documents. */
    public function masihDraf(): bool
    {
        return $this->status === ApplicantStatus::Draf;
    }

    /**
     * The terms this application was made under: the first-choice programme's
     * offering in its wave. Reads loaded relations; callers load
     * `gelombang.prodiDitawarkan`.
     */
    public function tawaran(): ?PmbGelombangProdi
    {
        return $this->gelombang?->prodiDitawarkan
            ->firstWhere('prodi_id', $this->prodi_pilihan_1_id);
    }

    /** @return array<int, string> document kinds this application must carry */
    public function dokumenWajib(): array
    {
        return $this->gelombang?->dokumenWajibUntuk($this->tawaran()) ?? [];
    }

    /**
     * Required kinds with no upload that still stands.
     *
     * A document the desk sent back (catatan set, not verified) does not
     * count: the applicant has to replace it.
     *
     * @return array<int, string>
     */
    public function dokumenKurang(): array
    {
        $ada = $this->berkas
            ->reject(fn (PmbBerkas $b): bool => $b->dikembalikan())
            ->pluck('jenis')
            ->all();

        return array_values(array_diff($this->dokumenWajib(), $ada));
    }

    /** Whether every required document has been checked and accepted. */
    public function dokumenTerverifikasi(): bool
    {
        $terverifikasi = $this->berkas->where('is_verified', true)->pluck('jenis')->all();

        return array_diff($this->dokumenWajib(), $terverifikasi) === [];
    }

    /** Settled form-fee payments. Reads the loaded relation when present. */
    public function terbayarFormulir(): int
    {
        $pembayaran = $this->relationLoaded('pembayaran')
            ? $this->pembayaran
            : $this->pembayaran()->get();

        return (int) $pembayaran->where('status', PaymentStatus::Settlement)->sum('nominal');
    }

    public function sisaFormulir(): int
    {
        return max(0, (int) $this->biaya_formulir - $this->terbayarFormulir());
    }

    public function formulirLunas(): bool
    {
        return $this->sisaFormulir() === 0;
    }

    /** Whether the outcome may be shown to the applicant. */
    public function hasilTerlihat(): bool
    {
        return $this->status->sudahDiputuskan() && ($this->gelombang?->sudahDiumumkan() ?? false);
    }

    /**
     * The status as the applicant may see it.
     *
     * A decision taken before the wave is announced reads as "Seleksi" on the
     * applicant's side. Registration and enrolment are facts, not decisions
     * awaiting announcement, and always show.
     */
    public function statusTerlihat(): ApplicantStatus
    {
        if (in_array($this->status, [ApplicantStatus::Lulus, ApplicantStatus::TidakLulus], true)
            && !($this->gelombang?->sudahDiumumkan() ?? false)) {
            return ApplicantStatus::Seleksi;
        }

        return $this->status;
    }

    /** Data completeness that a future Feeder biodata push depends on. */
    public function siapSinkronFeeder(): bool
    {
        return filled($this->nik)
            && filled($this->tempat_lahir)
            && $this->tanggal_lahir !== null
            && $this->jenis_kelamin !== null;
    }

    /** @param Builder<self> $query */
    public function scopeStatus(Builder $query, ApplicantStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
