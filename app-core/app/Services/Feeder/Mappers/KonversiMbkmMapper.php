<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\KrsDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Konversi Kampus Merdeka — the credits an MBKM activity earned, as a course.
 *
 * The KRS line that carries `aktivitas_mahasiswa_id` is both a KRS entry and
 * a conversion record at PDDIKTI; the two have different identifiers, so the
 * conversion's lives in `feeder_konversi_id` beside the KRS one. Keyed on the
 * activity *member* (#22), not the activity: PDDIKTI converts credits for a
 * participant, not for a programme.
 *
 * Only finalised grades are reported, as everywhere else.
 */
class KonversiMbkmMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertKonversiKampusMerdeka';
    }

    /** @return Builder<KrsDetail> */
    protected function query(string $termCode): Builder
    {
        return KrsDetail::query()
            ->with(['krs.tahunAkademik', 'krs.mahasiswa', 'kelasKuliah.mataKuliah', 'aktivitas', 'nilai'])
            ->whereNotNull('aktivitas_mahasiswa_id')
            ->whereHas('nilai', fn (Builder $n) => $n->where('is_final', true))
            ->whereHas('krs.tahunAkademik', fn (Builder $t) => $t->where('kode', $termCode))
            ->orderBy('id');
    }

    /** @param KrsDetail $model */
    public function payload(Model $model): array
    {
        $nilai = $model->nilai;

        return array_filter([
            'id_matkul' => $model->kelasKuliah->mataKuliah->feeder_id,
            'id_anggota' => $model->aktivitas?->feeder_anggota_id,
            'id_semester' => $model->krs->tahunAkademik->kode,
            'sks_mata_kuliah' => $model->sks,
            'nilai_angka' => $nilai?->nilai_angka !== null ? number_format((float) $nilai->nilai_angka, 2, '.', '') : null,
            'nilai_huruf' => $nilai?->nilai_huruf?->value,
            'nilai_indeks' => $nilai?->bobot !== null ? number_format((float) $nilai->bobot, 2, '.', '') : null,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_konversi_id');
    }

    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill(['feeder_konversi_id' => $feederId])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var KrsDetail $model */
        return $model->krs->mahasiswa->nim.' · '.$model->kelasKuliah->mataKuliah->kode.' (MBKM)';
    }
}
