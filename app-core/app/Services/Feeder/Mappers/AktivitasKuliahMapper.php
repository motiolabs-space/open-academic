<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Kemahasiswaan\StatusMahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Aktivitas Kuliah Mahasiswa — the per-term enrolment record: status, credits
 * taken, IPS and IPK.
 *
 * This is the payload PDDIKTI uses to judge whether a campus is reporting
 * honestly, so it is pushed from the same row the KHS is derived from rather
 * than recomputed here. One source, one number.
 */
class AktivitasKuliahMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertAktivitasKuliahMahasiswa';
    }

    /** @return Collection<int, StatusMahasiswa> */
    public function rows(string $termCode): Collection
    {
        return StatusMahasiswa::query()
            ->with(['mahasiswa', 'tahunAkademik'])
            ->whereHas('tahunAkademik', fn ($query) => $query->where('kode', $termCode))
            ->get();
    }

    /** @param StatusMahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_registrasi_mahasiswa' => $model->mahasiswa->feeder_registrasi_id,
            'id_semester' => $model->tahunAkademik->kode,
            'id_status_mahasiswa' => $this->kode('status_mahasiswa', $model->status->value, $model->status->value),
            'ips' => number_format((float) $model->ips, 2, '.', ''),
            'ipk' => number_format((float) $model->ipk, 2, '.', ''),
            'sks_semester' => $model->sks_semester,
            'sks_total' => $model->sks_kumulatif,
            'biaya_kuliah_smt' => $model->biaya_kuliah,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var StatusMahasiswa $model */
        return $model->mahasiswa->nim.' · '.$model->tahunAkademik->kode;
    }
}
