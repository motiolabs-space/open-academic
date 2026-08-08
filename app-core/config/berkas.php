<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disk Penyimpanan
    |--------------------------------------------------------------------------
    |
    | WAJIB disk privat. Berkas yang disimpan di sini berisi KTP, kartu
    | keluarga, ijazah, dan surat keterangan sakit — dokumen identitas dan
    | rekam medis milik orang sungguhan.
    |
    | Disk "public" akan menaruhnya di bawah document root, sehingga siapa pun
    | yang menebak nama berkasnya dapat mengunduhnya tanpa masuk sistem. Disk
    | "local" menyimpan di storage/app dan tidak dapat dijangkau web server;
    | seluruh unduhan lewat controller yang memeriksa izin lebih dulu.
    |
    | Untuk beberapa server aplikasi, ganti ke "s3" dengan bucket privat.
    |
    */

    'disk' => env('BERKAS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Batas Ukuran (kilobyte)
    |--------------------------------------------------------------------------
    |
    | Ingat menaikkan `upload_max_filesize` dan `post_max_size` pada php.ini
    | bila batas ini dinaikkan — PHP menolak lebih dulu, dan penolakannya tidak
    | menghasilkan pesan validasi yang bisa dibaca pengguna.
    |
    */

    'maks_kb' => (int) env('BERKAS_MAKS_KB', 4096),

    /*
    |--------------------------------------------------------------------------
    | Jenis Berkas yang Diizinkan
    |--------------------------------------------------------------------------
    |
    | Diperiksa terhadap isi berkas, bukan ekstensinya. Ekstensi hanyalah bagian
    | dari nama yang diketik pengunggah; ia tidak menyatakan apa pun tentang isi.
    |
    | `mimes` dipakai aturan validasi Laravel, yang mencocokkan tipe hasil
    | penebakan dari isi berkas.
    |
    */

    'jenis' => [
        'dokumen' => ['pdf', 'jpg', 'jpeg', 'png'],
        'gambar' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kategori Berkas PMB
    |--------------------------------------------------------------------------
    */

    'pmb' => [
        'ijazah' => 'Ijazah / SKL',
        'rapor' => 'Rapor',
        'kk' => 'Kartu Keluarga',
        'ktp' => 'KTP / KIA',
        'foto' => 'Pas Foto',
        'lainnya' => 'Lainnya',
    ],

];
