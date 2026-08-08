<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\Nilai;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Nilai Perkuliahan Kelas.
 *
 * Only finalised grades are reported. A provisional grade can still change, and
 * a number that reaches PDDIKTI is far harder to correct than one that has not
 * left the building yet.
 */
class NilaiMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertNilaiPerkuliahanKelas';
    }

    /** @return Collection<int, Nilai> */
    public function rows(string $termCode): Collection
    {
        return Nilai::query()
            ->with(['mahasiswa', 'kelasKuliah.mataKuliah', 'krsDetail.krs.tahunAkademik'])
            ->final()
            ->whereHas('krsDetail.krs.tahunAkademik', fn ($query) => $query->where('kode', $termCode))
            ->get();
    }

    /** @param Nilai $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_kelas_kuliah' => $model->kelasKuliah->feeder_id,
            'id_registrasi_mahasiswa' => $model->mahasiswa->feeder_registrasi_id,
            'nilai_angka' => number_format((float) $model->nilai_angka, 2, '.', ''),
            'nilai_huruf' => $model->nilai_huruf?->value,
            'nilai_indeks' => number_format((float) $model->bobot, 2, '.', ''),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var Nilai $model */
        return $model->mahasiswa->nim.' · '.$model->kelasKuliah->mataKuliah->kode;
    }
}
