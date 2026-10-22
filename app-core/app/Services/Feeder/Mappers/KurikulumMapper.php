<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\Kurikulum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Kurikulum — the programme's curriculum as PDDIKTI records it.
 *
 * Reference data, not a per-term transaction: every curriculum is due every
 * run, and the hash check makes the unchanged ones skips. The term it "starts"
 * at PDDIKTI is the odd term of its first year.
 */
class KurikulumMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertKurikulum';
    }

    /** @return Builder<Kurikulum> */
    protected function query(string $termCode): Builder
    {
        return Kurikulum::query()->with('prodi')->orderBy('prodi_id')->orderBy('tahun_mulai');
    }

    /** @param Kurikulum $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'nama_kurikulum' => $model->nama,
            'id_prodi' => $model->prodi->kode_pddikti,
            'id_semester' => $model->tahun_mulai.'1',
            'jumlah_sks_lulus' => $model->sks_lulus,
            'jumlah_sks_wajib' => $model->sks_wajib,
            'jumlah_sks_pilihan' => $model->sks_pilihan,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var Kurikulum $model */
        return $model->prodi->kode.' · '.$model->kode;
    }
}
