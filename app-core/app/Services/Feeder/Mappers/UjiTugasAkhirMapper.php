<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\JenisUjian;
use App\Enums\PeranPenguji;
use App\Enums\StatusUjian;
use App\Models\TugasAkhir\Penguji;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Uji Mahasiswa — a final-project examiner.
 *
 * Only the examiners of a *held* final defence are reported. A scheduled
 * panel is a plan, and PDDIKTI records examinations, not plans; proposal and
 * progress seminars are campus matters.
 */
class UjiTugasAkhirMapper extends FeederMapper
{
    public const KATEGORI = 'penguji_tugas_akhir';

    public function act(): string
    {
        return 'InsertUjiMahasiswa';
    }

    /** @return Builder<Penguji> */
    protected function query(string $termCode): Builder
    {
        return Penguji::query()
            ->with(['dosen', 'ujian.tugasAkhir.mahasiswa'])
            ->whereHas('ujian', fn (Builder $u) => $u
                ->where('jenis', JenisUjian::Sidang->value)
                ->where('status', StatusUjian::Selesai->value)
                ->whereHas('tugasAkhir', fn (Builder $q) => $q
                    ->whereNotNull('feeder_id')
                    ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('kode', $termCode))))
            ->orderBy('tugas_akhir_ujian_id')
            ->orderBy('peran');
    }

    /** @param Penguji $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_aktivitas' => $model->ujian->tugasAkhir->feeder_id,
            'id_kategori_kegiatan' => $this->kode('kategori_kegiatan', self::KATEGORI),
            'id_dosen' => $model->dosen->feeder_id,
            'penguji_ke' => match ($model->peran) {
                PeranPenguji::Ketua => 1,
                PeranPenguji::Sekretaris => 2,
                default => 3,
            },
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var Penguji $model */
        return $model->ujian->tugasAkhir->mahasiswa->nim.' · penguji '.$model->dosen->nama;
    }
}
