<?php

declare(strict_types=1);

return [

    'failed' => 'Data yang Anda masukkan tidak cocok dengan catatan kami.',
    'password' => 'Kata sandi salah.',
    'throttle' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',
    'nonaktif' => 'Akun Anda telah dinonaktifkan. Hubungi administrator kampus bila ini keliru.',
    'sandi_berubah' => 'Kata sandi akun ini baru saja diganti. Silakan masuk kembali.',

    /*
     | Masuk lewat IdP eksternal. Kunci selain `masuk` dan `atau` adalah kode
     | alasan dari IdpException; yang tidak punya kalimat sendiri jatuh ke
     | `gagal`, yang sengaja tidak menjelaskan apa-apa kepada siapa pun yang
     | mencoba-coba.
     */
    'idp' => [
        'masuk' => 'Masuk dengan :idp',
        'atau' => 'atau',
        'gagal' => 'Masuk lewat :idp tidak dapat diselesaikan. Silakan coba lagi, atau masuk dengan kata sandi.',
        'state_salah' => 'Sesi masuk tidak dikenali. Mulai lagi dari halaman ini.',
        'state_kedaluwarsa' => 'Sesi masuk kedaluwarsa. Mulai lagi dari halaman ini.',
        'ditolak_idp' => ':idp menolak permintaan masuk.',
        'tidak_dikenal' => 'Surel :email tidak terdaftar sebagai akun di sini. Hubungi administrator untuk ditautkan.',
        'nonaktif' => 'Akun Anda dinonaktifkan.',
        'ambigu' => 'Surel :email terdaftar pada lebih dari satu akun. Hubungi administrator.',
        'tertaut_identitas_lain' => 'Akun dengan surel :email sudah tertaut ke identitas lain. Hubungi administrator.',
        'surel_belum_terverifikasi' => ':idp belum memverifikasi surel :email.',
        'tanpa_surel' => ':idp tidak membagikan alamat surel Anda.',
    ],

];
