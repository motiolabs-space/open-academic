<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\JenisPoin;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Prestasi Mahasiswa — a verified achievement from the student-points ledger.
 *
 * Only the achievement side of that ledger, and only entries a member of
 * staff verified: the points ledger accepts claims, PDDIKTI records facts.
 * Kind and level go through the mapping table (groups jenis_prestasi and
 * tingkat_prestasi), filled from the reference pull and not seeded.
 */
class PrestasiMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertPrestasiMahasiswa';
    }

    /** @return Builder<PoinMahasiswa> */
    protected function query(string $termCode): Builder
    {
        return PoinMahasiswa::query()
            ->with(['mahasiswa', 'kategori'])
            ->where('jenis', JenisPoin::Prestasi->value)
            ->where('is_verified', true)
            ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('id');
    }

    /** @param PoinMahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_mahasiswa' => $model->mahasiswa->feeder_id,
            'id_jenis_prestasi' => $this->kode('jenis_prestasi', $model->kategori->kode),
            'id_tingkat_prestasi' => $this->kode('tingkat_prestasi', $model->kategori->tingkat),
            'nama' => $model->judul,
            'tahun_prestasi' => $model->tanggal?->year,
            'penyelenggara' => $model->keterangan,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var PoinMahasiswa $model */
        return $model->mahasiswa->nim.' · '.$model->judul;
    }
}
