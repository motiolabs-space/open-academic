<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\KelasDosen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dosen Pengajar Kelas Kuliah — which lecturer teaches which class, for how
 * many credits.
 *
 * Three things depend on this row reaching PDDIKTI, and none of them is
 * served by the class record alone: the lecturer–student ratio is counted
 * from it, team teaching splits SKS across it, and SISTER's "pengajaran"
 * entry for the lecturer is read from it. A campus that pushes classes but
 * not this row reports classes nobody teaches.
 *
 * Credits reported are the lecturer's own portion when the campus recorded
 * one; otherwise the whole class. That fallback is deliberate: splitting
 * evenly would be a policy this application has no business inventing, so
 * the validator warns instead and lets the operator decide.
 *
 * Field names follow the Neo Feeder web service as documented for the 2.x
 * builds. They are confined to this class so that a build that renames one
 * is a one-line change here.
 */
class DosenPengajarMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertDosenPengajarKelasKuliah';
    }

    /** @return Builder<KelasDosen> */
    protected function query(string $termCode): Builder
    {
        return KelasDosen::query()
            ->with(['dosen', 'kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik', 'kelasKuliah.pertemuan'])
            ->term($termCode)
            ->orderBy('kelas_kuliah_id')
            ->orderBy('id');
    }

    /** @param KelasDosen $model */
    public function payload(Model $model): array
    {
        $kelas = $model->kelasKuliah;

        return array_filter([
            'id_kelas_kuliah' => $kelas->feeder_id,
            'id_dosen' => $model->dosen->feeder_id,
            'nidn' => $model->dosen->nidn,
            'sks_substansi_total' => number_format((float) ($model->porsi_sks ?? $kelas->sks), 2, '.', ''),
            'rencana_minggu_pertemuan' => (int) config('academic.attendance.meetings_per_term', 16),
            'realisasi_minggu_pertemuan' => $kelas->pertemuan->where('is_terlaksana', true)->count(),
            'id_jenis_evaluasi' => $this->kode('jenis_evaluasi', 'default', '1'),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var KelasDosen $model */
        return $model->kelasKuliah->mataKuliah->kode.' · Kelas '.$model->kelasKuliah->kode.' · '.$model->dosen->nama;
    }
}
