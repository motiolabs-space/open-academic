<?php

declare(strict_types=1);

namespace App\Services\Lkps;

use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Dosen;
use Illuminate\Database\Eloquent\Builder;

/**
 * The quantities an accreditation form asks for, computed once.
 *
 * Written as a *canonical* layer rather than as one LAM's form. Across BAN-PT
 * and the discipline LAMs, what differs is mostly presentation — table
 * numbering, column grouping, a few definitions. What is being counted is
 * largely the same: intake and selectivity, staffing ratio, teaching load,
 * grade distribution, time to degree, attrition.
 *
 * So the arrangement into a particular form belongs one layer up. A second LAM
 * is then a second arranger, not a second project. Same reasoning as the
 * canonical payload recommended for Feeder in docs/PELAPORAN.md, and for the
 * same reason: the differences between destinations belong in a thin layer at
 * the end.
 *
 * **Every definition here is configurable and none of them is guessed.** The
 * decisions live in config/lkps.php with their consequences written out in
 * docs/LKPS-DEFINISI.md, because they are the campus's to make — an accredited
 * figure computed on an assumption nobody agreed to is worse than no figure.
 *
 * Where a quantity cannot be computed, this returns `tersedia: false` with the
 * reason. It never returns zero for something it did not measure: a zero in an
 * accreditation table is a claim about the campus, not about the software.
 */
class IndikatorLkps
{
    /* ---------------------------------------------------------------------
     | Mahasiswa masuk
     |-------------------------------------------------------------------- */

    /**
     * Intake funnel for one programme in one admission year.
     *
     * @return array{pendaftar: int, diterima: int, daftar_ulang: int, keketatan: float|null}
     */
    public function keketatan(Prodi $prodi, int $tahun): array
    {
        $dasar = fn (): Builder => PmbPendaftar::query()
            ->whereHas('gelombang', fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->where(function ($q) use ($prodi): void {
                $q->where('prodi_pilihan_1_id', $prodi->id);

                if (config('lkps.keketatan.prodi_dari') === 'semua_pilihan') {
                    $q->orWhere('prodi_pilihan_2_id', $prodi->id);
                }
            });

        $pendaftar = $dasar()->whereIn('status', $this->tahapPendaftar())->count();
        $diterima = $dasar()->whereIn('status', (array) config('lkps.keketatan.diterima'))->count();
        $daftarUlang = $dasar()->whereIn('status', (array) config('lkps.keketatan.daftar_ulang'))->count();

        return [
            'pendaftar' => $pendaftar,
            'diterima' => $diterima,
            'daftar_ulang' => $daftarUlang,

            // Null, not 1.0, when nobody was admitted. A ratio with a zero
            // denominator is undefined, and printing 1.0 would report perfect
            // selectivity for a programme that admitted nobody.
            'keketatan' => $diterima > 0 ? round($pendaftar / $diterima, 2) : null,
        ];
    }

    /**
     * Applicant statuses at or beyond the configured starting stage.
     *
     * The funnel is ordered, so "sejak verifikasi" means verifikasi and
     * everything downstream of it — a registrant who has already enrolled
     * passed through verification on the way.
     *
     * @return array<int, string>
     */
    private function tahapPendaftar(): array
    {
        $urutan = ['mendaftar', 'verifikasi', 'seleksi', 'lulus', 'tidak_lulus', 'daftar_ulang', 'mahasiswa'];
        $mulai = (string) config('lkps.keketatan.pendaftar_sejak');
        $posisi = array_search($mulai, $urutan, true);

        return $posisi === false ? $urutan : array_slice($urutan, $posisi);
    }

    /* ---------------------------------------------------------------------
     | Mahasiswa & dosen
     |-------------------------------------------------------------------- */

    /** Enrolled students in one programme in one term. */
    public function mahasiswaAktif(Prodi $prodi, TahunAkademik $term): int
    {
        return StatusMahasiswa::query()
            ->where('tahun_akademik_id', $term->id)
            ->whereIn('status', (array) config('lkps.mahasiswa_aktif.status'))
            ->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $prodi->id))
            ->count();
    }

    /** Permanent teaching staff of one programme. */
    public function dtps(Prodi $prodi): int
    {
        return $this->kueriDtps($prodi)->count();
    }

    /**
     * Students per permanent lecturer.
     *
     * Null when the programme has no permanent lecturer on record — dividing
     * by zero is not the finding, "no lecturers recorded" is, and the caller
     * needs to be able to tell them apart.
     */
    public function rasioDosenMahasiswa(Prodi $prodi, TahunAkademik $term): ?float
    {
        $dosen = $this->dtps($prodi);

        return $dosen > 0 ? round($this->mahasiswaAktif($prodi, $term) / $dosen, 2) : null;
    }

    /** @return Builder<Dosen> */
    private function kueriDtps(Prodi $prodi): Builder
    {
        $query = Dosen::aktif()
            ->where('prodi_id', $prodi->id)
            ->whereIn('status_kepegawaian', (array) config('lkps.dtps.status_kepegawaian'));

        if (!config('lkps.dtps.sertakan_praktisi')) {
            $query->where('is_praktisi', false);
        }

        return $query;
    }

    /* ---------------------------------------------------------------------
     | Lulusan
     |-------------------------------------------------------------------- */

    /**
     * Graduates of one programme in one calendar year.
     *
     * @return array{jumlah: int, ipk_rata: float|null, ipk_min: float|null, ipk_maks: float|null, masa_studi_rata: float|null, tepat_waktu: int, dikecualikan: int, catatan: string|null}
     */
    public function lulusan(Prodi $prodi, int $tahun): array
    {
        $lulusan = Yudisium::query()
            ->with(['mahasiswa', 'tahunAkademik'])
            ->whereYear('tanggal_lulus', $tahun)
            ->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $prodi->id))
            ->get();

        if ($lulusan->isEmpty()) {
            return [
                'jumlah' => 0, 'ipk_rata' => null, 'ipk_min' => null, 'ipk_maks' => null,
                'masa_studi_rata' => null, 'tepat_waktu' => 0, 'dikecualikan' => 0,
                'catatan' => null,
            ];
        }

        $batas = $this->batasSemester($prodi);
        $bisaBedakanJalur = $this->pemetaanJalurMasukAda();

        $tepat = $dikecualikan = 0;
        $masaStudi = [];

        foreach ($lulusan as $satu) {
            if (config('lkps.tepat_waktu.kecualikan_alih_jenjang')
                && $bisaBedakanJalur
                && $this->bukanMahasiswaBaru($satu->mahasiswa)) {
                $dikecualikan++;

                continue;
            }

            $semester = $this->semesterTempuh($satu);

            if ($semester === null) {
                continue;
            }

            $masaStudi[] = $semester;

            if ($batas !== null && $semester <= $batas) {
                $tepat++;
            }
        }

        return [
            'jumlah' => $lulusan->count(),
            'ipk_rata' => round((float) $lulusan->avg('ipk'), 2),
            'ipk_min' => round((float) $lulusan->min('ipk'), 2),
            'ipk_maks' => round((float) $lulusan->max('ipk'), 2),
            'masa_studi_rata' => $masaStudi === [] ? null : round(array_sum($masaStudi) / count($masaStudi), 2),
            'tepat_waktu' => $tepat,
            'dikecualikan' => $dikecualikan,

            /*
             * The caveat travels with the number.
             *
             * A campus that never filled the jenis_daftar mapping cannot tell a
             * transfer student from a first-year one — so the exclusion the
             * config asks for silently does not happen, and the time-to-degree
             * average quietly includes people who started elsewhere.
             */
            'catatan' => config('lkps.tepat_waktu.kecualikan_alih_jenjang') && !$bisaBedakanJalur
                ? 'Mahasiswa alih jenjang tidak dapat dipisahkan: pemetaan jenis_daftar '
                    .'belum diisi, jadi seluruh lulusan ikut dihitung.'
                : null,
        ];
    }

    /**
     * How many semesters one graduate took.
     *
     * `semester_ke` on the term's status row is the campus's own count and is
     * preferred. The arithmetic on PDDIKTI term codes is the fallback for a
     * record that predates the status row.
     */
    private function semesterTempuh(Yudisium $yudisium): ?int
    {
        $status = StatusMahasiswa::query()
            ->where('mahasiswa_id', $yudisium->mahasiswa_id)
            ->where('tahun_akademik_id', $yudisium->tahun_akademik_id)
            ->first();

        $semester = $status?->semester_ke ?? $this->semesterAntara(
            $yudisium->mahasiswa->term_masuk,
            $yudisium->tahunAkademik->kode,
        );

        if ($semester === null) {
            return null;
        }

        if (config('lkps.masa_studi.kurangi_cuti')) {
            $semester -= StatusMahasiswa::query()
                ->where('mahasiswa_id', $yudisium->mahasiswa_id)
                ->where('status', 'C')
                ->count();
        }

        return max(1, $semester);
    }

    /**
     * Semesters between two PDDIKTI term codes.
     *
     * A code is the starting year followed by the semester digit: 20261 is the
     * odd semester of 2026/2027. Two per year, so the difference is arithmetic
     * — but only for the regular semesters. The short semester (3) is not a
     * step in a student's progress and would add half a year to everyone who
     * happens to graduate in one.
     */
    private function semesterAntara(?string $dari, ?string $sampai): ?int
    {
        if (!$this->kodeTermSah($dari) || !$this->kodeTermSah($sampai)) {
            return null;
        }

        $urai = static fn (string $kode): array => [
            (int) substr($kode, 0, 4),
            min(2, (int) substr($kode, 4, 1)),
        ];

        [$tahunA, $semA] = $urai((string) $dari);
        [$tahunB, $semB] = $urai((string) $sampai);

        return ($tahunB - $tahunA) * 2 + ($semB - $semA) + 1;
    }

    private function kodeTermSah(?string $kode): bool
    {
        return $kode !== null && preg_match('/^\d{4}[123]$/', $kode) === 1;
    }

    /** Configured semester limit for this programme's level. */
    private function batasSemester(Prodi $prodi): ?int
    {
        $batas = (array) config('lkps.tepat_waktu.batas_semester');

        return $batas[$prodi->jenjang->name] ?? null;
    }

    /* ---------------------------------------------------------------------
     | Putus studi
     |-------------------------------------------------------------------- */

    /**
     * Students who left without graduating, in one term.
     *
     * Counts the configured statuses. The consecutive-inactive threshold is
     * deliberately not applied here yet — see the note in config/lkps.php; it
     * needs a rule for what "consecutive" means across a leave of absence, and
     * that is one of the eight open decisions.
     */
    public function putusStudi(Prodi $prodi, TahunAkademik $term): int
    {
        return StatusMahasiswa::query()
            ->where('tahun_akademik_id', $term->id)
            ->whereIn('status', (array) config('lkps.putus_studi.status'))
            ->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $prodi->id))
            ->count();
    }

    /* ---------------------------------------------------------------------
     | Yang tidak dapat dihitung dari sini
     |-------------------------------------------------------------------- */

    /**
     * Table groups this application cannot fill, and why.
     *
     * Returned as data so an arranger prints the reason into the form instead
     * of leaving cells blank. A blank cell in an accreditation table is read
     * as a zero, and a zero in the research table is a very different claim
     * from "this system does not hold that".
     *
     * @return array<string, string>
     */
    public function tidakTersedia(): array
    {
        $hasil = [
            'tracer_study' => 'Tabel alumni menyimpan status pekerjaan sebagai kerangka, '
                .'bukan instrumen tracer study. Instrumennya milik Open Campus.',

            'penelitian_pkm' => 'penugasan_dosen adalah catatan mandiri dosen untuk BKD dan IKU. '
                .'Tidak ada usulan, review, kontrak, maupun luaran — ini bukan basis data penelitian.',
        ];

        if (config('lkps.kepuasan.sumber') === null) {
            $hasil['kepuasan_layanan'] = 'LKPS menanyakan kepuasan atas layanan; yang tersedia EDOM, '
                .'yaitu kepuasan atas pengajaran per dosen. Keduanya tidak sama.';
        }

        return $hasil;
    }

    /* ---------------------------------------------------------------------
     | Jalur masuk
     |-------------------------------------------------------------------- */

    /**
     * Whether the campus can distinguish a transfer student at all.
     *
     * The distinction is not stored directly: `mahasiswa.jalur_masuk` holds the
     * campus's own wording, translated to a PDDIKTI jenis_daftar code through
     * FeederMapping. A campus that never filled that mapping has the column but
     * not the meaning.
     */
    private function pemetaanJalurMasukAda(): bool
    {
        return FeederMapping::query()->where('group', 'jenis_daftar')->exists();
    }

    /** Code '1' is a first-year entrant; anything else started somewhere else. */
    private function bukanMahasiswaBaru(?Mahasiswa $mahasiswa): bool
    {
        if ($mahasiswa?->jalur_masuk === null) {
            return false;
        }

        $kode = FeederMapping::toFeeder('jenis_daftar', $mahasiswa->jalur_masuk);

        return $kode !== null && $kode !== '1';
    }
}
