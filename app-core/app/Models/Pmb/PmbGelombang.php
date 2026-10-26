<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Enums\JalurPmb;
use App\Enums\JenisDaftar;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** An admission wave within an intake term. */
class PmbGelombang extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pmb_gelombang';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'biaya_pendaftaran' => 'integer',
            'is_active' => 'boolean',
            'jalur' => JalurPmb::class,
            'is_daring' => 'boolean',
            'dokumen_wajib' => 'array',
            'diumumkan_at' => 'datetime',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pendaftar(): HasMany
    {
        return $this->hasMany(PmbPendaftar::class);
    }

    /** The programmes this wave offers, with their terms. */
    public function prodiDitawarkan(): HasMany
    {
        return $this->hasMany(PmbGelombangProdi::class, 'pmb_gelombang_id');
    }

    public function jadwalSeleksi(): HasMany
    {
        return $this->hasMany(PmbJadwalSeleksi::class, 'pmb_gelombang_id')->orderBy('mulai');
    }

    public function sedangBerjalan(): bool
    {
        $today = Carbon::today();

        return $this->is_active
            && $today->gte($this->tanggal_mulai)
            && $today->lte($this->tanggal_selesai);
    }

    /**
     * Whether applicants in this wave arrive with study done elsewhere.
     *
     * Their registration goes through the transfer intake instead of the
     * ordinary daftar ulang, which would record them as new entrants.
     */
    public function jalurPindahan(): bool
    {
        return $this->jalur?->pindahan() ?? false;
    }

    /** The enrolment kind the intake form starts from for this wave. */
    public function jenisDaftarBawaan(): ?JenisDaftar
    {
        return $this->jalur?->jenisDaftarBawaan();
    }

    /** Whether a prospective student may start an application here today. */
    public function terbukaDaring(): bool
    {
        return $this->is_daring && $this->sedangBerjalan();
    }

    /** Whether applicants may see their selection outcome. */
    public function sudahDiumumkan(): bool
    {
        return $this->diumumkan_at !== null && $this->diumumkan_at->isPast();
    }

    /**
     * Document kinds an applicant to the given offering must upload.
     *
     * The wave's list plus the programme's additions. A programme can ask for
     * more (a portfolio for design), never for less: an exemption from the
     * wave's own list is a different wave.
     *
     * @return array<int, string>
     */
    public function dokumenWajibUntuk(?PmbGelombangProdi $tawaran): array
    {
        $dikenal = array_keys((array) config('berkas.pmb'));

        return array_values(array_intersect(
            $dikenal,
            array_unique([...((array) $this->dokumen_wajib), ...((array) $tawaran?->dokumen_wajib)]),
        ));
    }

    /** The form fee for one offering: the programme's override, else the wave's. */
    public function biayaUntuk(?PmbGelombangProdi $tawaran): int
    {
        return (int) ($tawaran?->biaya_formulir ?? $this->biaya_pendaftaran);
    }

    /** @param Builder<self> $query */
    public function scopeTerbukaDaring(Builder $query): Builder
    {
        $hariIni = Carbon::today()->toDateString();

        return $query->where('is_active', true)
            ->where('is_daring', true)
            ->whereDate('tanggal_mulai', '<=', $hariIni)
            ->whereDate('tanggal_selesai', '>=', $hariIni);
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
