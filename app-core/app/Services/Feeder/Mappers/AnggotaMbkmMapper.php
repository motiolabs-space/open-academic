<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Anggota Aktivitas Mahasiswa — the student as the member of their own
 * MBKM activity.
 *
 * PDDIKTI models an activity as a group with members even when there is
 * one; the member record is what later carries the credit conversion. It is
 * pushed from the same local row as the activity, so the member id has its
 * own column rather than overwriting the activity's.
 */
class AnggotaMbkmMapper extends FeederMapper
{
    /** PDDIKTI's role code for a sole participant. */
    public const PERAN_PERSONAL = '3';

    public function act(): string
    {
        return 'InsertAnggotaAktivitasMahasiswa';
    }

    public function medan(): array
    {
        return ['id_aktivitas', 'id_registrasi_mahasiswa', 'jenis_peran'];
    }

    /** @return Builder<AktivitasMahasiswa> */
    protected function query(string $termCode): Builder
    {
        return AktivitasMahasiswa::query()
            ->with(['mahasiswa'])
            ->terverifikasi()
            ->whereNotNull('feeder_id')
            ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('id');
    }

    /** @param AktivitasMahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_aktivitas' => $model->feeder_id,
            'id_registrasi_mahasiswa' => $model->mahasiswa->feeder_registrasi_id,
            'jenis_peran' => self::PERAN_PERSONAL,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_anggota_id');
    }

    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill(['feeder_anggota_id' => $feederId])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var AktivitasMahasiswa $model */
        return $model->mahasiswa->nim.' · anggota';
    }
}
