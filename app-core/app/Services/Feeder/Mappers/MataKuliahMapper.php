<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\MataKuliah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Mata Kuliah — the course catalogue.
 *
 * The id Feeder hands back is what every class, KRS line, grade, transfer and
 * conversion payload points at as `id_matkul`. Until this mapper existed those
 * payloads sent the local course code and hoped.
 *
 * The course kind (wajib, pilihan, ...) is a property of a course's place in a
 * curriculum, not of the course; PDDIKTI nonetheless wants one on the course,
 * so the first curriculum placement decides and the mapping table translates.
 */
class MataKuliahMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertMataKuliah';
    }

    public function medan(): array
    {
        return [
            'kode_mata_kuliah',
            'nama_mata_kuliah',
            'id_prodi',
            'id_jenis_mata_kuliah',
            'sks_mata_kuliah',
            'sks_tatap_muka',
            'sks_praktek',
            'sks_praktek_lapangan',
            'sks_simulasi',
            'tanggal_mulai_efektif',
        ];
    }

    /** @return Builder<MataKuliah> */
    protected function query(string $termCode): Builder
    {
        return MataKuliah::query()->with(['prodi', 'kurikulum'])->aktif()->orderBy('prodi_id')->orderBy('kode');
    }

    /** @param MataKuliah $model */
    public function payload(Model $model): array
    {
        $jenis = $model->kurikulum->first()?->pivot?->jenis ?? 'wajib';

        return array_filter([
            'kode_mata_kuliah' => $model->kode,
            'nama_mata_kuliah' => $model->nama,
            'id_prodi' => $model->prodi->kode_pddikti,
            'id_jenis_mata_kuliah' => $this->kode('jenis_mata_kuliah', $jenis),
            'sks_mata_kuliah' => $model->sks,
            'sks_tatap_muka' => $model->sks_teori,
            'sks_praktek' => $model->sks_praktik,
            'sks_praktek_lapangan' => $model->sks_praktik_lapangan,
            'sks_simulasi' => 0,
            'tanggal_mulai_efektif' => $this->tanggal($model->created_at),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var MataKuliah $model */
        return $model->kode.' · '.$model->nama;
    }
}
