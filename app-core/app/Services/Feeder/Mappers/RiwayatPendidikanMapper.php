<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\JenisDaftar;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
     * @return Builder<Mahasiswa>
     */
    protected function query(string $termCode): Builder
    {
        return Mahasiswa::query()
            ->with('prodi')
            ->whereNotNull('feeder_id')
            ->where(fn ($query) => $query
                ->where('term_masuk', $termCode)
                ->orWhereNull('feeder_registrasi_id'))
            ->orderBy('nim');
    }

    /** @param Mahasiswa $model */
    public function payload(Model $model): array
    {
        $jenis = $model->jenis_daftar ?? JenisDaftar::Baru;

        return array_filter([
            'id_mahasiswa' => $model->feeder_id,
            'nim' => $model->nim,
            'id_prodi' => $model->prodi->kode_pddikti,
            'id_jenis_daftar' => $this->kode('jenis_daftar', $jenis->value),
            'id_jalur_masuk' => $this->kode('jalur_masuk', $model->jalur_masuk, '1'),
            'id_periode_masuk' => $model->term_masuk,
            'tanggal_daftar' => $this->tanggal($model->created_at),

            // Transfer and RPL only: what was recognised, and from where.
            // PDDIKTI has no identifier for another institution's programme,
            // so the origin travels as text.
            'sks_diakui' => $jenis->butuhAsal() ? $model->sks_diakui : null,
            'nama_perguruan_tinggi_asal' => $jenis->butuhAsal() ? $model->pt_asal : null,
            'nama_program_studi_asal' => $jenis->butuhAsal() ? $model->prodi_asal : null,
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
