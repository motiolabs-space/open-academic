<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\ProdiCpl;
use App\Models\Akademik\TahunAkademik;
use App\Services\Akademik\JurnalService;
use App\Services\Akademik\RpsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Teaching plans and journals, caught mid-semester.
 *
 * Three states are arranged deliberately, because a demo where everything is
 * complete demonstrates none of the screens that matter:
 *
 *   - most classes have a published plan with components mapped to outcomes, so
 *     the mastery figures are real;
 *   - one class has a plan but no outcome mapping, so the "belum dipetakan"
 *     path is exercised rather than assumed;
 *   - journals stop short of the meetings actually held, so the gap between
 *     "terlaksana" and "berjurnal" — the finding the screen exists for — is
 *     visible.
 */
class RpsSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string}> */
    private const MINGGU = [
        ['Menjelaskan ruang lingkup dan kontrak perkuliahan', 'Pendahuluan'],
        ['Menjelaskan konsep dasar dan terminologinya', 'Konsep dasar'],
        ['Menerapkan konsep dasar pada kasus sederhana', 'Studi kasus awal'],
        ['Menganalisis persoalan menjadi bagian-bagiannya', 'Analisis'],
        ['Merancang solusi atas persoalan terstruktur', 'Perancangan'],
        ['Mengevaluasi alternatif rancangan', 'Evaluasi rancangan'],
        ['Menyusun rencana implementasi', 'Implementasi'],
        ['Ujian Tengah Semester', 'Materi pekan 1–7'],
        ['Menerapkan metode lanjutan', 'Metode lanjutan'],
        ['Menganalisis studi kasus industri', 'Studi kasus industri'],
        ['Merancang solusi untuk kasus nyata', 'Perancangan lanjut'],
        ['Menguji dan memvalidasi rancangan', 'Pengujian'],
        ['Mengomunikasikan hasil secara tertulis', 'Pelaporan'],
        ['Mempresentasikan hasil kerja', 'Presentasi'],
        ['Merefleksikan keseluruhan materi', 'Sintesis'],
        ['Ujian Akhir Semester', 'Materi pekan 9–15'],
    ];

    public function run(): void
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            return;
        }

        $rpsService = app(RpsService::class);
        $jurnal = app(JurnalService::class);

        $kelas = KelasKuliah::with(['mataKuliah', 'dosen'])
            ->where('tahun_akademik_id', $term->id)
            ->orderBy('id')
            ->get();

        // One plan per course, not per class — parallel classes share it.
        $perMataKuliah = $kelas->unique('mata_kuliah_id')->take(8);

        foreach ($perMataKuliah as $index => $satu) {
            $cpl = ProdiCpl::where('prodi_id', $satu->mataKuliah->prodi_id)
                ->orderBy('kode')
                ->get();

            if ($cpl->isEmpty()) {
                continue;
            }

            $this->susunRps($rpsService, $satu, $cpl, $index);
        }

        foreach ($kelas as $index => $satu) {
            $this->isiJurnal($jurnal, $satu, $index);
        }

        /*
         * Outcome mapping covers every term, not just the running one.
         *
         * Mid-semester the active term has no marks yet — that is what
         * mid-semester means — so mapping only its components would leave the
         * mastery screens empty and the whole module looking broken. A campus
         * adopting this maps its historical components for exactly the same
         * reason: without a baseline there is nothing to compare against.
         */
        $semuaKelas = KelasKuliah::with('mataKuliah')->orderBy('id')->get();

        foreach ($semuaKelas as $index => $satu) {
            $this->petakanKomponen($satu, $index);
        }
    }

    /** @param  Collection<int, ProdiCpl>  $cpl */
    private function susunRps(RpsService $service, KelasKuliah $kelas, Collection $cpl, int $index): void
    {
        $penyusun = $kelas->dosen->first();

        $rps = $service->mulai($kelas->mataKuliah, $kelas->tahunAkademik, $penyusun);

        $service->simpanPertemuan($rps, collect(self::MINGGU)
            ->map(fn (array $m, int $i): array => [
                'pertemuan_ke' => $i + 1,
                'kemampuan_akhir' => $m[0],
                'bahan_kajian' => $m[1],
                'metode' => in_array($i + 1, [8, 16], true) ? 'ujian tulis' : 'ceramah & diskusi',
                'indikator' => 'Ketepatan '.lcfirst($m[0]),

                // 4 × 10 + 12 × 5 = 100
                'bobot' => in_array($i + 1, [4, 8, 12, 16], true) ? 10 : 5,
            ])
            ->all());

        // Two or three outcomes per course, never all of them.
        $service->simpanCpl($rps, $cpl->take(2 + ($index % 2))
            ->map(fn (ProdiCpl $c): array => [
                'prodi_cpl_id' => $c->id,
                'rumusan' => 'Mahasiswa mampu '.lcfirst(Str::limit($c->deskripsi, 60, '')),
            ])
            ->all());

        $service->terbitkan($rps->fresh(['pertemuan', 'cpl']), $penyusun);
    }

    /**
     * Ties grade components to outcomes.
     *
     * Every fifth class is left unmapped on purpose — that is the state the
     * "belum dipetakan" screen exists for, and a demo where it never appears
     * would let the message rot unnoticed.
     */
    private function petakanKomponen(KelasKuliah $kelas, int $index): void
    {
        if ($index % 5 === 4) {
            return;
        }

        /*
         * The outcomes of the course's own programme, not the plan's.
         *
         * A plan only exists for the running term, and the components worth
         * mapping are mostly in terms that are already closed. The mapping is a
         * property of the component either way — the plan says what the course
         * claims, this says what the exam measured.
         */
        $daftarCpl = ProdiCpl::where('prodi_id', $kelas->mataKuliah->prodi_id)
            ->orderBy('kode')
            ->take(3)
            ->get();

        if ($daftarCpl->isEmpty()) {
            return;
        }

        $komponen = KomponenNilai::where('kelas_kuliah_id', $kelas->id)->orderBy('id')->get();

        foreach ($komponen as $i => $k) {
            if ($k->cpl()->exists()) {
                continue;
            }

            $utama = $daftarCpl[$i % $daftarCpl->count()];

            /*
             * The midterm measures two outcomes, split 70/30.
             *
             * Present in the demo because a single-outcome mapping everywhere
             * would let the split-weight arithmetic go unexercised by anything a
             * person actually looks at.
             */
            if (Str::contains(Str::lower($k->nama), 'uts') && $daftarCpl->count() > 1) {
                $k->cpl()->syncWithoutDetaching([
                    $utama->id => ['porsi' => 70],
                    $daftarCpl[($i + 1) % $daftarCpl->count()]->id => ['porsi' => 30],
                ]);

                continue;
            }

            $k->cpl()->syncWithoutDetaching([$utama->id => ['porsi' => 100]]);
        }
    }

    /**
     * Fills journals for meetings already held — but not all of them.
     *
     * The shortfall is the point: a class with more meetings held than
     * journalled has a documentation problem, and that is the number the
     * programme screen chases.
     */
    private function isiJurnal(JurnalService $jurnal, KelasKuliah $kelas, int $index): void
    {
        $dosen = $kelas->dosen->first();

        if ($dosen === null) {
            return;
        }

        $terlaksana = PertemuanKelas::where('kelas_kuliah_id', $kelas->id)
            ->where('is_terlaksana', true)
            ->orderBy('pertemuan_ke')
            ->get();

        // Two meetings behind, varying by class so the list is not uniform.
        $isi = max(0, $terlaksana->count() - (1 + $index % 3));

        foreach ($terlaksana->take($isi) as $p) {
            $rencana = self::MINGGU[$p->pertemuan_ke - 1] ?? null;

            $jurnal->isi(
                $p,
                $dosen,
                $rencana === null ? 'Materi pertemuan '.$p->pertemuan_ke : $rencana[1],
            );
        }
    }
}
