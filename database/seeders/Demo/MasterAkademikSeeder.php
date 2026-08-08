<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\EducationLevel;
use App\Enums\SemesterType;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Gedung;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use Illuminate\Database\Seeder;

/**
 * Terms, faculty, programmes, curriculum, courses and rooms for the demo
 * campus. Course lists are real Indonesian informatics curricula rather than
 * faker words, so the KRS and transcript screens read plausibly.
 */
class MasterAkademikSeeder extends Seeder
{
    /**
     * kode => [nama, sks_teori, sks_praktik, semester, jenis, prasyarat kode]
     *
     * @var array<string, array{0: string, 1: int, 2: int, 3: int, 4: string, 5: array<int, string>}>
     */
    private const KURIKULUM_INFORMATIKA = [
        'IF1101' => ['Pendidikan Agama', 2, 0, 1, 'wajib_universitas', []],
        'IF1102' => ['Pancasila dan Kewarganegaraan', 2, 0, 1, 'wajib_universitas', []],
        'IF1103' => ['Bahasa Indonesia', 2, 0, 1, 'wajib_universitas', []],
        'IF1104' => ['Kalkulus I', 3, 0, 1, 'wajib', []],
        'IF1105' => ['Algoritma dan Pemrograman', 3, 1, 1, 'wajib', []],
        'IF1106' => ['Pengantar Teknologi Informasi', 2, 0, 1, 'wajib', []],

        'IF1201' => ['Bahasa Inggris Akademik', 2, 0, 2, 'wajib_universitas', []],
        'IF1202' => ['Kalkulus II', 3, 0, 2, 'wajib', ['IF1104']],
        'IF1203' => ['Struktur Data', 3, 1, 2, 'wajib', ['IF1105']],
        'IF1204' => ['Matematika Diskrit', 3, 0, 2, 'wajib', []],
        'IF1205' => ['Organisasi dan Arsitektur Komputer', 3, 0, 2, 'wajib', []],

        'IF2101' => ['Basis Data', 3, 1, 3, 'wajib', ['IF1203']],
        'IF2102' => ['Pemrograman Berorientasi Objek', 3, 1, 3, 'wajib', ['IF1105']],
        'IF2103' => ['Sistem Operasi', 3, 0, 3, 'wajib', ['IF1205']],
        'IF2104' => ['Statistika dan Probabilitas', 3, 0, 3, 'wajib', []],
        'IF2105' => ['Jaringan Komputer', 3, 0, 3, 'wajib', ['IF1205']],

        'IF2201' => ['Rekayasa Perangkat Lunak', 3, 0, 4, 'wajib', ['IF2102']],
        'IF2202' => ['Pemrograman Web', 2, 1, 4, 'wajib', ['IF2102', 'IF2101']],
        'IF2203' => ['Kecerdasan Buatan', 3, 0, 4, 'wajib', ['IF1204']],
        'IF2204' => ['Analisis dan Perancangan Sistem', 3, 0, 4, 'wajib', ['IF2101']],
        'IF2205' => ['Interaksi Manusia dan Komputer', 2, 0, 4, 'wajib', []],
        'IF2206' => ['Metodologi Penelitian', 2, 0, 4, 'wajib', []],
    ];

    /** @var array<string, array{0: string, 1: int, 2: int, 3: int, 4: string, 5: array<int, string>}> */
    private const KURIKULUM_SISTEM_INFORMASI = [
        'SI1101' => ['Pendidikan Agama', 2, 0, 1, 'wajib_universitas', []],
        'SI1102' => ['Pancasila dan Kewarganegaraan', 2, 0, 1, 'wajib_universitas', []],
        'SI1103' => ['Konsep Sistem Informasi', 3, 0, 1, 'wajib', []],
        'SI1104' => ['Matematika Bisnis', 3, 0, 1, 'wajib', []],
        'SI1105' => ['Dasar Pemrograman', 3, 1, 1, 'wajib', []],

        'SI1201' => ['Bahasa Inggris Akademik', 2, 0, 2, 'wajib_universitas', []],
        'SI1202' => ['Sistem Basis Data', 3, 1, 2, 'wajib', ['SI1105']],
        'SI1203' => ['Pengantar Manajemen', 3, 0, 2, 'wajib', []],
        'SI1204' => ['Akuntansi Dasar', 3, 0, 2, 'wajib', []],
        'SI1205' => ['Statistika Terapan', 3, 0, 2, 'wajib', ['SI1104']],

        'SI2101' => ['Analisis Proses Bisnis', 3, 0, 3, 'wajib', ['SI1203']],
        'SI2102' => ['Pemrograman Berbasis Web', 2, 1, 3, 'wajib', ['SI1105']],
        'SI2103' => ['Jaringan dan Komunikasi Data', 3, 0, 3, 'wajib', []],
        'SI2104' => ['Manajemen Proyek TI', 3, 0, 3, 'wajib', ['SI1203']],
        'SI2105' => ['Sistem Enterprise', 3, 0, 3, 'wajib', ['SI2101']],

        'SI2201' => ['Analisis dan Desain Sistem Informasi', 3, 0, 4, 'wajib', ['SI2101']],
        'SI2202' => ['Data Warehouse dan Business Intelligence', 3, 0, 4, 'wajib', ['SI1202']],
        'SI2203' => ['Keamanan Sistem Informasi', 3, 0, 4, 'wajib', ['SI2103']],
        'SI2204' => ['E-Business', 3, 0, 4, 'wajib', ['SI2105']],
        'SI2205' => ['Metodologi Penelitian', 2, 0, 4, 'wajib', []],
    ];

    public function run(): void
    {
        $this->seedTahunAkademik();

        $fakultas = Fakultas::create([
            'kode' => 'FTI',
            'nama' => 'Fakultas Teknologi Informasi',
            'singkatan' => 'FTI',
        ]);

        $informatika = Prodi::create([
            'fakultas_id' => $fakultas->id,
            'kode' => '55201',
            'kode_pddikti' => fake()->uuid(),
            'nama' => 'Informatika',
            'jenjang' => EducationLevel::S1,
            'gelar_pendek' => 'S.Kom.',
            'gelar_panjang' => 'Sarjana Komputer',
            'akreditasi' => 'Baik Sekali',
            'sks_lulus' => 144,
            'is_active' => true,
        ]);

        $sistemInformasi = Prodi::create([
            'fakultas_id' => $fakultas->id,
            'kode' => '57201',
            'kode_pddikti' => fake()->uuid(),
            'nama' => 'Sistem Informasi',
            'jenjang' => EducationLevel::S1,
            'gelar_pendek' => 'S.Kom.',
            'gelar_panjang' => 'Sarjana Komputer',
            'akreditasi' => 'Baik',
            'sks_lulus' => 144,
            'is_active' => true,
        ]);

        $this->seedKurikulum($informatika, self::KURIKULUM_INFORMATIKA);
        $this->seedKurikulum($sistemInformasi, self::KURIKULUM_SISTEM_INFORMASI);

        $this->seedRuang();
    }

    /**
     * Three terms: two closed ones that give students a grade history (and
     * therefore a real IPS-based credit ceiling), plus the active one.
     */
    private function seedTahunAkademik(): void
    {
        $tahun = (int) date('Y');

        TahunAkademik::factory()->term($tahun - 1, SemesterType::Ganjil)->terkunci()->create();
        TahunAkademik::factory()->term($tahun - 1, SemesterType::Genap)->terkunci()->create();

        // The active term is anchored to today rather than to the September
        // start of a real calendar, so the demo always opens mid-semester.
        TahunAkademik::factory()->term($tahun, SemesterType::Ganjil)->berjalan()->aktif()->create();
    }

    /** @param array<string, array{0: string, 1: int, 2: int, 3: int, 4: string, 5: array<int, string>}> $courses */
    private function seedKurikulum(Prodi $prodi, array $courses): void
    {
        $tahun = (int) date('Y') - 2;

        $kurikulum = Kurikulum::create([
            'prodi_id' => $prodi->id,
            'kode' => 'KUR'.$tahun,
            'nama' => 'Kurikulum Berbasis OBE '.$tahun,
            'tahun_mulai' => $tahun,
            'sks_wajib' => 120,
            'sks_pilihan' => 24,
            'sks_lulus' => $prodi->sks_lulus,
            'is_active' => true,
        ]);

        /** @var array<string, MataKuliah> $created */
        $created = [];

        foreach ($courses as $kode => [$nama, $sksTeori, $sksPraktik, $semester, $jenis, $prasyarat]) {
            $mk = MataKuliah::create([
                'prodi_id' => $prodi->id,
                'kode' => $kode,
                'nama' => $nama,
                'sks_teori' => $sksTeori,
                'sks_praktik' => $sksPraktik,
                'sks_praktik_lapangan' => 0,
                'sks' => $sksTeori + $sksPraktik,
                'is_active' => true,
            ]);

            $kurikulum->mataKuliah()->attach($mk->id, [
                'semester' => $semester,
                'jenis' => $jenis,
            ]);

            $created[$kode] = $mk;
        }

        // Prerequisites are attached in a second pass: a course may depend on
        // one that appears later in the source array.
        foreach ($courses as $kode => [, , , , , $prasyarat]) {
            foreach ($prasyarat as $prasyaratKode) {
                $created[$kode]->prasyarat()->attach($created[$prasyaratKode]->id, ['jenis' => 'prasyarat']);
            }
        }
    }

    private function seedRuang(): void
    {
        $gedung = Gedung::create([
            'kode' => 'GD-A',
            'nama' => 'Gedung Kuliah Bersama A',
            'alamat' => 'Kampus Pusat, Jl. Pendidikan No. 1',
        ]);

        $lab = Gedung::create([
            'kode' => 'GD-B',
            'nama' => 'Gedung Laboratorium Terpadu',
            'alamat' => 'Kampus Pusat, Jl. Pendidikan No. 1',
        ]);

        foreach (range(1, 8) as $i) {
            Ruang::create([
                'gedung_id' => $gedung->id,
                'kode' => sprintf('A-%02d', $i),
                'nama' => 'Ruang Kuliah A'.$i,
                'kapasitas' => 40,
                'jenis' => 'kelas',
            ]);
        }

        foreach (range(1, 4) as $i) {
            Ruang::create([
                'gedung_id' => $lab->id,
                'kode' => sprintf('LAB-%02d', $i),
                'nama' => 'Laboratorium Komputer '.$i,
                'kapasitas' => 30,
                'jenis' => 'laboratorium',
            ]);
        }
    }
}
