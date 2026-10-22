<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\PeranPembimbing;
use App\Models\TugasAkhir\Pembimbing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bimbing Mahasiswa — a final-project supervisor.
 *
 * One row per supervisor, ordered as the campus ordered them: the main
 * supervisor is pembimbing_ke 1, the co-supervisor 2. SISTER reads this back
 * as the lecturer's supervision record.
 */
class BimbingTugasAkhirMapper extends FeederMapper
{
    public const KATEGORI = 'pembimbing_tugas_akhir';

    public function act(): string
    {
        return 'InsertBimbingMahasiswa';
    }

    /** @return Builder<Pembimbing> */
    protected function query(string $termCode): Builder
    {
        return Pembimbing::query()
            ->with(['dosen', 'tugasAkhir.mahasiswa'])
            ->whereHas('tugasAkhir', fn (Builder $q) => $q
                ->whereNotNull('feeder_id')
                ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('kode', $termCode)))
            ->orderBy('tugas_akhir_id')
            ->orderBy('peran');
    }

    /** @param Pembimbing $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_aktivitas' => $model->tugasAkhir->feeder_id,
            'id_kategori_kegiatan' => $this->kode('kategori_kegiatan', self::KATEGORI),
            'id_dosen' => $model->dosen->feeder_id,
            'pembimbing_ke' => $model->peran === PeranPembimbing::Utama ? 1 : 2,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var Pembimbing $model */
        return $model->tugasAkhir->mahasiswa->nim.' · pembimbing '.$model->dosen->nama;
    }
}
