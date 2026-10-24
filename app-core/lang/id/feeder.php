<?php

declare(strict_types=1);

return [

    'perubahan' => [
        'judul' => 'Perubahan Data Mahasiswa & Jenis Keluar',
        'meta' => ':terbuka harus diajukan · :selesai selesai',
        'penjelasan' => 'Enam kolom identitas dan tujuh kolom keluar dikunci PDDIKTI setelah tersinkron. '
            .'Perubahan di bawah ini tercatat saat terjadi; biodata mahasiswanya tidak dikirim ulang sampai permintaannya selesai.',
        'kosong' => [
            'judul' => 'Tidak ada kolom terkunci yang berubah',
            'deskripsi' => 'Begitu NIM, nama, nama ibu, tempat atau tanggal lahir, periode masuk, atau data keluar seorang mahasiswa yang sudah tersinkron diubah, permintaannya muncul di sini.',
        ],
        'kolom' => [
            'mahasiswa' => 'Mahasiswa',
            'jenis' => 'Jenis',
            'kolom' => 'Kolom',
            'perubahan' => 'Perubahan',
            'jalur' => 'Jalur',
            'status' => 'Status',
        ],
        'jalur' => [
            'web_service' => 'Dapat dikirim lewat web service',
            'pddikti_admin' => 'Harus diajukan lewat PDDIKTI admin',
        ],
        'tombol' => [
            'ajukan' => 'Tandai diajukan',
            'kirim' => 'Kirim lewat web service',
            'selesai' => 'Tandai selesai',
        ],
        'nomor_surat' => 'Nomor surat permohonan',
        'kosong_nilai' => '(kosong)',
        'surat' => 'Surat :nomor',
        'gagal' => 'Ditolak Feeder: :sebab',
        'pesan' => [
            'diajukan' => 'Perubahan :kolom untuk :nim ditandai sudah diajukan ke PDDIKTI admin.',
            'selesai' => 'Perubahan :kolom untuk :nim ditandai selesai; biodata mahasiswa dapat disinkronkan kembali.',
            'terkirim' => 'Perubahan :kolom untuk :nim terkirim lewat UpdateRiwayatPendidikanMahasiswa.',
            'ditolak' => 'Perubahan :kolom ditolak Feeder: :sebab. Ajukan lewat PDDIKTI admin.',
        ],
    ],

];
