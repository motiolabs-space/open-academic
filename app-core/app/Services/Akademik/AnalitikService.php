<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\ProdiCpl;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Three lenses over data the campus already has.
 *
 * **What this class refuses to do is most of its design.** It does not predict.
 * There is no model here estimating whether a student will pass, because
 * Open Academic has neither the history nor the validation to make such a claim
 * honest — and a number presented as a forecast is trusted like a forecast.
 *
 * What it produces instead:
 *
 *   - **Fakta** — attendance percentages, mark distributions, outcome
 *     attainment. Counted, not estimated.
 *   - **Aturan** — flags raised when a figure crosses a threshold the campus
 *     configured. Every one of them is reported together with the rule that
 *     raised it, so a reader can disagree with the rule rather than with the
 *     student.
 *
 * The word "potensi" in the mastery lens means attainment measured so far
 * against outcomes assessed so far. It is a reading of the present, extrapolated
 * by nothing.
 */
class AnalitikService
{
    public function __construct(
        private readonly PresensiService $presensi,
        private readonly JurnalService $jurnal,
    ) {}

    /**
     * Attendance, for one class.
     *
     * Delegates the arithmetic to PresensiService rather than repeating it. Two
     * implementations of "percentage attended" is how a dashboard and a grade
     * sheet come to disagree about the same student — the lesson already learned
     * when three IPK calculations were consolidated into PerolehanAkademik.
     *
     * @return array<string, mixed>
     */
    public function kehadiran(KelasKuliah $kelas): array
    {
        $rekap = $this->presensi->rekapKelas($kelas);
        $minimum = (float) config('academic.attendance.min_percent_for_final_exam', 75);

        $peserta = $this->presensi->pesertaKelas($kelas);

        $persen = $peserta->map(fn (Mahasiswa $m): array => [
            'mahasiswa' => $m,
            'persen' => $this->presensi->persenKehadiran($m, $kelas),
        ])->filter(fn (array $b): bool => $b['persen'] !== null);

        return [
            'rekap' => $rekap,
            'ambang' => $minimum,
            'jumlah_peserta' => $peserta->count(),

            'rerata' => $persen->isEmpty()
                ? null
                : round((float) $persen->avg('persen'), 1),

            /*
             * Below the threshold, sorted worst first.
             *
             * A rule, and reported as one: the threshold travels with the list
             * so whoever reads it can argue with the number rather than with the
             * students under it.
             */
            'di_bawah_ambang' => $persen
                ->filter(fn (array $b): bool => $b['persen'] < $minimum)
                ->sortBy('persen')
                ->values(),

            'sebaran' => $this->sebaranPersen($persen->pluck('persen')),
        ];
    }

    /**
     * Marks, for one class.
     *
     * Reported per component as well as overall, because "the class did badly"
     * is not actionable and "the class did badly on the practical" is.
     *
     * @return array<string, mixed>
     */
    public function penilaian(KelasKuliah $kelas): array
    {
        $kelas->loadMissing('komponenNilai');

        // One grouped query for every component, rather than one query per
        // component inside a loop.
        $perKomponen = DB::table('nilai_komponen')
            ->join('komponen_nilai', 'komponen_nilai.id', '=', 'nilai_komponen.komponen_nilai_id')
            ->where('komponen_nilai.kelas_kuliah_id', $kelas->id)
            ->whereNotNull('nilai_komponen.nilai')
            ->groupBy('komponen_nilai.id', 'komponen_nilai.nama', 'komponen_nilai.bobot')
            ->selectRaw(
                'komponen_nilai.id, komponen_nilai.nama, komponen_nilai.bobot, '
                .'COUNT(*) as terisi, AVG(nilai_komponen.nilai) as rerata, '
                .'MIN(nilai_komponen.nilai) as terendah, MAX(nilai_komponen.nilai) as tertinggi'
            )
            ->get()
            ->map(fn ($r): array => [
                'nama' => $r->nama,
                'bobot' => (int) $r->bobot,
                'terisi' => (int) $r->terisi,
                'rerata' => round((float) $r->rerata, 2),
                'terendah' => round((float) $r->terendah, 2),
                'tertinggi' => round((float) $r->tertinggi, 2),
            ]);

        /*
         * `is_final`, bukan `nilai_akhir`.
         *
         * `nilai_akhir` kolom milik `tugas_akhir` — nilai sidang, bukan nilai
         * mata kuliah. Tabel `nilai` memakai `is_final` sebagai penanda
         * finalisasi dan `nilai_angka` untuk angkanya; baris di bawah yang
         * membaca `nilai_huruf` sudah membuktikan yang diambil memang baris
         * `nilai`.
         *
         * Sebelum diperbaiki, layar Analitik Kelas per-kelas membalas 500
         * dengan "Unknown column 'nilai_akhir'" — dan tidak ada satu pun tes
         * yang pernah membuka layar itu.
         */
        $final = $kelas->nilai()->where('is_final', true)->get();

        return [
            'per_komponen' => $perKomponen,

            /*
             * The weakest component, named.
             *
             * The single most useful line on the screen, and the one a lecturer
             * would otherwise derive by eye from a table of five rows every
             * time.
             */
            'komponen_terlemah' => $perKomponen->sortBy('rerata')->first(),

            'sudah_final' => $final->count(),
            // `avg()` pada Collection mengabaikan null, jadi nilai final yang
            // angkanya belum terisi tidak menyeret reratanya ke bawah.
            'rerata_akhir' => $final->isEmpty() ? null : round((float) $final->avg('nilai_angka'), 2),

            'sebaran_huruf' => $final
                ->groupBy(fn ($n): string => $n->nilai_huruf?->value ?? '—')
                ->map(fn (Collection $g): int => $g->count())
                ->sortKeys(),

            /*
             * Asks the enum rather than comparing to 'E'.
             *
             * The passing threshold is a property of the letter scale, and that
             * scale is configurable per campus — a literal here would quietly
             * become wrong the moment somebody adds a D- or renames the fail
             * grade.
             */
            'tidak_lulus' => $final
                ->filter(fn ($n): bool => $n->nilai_huruf !== null && !$n->nilai_huruf->isPassing())
                ->count(),
        ];
    }

    /**
     * Outcome attainment — the mastery lens.
     *
     * Only meaningful once grade components have been mapped to programme
     * outcomes; before that this returns nothing rather than a zero, because a
     * zero here would read as "students mastered nothing" when it means "nobody
     * said what this exam measures".
     *
     * The figure is a weighted mean of the marks on every component tied to an
     * outcome, weighted by the component's share of the grade **and** by the
     * share of that component attributed to the outcome. A midterm worth 30% of
     * the grade, 60% of which measures CPL-02, contributes 18 points of weight
     * to CPL-02.
     *
     * @return array<string, mixed>
     */
    public function penguasaanKelas(KelasKuliah $kelas): array
    {
        $baris = $this->baris($kelas->id);

        if ($baris->isEmpty()) {
            return ['terpetakan' => false, 'cpl' => collect()];
        }

        $cpl = ProdiCpl::whereIn('id', $baris->pluck('prodi_cpl_id')->unique())->get()->keyBy('id');
        $ambang = (float) config('academic.cpl.ambang_penguasaan', 65);

        return [
            'terpetakan' => true,
            'ambang' => $ambang,

            'cpl' => $baris
                ->groupBy('prodi_cpl_id')
                ->map(function (Collection $g, int|string $id) use ($cpl, $ambang): array {
                    $nilai = $this->rerataTerbobot($g);

                    return [
                        'cpl' => $cpl->get((int) $id),
                        'nilai' => $nilai,
                        'jumlah_pengukuran' => $g->count(),
                        'mahasiswa_dinilai' => $g->pluck('krs_detail_id')->unique()->count(),
                        'tercapai' => $nilai !== null && $nilai >= $ambang,
                    ];
                })
                ->filter(fn (array $b): bool => $b['cpl'] !== null)
                ->sortBy(fn (array $b): string => $b['cpl']->kode)
                ->values(),
        ];
    }

    /**
     * One student's outcome attainment across every class they have taken.
     *
     * The view that makes the whole module worth building: a mark of 68 in three
     * courses says nothing, but "consistently weak on CPL-03 wherever it is
     * measured" is something an advisor can raise in a meeting.
     *
     * @return array<string, mixed>
     */
    public function penguasaanMahasiswa(Mahasiswa $mahasiswa): array
    {
        $baris = $this->baris(mahasiswaId: $mahasiswa->id);

        if ($baris->isEmpty()) {
            return ['terpetakan' => false, 'cpl' => collect()];
        }

        $cpl = ProdiCpl::whereIn('id', $baris->pluck('prodi_cpl_id')->unique())->get()->keyBy('id');
        $ambang = (float) config('academic.cpl.ambang_penguasaan', 65);

        return [
            'terpetakan' => true,
            'ambang' => $ambang,

            'cpl' => $baris
                ->groupBy('prodi_cpl_id')
                ->map(function (Collection $g, int|string $id) use ($cpl, $ambang): array {
                    $nilai = $this->rerataTerbobot($g);

                    return [
                        'cpl' => $cpl->get((int) $id),
                        'nilai' => $nilai,
                        'jumlah_pengukuran' => $g->count(),
                        'mata_kuliah' => $g->pluck('mata_kuliah')->unique()->values(),
                        'tercapai' => $nilai !== null && $nilai >= $ambang,
                    ];
                })
                ->filter(fn (array $b): bool => $b['cpl'] !== null)
                ->sortBy(fn (array $b): float => $b['nilai'] ?? 0)
                ->values(),
        ];
    }

    /**
     * Students a lecturer should look at, and the reason for each.
     *
     * Reasons are strings, not scores. A ranked "risk index" invites the reader
     * to treat an arithmetic combination of two unrelated numbers as a
     * prediction; a list of stated reasons invites them to check.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perluPerhatian(KelasKuliah $kelas): Collection
    {
        $minimumHadir = (float) config('academic.attendance.min_percent_for_final_exam', 75);
        $minimumNilai = (float) config('academic.cpl.ambang_penguasaan', 65);

        $nilaiPerMahasiswa = DB::table('nilai_komponen')
            ->join('komponen_nilai', 'komponen_nilai.id', '=', 'nilai_komponen.komponen_nilai_id')
            ->join('krs_detail', 'krs_detail.id', '=', 'nilai_komponen.krs_detail_id')
            ->join('krs', 'krs.id', '=', 'krs_detail.krs_id')
            ->where('komponen_nilai.kelas_kuliah_id', $kelas->id)
            ->whereNotNull('nilai_komponen.nilai')
            ->groupBy('krs.mahasiswa_id')
            ->selectRaw('krs.mahasiswa_id, AVG(nilai_komponen.nilai) as rerata, COUNT(*) as terisi')
            ->get()
            ->keyBy('mahasiswa_id');

        return $this->presensi->pesertaKelas($kelas)
            ->map(function (Mahasiswa $m) use ($kelas, $nilaiPerMahasiswa, $minimumHadir, $minimumNilai): ?array {
                $persen = $this->presensi->persenKehadiran($m, $kelas);
                $nilai = $nilaiPerMahasiswa->get($m->id);

                $alasan = [];

                if ($persen !== null && $persen < $minimumHadir) {
                    $alasan[] = sprintf(
                        'Kehadiran %s%%, di bawah ambang %s%% untuk mengikuti UAS.',
                        number_format($persen, 1, ',', '.'),
                        number_format($minimumHadir, 0, ',', '.'),
                    );
                }

                if ($nilai !== null && (float) $nilai->rerata < $minimumNilai) {
                    $alasan[] = sprintf(
                        'Rerata %s dari %d komponen yang sudah dinilai.',
                        number_format((float) $nilai->rerata, 1, ',', '.'),
                        (int) $nilai->terisi,
                    );
                }

                /*
                 * Nothing marked yet, and already absent.
                 *
                 * Worth naming separately: it is the only case where waiting for
                 * grades means waiting until it is too late to act.
                 */
                if ($nilai === null && $persen !== null && $persen < 100.0) {
                    $alasan[] = 'Belum ada satu pun nilai komponen, sementara kehadiran sudah tidak penuh.';
                }

                return $alasan === [] ? null : [
                    'mahasiswa' => $m,
                    'persen_kehadiran' => $persen,
                    'rerata_nilai' => $nilai === null ? null : round((float) $nilai->rerata, 1),
                    'alasan' => $alasan,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Programme-level roll-up for one term.
     *
     * @param Collection<int, KelasKuliah> $kelas
     * @return array<string, mixed>
     */
    public function ringkasProdi(Collection $kelas): array
    {
        $keterlaksanaan = $kelas->map(fn (KelasKuliah $k): array => $this->jurnal->keterlaksanaan($k));

        return [
            'jumlah_kelas' => $kelas->count(),
            'kelas_ber_rps' => $keterlaksanaan->where('ada_rps', true)->count(),

            'rerata_persen_terlaksana' => $keterlaksanaan->isEmpty()
                ? null
                : round((float) $keterlaksanaan->avg('persen_terlaksana'), 1),

            'rerata_persen_berjurnal' => $keterlaksanaan->isEmpty()
                ? null
                : round((float) $keterlaksanaan->avg('persen_berjurnal'), 1),
        ];
    }

    /**
     * Raw measurement rows: one per (student, component, outcome).
     *
     * A single join rather than walking relations, because this is the query
     * that would otherwise become one-per-component inside a loop over
     * one-per-student.
     *
     * @return Collection<int, object>
     */
    private function baris(?int $kelasId = null, ?int $mahasiswaId = null): Collection
    {
        return DB::table('komponen_nilai_cpl')
            ->join('komponen_nilai', 'komponen_nilai.id', '=', 'komponen_nilai_cpl.komponen_nilai_id')
            ->join('nilai_komponen', 'nilai_komponen.komponen_nilai_id', '=', 'komponen_nilai.id')
            ->join('krs_detail', 'krs_detail.id', '=', 'nilai_komponen.krs_detail_id')
            ->join('krs', 'krs.id', '=', 'krs_detail.krs_id')
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'komponen_nilai.kelas_kuliah_id')
            ->join('mata_kuliah', 'mata_kuliah.id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->whereNotNull('nilai_komponen.nilai')
            ->when($kelasId !== null, fn ($q) => $q->where('komponen_nilai.kelas_kuliah_id', $kelasId))
            ->when($mahasiswaId !== null, fn ($q) => $q->where('krs.mahasiswa_id', $mahasiswaId))
            ->select([
                'komponen_nilai_cpl.prodi_cpl_id',
                'komponen_nilai_cpl.porsi',
                'komponen_nilai.bobot',
                'nilai_komponen.nilai',
                'nilai_komponen.krs_detail_id',
                'mata_kuliah.nama as mata_kuliah',
            ])
            ->get();
    }

    /**
     * Weighted mean of measurement rows.
     *
     * Weight is the component's share of the grade times the outcome's share of
     * the component. An exam worth 30% of a course, 60% of which measures this
     * outcome, carries eighteen units of weight — not thirty, and not one.
     *
     * @param Collection<int, object> $baris
     */
    private function rerataTerbobot(Collection $baris): ?float
    {
        $bobot = 0.0;
        $jumlah = 0.0;

        foreach ($baris as $b) {
            $w = ((int) $b->bobot) * ((int) $b->porsi) / 100;

            if ($w <= 0) {
                continue;
            }

            $bobot += $w;
            $jumlah += $w * (float) $b->nilai;
        }

        return $bobot > 0 ? round($jumlah / $bobot, 2) : null;
    }

    /**
     * @param Collection<int, float> $persen
     * @return array<string, int>
     */
    private function sebaranPersen(Collection $persen): array
    {
        return [
            '<50' => $persen->filter(fn (float $p): bool => $p < 50)->count(),
            '50-74' => $persen->filter(fn (float $p): bool => $p >= 50 && $p < 75)->count(),
            '75-89' => $persen->filter(fn (float $p): bool => $p >= 75 && $p < 90)->count(),
            '90-100' => $persen->filter(fn (float $p): bool => $p >= 90)->count(),
        ];
    }
}
