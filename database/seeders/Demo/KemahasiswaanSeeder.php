<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\StudentActivityType;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 50 students spread across two programmes and three intakes, plus the MBKM
 * activity records that feed IKU 2.
 *
 * One student is deliberately created without a NIK: the Feeder pre-flight
 * validator needs a failing row to demonstrate, and a demo campus where
 * everything is already perfect teaches an operator nothing.
 */
class KemahasiswaanSeeder extends Seeder
{
    public const TOTAL_MAHASISWA = 50;

    public function run(): void
    {
        $mahasiswa = $this->seedMahasiswa();

        $this->seedAktivitasMbkm($mahasiswa);
    }

    /** @return Collection<int, Mahasiswa> */
    private function seedMahasiswa(): Collection
    {
        $prodiList = Prodi::with('kurikulumAktif')->get();
        $waliList = Dosen::whereNotNull('nidn')->get();

        $tahunSekarang = (int) date('Y');
        $created = collect();

        for ($i = 1; $i <= self::TOTAL_MAHASISWA; $i++) {
            $prodi = $prodiList[$i % $prodiList->count()];

            // Three intakes so the demo has 1st, 3rd and 5th semester students.
            $angkatan = $tahunSekarang - [0, 1, 2][$i % 3];

            // Tanpa peran ini seluruh policy mahasiswa menolak — termasuk
            // pengisian KRS, yang izinnya (krs.submit) melekat pada peran.
            $created->push(Mahasiswa::factory()->create([
                'nim' => $this->nim($prodi->kode, $angkatan, $i),
                'email' => sprintf('mahasiswa%d@demo.test', $i),
                'password' => 'password',
                'prodi_id' => $prodi->id,
                'kurikulum_id' => $prodi->kurikulumAktif?->id,
                'dosen_wali_id' => $waliList[$i % $waliList->count()]->id,
                'angkatan' => $angkatan,
                'term_masuk' => $angkatan.'1',
                'status' => $this->statusUntuk($i),
                'nik' => $i === self::TOTAL_MAHASISWA ? null : (string) fake()->unique()->numerify('################'),
            ])->assignRole('mahasiswa'));
        }

        return $created;
    }

    /**
     * A campus is never 100% active: the demo carries a couple of students on
     * leave and one who dropped out so the status filters have something to
     * show and the Feeder status mapping gets exercised.
     */
    private function statusUntuk(int $index): StudentStatus
    {
        return match (true) {
            $index % 25 === 0 => StudentStatus::Cuti,
            $index % 37 === 0 => StudentStatus::DropOut,
            default => StudentStatus::Aktif,
        };
    }

    /** Pattern from config('academic.nim'): 2 digit year + prodi code + sequence. */
    private function nim(string $kodeProdi, int $angkatan, int $urut): string
    {
        return sprintf(
            '%02d%s%04d',
            $angkatan % 100,
            $kodeProdi,
            $urut,
        );
    }

    /** @param Collection<int, Mahasiswa> $mahasiswa */
    private function seedAktivitasMbkm(Collection $mahasiswa): void
    {
        $term = TahunAkademik::aktif();
        $verifikator = Staff::where('email', 'baak@demo.test')->firstOrFail();
        $pembimbing = Dosen::whereNotNull('nidn')->get();

        $mitra = [
            'PT Nusantara Digital Teknologi', 'PT Bank Rakyat Nusantara',
            'Kementerian Pendidikan Tinggi', 'Startup Ruang Belajar',
            'Pemerintah Kabupaten Sukamaju',
        ];

        $topik = [
            'Pengembangan Dasbor Analitik Penjualan',
            'Otomasi Pelaporan Keuangan Berbasis Web',
            'Modernisasi Layanan Pelanggan dengan Chatbot',
            'Pendampingan Literasi Digital Sekolah Dasar',
            'Digitalisasi Arsip Pemerintah Desa',
            'Optimasi Basis Data Sistem Inventori',
            'Perancangan Ulang Antarmuka Aplikasi Mobile',
            'Penerapan Keamanan Data pada Layanan Publik',
        ];

        // Senior students only — MBKM is taken from semester 5 onward.
        //
        // values() is load-bearing: a filtered Collection keeps its original
        // keys, so the loop index below would skip numbers and quietly leave
        // most records unverified — exactly the evidence IKU 2 is judged on.
        $peserta = $mahasiswa
            ->where('angkatan', (int) date('Y') - 2)
            ->where('status', StudentStatus::Aktif)
            ->values()
            ->take(8);

        foreach ($peserta as $index => $student) {
            $jenis = [
                StudentActivityType::Magang,
                StudentActivityType::StudiIndependen,
                StudentActivityType::AsistensiMengajar,
                StudentActivityType::MembangunDesa,
            ][$index % 4];

            AktivitasMahasiswa::create([
                'mahasiswa_id' => $student->id,
                'tahun_akademik_id' => $term->id,
                'dosen_pembimbing_id' => $pembimbing[$index % $pembimbing->count()]->id,
                'jenis' => $jenis,
                'judul' => $jenis->label().' — '.$topik[$index % count($topik)],
                'mitra_nama' => $mitra[$index % count($mitra)],
                'mitra_jenis' => $index % 3 === 0 ? 'pemerintah' : 'industri',
                'lokasi' => fake()->city(),
                'tanggal_mulai' => $term->tanggal_mulai,
                'tanggal_selesai' => $term->tanggal_selesai,

                // IKU 2 counts students clearing 20 recognised credits, so the
                // demo spans both sides of that threshold.
                'sks_konversi' => $index < 5 ? 20 : fake()->numberBetween(6, 14),

                'is_verified' => $index < 6,
                'verified_by_staff_id' => $index < 6 ? $verifikator->id : null,
                'verified_at' => $index < 6 ? now() : null,
            ]);
        }
    }
}
