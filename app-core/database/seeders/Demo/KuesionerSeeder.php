<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Kuesioner\KuesionerData;
use App\DTOs\Kuesioner\PertanyaanData;
use App\Enums\SasaranKuesioner;
use App\Enums\TipePertanyaan;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Kuesioner\KuesionerService;
use Illuminate\Database\Seeder;

/**
 * Three questionnaires, one per state a screen can show.
 *
 *  - an anonymous student satisfaction survey, published, answered by enough
 *    students to clear its threshold, so the results screen has something;
 *  - a staff poll, published and still unanswered, so a staff account has
 *    something to fill in;
 *  - a draft, so the editing screen is not only ever seen frozen.
 *
 * Everything goes through KuesionerService, like HelpdeskSeeder: demo rows
 * that skipped the rules would behave differently from real ones.
 */
class KuesionerSeeder extends Seeder
{
    public function run(): void
    {
        $layanan = app(KuesionerService::class);
        $admin = Staff::where('email', 'admin@demo.test')->firstOrFail();

        $kepuasan = $layanan->buat(new KuesionerData(
            kode: 'KEPUASAN-2026',
            nama: 'Survei Kepuasan Layanan Akademik 2026',
            deskripsi: 'Pendapat Anda membantu BAAK, keuangan, dan perpustakaan memperbaiki layanannya.',
            sasaran: SasaranKuesioner::Mahasiswa,
            anonim: true,
            minResponden: 5,
            selesai: now()->addMonth(),
        ), $admin);

        $layanan->tambahPertanyaan($kepuasan, new PertanyaanData('Seberapa puas Anda dengan layanan BAAK?', TipePertanyaan::Skala));
        $layanan->tambahPertanyaan($kepuasan, new PertanyaanData('Seberapa puas Anda dengan layanan keuangan?', TipePertanyaan::Skala));
        $layanan->tambahPertanyaan($kepuasan, new PertanyaanData('Layanan mana yang paling sering Anda gunakan?', TipePertanyaan::Pilihan, ['BAAK', 'Keuangan', 'Perpustakaan', 'Helpdesk TIK']));
        $layanan->tambahPertanyaan($kepuasan, new PertanyaanData('Apa yang paling perlu diperbaiki?', TipePertanyaan::Teks, wajib: false));
        $layanan->terbitkan($kepuasan);

        $pertanyaan = $kepuasan->pertanyaan()->get();
        $saran = ['Antrean legalisir terlalu lama.', 'Jam layanan keuangan diperpanjang.', null, 'Informasi jadwal KRS lebih awal.', null, null, 'Wi-Fi perpustakaan sering putus.'];
        $pilihan = ['BAAK', 'Keuangan', 'Perpustakaan', 'Helpdesk TIK'];

        // Students further down the list, not the first one: demo logins
        // (and SmokeLayarTest) sign in as the first, and that account should
        // still see the survey waiting to be filled.
        Mahasiswa::query()->orderByDesc('id')->limit(count($saran))->get()
            ->each(function (Mahasiswa $m, int $i) use ($layanan, $kepuasan, $pertanyaan, $saran, $pilihan): void {
                $layanan->isi($kepuasan, $m, [
                    $pertanyaan[0]->id => ['nilai' => [4, 5, 3, 4, 2, 5, 4][$i]],
                    $pertanyaan[1]->id => ['nilai' => [3, 2, 4, 3, 2, 3, 4][$i]],
                    $pertanyaan[2]->id => ['teks' => $pilihan[$i % count($pilihan)]],
                    $pertanyaan[3]->id => ['teks' => $saran[$i]],
                ]);
            });

        $staf = $layanan->buat(new KuesionerData(
            kode: 'SARPRAS-STAF-2026',
            nama: 'Kebutuhan Sarana Kerja Staf',
            deskripsi: 'Masukan untuk rencana pengadaan tahun depan.',
            sasaran: SasaranKuesioner::Staf,
            anonim: false,
        ), $admin);

        $layanan->tambahPertanyaan($staf, new PertanyaanData('Perangkat kerja Anda memadai?', TipePertanyaan::Skala));
        $layanan->tambahPertanyaan($staf, new PertanyaanData('Kebutuhan paling mendesak', TipePertanyaan::Teks, wajib: false));
        $layanan->terbitkan($staf);

        $draf = $layanan->buat(new KuesionerData(
            kode: 'ALUMNI-PRA-2027',
            nama: 'Kesiapan Lulus (draf)',
            deskripsi: null,
            sasaran: SasaranKuesioner::Mahasiswa,
            anonim: true,
        ), $admin);

        $layanan->tambahPertanyaan($draf, new PertanyaanData('Seberapa siap Anda memasuki dunia kerja?', TipePertanyaan::Skala));
    }
}
