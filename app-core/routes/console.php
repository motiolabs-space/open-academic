<?php

declare(strict_types=1);

use App\Support\Akuntansi;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Terjadwal
|--------------------------------------------------------------------------
|
| Perlu satu entri cron pada server:
|
|   * * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
|
| Tanpa itu tidak ada satu pun pengingat yang terkirim, dan tidak ada yang
| memberi tahu bahwa demikian — persis bentuk kegagalan yang modul notifikasi
| ini dibangun untuk menghilangkannya. Lihat docs/NOTIFIKASI.md.
|
*/

/*
 * Denda keterlambatan.
 *
 * Dini hari, sebelum pengingat pukul 07:00, supaya pengingat mengutip denda
 * hari ini dan bukan kemarin. Idempoten per hari: yang dikenai hanya periode
 * yang belum pernah dihitung, jadi jalan ganda atau jalan susulan sesudah
 * server mati tidak menggandakan apa pun — lihat DendaService.
 *
 * Tidak dijalankan sama sekali selagi kebijakan nonaktif, yang merupakan
 * bawaan Open Academic.
 */
Schedule::command('openacademic:hitung-denda')
    ->dailyAt('01:00')
    ->timezone(config('app.timezone'))
    ->onOneServer()
    ->withoutOverlapping()
    ->skip(fn (): bool => !(bool) config('payment.denda.aktif'));

// Pagi hari waktu kampus. Pengingat yang tiba tengah malam terbaca esok pagi
// bersama semalam penuh pesan lain, dan kehilangan urgensinya di tengahnya.
Schedule::command('openacademic:kirim-pengingat')
    ->dailyAt('07:00')
    ->timezone(config('app.timezone'))

    // Hanya satu instansi yang mengirim, sekalipun aplikasi berjalan di
    // beberapa server di belakang penyeimbang beban.
    ->onOneServer()

    // Jalannya yang berikutnya dilewati bila yang sekarang belum selesai,
    // alih-alih menumpuk pengiriman yang saling beririsan.
    ->withoutOverlapping();

/*
 * Outbox akuntansi.
 *
 * Tiap lima menit, bukan harian: piutang yang baru muncul di buku besar keesokan
 * harinya membuat bagian keuangan bekerja dengan angka kemarin sepanjang hari,
 * dan itu persis keadaan yang integrasi ini bangun untuk dihilangkan.
 *
 * Dokumen yang gagal tidak diulang di sini — ia menunggu tindakan manusia di
 * layar Akuntansi, karena penyebabnya hampir selalu kode akun yang belum ada di
 * seberang dan mengulangnya seribu kali tidak memperbaikinya.
 */
Schedule::command('openacademic:kirim-akuntansi')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping()

    // Tidak dijalankan sama sekali bila integrasinya nonaktif — bawaan Open
    // Academic. Kampus yang memegang buku besarnya di tempat lain tidak perlu
    // menanggung satu pun proses tiap lima menit untuk modul yang tidak dipakai.
    ->skip(fn (): bool => !Akuntansi::aktif());

/*
 * Jam SLA helpdesk.
 *
 * Tiap jam, karena SLA kategori dinyatakan dalam jam. Perintah ini hanya
 * MENANDAI tiket yang lewat tenggat — tidak menutup, tidak mengalihkan, tidak
 * memberi tahu pemohon. Tenggat yang terlewat adalah fakta untuk antrean unit,
 * bukan keputusan yang boleh diambil penjadwal atas nama unit itu.
 */
Schedule::command('openacademic:helpdesk-sla')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

/*
 * Akun dosen pensiun.
 *
 * Pensiun biasanya dicatat jauh sebelum TMT-nya, jadi persetujuan saja tidak
 * dapat menutup akun. Sekali sehari menutup yang sudah jatuh tempo — kecuali
 * yang masih mengampu kelas semester berjalan atau menjadi dosen wali, yang
 * dilaporkan dan dicoba lagi esok hari. Lihat docs/KEPEGAWAIAN.md.
 */
Schedule::command('openacademic:tutup-akun-pensiun')
    ->dailyAt('00:30')
    ->timezone(config('app.timezone'))
    ->onOneServer()
    ->withoutOverlapping();
