<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\KelasKuliah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Kelas Kuliah — the course offerings of a term, with the teaching lecturer.
 *
 * The IKU 7 method flags travel with this payload where the Feeder build
 * supports them; a build that does not simply ignores the extra keys, which is
 * why they are sent unconditionally rather than behind a version check we would
 * have to maintain.
 */
class KelasKuliahMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertKelasKuliah';
    }

    /** @return Collection<int, KelasKuliah> */
    public function rows(string $termCode): Collection
    {
        return KelasKuliah::query()
            ->with(['mataKuliah', 'prodi', 'tahunAkademik', 'dosenPengampu'])
            ->whereHas('tahunAkademik', fn ($query) => $query->where('kode', $termCode))
            ->orderBy('id')
            ->get();
    }

    /** @param KelasKuliah $model */
    public function payload(Model $model): array
    {
        $pengampu = $model->dosenPengampu->first();

        return array_filter([
            'id_semester' => $model->tahunAkademik->kode,
            'id_prodi' => $model->prodi->kode_pddikti,
            'id_matkul' => $model->mataKuliah->kode,
            'nama_kelas_kuliah' => $model->kode,
            'sks' => $model->sks,
            'jumlah_mahasiswa' => $model->terisi,
            'id_dosen' => $pengampu?->feeder_id,
            'nidn' => $pengampu?->nidn,

            // Evidence for IKU 7; harmless on builds that do not read them.
            'apa_untuk_pditt' => 0,
            'metode_case_method' => $model->is_case_method ? 1 : 0,
            'metode_team_based_project' => $model->is_team_based_project ? 1 : 0,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var KelasKuliah $model */
        return $model->mataKuliah->kode.' · Kelas '.$model->kode;
    }
}
