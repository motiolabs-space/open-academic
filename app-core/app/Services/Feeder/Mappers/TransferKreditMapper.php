<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\KonversiKredit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Transfer Kredit Mahasiswa — a course recognised from elsewhere.
 *
 * One row per recognised course, approved by staff. The origin course is
 * described, not referenced: PDDIKTI has no identifier for a course taught
 * at another institution, so its code, name, credits and grade travel as
 * text. The destination course is this prodi's, by its Feeder id once the
 * course catalogue has been pushed (#24).
 *
 * A conversion is reported in the term it was decided, and again whenever it
 * has never reached Feeder — so a backlog from before the integration was
 * switched on is not stranded.
 */
class TransferKreditMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertTransferKreditMahasiswa';
    }

    /** @return Builder<KonversiKredit> */
    protected function query(string $termCode): Builder
    {
        return KonversiKredit::query()
            ->with(['mahasiswa', 'mataKuliah'])
            ->diakui()
            ->where(fn (Builder $q) => $q
                ->whereNull('feeder_id')
                ->orWhereHas('mahasiswa', fn (Builder $m) => $m->whereHas('statusPerSemester', fn (Builder $s) => $s
                    ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('kode', $termCode)))))
            ->orderBy('id');
    }

    /** @param KonversiKredit $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_registrasi_mahasiswa' => $model->mahasiswa->feeder_registrasi_id,
            'id_matkul' => $model->mataKuliah->feeder_id,
            'kode_mata_kuliah_asal' => $model->asal_nama,
            'nama_mata_kuliah_asal' => $model->asal_nama,
            'sks_mata_kuliah_asal' => $model->asal_sks,
            'nilai_huruf_asal' => $model->asal_nilai,
            'nilai_huruf_diakui' => $model->nilai_huruf,
            'nilai_indeks_diakui' => $model->bobot !== null ? number_format((float) $model->bobot, 2, '.', '') : null,
            'sks_mata_kuliah_diakui' => $model->sks_diakui,
            'nama_perguruan_tinggi_asal' => $model->asal_institusi,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var KonversiKredit $model */
        return $model->mahasiswa->nim.' · '.$model->mataKuliah->kode.' ← '.$model->asal_nama;
    }
}
