<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A populated notification list for the demo campus.
 *
 * Rows are written straight to the table rather than sent through the services.
 * DemoCampusSeeder points the queue at the null driver while it runs — that is
 * what keeps the audit trail from filling with fabricated entries — so a
 * notification dispatched during seeding would be silently discarded and the
 * bell would be empty on a fresh clone.
 *
 * Deliberately mixed read and unread. A list where everything is unread looks
 * like a broken counter, and one where everything is read shows nothing about
 * what the screen is for.
 */
class NotifikasiSeeder extends Seeder
{
    public function run(): void
    {
        $mahasiswa = Mahasiswa::where('email', 'mahasiswa1@demo.test')->first();
        $dosen = Dosen::where('email', 'dosen1@demo.test')->first();
        $staf = Staff::where('email', 'baak@demo.test')->first();

        if ($mahasiswa !== null) {
            $this->tulis($mahasiswa, KategoriNotifikasi::Akademik, 'success',
                'Rencana studi disetujui',
                'Rencana studi Anda untuk semester berjalan telah disetujui dosen wali (21 SKS).',
                '/mahasiswa/krs', dibaca: true, umurJam: 96);

            $this->tulis($mahasiswa, KategoriNotifikasi::Keuangan, 'info',
                'Tagihan baru: Biaya kuliah semester berjalan',
                'Tagihan sebesar Rp4.850.000 telah diterbitkan.',
                '/mahasiswa/tagihan', dibaca: true, umurJam: 72);

            $this->tulis($mahasiswa, KategoriNotifikasi::Pengingat, 'warning',
                'Tagihan jatuh tempo 7 hari lagi',
                'Sisa tagihan Rp2.425.000. Jatuh tempo pekan depan.',
                '/mahasiswa/tagihan', dibaca: false, umurJam: 20);

            $this->tulis($mahasiswa, KategoriNotifikasi::Akademik, 'success',
                'Nilai Basis Data sudah terbit',
                'Nilai akhir untuk Basis Data sudah difinalisasi dan tampil pada KHS Anda.',
                '/mahasiswa/khs', dibaca: false, umurJam: 5);
        }

        if ($dosen !== null) {
            $this->tulis($dosen, KategoriNotifikasi::TugasAkhir, 'info',
                'Anda ditetapkan sebagai pembimbing',
                'Anda ditetapkan sebagai pembimbing utama untuk salah satu mahasiswa bimbingan tugas akhir.',
                '/dosen/tugas-akhir', dibaca: true, umurJam: 120);

            $this->tulis($dosen, KategoriNotifikasi::Pengingat, 'warning',
                '2 log bimbingan menunggu persetujuan Anda',
                'Log yang belum disetujui tidak dihitung sebagai syarat sidang.',
                '/dosen/tugas-akhir', dibaca: false, umurJam: 8);
        }

        if ($staf !== null) {
            $this->tulis($staf, KategoriNotifikasi::Sistem, 'danger',
                'Sinkronisasi Neo Feeder menemukan data tidak valid',
                'Beberapa baris tidak lolos validasi pra-kirim — antara lain NIK kosong dan NIDN tidak valid.',
                '/admin/feeder', dibaca: false, umurJam: 14);
        }
    }

    private function tulis(
        object $penerima,
        KategoriNotifikasi $kategori,
        string $tone,
        string $judul,
        string $ringkasan,
        string $tautan,
        bool $dibaca,
        int $umurJam,
    ): void {
        $waktu = now()->subHours($umurJam);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),

            // The class is never resolved from this string here, but the column
            // is what Laravel's own tooling reads, so it stays honest.
            'type' => 'App\\Notifications\\Demo',

            'notifiable_type' => $penerima->getMorphClass(),
            'notifiable_id' => $penerima->getKey(),
            'kategori' => $kategori->value,
            'data' => json_encode([
                'kategori' => $kategori->value,
                'judul' => $judul,
                'ringkasan' => $ringkasan,
                'tautan' => $tautan,
                'tone' => $tone,
            ], JSON_UNESCAPED_UNICODE),
            'read_at' => $dibaca ? $waktu->copy()->addHour() : null,
            'created_at' => $waktu,
            'updated_at' => $waktu,
        ]);
    }
}
