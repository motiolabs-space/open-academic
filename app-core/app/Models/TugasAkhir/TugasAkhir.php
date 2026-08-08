<?php

declare(strict_types=1);

namespace App\Models\TugasAkhir;

use App\Enums\HasilUjian;
use App\Enums\JenisUjian;
use App\Enums\PeranPembimbing;
use App\Enums\StatusUjian;
use App\Enums\TugasAkhirStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One student's final project, from proposed title to accepted manuscript.
 *
 * The record a diploma should be traceable to. Before this existed the title
 * printed on one was free text typed at graduation time, which meant nobody
 * could check it against anything.
 *
 * @property TugasAkhirStatus $status
 * @property Collection<int, Pembimbing> $pembimbing
 * @property Collection<int, Ujian> $ujian
 */
class TugasAkhir extends Model
{
    use DapatDicari;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'tugas_akhir';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => TugasAkhirStatus::class,
            'tanggal_pengajuan' => 'date',
            'tanggal_disetujui' => 'date',
            'tanggal_selesai' => 'date',
            'batas_selesai' => 'date',
            'nilai_akhir' => 'decimal:2',
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

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'disetujui_by_staff_id');
    }

    public function pembimbing(): HasMany
    {
        return $this->hasMany(Pembimbing::class)->orderBy('peran');
    }

    public function bimbingan(): HasMany
    {
        return $this->hasMany(Bimbingan::class)->orderByDesc('tanggal');
    }

    public function ujian(): HasMany
    {
        return $this->hasMany(Ujian::class)->orderBy('tanggal');
    }

    /** Live projects — the ones occupying a student's single slot. */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereIn('status', TugasAkhirStatus::nilaiAktif());
    }

    /**
     * What this student's programme calls the work.
     *
     * Requires mahasiswa.prodi to be loaded; every screen that shows this loads
     * it, and preventLazyLoading fails the test suite if one forgets.
     */
    public function sebutan(): string
    {
        return $this->mahasiswa->prodi->jenjang->sebutanTugasAkhir();
    }

    public function pembimbingUtama(): ?Dosen
    {
        return $this->pembimbing
            ->firstWhere('peran', PeranPembimbing::Utama)
            ?->dosen;
    }

    /** @return array<int, int> dosen ids supervising this project */
    public function idPembimbing(): array
    {
        return $this->pembimbing->pluck('dosen_id')->map(intval(...))->all();
    }

    /** Signed-off consultations only. An unapproved log is a claim, not a record. */
    public function jumlahBimbinganDisetujui(): int
    {
        return $this->bimbingan->where('disetujui', true)->count();
    }

    /**
     * The defence that concluded the work, if it has concluded.
     *
     * Only a Sidang closes a project; a passed proposal seminar does not.
     */
    public function sidangLulus(): ?Ujian
    {
        return $this->ujian
            ->first(fn (Ujian $u): bool => $u->jenis === JenisUjian::Sidang
                && $u->status === StatusUjian::Selesai
                && $u->hasil?->lulus() === true);
    }

    /**
     * Past its deadline and still running.
     *
     * Surfaced, never acted on automatically — see config('academic.tugas_akhir').
     */
    public function terlambat(): bool
    {
        return $this->batas_selesai !== null
            && $this->status->aktif()
            && $this->batas_selesai->isPast();
    }

    /** Whether revisions from the closing defence are still outstanding. */
    public function menungguRevisi(): bool
    {
        $sidang = $this->sidangLulus();

        return $sidang?->hasil === HasilUjian::LulusRevisi
            && $this->status !== TugasAkhirStatus::Selesai;
    }
}
