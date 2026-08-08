<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;

/**
 * Works out how many credits a student may take, and which term's IPS decided
 * it.
 *
 * The reference is the most recent *finalised* term before the one being
 * planned — not simply the previous row. A term whose grades are still being
 * entered has an IPS of zero, and letting that decide the ceiling would punish
 * every student for the registrar's timing.
 */
class BatasSksCalculator
{
    /** @return array{batas: int, ips: float|null, acuan: StatusMahasiswa|null} */
    public function untuk(Mahasiswa $mahasiswa, TahunAkademik $term): array
    {
        $acuan = $this->semesterAcuan($mahasiswa, $term);

        if ($acuan === null) {
            // New intake: no history to derive a ceiling from.
            return [
                'batas' => (int) config('academic.krs.default_credits'),
                'ips' => null,
                'acuan' => null,
            ];
        }

        $ips = (float) $acuan->ips;

        return [
            'batas' => $this->dariIps($ips),
            'ips' => $ips,
            'acuan' => $acuan,
        ];
    }

    /** First matching row of the configured matrix wins; it is ordered high to low. */
    public function dariIps(float $ips): int
    {
        foreach (config('academic.krs.credit_limits') as $baris) {
            if ($ips >= (float) $baris['min_ips']) {
                return (int) $baris['credits'];
            }
        }

        return (int) config('academic.krs.default_credits');
    }

    private function semesterAcuan(Mahasiswa $mahasiswa, TahunAkademik $term): ?StatusMahasiswa
    {
        return StatusMahasiswa::query()
            ->with('tahunAkademik')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('is_final', true)
            ->whereHas('tahunAkademik', fn ($query) => $query->where('kode', '<', $term->kode))
            ->join('tahun_akademik', 'tahun_akademik.id', '=', 'status_mahasiswa.tahun_akademik_id')
            ->orderByDesc('tahun_akademik.kode')
            ->select('status_mahasiswa.*')
            ->first();
    }

    /** Study semester the student enters in this term. */
    public function semesterKe(Mahasiswa $mahasiswa, TahunAkademik $term): int
    {
        $terakhir = StatusMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->join('tahun_akademik', 'tahun_akademik.id', '=', 'status_mahasiswa.tahun_akademik_id')
            ->where('tahun_akademik.kode', '<', $term->kode)
            ->orderByDesc('tahun_akademik.kode')
            ->value('status_mahasiswa.semester_ke');

        return $terakhir === null ? 1 : (int) $terakhir + 1;
    }
}
