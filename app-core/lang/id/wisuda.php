<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Wisuda — teks antarmuka pendaftaran wisuda
|--------------------------------------------------------------------------
|
| Dipakai layar mahasiswa, antrean staf, notifikasi, dan pesan penolakan
| WisudaService. Daftar periksanya satu (SyaratWisudaService), jadi labelnya
| juga satu: mahasiswa dan staf membaca kalimat yang sama untuk syarat yang sama.
|
*/

return [

    'judul' => 'Pendaftaran Wisuda',

    'syarat' => [
        'judul' => 'Daftar Periksa Syarat',
        'yudisium' => 'Kelulusan (yudisium) ditetapkan',
        'yudisium_belum_ada' => 'Belum ada yudisium',
        'yudisium_diajukan' => 'Yudisium diajukan, belum ditetapkan',
        'yudisium_ditetapkan' => 'Ditetapkan :tanggal',
        'keuangan' => 'Bebas tanggungan keuangan',
        'keuangan_sisa' => 'Sisa :sisa, termasuk denda yang belum dibebaskan',
        'keuangan_lunas' => 'Tidak ada tunggakan',
        'pin' => 'Kesiapan data Penomoran Ijazah Nasional (PIN)',
        'pin_siap' => 'Siap',
        'pin_belum' => ':jumlah butir masih diurus kampus',
        'wajib' => 'Wajib',
        'informasi' => 'Informasi',
        'informasi_pin' => 'Butir PIN diperbaiki oleh kampus di Feeder, bukan oleh Anda, sehingga tidak menghalangi pendaftaran. Tanyakan ke BAAK bila belum siap menjelang wisuda.',
    ],

    'layar' => [
        'konteks_terdaftar' => 'Terdaftar pada :periode',
        'konteks_belum' => 'Belum terdaftar',
        'periode_judul' => 'Periode yang Dibuka',
        'tanpa_periode' => 'Belum ada periode wisuda yang dibuka',
        'tanpa_periode_keterangan' => 'Periode baru diumumkan oleh BAAK. Halaman ini menampilkannya begitu pendaftaran dibuka.',
        'kuota' => 'Kuota :sisa dari :kuota kursi',
        'tanpa_kuota' => 'Tanpa batas kuota',
        'daftar' => 'Daftar Wisuda',
        'batal' => 'Batalkan Pendaftaran',
        'batal_konfirmasi' => 'Batalkan pendaftaran Anda pada :periode?',
        'terdaftar' => 'Anda terdaftar sebagai peserta nomor urut :urut.',
        'terdaftar_lain' => 'Anda sudah terdaftar pada :periode.',
        'belum_memenuhi' => 'Tombol daftar aktif setelah seluruh syarat wajib terpenuhi.',
        'batal_ditutup' => 'Pendaftaran periode ini sudah ditutup. Hubungi BAAK bila Anda berhalangan hadir.',
        'ijazah_terkunci' => 'Nomor ijazah Anda sudah tercatat, sehingga pendaftaran tidak dapat dibatalkan sendiri.',
        'sukses_daftar' => 'Anda terdaftar pada :periode dengan nomor urut :urut.',
        'sukses_batal' => 'Pendaftaran Anda pada :periode dibatalkan.',
    ],

    'staf' => [
        'syarat_kolom' => 'Syarat',
        'syarat_lengkap' => 'Lengkap',
        'syarat_kurang' => ':jumlah belum',
    ],

    'galat' => [
        'tanpa_yudisium' => 'Anda belum memiliki yudisium, sehingga belum dapat mendaftar wisuda.',
        'syarat' => ':nama belum memenuhi syarat wisuda: :syarat.',
        'tidak_terdaftar' => 'Anda tidak terdaftar pada :periode.',
        'batal_ditutup' => 'Pendaftaran :periode sudah ditutup; pembatalan dilakukan lewat BAAK.',
    ],

    'notifikasi' => [
        'terdaftar_judul' => 'Anda terdaftar sebagai peserta wisuda',
        'terdaftar_ringkasan' => ':periode, :tanggal di :lokasi. Nomor urut Anda :urut.',
        'batal_judul' => 'Pendaftaran wisuda Anda dibatalkan',
        'batal_ringkasan' => 'Nama Anda tidak lagi tercantum pada daftar peserta :periode. Hubungi BAAK bila ini tidak Anda kehendaki.',
        'ajakan' => 'Lihat Pendaftaran Wisuda',
    ],

];
