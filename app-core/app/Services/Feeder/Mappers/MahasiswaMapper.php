<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\StatusAdopsiFeeder;
use App\Enums\StatusLampauFeeder;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Feeder\MedanFeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public function medan(): array
    {
        return [
            'nama_mahasiswa',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'id_agama',
            'nik',
            'nisn',
            'kewarganegaraan',
            'jalan',
            'kelurahan',
            'kode_pos',
            MedanFeeder::hp(),
            'email',
            'nama_ayah',
            'nama_ibu_kandung',
        ];
    }

    /**
     * Students who first enrolled in this term.
     *
     * Biodata is pushed once per student, not once per term — re-pushing an
     * existing student is what the ledger's hash check is there to prevent.
     *
     * @return Builder<Mahasiswa>
     */
    protected function query(string $termCode): Builder
    {
        return Mahasiswa::query()
            ->with([
                'prodi',
                'perubahanFeeder' => fn ($q) => $q->terbuka(),
                'adopsiFeeder' => fn ($q) => $q->whereIn('status', [StatusAdopsiFeeder::Ambigu->value, StatusAdopsiFeeder::Dipisahkan->value]),
                'klaimLampauFeeder' => fn ($q) => $q->status(StatusLampauFeeder::Diklaim),
            ])
            ->where(fn ($query) => $query->where('term_masuk', $termCode)->orWhereNull('feeder_id'))
            ->orderBy('nim');
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
            // The name follows the campus Feeder's dictionary (ROADMAP #52).
            MedanFeeder::hp() => $model->telepon,
            'email' => $model->email,
            'nama_ayah' => $model->nama_ayah,
            'nama_ibu_kandung' => $model->nama_ibu,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }
}
