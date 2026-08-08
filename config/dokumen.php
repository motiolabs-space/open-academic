<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dokumen cetak
|--------------------------------------------------------------------------
|
| Kop, penandatangan, dan catatan kaki untuk dokumen yang dicetak rutin.
|
| Ini **bukan** templat yang dapat disunting pengguna. Templat Blade yang
| tersimpan di basis data berarti mengeksekusi kode yang tersimpan di basis
| data, dan keluwesan yang didapat tidak sebanding dengan jalur RCE yang
| dibuka. Yang dapat diubah kampus adalah isi kop, siapa yang bertanda
| tangan, dan apa yang tercetak di kaki halaman — tata letaknya tetap milik
| kode.
|
| Nilai di sini adalah bawaan. Kampus menimpanya lewat layar Pengaturan,
| yang menulis ke tabel `settings` grup `dokumen`.
|
*/

return [

    /*
     * Baris kop di bawah nama institusi.
     *
     * Nama institusi, singkatan, dan logonya sudah dipegang BrandingService —
     * mengulangnya di sini akan membuat dua sumber yang bisa berselisih.
     */
    'kop' => [
        'alamat' => env('DOKUMEN_ALAMAT', ''),
        'kontak' => env('DOKUMEN_KONTAK', ''),
    ],

    /*
     * Jenis dokumen yang punya pengaturannya sendiri.
     *
     * `penandatangan` menentukan apakah blok tanda tangan dicetak sama sekali.
     * Absensi dan jurnal ditandatangani di kertas oleh dosen yang hadir, jadi
     * nama tercetak justru salah di sana: yang dibutuhkan ruang kosong, bukan
     * pejabat yang tidak ada di ruangan itu.
     */
    'jenis' => [

        'ktm' => [
            'label' => 'Kartu Tanda Mahasiswa',
            'penandatangan' => true,
            'jabatan_bawaan' => 'Kepala Biro Administrasi Akademik',
            'catatan_kaki' => 'Kartu ini milik institusi dan wajib dikembalikan '
                .'apabila mahasiswa berhenti atau lulus.',
        ],

        'kartu_ujian' => [
            'label' => 'Kartu Ujian',
            'penandatangan' => true,
            'jabatan_bawaan' => 'Kepala Biro Administrasi Akademik',
            'catatan_kaki' => 'Wajib dibawa dan diperlihatkan kepada pengawas pada setiap ujian.',
        ],

        'absensi' => [
            'label' => 'Daftar Hadir Kuliah',
            'penandatangan' => false,
            'jabatan_bawaan' => '',
            'catatan_kaki' => 'Lembar ini ditandatangani dosen pengampu pada setiap pertemuan.',
        ],

        'jurnal' => [
            'label' => 'Jurnal Perkuliahan',
            'penandatangan' => false,
            'jabatan_bawaan' => '',
            'catatan_kaki' => 'Diisi dosen pengampu setiap pertemuan dan diverifikasi program studi.',
        ],
    ],

    /*
     * Berapa lama kartu ujian dianggap berlaku setelah dicetak, untuk teks
     * "berlaku sampai". Nol berarti mengikuti tanggal berakhirnya semester.
     */
    'kartu_ujian' => [

        /*
         * Menahan kartu ujian bagi mahasiswa yang tagihannya belum lunas.
         *
         * Kebijakan, bukan fakta — sebagian kampus menahan, sebagian tidak, dan
         * sebagian menahan hanya untuk UAS. Karena itu ia dapat dimatikan, dan
         * alasan penahanannya selalu disebutkan kepada mahasiswanya alih-alih
         * kartunya sekadar tidak muncul.
         */
        'tahan_bila_menunggak' => env('KARTU_UJIAN_TAHAN_MENUNGGAK', true),

        /*
         * Menahan kartu ujian bagi mahasiswa yang kehadirannya di bawah ambang.
         *
         * Ambangnya sendiri milik config/academic.php, karena angka yang sama
         * dipakai untuk memutuskan kelayakan ujian di tempat lain — dua salinan
         * akan berselisih pada mahasiswa yang persis di batas.
         */
        'tahan_bila_kehadiran_kurang' => env('KARTU_UJIAN_TAHAN_KEHADIRAN', false),
    ],
];
