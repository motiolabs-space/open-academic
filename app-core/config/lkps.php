<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | LKPS — Definisi yang Dipakai Menghitung
    |--------------------------------------------------------------------------
    |
    | Angka LKPS jarang meleset karena rumusnya. Ia meleset karena dua orang
    | memakai definisi berbeda untuk kata yang sama, dan tidak ada yang
    | menyadarinya sampai asesor bertanya.
    |
    | Setiap nilai di bawah adalah salah satu keputusan itu. Semuanya masih
    | BAWAAN SEMENTARA dan menunggu keputusan kampus — daftar pertanyaannya
    | beserta akibat tiap pilihan ada di docs/LKPS-DEFINISI.md.
    |
    | Bawaannya sengaja dipilih yang konservatif: menghitung lebih sedikit
    | ketimbang lebih banyak. Angka yang terlalu bagus karena definisi longgar
    | adalah angka yang tidak bertahan saat dicek silang ke PDDIKTI.
    |
    | Nomor dan susunan tabel LKPS berbeda antar-LAM dan berubah antar-revisi;
    | yang ditetapkan di sini adalah BESARANNYA, bukan letaknya di borang.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Keketatan
    |--------------------------------------------------------------------------
    |
    | Alur PMB: mendaftar → verifikasi → seleksi → lulus/tidak_lulus →
    | daftar_ulang → mahasiswa (dan batal).
    |
    */

    'keketatan' => [

        // Tahap paling awal yang sudah dihitung sebagai "pendaftar".
        // Bawaan 'verifikasi': yang berkasnya lengkap. 'mendaftar' akan
        // memasukkan orang yang tidak pernah menyerahkan apa pun, dan
        // membuat keketatan terlihat lebih baik daripada yang sebenarnya.
        'pendaftar_sejak' => 'verifikasi',

        // Status yang dihitung sebagai "diterima".
        'diterima' => ['lulus', 'daftar_ulang', 'mahasiswa'],

        // Status yang dihitung sebagai "mendaftar ulang".
        'daftar_ulang' => ['daftar_ulang', 'mahasiswa'],

        // Prodi mana yang "menerima" seorang pendaftar untuk keperluan
        // pencacahan: pilihan pertamanya, atau setiap prodi yang ia pilih.
        // 'pilihan_1' | 'semua_pilihan'
        'prodi_dari' => 'pilihan_1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dosen Tetap Program Studi (DTPS)
    |--------------------------------------------------------------------------
    |
    | Penyebut rasio dosen–mahasiswa dan pembagi beban penelitian. Satu dosen
    | saja menggeser beberapa tabel sekaligus.
    |
    */

    'dtps' => [

        // dosen.status_kepegawaian: tetap | tidak_tetap | luar_biasa
        'status_kepegawaian' => ['tetap'],

        // Dosen praktisi ikut dihitung sebagai DTPS.
        'sertakan_praktisi' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mahasiswa Aktif
    |--------------------------------------------------------------------------
    */

    'mahasiswa_aktif' => [

        // StudentStatus: A aktif, C cuti, N non-aktif, L lulus, D drop out,
        // K keluar, G ganti prodi.
        'status' => ['A'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Masa Studi
    |--------------------------------------------------------------------------
    */

    'masa_studi' => [

        // Titik awal: 'term_masuk' (semester pertama aktif) | 'angkatan'.
        'dari' => 'term_masuk',

        // Semester cuti dikurangkan dari masa studi.
        'kurangi_cuti' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lulus Tepat Waktu
    |--------------------------------------------------------------------------
    |
    | Batas dalam SEMESTER, per jenjang. Kunci mengikuti EducationLevel.
    |
    */

    'tepat_waktu' => [

        'batas_semester' => [
            'D3' => 6,
            'D4' => 8,
            'S1' => 8,
            'S2' => 4,
            'S3' => 6,
        ],

        /*
         * Mahasiswa alih jenjang dan pindahan dikeluarkan dari populasi.
         *
         * Menahannya dengan batas penuh menekan angka kelulusan tepat waktu
         * tanpa sebab yang nyata. Berapa pun yang dikeluarkan tetap ikut
         * dilaporkan, supaya angkanya dapat diperiksa ulang.
         *
         * CATATAN: pembedaannya bergantung pada `mahasiswa.jalur_masuk` yang
         * dipetakan ke kode jenis_daftar PDDIKTI lewat tabel FeederMapping.
         * Kampus yang belum mengisi pemetaan itu tidak dapat membedakannya,
         * dan kalkulatornya akan menyatakan demikian alih-alih menebak.
         */
        'kecualikan_alih_jenjang' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Putus Studi
    |--------------------------------------------------------------------------
    */

    'putus_studi' => [

        // Bawaan 'D' saja; 'K' (mengundurkan diri) sering dilaporkan terpisah.
        'status' => ['D'],

        /*
         * Berapa semester berturut-turut berstatus non-aktif sebelum seorang
         * mahasiswa dihitung putus studi.
         *
         * Tanpa ambang ini, mahasiswa yang hilang tanpa pernah diproses akan
         * berstatus N selamanya: tidak muncul sebagai aktif, tidak pula
         * sebagai putus studi, dan jumlah mahasiswa prodi tidak akan pernah
         * berimbang.
         */
        'nonaktif_berturut' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kepuasan Mahasiswa
    |--------------------------------------------------------------------------
    |
    | LKPS menanyakan kepuasan atas LAYANAN (akademik, sarana, kemahasiswaan).
    | Yang ada di aplikasi ini adalah EDOM — kepuasan atas pengajaran seorang
    | dosen, per kelas. Keduanya tidak sama.
    |
    | null  → tabelnya dinyatakan tidak terisi, dan itu jawaban yang jujur
    | 'edom' → EDOM dipakai sebagai proksi, DAN dinyatakan sebagai proksi
    |
    */

    'kepuasan' => [
        'sumber' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Susunan Borang
    |--------------------------------------------------------------------------
    |
    | Perakit menyusun besaran kanonis menjadi tabel. Yang berbeda antar-LAM
    | terutama letaknya: nomor tabel, judulnya, dan pengelompokan kolomnya.
    |
    | `nomor` sengaja KOSONG. Nomor tabel berbeda antar-LAM dan berubah
    | antar-revisi instrumen, dan menuliskan tebakan di sini akan membuat
    | seseorang menyalinnya ke borang tanpa memeriksa. Isi dari borang yang
    | sedang berlaku di LAM kampus Anda; sampai diisi, tabelnya tetap terakit
    | dan tercetak tanpa nomor.
    |
    | Menambah LAM kedua berarti menyalin blok ini dengan nomor yang berbeda —
    | bukan menulis perakit kedua.
    |
    */

    'borang' => [

        'seleksi' => [
            'nomor' => null,
            'judul' => 'Seleksi Mahasiswa Baru',
        ],

        'mahasiswa_dosen' => [
            'nomor' => null,
            'judul' => 'Mahasiswa Aktif & Rasio Dosen',
        ],

        'lulusan' => [
            'nomor' => null,
            'judul' => 'Profil Lulusan: IPK & Masa Studi',
        ],

        'putus_studi' => [
            'nomor' => null,
            'judul' => 'Mahasiswa Putus Studi',
        ],
    ],

    // Berapa tahun ke belakang ditampilkan pada tabel yang berderet tahun.
    // Borang lazimnya meminta TS, TS-1, TS-2.
    'tahun_deret' => 3,

];
