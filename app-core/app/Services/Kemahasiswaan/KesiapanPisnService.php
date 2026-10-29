<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\KesiapanPisn;
use App\Enums\EducationLevel;
use App\Enums\StudentStatus;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Support\KodeSemester;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The pre-flight check for a national diploma number (PISN).
 *
 * PISN issues the number only after the graduate's PDDIKTI record is
 * complete: status Lulus with a graduation date, and every PISN validator
 * passed — accredited programme, credits, GPA, study length, per-semester
 * and short-term loads, an entry term actually reported, a NIK (ADR-036 §2).
 * A record that fails is "tidak eligible", and the campus must fix it at
 * PDDIKTI or file an exception. Every one of those checks is visible from the
 * data this application already holds, so this service looks before the PISN
 * site is opened.
 *
 * It judges *readiness to be numbered*, not eligibility to graduate.
 * Graduation is YudisiumService's checklist; this one starts where that one
 * ends. The validator figures live in `config('academic.ijazah.pisn')`.
 */
class KesiapanPisnService
{
    public function periksa(Mahasiswa $mahasiswa): KesiapanPisn
    {
        return $this->periksaBanyak(collect([$mahasiswa]))[$mahasiswa->id];
    }

    /**
     * The checklist for many graduates in a fixed number of queries.
     *
     * @param Collection<int, Mahasiswa> $mahasiswa
     * @return Collection<int, KesiapanPisn> keyed by student id
     */
    public function periksaBanyak(Collection $mahasiswa): Collection
    {
        // Callers arrive with whatever shape their query produced — a plucked
        // relation is a plain collection, and only an Eloquent one can load.
        $mahasiswa = EloquentCollection::make($mahasiswa->unique('id')->values()->all());

        if ($mahasiswa->isEmpty()) {
            return collect();
        }

        $mahasiswa->loadMissing(['yudisium.tahunAkademik', 'prodi']);
        $mahasiswa->loadCount([
            'statusPerSemester as semester_total',
            'statusPerSemester as semester_belum_sinkron' => fn ($q) => $q->whereNull('feeder_id'),
            'nilai as nilai_final_total' => fn ($q) => $q->where('is_final', true),
            'nilai as nilai_belum_sinkron' => fn ($q) => $q->where('is_final', true)->whereNull('feeder_id'),
        ]);

        // Every term record of every graduate, in one read: the per-semester
        // load and the first reported term come from these.
        $semester = DB::table('status_mahasiswa as s')
            ->join('tahun_akademik as t', 't.id', '=', 's.tahun_akademik_id')
            ->whereIn('s.mahasiswa_id', $mahasiswa->modelKeys())
            ->orderBy('t.kode')
            ->get(['s.mahasiswa_id', 's.semester_ke', 's.sks_semester', 't.kode'])
            ->groupBy('mahasiswa_id');

        $aturan = (array) config('academic.ijazah.pisn');

        return $mahasiswa->mapWithKeys(fn (Mahasiswa $m): array => [
            $m->id => new KesiapanPisn($this->rincian($m, $semester->get($m->id, collect()), $aturan)),
        ]);
    }

    /**
     * @param Collection<int, object> $semester
     * @param array<string, mixed> $aturan
     * @return array<int, array{kode: string, label: string, siap: bool, keterangan: string}>
     */
    private function rincian(Mahasiswa $m, Collection $semester, array $aturan): array
    {
        $yudisium = $m->yudisium;
        $ditetapkan = $yudisium !== null && $yudisium->status === 'ditetapkan';
        $jenjang = $m->prodi?->jenjang;
        $kode = $jenjang?->value;

        $sks = $ditetapkan ? (int) $yudisium->total_sks : $m->sksKumulatif();
        $minSks = (int) ($aturan['sks_minimum'][$kode] ?? config('academic.graduation.min_credits'));
        $ipk = $ditetapkan ? (float) $yudisium->ipk : null;
        $minIpk = (float) ($aturan['ipk_minimum'][$kode] ?? config('academic.graduation.min_gpa'));

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
                'kode' => 'akreditasi',
                'label' => 'Program studi terakreditasi',
                'siap' => filled($m->prodi?->akreditasi),
                // The one validator PISN grants no exception for. Expiry is
                // not recorded here, so a stale grade still reads as filled.
                'keterangan' => filled($m->prodi?->akreditasi)
                    ? (string) $m->prodi->akreditasi.' — pastikan masih berlaku'
                    : 'akreditasi prodi belum dicatat',
            ],
            [
                'kode' => 'registrasi',
                'label' => 'Riwayat pendidikan tersinkron ke PDDIKTI',
                'siap' => filled($m->feeder_registrasi_id),
                'keterangan' => filled($m->feeder_registrasi_id) ? 'id registrasi ada' : 'belum ada id registrasi Feeder',
            ],
            $this->semesterMasuk($m, $semester),
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
                'kode' => 'ipk',
                'label' => 'IPK memenuhi ketentuan ('.number_format($minIpk, 2, ',', '').')',
                'siap' => $ipk !== null && $ipk >= $minIpk,
                'keterangan' => $ipk === null ? 'yudisium belum ditetapkan' : number_format($ipk, 2, ',', ''),
            ],
            $this->bebanSemester($jenjang, $semester, $aturan),
            $this->masaStudi($m, $kode, $aturan),
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

    /**
     * PISN wants the student reported active in the term they entered. A
     * record whose first reported term comes later was registered late, and
     * is refused until fixed or excepted.
     *
     * @param Collection<int, object> $semester
     * @return array{kode: string, label: string, siap: bool, keterangan: string}
     */
    private function semesterMasuk(Mahasiswa $m, Collection $semester): array
    {
        $pertama = $semester->first()?->kode;

        return [
            'kode' => 'semester_masuk',
            'label' => 'Dilaporkan aktif pada semester masuk',
            'siap' => KodeSemester::sah($m->term_masuk) && $pertama === $m->term_masuk,
            'keterangan' => match (true) {
                !KodeSemester::sah($m->term_masuk) => 'semester masuk tidak tercatat',
                $pertama === null => 'tidak ada catatan semester',
                $pertama !== $m->term_masuk => "masuk {$m->term_masuk}, catatan pertama {$pertama}",
                default => (string) $m->term_masuk,
            },
        ];
    }

    /**
     * Credits per term within the national ceilings: a short term at most the
     * short-term cap; for diploma and bachelor's programmes, the first two
     * semesters and the later ones each at most their cap.
     *
     * @param Collection<int, object> $semester
     * @param array<string, mixed> $aturan
     * @return array{kode: string, label: string, siap: bool, keterangan: string}
     */
    private function bebanSemester(?EducationLevel $jenjang, Collection $semester, array $aturan): array
    {
        $antara = (int) ($aturan['sks_semester_antara_maksimum'] ?? 9);
        $awal = (int) ($aturan['sks_semester_maksimum']['awal'] ?? 20);
        $lanjut = (int) ($aturan['sks_semester_maksimum']['lanjut'] ?? 24);
        $diplomaSarjana = in_array($jenjang, [
            EducationLevel::D1, EducationLevel::D2, EducationLevel::D3, EducationLevel::D4, EducationLevel::S1,
        ], true);

        $lebih = $semester->filter(function (object $s) use ($antara, $awal, $lanjut, $diplomaSarjana): bool {
            if (KodeSemester::antara($s->kode)) {
                return (int) $s->sks_semester > $antara;
            }

            return $diplomaSarjana && (int) $s->sks_semester > ((int) $s->semester_ke <= 2 ? $awal : $lanjut);
        });

        return [
            'kode' => 'beban_semester',
            'label' => 'SKS per semester dalam batas',
            'siap' => $lebih->isEmpty(),
            'keterangan' => $lebih->isEmpty()
                ? 'dalam batas'
                : 'melebihi batas pada '.$lebih->map(fn (object $s): string => "{$s->kode} ({$s->sks_semester} SKS)")->implode(', '),
        ];
    }

    /**
     * Elapsed regular semesters from entry to the graduation term, leave
     * included, against the national range for the degree level.
     *
     * @param array<string, mixed> $aturan
     * @return array{kode: string, label: string, siap: bool, keterangan: string}
     */
    private function masaStudi(Mahasiswa $m, ?string $kode, array $aturan): array
    {
        $lama = KodeSemester::rentang($m->term_masuk, $m->yudisium?->tahunAkademik?->kode);
        $maks = $aturan['masa_studi_maksimum_semester'][$kode] ?? null;
        $min = $aturan['masa_studi_minimum_semester'][$kode] ?? null;

        return [
            'kode' => 'masa_studi',
            'label' => 'Masa studi dalam batas',
            'siap' => $lama > 0 && ($maks === null || $lama <= $maks) && ($min === null || $lama >= $min),
            'keterangan' => match (true) {
                $lama === 0 => 'semester masuk atau semester lulus tidak tercatat',
                $maks !== null && $lama > $maks => "{$lama} semester, batas {$maks} — ajukan eksepsi bila ada cuti",
                $min !== null && $lama < $min => "{$lama} semester, paling sedikit {$min}",
                default => "{$lama} semester",
            },
        ];
    }
}
