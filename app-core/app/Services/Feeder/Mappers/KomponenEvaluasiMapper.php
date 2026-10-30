<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\KomponenNilai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Komponen Evaluasi Kelas — the weighted evaluation plan of a class.
 *
 * Mandatory at PDDIKTI since the 2024 odd term. It is also where IKU 7 is
 * decided: a class flagged as case method or team-based project is recognised
 * only when its participatory and project components reach half the marks.
 * The flag travels with the class record; the proof travels here.
 *
 * Field names follow the Neo Feeder 2.3 web service notes and are confined
 * to this class. Verify against the Postman collection before a live run.
 */
class KomponenEvaluasiMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertKomponenEvaluasiKelas';
    }

    public function medan(): array
    {
        return [
            'id_kelas_kuliah',
            'id_jenis_evaluasi',
            'nama',
            'nama_inggris',
            'nomor_urut',
            'bobot_evaluasi',
        ];
    }

    /** @return Builder<KomponenNilai> */
    protected function query(string $termCode): Builder
    {
        return KomponenNilai::query()
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.komponenNilai'])
            ->whereHas('kelasKuliah.tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('kelas_kuliah_id')
            ->orderBy('urutan')
            ->orderBy('id');
    }

    /** @param KomponenNilai $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_kelas_kuliah' => $model->kelasKuliah->feeder_id,
            'id_jenis_evaluasi' => $this->kode('jenis_evaluasi', $model->jenis_evaluasi?->value),
            'nama' => $model->nama,
            'nama_inggris' => $model->nama,
            'nomor_urut' => (int) $model->urutan + 1,
            'bobot_evaluasi' => number_format($model->bobot / 100, 2, '.', ''),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var KomponenNilai $model */
        return $model->kelasKuliah->mataKuliah->kode.' · Kelas '.$model->kelasKuliah->kode.' · '.$model->nama;
    }
}
