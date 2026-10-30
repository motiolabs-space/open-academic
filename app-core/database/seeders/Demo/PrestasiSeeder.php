<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\BentukKepesertaan;
use App\Enums\BidangLomba;
use App\Enums\JenisPoin;
use App\Enums\JenisRekognisi;
use App\Enums\KategoriSimkatmawa;
use App\Enums\ModaPelaksanaan;
use App\Enums\PeringkatPrestasi;
use App\Enums\StudentStatus;
use App\Enums\TingkatSimkatmawa;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\Seeder;

/**
 * A points catalogue and a handful of achievements, so the SIMKATMAWA report
 * (ADR-037 §1) has something to show on a fresh demo.
 *
 * Deliberately not all complete: four rows are ready to report, one is
 * missing the facts a competition needs and one is still waiting for
 * verification — the report is only worth demonstrating with the rows it
 * holds back. Dated within the last two months, so the default view (this
 * calendar year) shows them. Every link points at a `.test` host, which
 * resolves nowhere.
 */
class PrestasiSeeder extends Seeder
{
    public function run(): void
    {
        $term = TahunAkademik::aktif();
        $staf = Staff::query()->orderBy('id')->first();
        $pembimbing = Dosen::query()->whereNotNull('nidn')->orderBy('id')->first();

        $mahasiswa = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->orderByDesc('angkatan')
            ->orderBy('id')
            ->limit(6)
            ->get();

        if ($mahasiswa->count() < 6) {
            return;
        }

        $katalog = collect([
            ['LOMBA-NAS', 'Juara lomba tingkat nasional', 'nasional', 40, KategoriSimkatmawa::Kompetisi],
            ['LOMBA-INT', 'Juara lomba tingkat internasional', 'internasional', 60, KategoriSimkatmawa::Kompetisi],
            ['LOMBA-WIL', 'Juara lomba tingkat wilayah', 'lokal', 20, KategoriSimkatmawa::Kompetisi],
            ['KARYA', 'Karya atau rekognisi', 'nasional', 30, KategoriSimkatmawa::Rekognisi],
            ['SERTIF', 'Sertifikat kompetensi', 'nasional', 25, KategoriSimkatmawa::Sertifikasi],
            ['PANITIA', 'Panitia kegiatan kampus', 'internal', 5, null],
        ])->mapWithKeys(fn (array $k): array => [$k[0] => PoinKategori::create([
            'kode' => $k[0],
            'nama' => $k[1],
            'jenis' => JenisPoin::Prestasi,
            'tingkat' => $k[2],
            'poin' => $k[3],
            'simkatmawa_kategori' => $k[4],
            'wajib_bukti' => false,
        ])]);

        $catat = function (Mahasiswa $m, string $kode, array $atribut, bool $diverifikasi = true) use ($katalog, $term, $staf): void {
            $k = $katalog[$kode];

            PoinMahasiswa::create([
                'mahasiswa_id' => $m->id,
                'poin_kategori_id' => $k->id,
                'tahun_akademik_id' => $term?->id,
                'poin' => $k->poin,
                'jenis' => $k->jenis,
                'dicatat_by_staff_id' => $staf?->id,
                'is_verified' => $diverifikasi,
                'verified_by_staff_id' => $diverifikasi ? $staf?->id : null,
                'verified_at' => $diverifikasi ? now() : null,
                ...$atribut,
            ]);
        };

        $catat($mahasiswa[0], 'LOMBA-NAS', [
            'tanggal' => now()->subDays(40)->toDateString(),
            'tanggal_selesai' => now()->subDays(38)->toDateString(),
            'judul' => 'Kompetisi Pengembangan Perangkat Lunak Mahasiswa',
            'peringkat' => PeringkatPrestasi::Juara1,
            'bentuk_kepesertaan' => BentukKepesertaan::Kelompok,
            'moda_pelaksanaan' => ModaPelaksanaan::Luring,
            'bidang_lomba' => BidangLomba::RisetStem,
            'penyelenggara' => 'Asosiasi Perguruan Tinggi Informatika (demo)',
            'jumlah_pt_peserta' => 24,
            'tautan_sertifikat' => 'https://berkas.demo.test/prestasi/sertifikat-001.pdf',
            'tautan_bukti' => 'https://berita.demo.test/2026/juara-kompetisi-perangkat-lunak',
            'tautan_surat_tugas' => 'https://berkas.demo.test/prestasi/surat-tugas-001.pdf',
            'dosen_pembimbing_id' => $pembimbing?->id,
        ]);

        $catat($mahasiswa[1], 'LOMBA-INT', [
            'tanggal' => now()->subDays(25)->toDateString(),
            'judul' => 'Kompetisi Analitik Data Asia Tenggara',
            'peringkat' => PeringkatPrestasi::Juara3,
            'bentuk_kepesertaan' => BentukKepesertaan::Individu,
            'moda_pelaksanaan' => ModaPelaksanaan::Daring,
            'bidang_lomba' => BidangLomba::RisetStem,
            'penyelenggara' => 'Konsorsium Universitas Regional (demo)',
            'jumlah_negara_peserta' => 8,
            'tautan_sertifikat' => 'https://berkas.demo.test/prestasi/sertifikat-002.pdf',
            'tautan_bukti' => 'https://lomba.demo.test/hasil-akhir',
            'dosen_pembimbing_id' => $pembimbing?->id,
        ]);

        $catat($mahasiswa[2], 'KARYA', [
            'tanggal' => now()->subDays(55)->toDateString(),
            'judul' => 'Aplikasi pencatatan stok untuk koperasi desa',
            'jenis_rekognisi' => JenisRekognisi::Karya,
            'penyelenggara' => 'Dinas Koperasi Kabupaten (demo)',
            'tautan_sertifikat' => 'https://berkas.demo.test/prestasi/piagam-003.pdf',
            'tautan_bukti' => 'https://desa.demo.test/koperasi/aplikasi-stok',
        ]);

        $catat($mahasiswa[3], 'SERTIF', [
            'tanggal' => now()->subDays(30)->toDateString(),
            'tanggal_selesai' => now()->addYears(3)->toDateString(),
            'judul' => 'Sertifikasi Kompetensi Junior Web Developer',
            'tingkat' => TingkatSimkatmawa::Nasional,
            'penyelenggara' => 'Lembaga Sertifikasi Profesi Informatika (demo)',
            'nomor_sertifikat' => 'DEMO-LSP-0001',
            'tautan_sertifikat' => 'https://berkas.demo.test/prestasi/sertifikat-004.pdf',
        ]);

        // Recorded before the SIMKATMAWA fields existed: the report shows what
        // it still lacks instead of dropping it silently.
        $catat($mahasiswa[4], 'LOMBA-WIL', [
            'tanggal' => now()->subDays(50)->toDateString(),
            'judul' => 'Lomba Desain Poster Hari Pendidikan',
        ]);

        // Still a claim: not in the report until verified.
        $catat($mahasiswa[5], 'LOMBA-NAS', [
            'tanggal' => now()->subDays(10)->toDateString(),
            'judul' => 'Kompetisi Keamanan Siber Mahasiswa',
            'penyelenggara' => 'Komunitas Keamanan Siber (demo)',
        ], diverifikasi: false);
    }
}
