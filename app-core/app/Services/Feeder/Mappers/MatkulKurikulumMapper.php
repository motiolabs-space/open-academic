<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\KurikulumMataKuliah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Matkul Kurikulum — a course's position in a curriculum.
 *
 * PDDIKTI keys this record on the pair (curriculum, course) and hands no
 * identifier of its own back, so the pair is what gets stored — joined, in
 * the row's `feeder_id` — and split again when an update needs the key.
 */
class MatkulKurikulumMapper extends FeederMapper
{
    public const PEMISAH = ':';

    public function act(): string
    {
        return 'InsertMatkulKurikulum';
    }

    public function medan(): array
    {
        return ['id_kurikulum', 'id_matkul', 'semester', 'sks_mata_kuliah', 'apakah_wajib'];
    }

    /** @return Builder<KurikulumMataKuliah> */
    protected function query(string $termCode): Builder
    {
        return KurikulumMataKuliah::query()
            ->with(['kurikulum', 'mataKuliah'])
            ->whereHas('kurikulum', fn (Builder $q) => $q->whereNotNull('feeder_id'))
            ->whereHas('mataKuliah', fn (Builder $q) => $q->whereNotNull('feeder_id'))
            ->orderBy('kurikulum_id')
            ->orderBy('semester');
    }

    /** @param KurikulumMataKuliah $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_kurikulum' => $model->kurikulum->feeder_id,
            'id_matkul' => $model->mataKuliah->feeder_id,
            'semester' => (int) $model->semester,
            'sks_mata_kuliah' => $model->mataKuliah->sks,
            'apakah_wajib' => $model->jenis === 'pilihan' ? 0 : 1,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederIdBawaan(Model $model): ?string
    {
        return $model->kurikulum->feeder_id.self::PEMISAH.$model->mataKuliah->feeder_id;
    }

    /**
     * Whatever the reply carries, the stored identifier is the pair: that is
     * the only key an update of this record can be addressed by.
     */
    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill([
            'feeder_id' => $this->feederIdBawaan($model),
            'feeder_synced_at' => now(),
        ])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var KurikulumMataKuliah $model */
        return $model->kurikulum->kode.' · '.$model->mataKuliah->kode.' · smt '.$model->semester;
    }
}
