<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Aktifkan SSO
    |--------------------------------------------------------------------------
    |
    | Nonaktif secara bawaan. Kampus yang hanya memakai Open Academic sendirian
    | tidak perlu menjalankan server OAuth, dan permukaan serang yang tidak
    | dipakai sebaiknya tidak menyala.
    |
    | Saat nonaktif: rute /oauth tidak terdaftar dan tombol SSO di halaman masuk
    | tidak muncul.
    |
    */

    'enabled' => (bool) env('SSO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Masa Berlaku Token (menit)
    |--------------------------------------------------------------------------
    |
    | Access token sengaja pendek. Token yang bocor hanya berguna selama masih
    | berlaku, dan konsumen bisa memperbaruinya diam-diam lewat refresh token.
    |
    */

    'lifetimes' => [
        'access_token' => (int) env('SSO_ACCESS_TOKEN_MINUTES', 60),
        'refresh_token' => (int) env('SSO_REFRESH_TOKEN_MINUTES', 60 * 24 * 30),
        'personal_token' => (int) env('SSO_PERSONAL_TOKEN_MINUTES', 60 * 24 * 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scope
    |--------------------------------------------------------------------------
    |
    | Deskripsi di sini adalah kalimat yang dibaca mahasiswa dan dosen pada
    | layar persetujuan. Tulis apa yang benar-benar dibagikan, dalam bahasa
    | orang yang menyetujuinya — bukan nama teknis endpoint.
    |
    | Kampus boleh menambah scope sendiri; yang tidak tercantum di sini akan
    | ditolak saat diminta.
    |
    */

    'scopes' => [
        'identitas' => 'Mengetahui nama, peran, dan nomor induk Anda',
        'akademik.baca' => 'Membaca riwayat akademik Anda: KRS, nilai, dan IPK',
        'keuangan.baca' => 'Melihat status tagihan dan pembayaran Anda',
        'aktivitas.tulis' => 'Mencatatkan aktivitas MBKM atas nama Anda',
    ],

    /*
    | Scope yang diberikan bila konsumen tidak meminta apa pun secara spesifik.
    | Sengaja hanya identitas: permintaan yang tidak menyebut kebutuhannya tidak
    | pantas mendapat akses ke nilai.
    */

    'default_scopes' => ['identitas'],

    /*
    |--------------------------------------------------------------------------
    | Aplikasi Pihak Pertama
    |--------------------------------------------------------------------------
    |
    | Slug klien yang boleh melewati layar persetujuan. Isi hanya dengan
    | aplikasi yang dijalankan kampus itu sendiri — Open Campus milik kampus,
    | misalnya. Menaruh aplikasi pihak ketiga di sini berarti menghapus satu-
    | satunya titik di mana mahasiswa bisa menolak.
    |
    | Kosongkan bila setiap aplikasi harus selalu meminta persetujuan.
    |
    */

    'first_party' => array_filter(
        explode(',', (string) env('SSO_FIRST_PARTY', '')),
    ),

    /*
    |--------------------------------------------------------------------------
    | Populasi yang Boleh Memakai SSO
    |--------------------------------------------------------------------------
    |
    | Sebagian kampus ingin membuka SSO untuk mahasiswa lebih dulu dan menahan
    | akun staf sampai kebijakannya matang. Nilai yang sah: mahasiswa, dosen,
    | staff.
    |
    */

    'allowed_roles' => array_filter(
        explode(',', (string) env('SSO_ALLOWED_ROLES', 'mahasiswa,dosen,staff')),
    ),

];
