<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\HasilUjian;
use App\Enums\JenisUjian;
use App\Enums\PeranPembimbing;
use App\Enums\PeranPenguji;
use App\Enums\StatusUjian;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\Bimbingan;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\Penguji;
use App\Models\TugasAkhir\TugasAkhir;
use App\Models\TugasAkhir\Ujian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Final projects, both finished and in flight.
 *
 * Runs before KelulusanSeeder on purpose: the graduating cohort needs completed
 * projects to graduate *from*. Without them the demo campus would contradict the
 * rule this module introduced — diplomas carrying titles that trace to nothing,
 * which is precisely the situation the tables were built to end.
 *
 * The in-flight half deliberately includes the states that go unnoticed on a
 * real campus: a title still awaiting a decision, and work approved months ago
 * with nobody assigned to supervise it. A demo where every project is healthy
 * shows nothing about what the screens are for.
 *
 * Written directly to the tables rather than through the services, like the
 * other demo seeders: the services enforce credit floors and consultation
 * minimums that a three-term demo campus cannot satisfy. The rules stay real for
 * anything a human does on the screens.
 */
class TugasAkhirSeeder extends Seeder
{
    /** @var array<int, string> */
    public const JUDUL_LULUS = [
        'Rancang Bangun Sistem Informasi Akademik Berbasis Web',
        'Analisis Sentimen Ulasan Layanan Kampus dengan Naive Bayes',
        'Implementasi Keamanan API pada Layanan Pemerintah Daerah',
        'Optimasi Basis Data Transaksi Koperasi Simpan Pinjam',
        'Perancangan Data Warehouse Penerimaan Mahasiswa Baru',
        'Sistem Rekomendasi Mata Kuliah Pilihan Berbasis Riwayat Studi',
        'Evaluasi Usability Portal Akademik dengan Metode SUS',
        'Deteksi Anomali Trafik Jaringan Kampus Menggunakan Machine Learning',
        'Digitalisasi Arsip Kepegawaian pada Instansi Pemerintah Desa',
    ];

    public function run(): void
    {
        $term = TahunAkademik::aktif();
        $dosen = Dosen::query()->where('is_active', true)->orderBy('id')->get();

        if ($term === null || $dosen->count() < 3) {
            return;
        }

        $ruang = Ruang::orderBy('id')->first();

        // The same nine KelulusanSeeder will graduate, selected identically so
        // the two seeders agree on who is who.
        $lulusan = $this->calonLulusan();

        foreach ($lulusan as $index => $mahasiswa) {
            $this->selesai($mahasiswa, $term, $dosen, $ruang, $index);
        }

        $berjalan = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereNotIn('id', $lulusan->pluck('id'))
            ->whereDoesntHave('tugasAkhir')
            ->orderByDesc('angkatan')
            ->orderBy('id')
            ->limit(6)
            ->get();

        foreach ($berjalan as $index => $mahasiswa) {
            match ($index % 3) {
                0 => $this->menungguKeputusan($mahasiswa, $term),
                1 => $this->tanpaPembimbing($mahasiswa, $term),
                default => $this->dibimbing($mahasiswa, $term, $dosen, $ruang, $index),
            };
        }
    }

    /** @return Collection<int, Mahasiswa> */
    private function calonLulusan()
    {
        return Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->orderBy('angkatan')
            ->orderBy('id')
            ->limit(9)
            ->get();
    }

    /** A finished project: supervised, defended, graded, closed. */
    private function selesai(Mahasiswa $mahasiswa, TahunAkademik $term, $dosen, ?Ruang $ruang, int $index): void
    {
        $sidang = now()->subMonths(3)->subDays($index);
        $nilai = round(78 + ($index % 5) * 3.5, 2);

        $ta = TugasAkhir::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,

            // Released: the work is done, so it no longer occupies the slot.
            'mahasiswa_aktif_id' => null,

            'judul' => self::JUDUL_LULUS[$index % count(self::JUDUL_LULUS)],
            'status' => TugasAkhirStatus::Selesai,
            'tanggal_pengajuan' => $sidang->copy()->subMonths(8),
            'tanggal_disetujui' => $sidang->copy()->subMonths(7),
            'tanggal_selesai' => $sidang->copy()->addWeeks(2),
            'nilai_akhir' => $nilai,
            'nilai_huruf' => $nilai >= 80 ? 'A' : 'AB',
        ]);

        $utama = $dosen[$index % $dosen->count()];
        $penguji = $dosen[($index + 1) % $dosen->count()];

        Pembimbing::create([
            'tugas_akhir_id' => $ta->id,
            'dosen_id' => $utama->id,
            'peran' => PeranPembimbing::Utama,
            'ditetapkan_pada' => $ta->tanggal_disetujui,
        ]);

        $this->isiBimbingan($ta, $utama, 10, $sidang->copy()->subMonths(6));

        $ujian = Ujian::create([
            'tugas_akhir_id' => $ta->id,
            'jenis' => JenisUjian::Sidang,
            'tanggal' => $sidang,
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'ruang_id' => $ruang?->id,
            'status' => StatusUjian::Selesai,
            'hasil' => HasilUjian::Lulus,
            'nilai' => $nilai,
        ]);

        Penguji::create([
            'tugas_akhir_ujian_id' => $ujian->id,
            'dosen_id' => $penguji->id,
            'peran' => PeranPenguji::Ketua,
            'nilai' => $nilai,
        ]);

        Penguji::create([
            'tugas_akhir_ujian_id' => $ujian->id,
            'dosen_id' => $utama->id,
            'peran' => PeranPenguji::Sekretaris,
            'nilai' => $nilai,
        ]);
    }

    /** A title submitted last week, still waiting on the department. */
    private function menungguKeputusan(Mahasiswa $mahasiswa, TahunAkademik $term): void
    {
        TugasAkhir::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'mahasiswa_aktif_id' => $mahasiswa->id,
            'judul' => 'Penerapan Metode SAW pada Seleksi Penerima Beasiswa',
            'bidang_kajian' => 'Sistem Pendukung Keputusan',
            'status' => TugasAkhirStatus::Diajukan,
            'tanggal_pengajuan' => now()->subWeek(),
            'batas_selesai' => now()->addMonths(24),
        ]);
    }

    /**
     * Approved four months ago and never assigned a supervisor.
     *
     * The state nobody complains about until a semester has gone. It is here so
     * the screen has one to surface.
     */
    private function tanpaPembimbing(Mahasiswa $mahasiswa, TahunAkademik $term): void
    {
        TugasAkhir::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'mahasiswa_aktif_id' => $mahasiswa->id,
            'judul' => 'Klasifikasi Keluhan Layanan Publik dengan Support Vector Machine',
            'bidang_kajian' => 'Kecerdasan Buatan',
            'status' => TugasAkhirStatus::Disetujui,
            'tanggal_pengajuan' => now()->subMonths(5),
            'tanggal_disetujui' => now()->subMonths(4),
            'batas_selesai' => now()->addMonths(20),
        ]);
    }

    /** Under supervision, consultations running, defence not yet scheduled. */
    private function dibimbing(Mahasiswa $mahasiswa, TahunAkademik $term, $dosen, ?Ruang $ruang, int $index): void
    {
        $ta = TugasAkhir::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'mahasiswa_aktif_id' => $mahasiswa->id,
            'judul' => 'Pengembangan Modul Presensi Berbasis Kode QR pada Portal Akademik',
            'bidang_kajian' => 'Rekayasa Perangkat Lunak',
            'status' => TugasAkhirStatus::Dibimbing,
            'tanggal_pengajuan' => now()->subMonths(6),
            'tanggal_disetujui' => now()->subMonths(5),
            'batas_selesai' => now()->addMonths(18),
        ]);

        $utama = $dosen[$index % $dosen->count()];
        $pendamping = $dosen[($index + 2) % $dosen->count()];

        Pembimbing::create([
            'tugas_akhir_id' => $ta->id,
            'dosen_id' => $utama->id,
            'peran' => PeranPembimbing::Utama,
            'ditetapkan_pada' => $ta->tanggal_disetujui,
        ]);

        if ($pendamping->id !== $utama->id) {
            Pembimbing::create([
                'tugas_akhir_id' => $ta->id,
                'dosen_id' => $pendamping->id,
                'peran' => PeranPembimbing::Pendamping,
                'ditetapkan_pada' => $ta->tanggal_disetujui,
            ]);
        }

        // Six signed off and two still waiting — a log where everything is
        // already approved never shows the lecturer what the screen is for.
        $this->isiBimbingan($ta, $utama, 6, now()->subMonths(4));

        foreach ([1, 2] as $n) {
            Bimbingan::create([
                'tugas_akhir_id' => $ta->id,
                'dosen_id' => $utama->id,
                'tanggal' => now()->subDays($n * 3),
                'topik' => "Revisi bab {$n} setelah masukan pembimbing",
                'uraian' => 'Menunggu persetujuan pembimbing.',
                'disetujui' => false,
            ]);
        }
    }

    /** @param Carbon $mulai */
    private function isiBimbingan(TugasAkhir $ta, Dosen $dosen, int $jumlah, $mulai): void
    {
        $topik = [
            'Penetapan rumusan masalah dan batasan penelitian',
            'Kajian pustaka dan penelitian terdahulu',
            'Perancangan metode dan instrumen',
            'Pengumpulan data awal',
            'Analisis data dan pembahasan',
            'Penyusunan kesimpulan',
            'Perbaikan sistematika penulisan',
            'Persiapan seminar',
            'Perbaikan setelah seminar',
            'Finalisasi naskah',
        ];

        for ($i = 0; $i < $jumlah; $i++) {
            $tanggal = $mulai->copy()->addWeeks($i * 2);

            Bimbingan::create([
                'tugas_akhir_id' => $ta->id,
                'dosen_id' => $dosen->id,
                'tanggal' => $tanggal,
                'topik' => $topik[$i % count($topik)],
                'disetujui' => true,
                'disetujui_at' => $tanggal->copy()->addDay(),
            ]);
        }
    }
}
