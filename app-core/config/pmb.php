<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pendaftaran Mahasiswa Baru Daring
|--------------------------------------------------------------------------
|
| Calon mahasiswa mendaftar sendiri lewat /pmb. Lihat docs/PMB.md dan
| ADR-024. Gelombang hanya terbuka bagi pendaftaran daring bila staf
| menyalakan "Terima pendaftaran daring" di layar gelombang — gelombang lama
| tidak berubah menjadi formulir publik sesudah pembaruan.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Kelulusan otomatis berdasarkan ambang
    |--------------------------------------------------------------------------
    |
    | Bawaan MATI: kelulusan diputuskan staf. Bila dinyalakan, setiap impor
    | nilai seleksi langsung meluluskan pendaftar yang nilainya mencapai
    | ambang prodi pilihannya (diatur per prodi di layar gelombang), urut dari
    | nilai tertinggi sampai daya tampung penuh, dan menyatakan tidak lulus
    | yang tidak mencapai ambang pada kedua pilihannya. Prodi tanpa ambang
    | tidak pernah diputuskan otomatis.
    |
    | Pengumuman tetap tindakan staf yang terpisah: menyalakan ini tidak
    | membuat hasil terlihat oleh pendaftar sebelum diumumkan.
    |
    */

    'kelulusan_otomatis' => (bool) env('PMB_KELULUSAN_OTOMATIS', false),

    /*
    |--------------------------------------------------------------------------
    | Batas laju
    |--------------------------------------------------------------------------
    |
    | Halaman PMB publik dan dapat dijangkau siapa pun. Daftar dibatasi per
    | IP agar satu skrip tidak memenuhi tabel akun; masuk dibatasi per surel
    | + IP, pola yang sama dengan halaman masuk utama; pengiriman surel
    | (verifikasi, lupa sandi) per IP agar formulir ini tidak dipakai mengirim
    | surel ke alamat orang lain berulang-ulang.
    |
    */

    'batas' => [
        'daftar_per_jam' => (int) env('PMB_BATAS_DAFTAR_PER_JAM', 10),
        'masuk_percobaan' => (int) env('PMB_BATAS_MASUK', 5),
        'surel_per_menit' => (int) env('PMB_BATAS_SUREL_PER_MENIT', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Impor nilai seleksi
    |--------------------------------------------------------------------------
    |
    | Berkas CSV dari CBT mana pun, minimal dua kolom berjudul
    | `nomor_pendaftaran` dan `nilai`. Seluruh baris diperiksa lebih dulu;
    | satu baris salah membatalkan seluruh impor dan semua kesalahan
    | dilaporkan sekaligus.
    |
    */

    'impor' => [
        'maks_baris' => (int) env('PMB_IMPOR_MAKS_BARIS', 5000),
        'nilai_maks' => 100,
    ],

];
