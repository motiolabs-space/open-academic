<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\BentukKeterlibatanKebijakan;
use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\IndeksPublikasi;
use App\Enums\JenisBuktiKebijakan;
use App\Enums\JenisLuaran;
use App\Enums\KuartilPublikasi;
use App\Enums\LecturerAssignmentType;
use App\Enums\PeranKegiatan;
use App\Enums\TingkatKebijakan;
use App\Enums\TingkatKegiatan;
use App\Enums\UnsurBkd;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Staff and lecturers, including the two populations the IKU data provider
 * cares about: practitioners who teach (IKU 4) and lecturers with external
 * assignments (IKU 3).
 *
 * Demo credentials are documented in the README and are safe only because
 * DemoCampusSeeder refuses to run in production.
 */
class SdmSeeder extends Seeder
{
    // A one-page PDF, so the demo evidence link opens something.
    private const PDF_CONTOH = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n%%EOF\n";

    public function run(): void
    {
        $this->seedStaff();

        $dosen = $this->seedDosen();

        $this->assignPimpinan($dosen);
        $this->seedPenugasan($dosen);
    }

    private function seedStaff(): void
    {
        Staff::create([
            'nip' => '198501012010011001',
            'nama' => 'Admin Sistem',
            'email' => 'admin@demo.test',
            'password' => 'password',
            'jabatan' => 'Administrator Sistem',
            'unit' => 'TIK',
        ])->assignRole('super-admin');

        Staff::create([
            'nip' => '198702152011012002',
            'nama' => 'Sri Wahyuni',
            'email' => 'baak@demo.test',
            'password' => 'password',
            'jabatan' => 'Kepala BAAK',
            'unit' => 'BAAK',
        ])->assignRole('baak');

        Staff::create([
            'nip' => '199003202015011003',
            'nama' => 'Bayu Nugroho',
            'email' => 'keuangan@demo.test',
            'password' => 'password',
            'jabatan' => 'Staf Keuangan',
            'unit' => 'Keuangan',
        ])->assignRole('keuangan');

        Staff::create([
            'nip' => '199205102018012004',
            'nama' => 'Rina Kartika',
            'email' => 'pddikti@demo.test',
            'password' => 'password',
            'jabatan' => 'Operator PDDIKTI',
            'unit' => 'BAAK',
        ])->assignRole('operator-pddikti');
    }

    /** @return Collection<int, Dosen> */
    private function seedDosen(): Collection
    {
        $informatika = Prodi::where('kode', '55201')->firstOrFail();
        $sistemInformasi = Prodi::where('kode', '57201')->firstOrFail();

        $daftar = [
            ['Dr.', 'Ahmad Fauzi', 'S.Kom., M.Kom.', $informatika, 'Lektor Kepala', Gender::LakiLaki],
            [null, 'Dewi Lestari', 'S.T., M.T.', $informatika, 'Lektor', Gender::Perempuan],
            ['Dr.', 'Bambang Setiawan', 'S.Si., M.Sc.', $informatika, 'Lektor', Gender::LakiLaki],
            [null, 'Nur Aisyah', 'S.Kom., M.Kom.', $sistemInformasi, 'Asisten Ahli', Gender::Perempuan],
            [null, 'Rizky Pratama', 'S.Kom., M.T.', $sistemInformasi, 'Lektor', Gender::LakiLaki],
            [null, 'Siti Rahmawati', 'S.E., M.M.', $sistemInformasi, 'Asisten Ahli', Gender::Perempuan],
        ];

        $dosen = collect();

        foreach ($daftar as $index => [$gelarDepan, $nama, $gelarBelakang, $prodi, $jabatan, $gender]) {
            $dosen->push(Dosen::create([
                'nidn' => sprintf('0%010d', 1234567890 + $index),
                'nama' => $nama,
                'gelar_depan' => $gelarDepan,
                'gelar_belakang' => $gelarBelakang,
                'email' => 'dosen'.($index + 1).'@demo.test',
                'password' => 'password',
                'nik' => (string) fake()->unique()->numerify('################'),
                'tempat_lahir' => fake()->city(),
                'tanggal_lahir' => fake()->dateTimeBetween('-55 years', '-32 years'),
                'jenis_kelamin' => $gender,
                'telepon' => fake()->phoneNumber(),
                'prodi_id' => $prodi->id,
                'jabatan_fungsional' => $jabatan,
                'status_kepegawaian' => 'tetap',
                'pendidikan_tertinggi' => $gelarDepan === 'Dr.' ? EducationLevel::S3 : EducationLevel::S2,
                // Di kampus kecil setiap dosen tetap merangkap dosen wali, dan
                // KemahasiswaanSeeder memang menyebar perwalian ke mereka semua.
                // Memberi peran hanya ke sebagian akan membuat dosen yang
                // benar-benar mewalikan mahasiswa ditolak saat menyetujui KRS.
            ])->assignRole('dosen-wali'));
        }

        // A practitioner from industry — no NIDN, which is exactly the row the
        // Feeder pre-flight validator is meant to flag before a sync run.
        $dosen->push(Dosen::factory()->praktisi()->create([
            'nama' => 'Yusuf Maulana',
            'gelar_belakang' => 'S.Kom.',
            'email' => 'praktisi@demo.test',
            'password' => 'password',
            'prodi_id' => $informatika->id,
            'praktisi_instansi' => 'PT Nusantara Digital Teknologi',
        ])->assignRole('dosen'));

        return $dosen;
    }

    /** @param Collection<int, Dosen> $dosen */
    private function assignPimpinan(Collection $dosen): void
    {
        Fakultas::where('kode', 'FTI')->update(['dekan_dosen_id' => $dosen[0]->id]);
        Prodi::where('kode', '55201')->update(['kaprodi_dosen_id' => $dosen[2]->id]);
        Prodi::where('kode', '57201')->update(['kaprodi_dosen_id' => $dosen[4]->id]);
    }

    /** @param Collection<int, Dosen> $dosen */
    private function seedPenugasan(Collection $dosen): void
    {
        $term = TahunAkademik::aktif();
        $staff = Staff::where('email', 'baak@demo.test')->firstOrFail();

        /*
         * The same rows serve three readers, which is why they now carry an
         * element, a role, a reach, and an output as well as an IKU type:
         * IKU 3/4 count them, BKD weighs them, and the SISTER portfolio reports
         * what they produced.
         */
        $penugasan = [
            [$dosen[0], LecturerAssignmentType::Penelitian, UnsurBkd::Penelitian,
                'Riset Kolaboratif Deteksi Anomali Jaringan', 'Badan Riset dan Inovasi Nasional', 'pemerintah',
                PeranKegiatan::Ketua, TingkatKegiatan::Nasional,
                JenisLuaran::Jurnal, '10.1234/jnteti.2026.01'],

            [$dosen[1], LecturerAssignmentType::TugasIndustri, UnsurBkd::Penunjang,
                'Praktisi Tamu Rekayasa Data', 'PT Nusantara Digital Teknologi', 'industri',
                PeranKegiatan::Anggota, TingkatKegiatan::Lokal, null, null],

            [$dosen[2], LecturerAssignmentType::Pengabdian, UnsurBkd::Pengabdian,
                'Pendampingan Digitalisasi UMKM Desa Sukamaju', 'Pemerintah Desa Sukamaju', 'pemerintah',
                PeranKegiatan::Ketua, TingkatKegiatan::Lokal,
                JenisLuaran::Laporan, 'LP-2026-014'],

            [$dosen[3], LecturerAssignmentType::Sertifikasi, UnsurBkd::Penunjang,
                'Sertifikasi Kompetensi Data Analyst BNSP', 'BNSP', 'pemerintah',
                PeranKegiatan::Anggota, TingkatKegiatan::Nasional, null, null],

            [$dosen[6], LecturerAssignmentType::PraktisiMengajar, UnsurBkd::Penunjang,
                'Praktisi Mengajar — Rekayasa Perangkat Lunak', 'PT Nusantara Digital Teknologi', 'industri',
                PeranKegiatan::Anggota, TingkatKegiatan::Lokal, null, null],
        ];

        foreach ($penugasan as [
            $pengampu, $jenis, $unsur, $judul, $mitra, $mitraJenis,
            $peran, $tingkat, $luaran, $identitas,
        ]) {
            PenugasanDosen::create([
                'dosen_id' => $pengampu->id,
                'tahun_akademik_id' => $term->id,
                'jenis' => $jenis,
                'unsur' => $unsur,
                'judul' => $judul,
                'peran' => $peran,
                'tingkat' => $tingkat,
                'mitra_nama' => $mitra,
                'mitra_jenis' => $mitraJenis,
                'lokasi' => fake()->city(),
                'tanggal_mulai' => $term->tanggal_mulai,
                'tanggal_selesai' => $term->tanggal_selesai,
                'sks_ekuivalen' => fake()->randomElement([2.0, 3.0, 4.0]),
                'luaran_jenis' => $luaran,
                'luaran_identitas' => $identitas,
                'luaran_tahun' => $luaran !== null ? (int) date('Y') : null,
                'is_verified' => true,
                'verified_by_staff_id' => $staff->id,
                'verified_at' => now(),
            ]);
        }

        /*
         * Publication facts (IKU 6, ADR-041 §2): the national journal row
         * above gains its SINTA rank and DOI, and one Scopus Q2 article with a
         * foreign co-author is added.
         */
        PenugasanDosen::query()
            ->where('dosen_id', $dosen[0]->id)
            ->where('luaran_jenis', JenisLuaran::Jurnal->value)
            ->update(['luaran_indeks' => IndeksPublikasi::Sinta2->value, 'luaran_doi' => '10.1234/jnteti.2026.01']);

        PenugasanDosen::create([
            'dosen_id' => $dosen[4]->id,
            'tahun_akademik_id' => $term->id,
            'jenis' => LecturerAssignmentType::Penelitian,
            'unsur' => UnsurBkd::Penelitian,
            'judul' => 'Graph-Based Intrusion Detection for Campus Networks',
            'peran' => PeranKegiatan::Anggota,
            'tingkat' => TingkatKegiatan::Internasional,
            'mitra_nama' => 'Universiti Teknologi Demo',
            'mitra_jenis' => 'kampus',
            'tanggal_mulai' => $term->tanggal_mulai,
            'luaran_jenis' => JenisLuaran::Jurnal,
            'luaran_identitas' => 'ISSN 0000-0000',
            'luaran_tahun' => (int) date('Y'),
            'luaran_indeks' => IndeksPublikasi::Scopus,
            'luaran_kuartil' => KuartilPublikasi::Q2,
            'luaran_doi' => '10.5555/demo.2026.042',
            'luaran_penerbit' => 'Penerbit Demo Internasional',
            'luaran_kolaborasi_internasional' => true,
            'is_verified' => true,
            'verified_by_staff_id' => $staff->id,
            'verified_at' => now(),
        ]);

        /*
         * Policy drafting (IKU 8, ADR-041 §1): one verified with its decree on
         * file, one still waiting on the verification screen — the two states
         * a demo of /admin/data-iku needs.
         */
        // The portfolio form refuses this type without its document, so the
        // demo rows carry one too.
        $sk = 'portofolio/'.$dosen[0]->uuid.'/sk-tim-penyusun-demo.pdf';
        $undangan = 'portofolio/'.$dosen[2]->uuid.'/undangan-narasumber-demo.pdf';
        if (!app()->runningUnitTests()) {
            Storage::disk((string) config('berkas.disk'))->put($sk, self::PDF_CONTOH);
            Storage::disk((string) config('berkas.disk'))->put($undangan, self::PDF_CONTOH);
        }

        foreach ([
            [$dosen[0], 'Tim Penyusun Peraturan Daerah tentang Transformasi Digital', 'Pemerintah Kota Demo',
                BentukKeterlibatanKebijakan::TimPenyusun, TingkatKebijakan::Daerah,
                JenisBuktiKebijakan::SkPenugasan, 'SK/DEMO/014/2026', $sk, true],
            [$dosen[2], 'Masukan Tertulis Rancangan Standar Keamanan Data Sektor Keuangan', 'Asosiasi Industri Demo',
                BentukKeterlibatanKebijakan::Narasumber, TingkatKebijakan::Industri,
                JenisBuktiKebijakan::UndanganResmi, 'UND/DEMO/031/2026', $undangan, false],
        ] as [$pengampu, $judul, $lembaga, $bentuk, $tingkat, $jenisBukti, $nomor, $path, $terverifikasi]) {
            PenugasanDosen::create([
                'dosen_id' => $pengampu->id,
                'tahun_akademik_id' => $term->id,
                'jenis' => LecturerAssignmentType::PenyusunanKebijakan,
                'unsur' => UnsurBkd::Pengabdian,
                'judul' => $judul,
                'mitra_nama' => $lembaga,
                'mitra_jenis' => 'pemerintah',
                'tanggal_mulai' => $term->tanggal_mulai,
                'kebijakan_bentuk' => $bentuk,
                'kebijakan_tingkat' => $tingkat,
                'kebijakan_bukti_jenis' => $jenisBukti,
                'kebijakan_nomor_bukti' => $nomor,
                'dokumen_path' => $path,
                'is_verified' => $terverifikasi,
                'verified_by_staff_id' => $terverifikasi ? $staff->id : null,
                'verified_at' => $terverifikasi ? now() : null,
            ]);
        }
    }
}
