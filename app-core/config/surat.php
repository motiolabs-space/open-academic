<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pola Nomor Surat
    |--------------------------------------------------------------------------
    |
    | Placeholder: {urut} nomor urut ber-padding, {kode} kode jenis surat,
    | {bulan} bulan Romawi, {tahun} tahun empat digit, {institusi} kode kampus.
    |
    | Setiap kampus punya konvensinya sendiri, dan nomor ini tercetak pada
    | dokumen yang dibawa orang ke bank, kedutaan, dan calon pemberi kerja.
    | Tetapkan sebelum surat pertama terbit — mengubahnya setelah itu berarti
    | dua konvensi hidup berdampingan di lemari arsip yang sama.
    |
    */

    'pola_nomor' => env('SURAT_POLA_NOMOR', '{urut}/{kode}/{institusi}/{bulan}/{tahun}'),

    'panjang_urut' => 4,

    /*
    |--------------------------------------------------------------------------
    | Penandatangan
    |--------------------------------------------------------------------------
    |
    | Tercetak pada blok tanda tangan. Dikosongkan berarti hanya nama institusi
    | yang muncul — lebih baik daripada nama pejabat yang sudah tidak menjabat.
    |
    */

    'penandatangan' => [
        'nama' => env('SURAT_PENANDATANGAN_NAMA'),
        'jabatan' => env('SURAT_PENANDATANGAN_JABATAN', 'Kepala Biro Administrasi Akademik'),
        'nip' => env('SURAT_PENANDATANGAN_NIP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Syarat Penerbitan
    |--------------------------------------------------------------------------
    */

    'syarat' => [
        /*
         * Menahan surat keterangan aktif kuliah selama ada tagihan yang sudah
         * lewat jatuh tempo.
         *
         * Sebagian kampus memakai surat ini sebagai alat penagihan; sebagian
         * menganggapnya keterlaluan karena surat itu sering justru dibutuhkan
         * untuk mencairkan beasiswa yang akan membayar tagihannya. Karena itu
         * bisa dimatikan, dan hanya menghitung yang sudah lewat tempo — bukan
         * setiap tagihan yang belum lunas.
         */
        'tahan_bila_menunggak' => (bool) env('SURAT_TAHAN_BILA_MENUNGGAK', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verifikasi Publik
    |--------------------------------------------------------------------------
    |
    | Halaman verifikasi terbuka tanpa autentikasi — memang itu gunanya; yang
    | memeriksa adalah pihak ketiga yang tidak punya akun di sini.
    |
    | Karena itu ia hanya menampilkan seminimal mungkin, dan dibatasi lajunya.
    |
    */

    'verifikasi' => [
        'aktif' => (bool) env('SURAT_VERIFIKASI_AKTIF', true),

        // Percobaan per menit per alamat IP untuk pencarian manual.
        'batas_per_menit' => (int) env('SURAT_VERIFIKASI_BATAS', 10),
    ],

];
