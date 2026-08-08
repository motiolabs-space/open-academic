<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dompdf
|--------------------------------------------------------------------------
|
| Hanya satu kunci yang ditimpa. Sisanya tetap datang dari bawaan paket lewat
| `mergeConfigFrom`, jadi berkas ini tidak perlu ikut berubah setiap kali
| paketnya menambah opsi.
|
*/

return [

    /*
     * Direktori dasar untuk aset yang dirujuk PDF (logo, gambar, CSS).
     *
     * barryvdh/laravel-dompdf mencarinya di `base_path('public')` — jalur yang
     * di-hardcode dan mengabaikan `usePublicPath()`. Pada tata letak app-core
     * direktori itu tidak ada sama sekali, sehingga `realpath()`-nya gagal dan
     * setiap pembuatan PDF berakhir dengan RuntimeException "Cannot resolve
     * public path".
     *
     * Dievaluasi saat konfigurasi dimuat, yang terjadi setelah bootstrap/app.php
     * menetapkan direktori publiknya — jadi nilai ini sudah benar di sini.
     */
    'public_path' => public_path(),

];
