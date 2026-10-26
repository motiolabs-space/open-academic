<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Models\Akademik\KonversiKredit;
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
            // No term here yet. A transfer student's graded recognised credits
            // are the only record of how they study; anyone else starts from
            // the default.
            $ipsKonversi = $this->ipsKonversi($mahasiswa);

            return [
                'batas' => $ipsKonversi === null
                    ? (int) config('academic.krs.default_credits')
                    : $this->dariIps($ipsKonversi),
                'ips' => $ipsKonversi,
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

    /**
     * The grade average of a student's approved, graded conversions.
     *
     * Weighted by the credits recognised, exactly as an IPS would be. Read
     * whether or not conversions count towards the IPK: that setting is about
     * what the diploma measures, while this only asks how much a student who
     * passed Basis Data elsewhere with an A can carry in their first term here.
     * Ungraded recognition — ordinary for RPL — says nothing about that and is
     * left out; with nothing graded the default ceiling applies.
     */
    public function ipsKonversi(Mahasiswa $mahasiswa): ?float
    {
        $baris = KonversiKredit::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->diakui()
            ->whereNotNull('bobot')
            ->get(['sks_diakui', 'bobot']);

        $sks = (int) $baris->sum('sks_diakui');

        if ($sks === 0) {
            return null;
        }

        $mutu = $baris->sum(fn (KonversiKredit $k): float => (float) $k->bobot * (int) $k->sks_diakui);

        return round($mutu / $sks, 2);
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

        return $terakhir === null ? $this->semesterAwal($mahasiswa) : (int) $terakhir + 1;
    }

    /**
     * The semester a student starts in, before any term here.
     *
     * One for everybody but a transfer, who enters as far along as their
     * recognised credits carry them. Read from `sks_diakui`, which is only
     * ever the sum of approved conversions, so this moves when the assessor
     * decides and not when somebody edits a number. After the first term the
     * count simply continues from the stored record.
     */
    public function semesterAwal(Mahasiswa $mahasiswa): int
    {
        $perSemester = max(1, (int) config('academic.pindahan.sks_per_semester', 20));

        return 1 + intdiv((int) $mahasiswa->sks_diakui, $perSemester);
    }
}
