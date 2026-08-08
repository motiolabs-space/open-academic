<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Siapa yang wajib melapor
    |--------------------------------------------------------------------------
    |
    | BKD adalah syarat tunjangan sertifikasi. Yang belum bersertifikat pendidik
    | tidak terikat kewajiban itu, jadi bawaannya hanya pemegang Serdos yang
    | diminta melapor — memaksa semua dosen berarti membebankan administrasi
    | yang tidak diminta regulasi.
    |
    | Set "semua" bila kampus memang memakai BKD sebagai instrumen internal
    | untuk seluruh dosen. Itu keputusan kampus, bukan bawaan yang aman.
    |
    */

    'wajib' => env('BKD_WAJIB', 'serdos'), // serdos | semua

    /*
    |--------------------------------------------------------------------------
    | Batas Beban
    |--------------------------------------------------------------------------
    |
    | Rentang baku 12–16 SKS per semester. Disimpan dalam perseratus SKS, sama
    | seperti uang disimpan sebagai integer: angka ini dibandingkan dengan
    | ambang yang menentukan dibayar atau tidaknya tunjangan, dan selisih 0,01
    | di sekitar 12,00 adalah beda antara memenuhi dan tidak.
    |
    | Dosen dengan tugas tambahan (rektor, dekan, kaprodi) memiliki aturan
    | tersendiri di regulasi. Open Academic tidak menerapkannya otomatis —
    | asesorlah yang memutuskan, dan lembar penilaian menyediakan tempat untuk
    | menuliskan alasannya.
    |
    */

    'batas' => [
        'minimum_ratus' => (int) env('BKD_MIN_SKS_RATUS', 1200),   // 12,00 SKS
        'maksimum_ratus' => (int) env('BKD_MAKS_SKS_RATUS', 1600), // 16,00 SKS

        // Batas per unsur. Nol berarti tidak ada syarat minimum untuk unsur itu.
        'minimum_pendidikan_ratus' => (int) env('BKD_MIN_PENDIDIKAN_RATUS', 900),
        'minimum_penelitian_ratus' => (int) env('BKD_MIN_PENELITIAN_RATUS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rubrik Ekuivalensi SKS
    |--------------------------------------------------------------------------
    |
    | **Ini kebijakan, bukan fakta.** Angka-angka di bawah adalah tafsir kampus
    | atas pedoman yang berubah tiap beberapa tahun, dan berbeda antar perguruan
    | tinggi untuk pedoman yang sama.
    |
    | Karena itu ia tinggal di sini, bukan di dalam service — sikap yang sama
    | dengan IkuDataController yang menolak menerapkan ambang dan hanya
    | menyerahkan cacahannya. Yang dijamin Open Academic adalah cacahnya benar:
    | berapa kelas, berapa SKS, berapa mahasiswa dibimbing. Berapa nilainya
    | dalam SKS BKD adalah keputusan kampus, dan diletakkan di tempat yang dapat
    | diubah tanpa menyentuh kode.
    |
    | Semua dalam perseratus SKS.
    |
    */

    'bobot' => [

        /*
         * Mengajar.
         *
         * Bila porsi_sks pada pivot kelas_dosen terisi, itulah yang dipakai —
         * kampus sudah menyatakan pembagiannya secara eksplisit. Nilai di bawah
         * hanya cadangan untuk kelas yang porsinya tidak pernah diisi.
         *
         * "bagi_rata" membagi SKS kelas kepada seluruh pengampu. Alternatifnya,
         * memberi SKS penuh kepada masing-masing, membuat kelas yang diampu
         * berdua terhitung dua kali di tingkat kampus.
         */
        'mengajar' => [
            'bagi_rata' => (bool) env('BKD_MENGAJAR_BAGI_RATA', true),

            // Praktisi dari industri biasanya mengambil beberapa pertemuan saja.
            'porsi_praktisi_ratus' => (int) env('BKD_PORSI_PRAKTISI_RATUS', 100),
        ],

        /*
         * Membimbing tugas akhir, per mahasiswa.
         *
         * Pembimbing utama dan pendamping dibedakan karena bebannya berbeda dan
         * setiap rubrik membedakannya.
         */
        'bimbingan_ta' => [
            'utama_ratus' => (int) env('BKD_BIMBINGAN_UTAMA_RATUS', 100),
            'pendamping_ratus' => (int) env('BKD_BIMBINGAN_PENDAMPING_RATUS', 50),

            // Batas mahasiswa yang dihitung. Membimbing dua puluh mahasiswa itu
            // nyata, tetapi mengakuinya penuh menghasilkan BKD yang lulus tanpa
            // penelitian sama sekali.
            'maks_mahasiswa' => (int) env('BKD_BIMBINGAN_MAKS', 8),
        ],

        // Menguji sidang, per mahasiswa.
        'menguji_ratus' => (int) env('BKD_MENGUJI_RATUS', 25),
        'menguji_maks_mahasiswa' => (int) env('BKD_MENGUJI_MAKS', 12),

        /*
         * Perwalian.
         *
         * Dihitung per rombongan, bukan per mahasiswa: menjadi dosen wali dua
         * belas mahasiswa dan dua puluh empat mahasiswa adalah pekerjaan yang
         * sama bentuknya. Nol berarti kampus tidak mengakuinya sebagai beban.
         */
        'perwalian' => [
            'per_rombongan_ratus' => (int) env('BKD_PERWALIAN_RATUS', 100),
            'mahasiswa_per_rombongan' => (int) env('BKD_PERWALIAN_PER_ROMBONGAN', 12),
        ],

        /*
         * Pengali peran dan tingkat untuk kegiatan yang dilaporkan sendiri.
         *
         * Dipakai hanya bila baris penugasan tidak membawa sks_ekuivalen. Bila
         * membawanya, angka itu yang dipakai — seseorang sudah memutuskan, dan
         * menimpanya dengan hitungan otomatis akan menghapus keputusan itu.
         */
        'peran' => [
            'ketua' => (float) env('BKD_PENGALI_KETUA', 1.0),
            'anggota' => (float) env('BKD_PENGALI_ANGGOTA', 0.6),
        ],

        'tingkat' => [
            'lokal' => (float) env('BKD_PENGALI_LOKAL', 1.0),
            'nasional' => (float) env('BKD_PENGALI_NASIONAL', 1.5),
            'internasional' => (float) env('BKD_PENGALI_INTERNASIONAL', 2.0),
        ],

        // Dasar per unsur untuk kegiatan tanpa sks_ekuivalen, sebelum pengali.
        'dasar' => [
            'penelitian_ratus' => (int) env('BKD_DASAR_PENELITIAN_RATUS', 200),
            'pengabdian_ratus' => (int) env('BKD_DASAR_PENGABDIAN_RATUS', 100),
            'penunjang_ratus' => (int) env('BKD_DASAR_PENUNJANG_RATUS', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ekspor
    |--------------------------------------------------------------------------
    |
    | Selama kredensial SISTER belum ada, ekspor adalah cara modul ini berguna.
    | Formatnya sengaja apa adanya — CSV dan JSON datar — supaya dapat ditempel
    | ke borang mana pun dan dibaca skrip integrasi nanti tanpa perlu memahami
    | model internal Open Academic.
    |
    */

    'ekspor' => [
        'pemisah_csv' => env('BKD_CSV_PEMISAH', ','),

        // Excel di Windows berbahasa Indonesia membuka CSV UTF-8 tanpa BOM
        // sebagai mojibake. BOM-nya jelek, tetapi alternatifnya adalah setiap
        // nama bergelar tampil rusak di layar orang yang harus memeriksanya.
        'bom_utf8' => (bool) env('BKD_CSV_BOM', true),
    ],

];
