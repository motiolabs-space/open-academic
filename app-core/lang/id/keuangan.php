<?php

declare(strict_types=1);

return [

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
