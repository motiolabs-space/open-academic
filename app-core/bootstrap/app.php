<?php

declare(strict_types=1);

use App\Exceptions\AturanAkademikException;
use App\Exceptions\FeederException;
use App\Http\Middleware\EnsureBridgeScope;
use App\Http\Middleware\EnsureTermIsActive;
use App\Http\Middleware\PastikanDuaFaktor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * Tata letak app-core: aplikasinya di sini, berkas yang dilayani web satu
 * tingkat di atas.
 *
 * Direktori publiknya ditetapkan di sini, bukan di index.php, karena index.php
 * hanya dilewati permintaan web. Artisan, worker antrean, dan test runner tidak
 * melewatinya sama sekali — dan mereka juga memanggil `public_path()`:
 * `storage:link` menaruh tautannya, dan pembantu Vite mencari manifesnya di
 * situ. Menetapkannya hanya di index.php membuat ketiganya menunjuk
 * app-core/public, direktori yang bahkan tidak ada lagi.
 */
$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'term.active' => EnsureTermIsActive::class,
            'bridge.scope' => EnsureBridgeScope::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        /*
         * Pada grup web, bukan tumpukan global.
         *
         * Middleware global berjalan sebelum perutean, sehingga routeIs() di
         * sana selalu false — dan pengecualian untuk layar pemasangan serta
         * keluar tidak akan pernah cocok, membuat keduanya dialihkan ke diri
         * sendiri. Pada grup web rutenya sudah terselesaikan.
         *
         * Tetap se-grup dan bukan per-rute admin, supaya tidak ada rute staf
         * yang terlewat begitu rute baru ditambahkan.
         */
        $middleware->web(append: [PastikanDuaFaktor::class]);

        // Guests land on the single sign-in page whichever portal they aimed at.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A refused academic rule is a normal outcome, not a crash: the person
        // who tripped it gets the reason back on the page they were already on.
        $exceptions->renderable(function (AturanAkademikException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('galat', $e->getMessage());
        });

        /*
         * Penolakan Feeder juga hasil yang wajar, bukan kerusakan.
         *
         * Seluruh pesan FeederException memang ditulis untuk dibaca operator —
         * "integrasi dinonaktifkan", "dependensi belum sinkron", "N baris tidak
         * valid". Tanpa penanganan ini, menekan "Tarik Referensi" saat
         * FEEDER_ENABLED=false menghasilkan halaman 500, dan operator tidak
         * pernah tahu bahwa yang kurang cuma satu bendera konfigurasi.
         *
         * Ditemukan dengan menyapu seluruh rute tulis memakai muatan kosong:
         * satu-satunya yang membalas 500 dari 57 rute tanpa parameter.
         */
        $exceptions->renderable(function (FeederException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('galat', $e->getMessage());
        });
    })->create();

$app->usePublicPath(dirname(__DIR__, 2));

return $app;
