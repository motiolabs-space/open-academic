<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\LecturerAssignmentType;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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

        $penugasan = [
            [$dosen[0], LecturerAssignmentType::Penelitian, 'Riset Kolaboratif Deteksi Anomali Jaringan', 'Badan Riset dan Inovasi Nasional', 'pemerintah'],
            [$dosen[1], LecturerAssignmentType::TugasIndustri, 'Praktisi Tamu Rekayasa Data', 'PT Nusantara Digital Teknologi', 'industri'],
            [$dosen[2], LecturerAssignmentType::Pengabdian, 'Pendampingan Digitalisasi UMKM Desa Sukamaju', 'Pemerintah Desa Sukamaju', 'pemerintah'],
            [$dosen[3], LecturerAssignmentType::Sertifikasi, 'Sertifikasi Kompetensi Data Analyst BNSP', 'BNSP', 'pemerintah'],
            [$dosen[6], LecturerAssignmentType::PraktisiMengajar, 'Praktisi Mengajar — Rekayasa Perangkat Lunak', 'PT Nusantara Digital Teknologi', 'industri'],
        ];

        foreach ($penugasan as [$pengampu, $jenis, $judul, $mitra, $mitraJenis]) {
            PenugasanDosen::create([
                'dosen_id' => $pengampu->id,
                'tahun_akademik_id' => $term->id,
                'jenis' => $jenis,
                'judul' => $judul,
                'mitra_nama' => $mitra,
                'mitra_jenis' => $mitraJenis,
                'lokasi' => fake()->city(),
                'tanggal_mulai' => $term->tanggal_mulai,
                'tanggal_selesai' => $term->tanggal_selesai,
                'sks_ekuivalen' => fake()->randomElement([2.0, 3.0, 4.0]),
                'is_verified' => true,
                'verified_by_staff_id' => $staff->id,
                'verified_at' => now(),
            ]);
        }
    }
}
