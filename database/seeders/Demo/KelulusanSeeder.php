<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use Illuminate\Database\Seeder;

/**
 * A cohort of graduates, so IKU 1 has a population to trace.
 *
 * Written directly rather than through YudisiumService: the service refuses
 * anyone short of the full credit requirement, and the demo campus only carries
 * three terms of coursework. Fabricating a plausible graduating class is the
 * point of demo data — but the requirement checks stay real for anyone the
 * screen proposes.
 */
class KelulusanSeeder extends Seeder
{
    public function run(): void
    {
        $term = TahunAkademik::aktif();

        $periode = WisudaPeriode::create([
            'nama' => 'Wisuda Periode I '.now()->year,
            'tanggal' => now()->subMonths(2),
            'lokasi' => 'Auditorium Kampus Pusat',
            'kuota' => 300,
            'is_pendaftaran_dibuka' => false,
        ]);

        // Angkatan tertua yang statusnya masih aktif. Diurutkan sama persis
        // dengan TugasAkhirSeeder supaya keduanya sepakat siapa yang lulus.
        $calon = Mahasiswa::query()
            ->with(['prodi', 'tugasAkhir'])
            ->where('status', StudentStatus::Aktif->value)
            ->orderBy('angkatan')
            ->orderBy('id')
            ->limit(9)
            ->get();

        $pekerjaan = ['bekerja', 'bekerja', 'wirausaha', 'studi_lanjut', 'belum'];
        $instansi = [
            'PT Nusantara Digital Teknologi',
            'PT Bank Rakyat Nusantara',
            'Kementerian Pendidikan Tinggi',
            'Wirausaha mandiri',
            null,
        ];

        foreach ($calon as $index => $mahasiswa) {
            $ipk = round(2.85 + ($index % 5) * 0.22, 2);
            $lulus = now()->subMonths(2)->subDays($index);

            $yudisium = Yudisium::create([
                'mahasiswa_id' => $mahasiswa->id,
                'tahun_akademik_id' => $term->id,
                'nomor_sk' => sprintf('SK/YUD/%d/%03d', now()->year, $index + 1),
                'tanggal_yudisium' => $lulus,
                'tanggal_lulus' => $lulus,
                'total_sks' => $mahasiswa->prodi->sks_lulus,
                'ipk' => $ipk,
                'predikat' => Yudisium::predikatUntuk($ipk),
                // Dibaca dari tugas akhir yang sudah diuji, persis seperti
                // YudisiumService melakukannya. Daftar judul di bawah hanya
                // cadangan bila TugasAkhirSeeder tidak berjalan.
                'judul_tugas_akhir' => $mahasiswa->tugasAkhir
                    ->firstWhere('status', TugasAkhirStatus::Selesai)
                    ?->judul ?? $this->judul($index),
                'status' => 'ditetapkan',
                'ditetapkan_at' => $lulus,
            ]);

            $mahasiswa->update(['status' => StudentStatus::Lulus]);

            // Sebagian lulusan sudah tertelusuri, sebagian belum — tracer study
            // yang menemukan seluruhnya sudah terisi tidak menguji apa pun.
            Alumni::create([
                'mahasiswa_id' => $mahasiswa->id,
                'tahun_lulus' => $lulus->year,
                'email_pribadi' => $mahasiswa->email,
                'telepon' => $mahasiswa->telepon,
                'status_pekerjaan' => $index < 6 ? $pekerjaan[$index % 5] : 'belum',
                'instansi' => $index < 6 ? $instansi[$index % 5] : null,
                'mulai_bekerja' => $index < 6 ? $lulus->copy()->addMonth() : null,
            ]);

            WisudaPeserta::create([
                'wisuda_periode_id' => $periode->id,
                'yudisium_id' => $yudisium->id,
                'nomor_ijazah' => sprintf('%s/IJZ/%d', str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), now()->year),
                'nomor_urut' => $index + 1,
            ]);
        }
    }

    private function judul(int $index): string
    {
        return [
            'Rancang Bangun Sistem Informasi Akademik Berbasis Web',
            'Analisis Sentimen Ulasan Layanan Kampus dengan Naive Bayes',
            'Implementasi Keamanan API pada Layanan Pemerintah Daerah',
            'Optimasi Basis Data Transaksi Koperasi Simpan Pinjam',
            'Perancangan Data Warehouse Penerimaan Mahasiswa Baru',
            'Sistem Rekomendasi Mata Kuliah Pilihan Berbasis Riwayat Studi',
            'Evaluasi Usability Portal Akademik dengan Metode SUS',
            'Deteksi Anomali Trafik Jaringan Kampus Menggunakan Machine Learning',
            'Digitalisasi Arsip Kepegawaian pada Instansi Pemerintah Desa',
        ][$index % 9];
    }
}
