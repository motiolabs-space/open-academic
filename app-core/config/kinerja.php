<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rencana Kinerja
|--------------------------------------------------------------------------
|
| Lapisan perencanaan di atas struktur organisasi: sasaran per unit, bertingkat
| mengikuti pohon unit kerja, dengan target yang diketik dan realisasi yang
| sedapat mungkin dihitung.
|
| **Ini bukan dasbor IKU dan bukan SPMI.** Keduanya milik Open Campus — lihat
| ROADMAP §Sengaja Bukan di Sini dan docs/KINERJA.md. Yang ada di sini hanya
| indikator yang realisasinya dapat dihitung dari data yang aplikasi ini
| benar-benar miliki.
|
*/

return [

    /*
     * Katalog indikator yang realisasinya dihitung.
     *
     * Di config, bukan di basis data — berbeda dari katalog poin kemahasiswaan.
     * Alasannya: tiap baris di sini terikat pada sepotong kode yang tahu cara
     * menghitungnya. Baris yang dapat ditambahkan lewat layar akan menjadi
     * indikator tanpa penghitung, yaitu target yang tidak pernah dapat
     * terealisasi dan tidak ada yang menyadarinya sampai tinjauan.
     *
     * `lingkup` menentukan apakah angkanya dapat dipersempit ke prodi:
     *   prodi  — dapat dihitung per prodi, jadi unit akademik mendapat angkanya
     *            sendiri dan unit induk menjumlahkan turunannya
     *   kampus — hanya bermakna untuk seluruh kampus
     */
    'indikator' => [

        'mahasiswa_aktif' => [
            'label' => 'Mahasiswa aktif',
            'satuan' => 'orang',
            'lingkup' => 'prodi',
        ],

        'lulusan' => [
            'label' => 'Lulusan ditetapkan',
            'satuan' => 'orang',
            'lingkup' => 'prodi',
        ],

        'mbkm_peserta' => [
            'label' => 'Mahasiswa berkegiatan di luar kampus',
            'satuan' => 'orang',
            'lingkup' => 'prodi',
        ],

        'praktisi_mengajar' => [
            'label' => 'Kelas diampu praktisi',
            'satuan' => 'kelas',
            'lingkup' => 'prodi',
        ],

        'kelas_kolaboratif' => [
            'label' => 'Kelas kolaboratif & partisipatif',
            'satuan' => 'kelas',
            'lingkup' => 'prodi',
        ],

        'dosen_luar_kampus' => [
            'label' => 'Dosen berkegiatan di luar kampus',
            'satuan' => 'orang',
            'lingkup' => 'kampus',
        ],

        'rerata_ipk' => [
            'label' => 'Rerata IPK semester berjalan',
            'satuan' => 'IPK',
            'lingkup' => 'prodi',
            'desimal' => 2,
        ],

        'keterlaksanaan_jurnal' => [
            'label' => 'Pertemuan berjurnal',
            'satuan' => '%',
            'lingkup' => 'prodi',
            'desimal' => 1,
        ],
    ],

    /*
     * Ambang warna capaian, diperiksa dari yang tertinggi.
     *
     * Kebijakan, bukan fakta — dan sengaja tidak dipakai untuk menilai orang.
     * Angka di bawah target adalah angka di bawah target; apakah itu kegagalan
     * seseorang adalah keputusan manusia dengan alasan tertulis.
     */
    'ambang_capaian' => [
        ['persen' => 100, 'sebutan' => 'Tercapai', 'tone' => 'success'],
        ['persen' => 80, 'sebutan' => 'Mendekati', 'tone' => 'warning'],
        ['persen' => 0, 'sebutan' => 'Jauh dari target', 'tone' => 'danger'],
    ],
];
