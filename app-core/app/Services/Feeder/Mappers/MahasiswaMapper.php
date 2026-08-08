<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Biodata Mahasiswa — the first entity that must reach Feeder, because every
 * later one references the student registration it creates.
 */
class MahasiswaMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertBiodataMahasiswa';
    }

    /**
     * Students who first enrolled in this term.
     *
     * Biodata is pushed once per student, not once per term — re-pushing an
     * existing student is what the ledger's hash check is there to prevent.
     *
     * @return Collection<int, Mahasiswa>
     */
    public function rows(string $termCode): Collection
    {
        return Mahasiswa::query()
            ->with('prodi')
            ->where(fn ($query) => $query->where('term_masuk', $termCode)->orWhereNull('feeder_id'))
            ->orderBy('nim')
            ->get();
    }

    /** @param Mahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'nama_mahasiswa' => $model->nama,
            'jenis_kelamin' => $model->jenis_kelamin?->value,
            'tempat_lahir' => $model->tempat_lahir,
            'tanggal_lahir' => $this->tanggal($model->tanggal_lahir),
            'id_agama' => $this->kode('agama', $model->agama_kode),
            'nik' => $model->nik,
            'nisn' => $model->nisn,
            'kewarganegaraan' => 'ID',
            'jalan' => $model->alamat,
            'kelurahan' => $model->kelurahan,
            'kode_pos' => $model->kode_pos,
            'telepon_seluler' => $model->telepon,
            'email' => $model->email,
            'nama_ayah' => $model->nama_ayah,
            'nama_ibu_kandung' => $model->nama_ibu,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }
}
