<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Konsentrasi;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\PaketKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\PadananMataKuliah;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A campus that has already replaced its curriculum once, branched into tracks,
 * and runs one programme on issued study plans.
 *
 * The three features are only legible against that history. Equivalence with a
 * single curriculum has nothing to map; tracks with no track courses are an
 * empty list; a package in a programme that lets students choose is a button
 * nobody presses. So the demo supplies the history rather than the feature.
 */
class KurikulumLanjutanSeeder extends Seeder
{
    /**
     * Superseded course => the current course that replaces it.
     *
     * @var array<string, array{0: string, 1: int, 2: string}>
     */
    private const WARISAN = [
        'IF0105' => ['Dasar-Dasar Pemrograman', 4, 'IF1105'],
        'IF0203' => ['Algoritma dan Struktur Data', 4, 'IF1203'],
        'IF0101' => ['Basis Data Relasional', 3, 'IF2101'],
    ];

    /** kode => [nama, konsentrasi] */
    private const PEMINATAN = [
        'RPL' => ['Rekayasa Perangkat Lunak', [
            'IF3101' => ['Arsitektur Perangkat Lunak', 3],
            'IF3102' => ['Penjaminan Mutu Perangkat Lunak', 3],
        ]],
        'JAR' => ['Jaringan dan Keamanan', [
            'IF3201' => ['Administrasi Jaringan', 3],
            'IF3202' => ['Keamanan Siber', 3],
        ]],
    ];

    public function __construct(private readonly PadananMataKuliah $padanan) {}

    public function run(): void
    {
        /*
         * Found through the courses this seeder actually needs, not through a
         * programme code. `prodi.kode` holds the PDDIKTI number (55201), so
         * matching on "IF" silently seeded nothing — and a demo seeder that
         * quietly does nothing is worse than one that stops, because the empty
         * screen it produces looks like a broken feature.
         */
        $informatika = Prodi::whereHas(
            'mataKuliah',
            fn ($query) => $query->whereIn('kode', array_column(self::WARISAN, 2)),
        )->first();

        $berpaket = Prodi::where('id', '!=', $informatika?->id)->first();

        if ($informatika === null || $berpaket === null) {
            throw new RuntimeException(
                'KurikulumLanjutanSeeder butuh dua prodi dengan kurikulum aktif; jalankan MasterAkademikSeeder lebih dulu.',
            );
        }

        $this->seedPadanan($informatika);
        $this->seedKonsentrasi($informatika);
        $this->seedPaket($berpaket);
    }

    /**
     * A retired curriculum, and the equivalences that keep its students moving.
     *
     * The chain is deliberately two hops long in one place — IF0105 is
     * recognised as IF1105, which is itself recognised as nothing further, but
     * IF0203 → IF1203 sits behind a prerequisite on IF1105. That is the shape
     * that breaks a one-hop resolver, so the demo contains it.
     */
    private function seedPadanan(Prodi $prodi): void
    {
        $tahun = (int) date('Y') - 8;

        $lama = Kurikulum::create([
            'prodi_id' => $prodi->id,
            'kode' => 'KUR'.$tahun,
            'nama' => 'Kurikulum '.$tahun.' (tidak berlaku)',
            'tahun_mulai' => $tahun,
            'sks_wajib' => 120,
            'sks_pilihan' => 24,
            'sks_lulus' => $prodi->sks_lulus,

            // Superseded, not deleted. A retired curriculum still has to be
            // readable: transcripts and equivalences both point back into it.
            'is_active' => false,
        ]);

        $semester = 1;

        foreach (self::WARISAN as $kode => [$nama, $sks, $penggantiKode]) {
            $pengganti = MataKuliah::where('prodi_id', $prodi->id)->where('kode', $penggantiKode)->first();

            if ($pengganti === null) {
                continue;
            }

            $mk = MataKuliah::create([
                'prodi_id' => $prodi->id,
                'kode' => $kode,
                'nama' => $nama,
                'sks_teori' => $sks,
                'sks_praktik' => 0,
                'sks_praktik_lapangan' => 0,
                'sks' => $sks,

                // Still on file, no longer offered.
                'is_active' => false,
            ]);

            $lama->mataKuliah()->attach($mk->id, ['semester' => $semester++, 'jenis' => 'wajib']);

            $this->padanan->tetapkan(
                $mk,
                $pengganti,
                null,
                'Pergantian kurikulum '.$tahun.' → '.date('Y', strtotime('-2 years')),
            );
        }
    }

    /**
     * Two tracks, their courses, offerings for them, and students in each.
     *
     * Students are split rather than all assigned: the "belum memilih
     * konsentrasi" path is the one an operator actually meets, and a demo where
     * everybody has a track never shows it.
     */
    private function seedKonsentrasi(Prodi $prodi): void
    {
        $kurikulum = Kurikulum::where('prodi_id', $prodi->id)->where('is_active', true)->first();
        $term = TahunAkademik::aktif();

        if ($kurikulum === null) {
            return;
        }

        $dibuat = [];

        foreach (self::PEMINATAN as $kode => [$nama, $mataKuliah]) {
            $konsentrasi = Konsentrasi::create([
                'kurikulum_id' => $kurikulum->id,
                'kode' => $kode,
                'nama' => $nama,
                'sks_wajib' => 6,
            ]);

            foreach ($mataKuliah as $mkKode => [$mkNama, $sks]) {
                $mk = MataKuliah::create([
                    'prodi_id' => $prodi->id,
                    'kode' => $mkKode,
                    'nama' => $mkNama,
                    'sks_teori' => $sks,
                    'sks_praktik' => 0,
                    'sks_praktik_lapangan' => 0,
                    'sks' => $sks,
                    'is_active' => true,
                ]);

                $kurikulum->mataKuliah()->attach($mk->id, [
                    'semester' => 5,
                    'jenis' => 'wajib',
                    'konsentrasi_id' => $konsentrasi->id,
                ]);

                if ($term !== null) {
                    $this->bukaKelas($mk, $prodi, $term);
                }
            }

            $dibuat[] = $konsentrasi;
        }

        if ($dibuat === []) {
            return;
        }

        /*
         * Two-thirds of the cohort has chosen; the rest have not yet.
         *
         * Both states are real on a live campus mid-year, and the KRS screen
         * says something different for each — "bukan untuk konsentrasi X"
         * versus "tetapkan konsentrasi Anda lebih dulu".
         */
        $mahasiswa = Mahasiswa::where('prodi_id', $prodi->id)->orderBy('id')->get();

        foreach ($mahasiswa as $index => $satu) {
            if ($index % 3 === 2) {
                continue;
            }

            $satu->forceFill([
                'konsentrasi_id' => $dibuat[$index % count($dibuat)]->id,
            ])->save();
        }
    }

    /**
     * One programme that issues study plans instead of taking choices.
     *
     * Packages are built for the semesters the demo term actually offers —
     * a package pointing at courses with no open class applies to nothing and
     * would read as a broken feature rather than an empty one.
     */
    private function seedPaket(Prodi $prodi): void
    {
        $kurikulum = Kurikulum::with('mataKuliah')->where('prodi_id', $prodi->id)->where('is_active', true)->first();

        if ($kurikulum === null) {
            return;
        }

        $prodi->forceFill(['mode_krs' => 'paket'])->save();

        foreach ([1, 3] as $semester) {
            $mataKuliah = $kurikulum->mataKuliah
                ->filter(fn (MataKuliah $mk): bool => (int) $mk->pivot->semester === $semester);

            if ($mataKuliah->isEmpty()) {
                continue;
            }

            $paket = PaketKuliah::create([
                'kurikulum_id' => $kurikulum->id,

                // Shared package: this programme has no tracks, and a null here
                // is what PaketKuliah::untuk falls back to.
                'konsentrasi_id' => null,

                'semester_ke' => $semester,
                'nama' => 'Paket Semester '.$semester.' — '.$kurikulum->kode,
            ]);

            $paket->mataKuliah()->attach($mataKuliah->pluck('id')->all());
        }
    }

    private function bukaKelas(MataKuliah $mk, Prodi $prodi, TahunAkademik $term): void
    {
        $dosen = Dosen::where('prodi_id', $prodi->id)->first();
        $ruang = Ruang::where('jenis', 'kelas')->first();

        $kelas = KelasKuliah::create([
            'tahun_akademik_id' => $term->id,
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $prodi->id,
            'kode' => 'A',
            'kuota' => 30,
            'terisi' => 0,
            'sks' => $mk->sks,
            'mode' => 'tatap_muka',
            'is_case_method' => true,
            'is_team_based_project' => false,
            'status_nilai' => 'belum',
        ]);

        if ($dosen !== null) {
            $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu', 'porsi_sks' => $kelas->sks]);
        }

        if ($ruang !== null) {
            // Friday afternoon, which the main timetable seeder leaves free —
            // these must not collide with the classes a student already holds,
            // or the demo shows a clash instead of a track restriction.
            JadwalKuliah::create([
                'kelas_kuliah_id' => $kelas->id,
                'ruang_id' => $ruang->id,
                'hari' => 5,
                'jam_mulai' => '16:00:00',
                'jam_selesai' => '17:40:00',
            ]);
        }
    }
}
