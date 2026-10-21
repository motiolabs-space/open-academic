<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\NilaiKomponen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Nilai per komponen evaluasi — one student's score on one component.
 *
 * Only scores behind a finalised final grade are reported, for the same
 * reason NilaiMapper reports only finalised grades: a provisional number can
 * still change, and a number that has reached PDDIKTI is far harder to
 * correct than one that has not left the building.
 *
 * Feeder exposes a single act for this entity that both inserts and updates,
 * keyed on the student registration, the class, and the component. There is
 * no identifier to receive, so the row is keyed naturally (kunciAlami) and
 * marked with the component's Feeder id once accepted.
 */
class NilaiKomponenMapper extends FeederMapper
{
    public function act(): string
    {
        return 'UpdateNilaiPerkuliahanKelasKomponenEvaluasi';
    }

    /** @return Builder<NilaiKomponen> */
    protected function query(string $termCode): Builder
    {
        return NilaiKomponen::query()
            ->with(['komponen.kelasKuliah.mataKuliah', 'krsDetail.krs.mahasiswa'])
            ->whereNotNull('nilai')
            ->whereHas('komponen.kelasKuliah.tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->whereHas('krsDetail.nilai', fn (Builder $q) => $q->where('is_final', true))
            ->orderBy('komponen_nilai_id')
            ->orderBy('id');
    }

    /** @param NilaiKomponen $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'nilai_komponen_evaluasi' => number_format((float) $model->nilai, 2, '.', ''),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    /**
     * @param NilaiKomponen $model
     * @return array<string, string|null>
     */
    public function kunciAlami(Model $model): ?array
    {
        return [
            'id_registrasi_mahasiswa' => $model->krsDetail->krs->mahasiswa->feeder_registrasi_id,
            'id_kelas_kuliah' => $model->komponen->kelasKuliah->feeder_id,
            'id_komponen_evaluasi' => $model->komponen->feeder_id,
        ];
    }

    public function feederIdBawaan(Model $model): ?string
    {
        return $model->komponen->feeder_id;
    }

    public function label(Model $model): string
    {
        /** @var NilaiKomponen $model */
        return $model->krsDetail->krs->mahasiswa->nim.' · '.$model->komponen->kelasKuliah->mataKuliah->kode.' · '.$model->komponen->nama;
    }
}
