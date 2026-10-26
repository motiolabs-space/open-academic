<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Pmb;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PMB Daring — portal calon mahasiswa (docs/PMB.md, ADR-024)
|--------------------------------------------------------------------------
|
| Portal kelima, di atas guard `pendaftar` yang tidak dikenal portal lain.
| Halaman masuknya sendiri; /masuk utama tidak pernah mencoba guard ini, dan
| auth:mahasiswa tidak pernah menerima sesi pendaftar — calon baru menjadi
| mahasiswa ketika BAAK menerbitkan NIM, dengan akun yang terpisah.
|
| Tanpa term.active: pendaftaran berlangsung sebelum semesternya dimulai.
|
*/

Route::prefix('pmb')->name('pmb.')->group(function (): void {
    Route::get('/', Pmb\BerandaController::class)->name('beranda');

    Route::get('/daftar', [Pmb\AkunController::class, 'create'])->name('daftar');
    Route::post('/daftar', [Pmb\AkunController::class, 'store'])
        ->middleware('throttle:pmb-daftar')
        ->name('daftar.store');

    Route::get('/masuk', [Pmb\MasukController::class, 'show'])->name('masuk');
    Route::post('/masuk', [Pmb\MasukController::class, 'store'])->name('masuk.store');
    Route::post('/keluar', [Pmb\MasukController::class, 'destroy'])->name('keluar');

    Route::get('/lupa-sandi', [Pmb\SandiController::class, 'lupa'])->name('sandi.lupa');
    Route::post('/lupa-sandi', [Pmb\SandiController::class, 'kirimTautan'])
        ->middleware('throttle:pmb-surel')
        ->name('sandi.kirim');
    Route::get('/atur-ulang-sandi/{token}', [Pmb\SandiController::class, 'formAturUlang'])->name('sandi.atur-ulang');
    Route::post('/atur-ulang-sandi', [Pmb\SandiController::class, 'aturUlang'])
        ->middleware('throttle:pmb-surel')
        ->name('sandi.simpan');

    Route::middleware('auth:pendaftar')->prefix('verifikasi-surel')->name('verifikasi.')->group(function (): void {
        Route::get('/', [Pmb\VerifikasiSurelController::class, 'pemberitahuan'])->name('pemberitahuan');
        Route::get('/{akun}/{hash}', [Pmb\VerifikasiSurelController::class, 'verifikasi'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verify');
        Route::post('/kirim-ulang', [Pmb\VerifikasiSurelController::class, 'kirimUlang'])
            ->middleware('throttle:pmb-surel')
            ->name('kirim');
    });

    Route::middleware(['auth:pendaftar', 'verified:pmb.verifikasi.pemberitahuan'])
        ->prefix('portal')
        ->name('portal.')
        ->group(function (): void {
            Route::get('/', Pmb\PortalController::class)->name('index');
            Route::post('/pendaftaran', [Pmb\PendaftaranController::class, 'store'])->name('store');

            Route::get('/{pendaftar}', [Pmb\PendaftaranController::class, 'show'])->name('show');
            Route::put('/{pendaftar}/biodata', [Pmb\PendaftaranController::class, 'biodata'])->name('biodata');
            Route::post('/{pendaftar}/ajukan', [Pmb\PendaftaranController::class, 'ajukan'])->name('ajukan');
            Route::post('/{pendaftar}/batal', [Pmb\PendaftaranController::class, 'batal'])->name('batal');
            Route::get('/{pendaftar}/kartu-ujian', [Pmb\PendaftaranController::class, 'kartu'])->name('kartu');

            Route::post('/{pendaftar}/berkas', [Pmb\BerkasPendaftarController::class, 'store'])->name('berkas.store');
            Route::get('/{pendaftar}/berkas/{berkas}', [Pmb\BerkasPendaftarController::class, 'show'])
                ->scopeBindings()
                ->name('berkas.show');
            Route::delete('/{pendaftar}/berkas/{berkas}', [Pmb\BerkasPendaftarController::class, 'destroy'])
                ->scopeBindings()
                ->name('berkas.destroy');
        });
});

/*
|--------------------------------------------------------------------------
| PMB — layar staf untuk gelombang, pendaftar, dan biaya formulir
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:staff', 'term.active'])->prefix('admin/pmb')->name('admin.pmb.')->group(function (): void {
    Route::get('/formulir', [Admin\PmbFormulirController::class, 'index'])->name('formulir');
    Route::post('/pendaftar/{pendaftar}/pembayaran', [Admin\PmbFormulirController::class, 'store'])->name('pembayaran.store');
    Route::post('/pembayaran/{pembayaran}/batal', [Admin\PmbFormulirController::class, 'batal'])->name('pembayaran.batal');

    Route::get('/pendaftar/{pendaftar}', [Admin\PmbPendaftarController::class, 'show'])->name('pendaftar');
    Route::post('/berkas/{berkas}/kembalikan', [Admin\PmbPendaftarController::class, 'kembalikanBerkas'])->name('berkas.kembalikan');

    Route::get('/gelombang/{gelombang}', [Admin\PmbGelombangController::class, 'show'])->name('gelombang');
    Route::put('/gelombang/{gelombang}', [Admin\PmbGelombangController::class, 'update'])->name('gelombang.update');
    Route::post('/gelombang/{gelombang}/prodi', [Admin\PmbGelombangController::class, 'simpanTawaran'])->name('gelombang.prodi');
    Route::delete('/gelombang/{gelombang}/prodi/{tawaran}', [Admin\PmbGelombangController::class, 'hapusTawaran'])->name('gelombang.prodi.hapus');
    Route::post('/gelombang/{gelombang}/jadwal', [Admin\PmbGelombangController::class, 'storeJadwal'])->name('jadwal.store');
    Route::delete('/gelombang/{gelombang}/jadwal/{jadwal}', [Admin\PmbGelombangController::class, 'hapusJadwal'])->name('jadwal.hapus');
    Route::post('/gelombang/{gelombang}/impor-nilai', [Admin\PmbGelombangController::class, 'imporNilai'])->name('nilai.impor');
    Route::post('/gelombang/{gelombang}/umumkan', [Admin\PmbGelombangController::class, 'umumkan'])->name('umumkan');
});
