<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bimbing Mahasiswa — the lecturer supervising an MBKM activity.
 *
 * This is the row SISTER reads back as the lecturer's "pembimbingan". A
 * campus that counts supervision in BKD here, but never sends this, has a
 * BKD figure PDDIKTI cannot corroborate.
 *
 * The activity category (group kategori_kegiatan) comes from the
 * GetKategoriKegiatan reference pull and is mapped by the campus; the local
 * value `pembimbing_mbkm` is the key to map.
 */
class BimbingMbkmMapper extends FeederMapper
{
    public const KATEGORI = 'pembimbing_mbkm';

    public function act(): string
    {
        return 'InsertBimbingMahasiswa';
    }

    public function medan(): array
    {
        return ['id_aktivitas', 'id_kategori_kegiatan', 'id_dosen', 'pembimbing_ke'];
    }

    /** @return Builder<AktivitasMahasiswa> */
    protected function query(string $termCode): Builder
    {
        return AktivitasMahasiswa::query()
            ->with(['mahasiswa', 'pembimbing'])
            ->terverifikasi()
            ->whereNotNull('feeder_id')
            ->whereNotNull('dosen_pembimbing_id')
            ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('id');
    }

    /** @param AktivitasMahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_aktivitas' => $model->feeder_id,
            'id_kategori_kegiatan' => $this->kode('kategori_kegiatan', self::KATEGORI),
            'id_dosen' => $model->pembimbing?->feeder_id,
            'pembimbing_ke' => 1,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_bimbing_id');
    }

    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill(['feeder_bimbing_id' => $feederId])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var AktivitasMahasiswa $model */
        return $model->mahasiswa->nim.' · pembimbing '.($model->pembimbing?->nama ?? '-');
    }
}
