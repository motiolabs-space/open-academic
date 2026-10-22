<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\KesiapanPin;
use App\Enums\StudentStatus;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The pre-flight check for a national diploma number (PIN).
 *
 * PIN is reserved per graduate at the ministry's portal, and the portal
 * refuses a graduate whose PDDIKTI record is incomplete. The refusals
 * campuses meet most often — a wrong NIK, too few credits, a graduate still
 * *Aktif* at PDDIKTI, and a record that never reached PDDIKTI at all — are
 * every one of them visible from the data this application already holds.
 * This service looks before the portal is opened.
 *
 * It judges *readiness to reserve*, not eligibility to graduate. Graduation
 * is YudisiumService's checklist; this one starts where that one ends.
 */
class KesiapanPinService
{
    public function periksa(Mahasiswa $mahasiswa): KesiapanPin
    {
        return $this->periksaBanyak(collect([$mahasiswa]))[$mahasiswa->id];
    }

    /**
     * The checklist for many graduates in a fixed number of queries.
     *
     * @param Collection<int, Mahasiswa> $mahasiswa
     * @return Collection<int, KesiapanPin> keyed by student id
     */
    public function periksaBanyak(Collection $mahasiswa): Collection
    {
        // Callers arrive with whatever shape their query produced — a plucked
        // relation is a plain collection, and only an Eloquent one can load.
        $mahasiswa = EloquentCollection::make($mahasiswa->unique('id')->values()->all());

        if ($mahasiswa->isEmpty()) {
            return collect();
        }

        $mahasiswa->loadMissing('yudisium');
        $mahasiswa->loadCount([
            'statusPerSemester as semester_total',
            'statusPerSemester as semester_belum_sinkron' => fn ($q) => $q->whereNull('feeder_id'),
            'nilai as nilai_final_total' => fn ($q) => $q->where('is_final', true),
            'nilai as nilai_belum_sinkron' => fn ($q) => $q->where('is_final', true)->whereNull('feeder_id'),
        ]);

        $minSks = (int) config('academic.graduation.min_credits');

        return $mahasiswa->mapWithKeys(fn (Mahasiswa $m): array => [
            $m->id => new KesiapanPin($this->rincian($m, $minSks)),
        ]);
    }

    /** @return array<int, array{kode: string, label: string, siap: bool, keterangan: string}> */
    private function rincian(Mahasiswa $m, int $minSks): array
    {
        $yudisium = $m->yudisium;
        $ditetapkan = $yudisium !== null && $yudisium->status === 'ditetapkan';
        $sks = $ditetapkan ? (int) $yudisium->total_sks : $m->sksKumulatif();

        $nikValid = (bool) preg_match('/^\d{16}$/', (string) $m->nik);
        $identitas = filled($m->nama_ibu) && filled($m->tempat_lahir) && $m->tanggal_lahir !== null;

        return [
            [
                'kode' => 'nik',
                'label' => 'NIK 16 digit',
                'siap' => $nikValid,
                'keterangan' => $nikValid ? 'valid' : (blank($m->nik) ? 'kosong' : 'bukan 16 digit angka'),
            ],
            [
                'kode' => 'identitas',
                'label' => 'Identitas PDM lengkap (nama ibu, tempat & tanggal lahir)',
                'siap' => $identitas,
                'keterangan' => $identitas ? 'lengkap' : 'ada yang kosong — mengubahnya setelah sinkron butuh ajuan PDM',
            ],
            [
                'kode' => 'registrasi',
                'label' => 'Riwayat pendidikan tersinkron ke PDDIKTI',
                'siap' => filled($m->feeder_registrasi_id),
                'keterangan' => filled($m->feeder_registrasi_id) ? 'id registrasi ada' : 'belum ada id registrasi Feeder',
            ],
            [
                'kode' => 'akm',
                'label' => 'Aktivitas kuliah setiap semester tersinkron',
                'siap' => (int) $m->semester_total > 0 && (int) $m->semester_belum_sinkron === 0,
                'keterangan' => (int) $m->semester_total === 0
                    ? 'tidak ada catatan semester'
                    : sprintf('%d dari %d semester tersinkron', (int) $m->semester_total - (int) $m->semester_belum_sinkron, (int) $m->semester_total),
            ],
            [
                'kode' => 'nilai',
                'label' => 'Seluruh nilai final tersinkron',
                'siap' => (int) $m->nilai_belum_sinkron === 0,
                'keterangan' => (int) $m->nilai_belum_sinkron === 0
                    ? sprintf('%d nilai', (int) $m->nilai_final_total)
                    : sprintf('%d nilai belum terkirim', (int) $m->nilai_belum_sinkron),
            ],
            [
                'kode' => 'sks',
                'label' => "SKS memenuhi ketentuan ({$minSks})",
                'siap' => $sks >= $minSks,
                'keterangan' => "{$sks} SKS",
            ],
            [
                'kode' => 'yudisium',
                'label' => 'Yudisium ditetapkan dengan nomor SK',
                'siap' => $ditetapkan && filled($yudisium->nomor_sk),
                'keterangan' => match (true) {
                    !$ditetapkan => 'belum ditetapkan',
                    blank($yudisium->nomor_sk) => 'nomor SK kosong',
                    default => $yudisium->nomor_sk,
                },
            ],
            [
                'kode' => 'lulus_pddikti',
                'label' => 'Status Lulus sudah sampai di PDDIKTI',
                'siap' => $m->status === StudentStatus::Lulus && filled($m->feeder_keluar_id),
                'keterangan' => match (true) {
                    $m->status !== StudentStatus::Lulus => 'status lokal '.$m->status->label(),
                    blank($m->feeder_keluar_id) => 'masih Aktif di PDDIKTI — kirim entitas Lulus/DO',
                    default => 'terkirim',
                },
            ],
        ];
    }
}
