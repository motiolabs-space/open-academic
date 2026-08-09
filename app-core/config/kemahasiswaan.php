<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Poin Kemahasiswaan
|--------------------------------------------------------------------------
|
| Dua buku besar yang **tidak pernah dijumlahkan satu sama lain**.
|
| Prestasi dan pelanggaran adalah dua catatan terpisah, dan menjumlahkannya
| berarti membiarkan mahasiswa menebus pelanggaran dengan prestasi. Tidak ada
| bagian kemahasiswaan yang bermaksud begitu — sanksi karena memalsukan tanda
| tangan tidak hilang karena yang bersangkutan juara lomba tahun berikutnya.
|
| Karena itu tidak ada satu pun angka "poin bersih" di modul ini, dan tidak ada
| metode yang mengembalikannya.
|
*/

return [

    'prestasi' => [

        /*
         * Minimum poin prestasi untuk dapat lulus (SKKM).
         *
         * Nol berarti kampus tidak memberlakukannya — dan bila nol, barisnya
         * **dihilangkan sama sekali** dari daftar syarat kelulusan, bukan
         * ditampilkan sebagai syarat yang otomatis terpenuhi. Pola yang sama
         * dengan tugas akhir pada prodi yang tidak mewajibkannya: persentase
         * kelulusan tetap jujur, dan tidak ada yang membaca baris hijau untuk
         * syarat yang tidak pernah ada.
         */
        'minimum_lulus' => (int) env('KEMAHASISWAAN_POIN_MINIMUM', 0),
    ],

    'pelanggaran' => [

        /*
         * Ambang akumulasi pelanggaran, diperiksa dari yang terberat.
         *
         * Menghasilkan **temuan**, bukan sanksi. Sanksi adalah keputusan orang,
         * dengan alasan tertulis — persis seperti evaluasi studi. Sistem boleh
         * mengamati bahwa seorang mahasiswa melewati 100 poin; keputusan apa
         * yang menyusul bukan miliknya.
         */
        'ambang' => [
            ['poin' => 100, 'sebutan' => 'Terancam sanksi berat'],
            ['poin' => 50, 'sebutan' => 'Perlu pembinaan'],
            ['poin' => 25, 'sebutan' => 'Peringatan'],
        ],
    ],

    /*
     * Tingkat yang boleh dipakai katalog poin.
     *
     * Daftarnya di sini supaya layar dan validasi memakai sumber yang sama;
     * nilai poinnya sendiri milik tiap baris katalog, karena kampus menetapkan
     * angkanya per jenis kegiatan, bukan per tingkat.
     */
    'tingkat' => [
        'internal' => 'Internal kampus',
        'lokal' => 'Lokal / wilayah',
        'nasional' => 'Nasional',
        'internasional' => 'Internasional',
        'ringan' => 'Ringan',
        'sedang' => 'Sedang',
        'berat' => 'Berat',
    ],
];
