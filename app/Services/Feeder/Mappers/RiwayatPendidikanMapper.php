<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Riwayat Pendidikan Mahasiswa — the enrolment of a person into a programme.
 *
 * This is the payload that yields `id_registrasi_mahasiswa`, which every later
 * entity references. It is stored in its own column rather than overwriting the
 * biodata identifier: the two mean different things, and conflating them breaks
 * the moment a student transfers programme.
 */
class RiwayatPendidikanMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertRiwayatPendidikanMahasiswa';
    }

    /**
     * Students whose biodata already reached Feeder but whose enrolment has not.
     *
     * @return Collection<int, Mahasiswa>
     */
    public function rows(string $termCode): Collection
    {
        return Mahasiswa::query()
            ->with('prodi')
            ->whereNotNull('feeder_id')
            ->where(fn ($query) => $query
                ->where('term_masuk', $termCode)
                ->orWhereNull('feeder_registrasi_id'))
            ->orderBy('nim')
            ->get();
    }

    /** @param Mahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_mahasiswa' => $model->feeder_id,
            'nim' => $model->nim,
            'id_prodi' => $model->prodi->kode_pddikti,
            'id_jenis_daftar' => $this->kode('jenis_daftar', $model->jalur_masuk, '1'),
            'id_jalur_masuk' => $this->kode('jalur_masuk', $model->jalur_masuk, '1'),
            'id_periode_masuk' => $model->term_masuk,
            'tanggal_daftar' => $this->tanggal($model->created_at),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_registrasi_id');
    }

    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill([
            'feeder_registrasi_id' => $feederId,
            'feeder_synced_at' => now(),
        ])->saveQuietly();
    }
}
