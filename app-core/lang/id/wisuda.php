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
        'pisn' => 'Kesiapan data Penomoran Ijazah dan Sertifikat Profesi Nasional (PISN)',
        'pisn_siap' => 'Siap',
        'pisn_belum' => ':jumlah butir masih diurus kampus',
        'wajib' => 'Wajib',
        'informasi' => 'Informasi',
        'informasi_pisn' => 'Butir PISN diperbaiki oleh kampus di Feeder, bukan oleh Anda, sehingga tidak menghalangi pendaftaran. Tanyakan ke BAAK bila belum siap menjelang wisuda.',
    ],

    // National diploma numbering (PISN; ADR-036 §2).
    'pisn' => [
        'kolom_siap' => 'Siap PISN',
        'alias_usang' => 'Setelan ACADEMIC_IJAZAH_SUMBER=pin masih dibaca sebagai pisn, tetapi nilai itu usang dan akan berhenti diterima pada rilis mayor berikutnya. Ganti menjadi ACADEMIC_IJAZAH_SUMBER=pisn di berkas .env.',
        'belum_terbit' => 'belum terbit',
        'belum_dicatat' => 'belum dicatat dari PISN',
        'judul_catat' => 'Catat Nomor Ijazah dari PISN',
        'label_isian' => 'Nomor terbitan PISN',
        'placeholder' => "NIM, nomor ijazah — satu lulusan per baris\n2201001, 001001552012026100001\n2201002; 001001552012026100002",
        'penjelasan' => 'Nomor ijazah tidak dibangkitkan di sini. PISN menerbitkannya setelah data lulusan di PDDIKTI lengkap dan lolos validator — status Lulus, tanggal lulus, SKS, IPK, masa studi — lalu nomor itulah yang dicetak dan dapat diverifikasi publik di laman PISN. Nomor yang sudah tercatat tidak pernah ditimpa. Periksa kolom Siap PISN di layar Yudisium sebelum membuka PISN.',
        'tidak_dibangkitkan' => 'Nomor ijazah kampus ini berasal dari Penomoran Ijazah dan Sertifikat Profesi Nasional (PISN) dan tidak dibangkitkan di sini. Luluskan mahasiswa di PDDIKTI, terbitkan nomornya di PISN, lalu catat hasilnya pada periode wisuda. Ubah ACADEMIC_IJAZAH_SUMBER=lokal hanya bila institusi berada di luar skema nasional.',
        'format_digit' => 'nomor :nomor bukan nomor PISN — nomor PISN terdiri dari 21 digit angka.',
        'format_pt' => 'kode perguruan tinggi pada nomor (:kode) bukan kode kampus ini (:pt).',
        'format_prodi' => 'kode program studi pada nomor (:kode) bukan prodi lulusan ini (:prodi).',
        'format_tahun' => 'tahun terbit pada nomor (:tahun) tidak masuk akal.',
        'format_tipe_ijazah' => 'digit tipe pada nomor menandai sertifikat profesi, padahal prodi ini menerbitkan ijazah (tipe 1).',
        'format_tipe_sertifikat' => 'digit tipe pada nomor menandai ijazah, padahal prodi profesi/spesialis menerbitkan sertifikat profesi (tipe 2).',
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
