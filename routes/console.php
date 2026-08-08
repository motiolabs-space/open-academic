<?php

declare(strict_types=1);

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
