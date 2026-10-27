<?php

declare(strict_types=1);

return [

    'komponen' => [
        'ukt' => 'UKT',
        'spp' => 'SPP',
        'praktikum' => 'Praktikum',
        'registrasi' => 'Registrasi',
        'wisuda' => 'Wisuda',
        'lainnya' => 'Lainnya',
        'per_sks' => 'Per SKS',
        'mata_kuliah' => 'Per Mata Kuliah',
    ],

    'tarif' => [
        'mata_kuliah' => 'Mata Kuliah',
        'mata_kuliah_bantuan' => 'Wajib untuk komponen Per Mata Kuliah; kosongkan untuk komponen lain.',
        'mata_kuliah_wajib' => 'Komponen Per Mata Kuliah harus menyebut mata kuliah yang ditagih.',
        'mata_kuliah_dilarang' => 'Mata kuliah hanya diisi untuk komponen Per Mata Kuliah.',
        'nominal_per_sks' => 'Untuk komponen Per SKS, nominal adalah tarif untuk satu SKS.',
        'per_sks_satuan' => 'per SKS',
        'dari_krs' => 'Komponen Per SKS dan Per Mata Kuliah tidak ikut penerbitan massal. Keduanya ditagih ke tagihan semester yang sama ketika Dosen Wali menyetujui KRS, dan disesuaikan ketika KRS yang direvisi disetujui kembali.',
        'simulasi_dari_krs' => 'Ditagih dari KRS yang disetujui — tidak termasuk total di bawah.',
        'total_tetap' => 'Total komponen tetap',
    ],

    'krs' => [
        'baris_per_sks' => 'Biaya per SKS — :mk (:sks SKS × :tarif)',
        'alasan_dikurangi' => 'Kelas dibatalkan dari KRS pada :tanggal, sebelum batas pengurangan tagihan.',
        'alasan_tidak_dikurangi' => 'Kelas dibatalkan dari KRS pada :tanggal, sesudah batas pengurangan tagihan; tagihan tidak berkurang.',
        'koreksi' => 'koreksi',

        'notifikasi_judul' => 'Tagihan Anda disesuaikan dengan KRS',
        'notifikasi_ringkasan' => 'Tagihan :nomor kini :total (:selisih) mengikuti KRS yang disetujui, jatuh tempo :tanggal.',
        'notifikasi_ajakan' => 'Lihat Tagihan',
    ],

    'denda' => [
        'nama_baris' => 'Denda keterlambatan',
        'nama_pembebasan' => 'Pembebasan denda keterlambatan',

        'aturan' => 'Pembayaran yang melewati jatuh tempo dikenai denda :tarif.',
        'tarif_nominal' => ':nominal per :periode keterlambatan',
        'tarif_persen' => ':persen% dari sisa tagihan per :periode keterlambatan',
        'tenggang' => 'Masa tenggang :hari hari sesudah jatuh tempo masih bebas denda.',
        'maksimum' => 'Denda dibatasi paling banyak :maksimum per tagihan.',

        'berjalan' => 'Denda yang telah berjalan pada tagihan ini: :denda.',
        'tidak_mengunci_krs' => 'Denda tidak memengaruhi syarat pembayaran untuk pengisian KRS; ambang dihitung dari tagihan pokok.',
        'dibebaskan' => 'Denda keterlambatan sebesar :denda dibebaskan oleh Bagian Keuangan pada :tanggal. Alasan: :alasan',

        'pengingat' => 'Perhatikan: :aturan',

        'berhasil_dibebaskan' => 'Denda :denda dibebaskan dan tagihan diperbarui.',
        'alasan_wajib' => 'Pembebasan denda wajib disertai alasan — penghapusan tanpa alasan tertulis tidak dapat dibedakan dari penyalahgunaan.',
        'alasan_singkat' => 'Alasan terlalu singkat untuk menjelaskan pembebasan.',

        'tombol_bebaskan' => 'Bebaskan Denda',
        'chip_dibebaskan' => 'denda dibebaskan',
        'termasuk' => 'termasuk denda :denda',

        'notifikasi_judul' => 'Denda keterlambatan Anda dibebaskan',
        'notifikasi_ringkasan' => 'Denda keterlambatan :denda pada tagihan :nomor dibebaskan oleh Bagian Keuangan. Alasan: :alasan Sisa yang harus dibayar :sisa.',
    ],

];
