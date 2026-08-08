<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\KrsStatus;
use App\Models\Akademik\KrsDetail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * KRS Mahasiswa — one row per class taken.
 *
 * Only approved study plans are reported. A draft or a plan still waiting on
 * the advisor is not an enrolment, and sending it would overstate the campus's
 * numbers to PDDIKTI.
 */
class KrsMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertKRSMahasiswa';
    }

    /** @return Collection<int, KrsDetail> */
    public function rows(string $termCode): Collection
    {
        return KrsDetail::query()
            ->with(['krs.mahasiswa', 'kelasKuliah.mataKuliah'])
            ->whereHas('krs', fn ($query) => $query
                ->where('status', KrsStatus::Disetujui->value)
                ->whereHas('tahunAkademik', fn ($sub) => $sub->where('kode', $termCode)))
            ->get();
    }

    /** @param KrsDetail $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_registrasi_mahasiswa' => $model->krs->mahasiswa->feeder_registrasi_id,
            'id_kelas_kuliah' => $model->kelasKuliah->feeder_id,
            'id_matkul' => $model->kelasKuliah->mataKuliah->kode,
            'id_semester' => $model->krs->tahunAkademik->kode,
            'sks_mata_kuliah' => $model->sks,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var KrsDetail $model */
        return $model->krs->mahasiswa->nim.' · '.$model->kelasKuliah->mataKuliah->kode;
    }
}
