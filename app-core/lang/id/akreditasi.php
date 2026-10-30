<?php

declare(strict_types=1);

/*
 * A programme's accreditation history (ADR-041 §3): master data, the staff
 * dashboard warning, the PISN checklist, and the LKPS identity block.
 */
return [
    'judul' => 'Riwayat Akreditasi',
    'konteks' => ':prodi · :jumlah SK tercatat',
    'pengantar' => 'Setiap SK akreditasi dicatat sebagai baris baru; SK yang lebih baru tidak menimpa yang lama. Validator PISN memeriksa akreditasi yang berlaku pada tanggal lulus, jadi riwayat inilah yang dibaca daftar periksa nomor ijazah.',
    'kosong' => 'Belum ada SK akreditasi tercatat untuk program studi ini.',
    'catat' => 'Catat SK Akreditasi',
    'simpan' => 'Simpan SK',
    'tarik' => 'Tarik',
    'tarik_konfirmasi' => 'Tarik SK :nomor? Baris ini tetap tercatat di Log Aktivitas.',
    'riwayat' => 'Riwayat',
    'unduh_sk' => 'Unduh SK',
    'dicatat_oleh' => 'dicatat :nama',
    'lama_tanpa_riwayat' => 'Peringkat ":peringkat" tercatat tanpa SK dan masa berlaku. Catat SK-nya di bawah agar validator PISN dapat memeriksanya.',

    'field' => [
        'lembaga' => 'Lembaga',
        'peringkat' => 'Peringkat',
        'nomor_sk' => 'Nomor SK',
        'tanggal_sk' => 'Tanggal SK',
        'tanggal_berlaku' => 'Berlaku sejak',
        'tanggal_berlaku_hint' => 'Kosongkan bila sama dengan tanggal SK.',
        'tanggal_kedaluwarsa' => 'Berlaku sampai',
        'keterangan' => 'Keterangan',
        'dokumen' => 'Berkas SK',
        'masa_berlaku' => 'Masa berlaku',
        'status' => 'Akreditasi',
    ],

    'lembaga' => [
        'ban_pt' => 'BAN-PT',
        'lam_ptkes' => 'LAM-PTKes',
        'lamdik' => 'LAMDIK',
        'lamemba' => 'LAMEMBA',
        'lamsama' => 'LAMSAMA',
        'laminfokom' => 'LAMINFOKOM',
        'lam_teknik' => 'LAM Teknik',
        'lamspak' => 'LAMSPAK',
        'internasional' => 'Lembaga internasional',
    ],

    'peringkat' => [
        'unggul' => 'Unggul',
        'baik_sekali' => 'Baik Sekali',
        'baik' => 'Baik',
        'terakreditasi' => 'Terakreditasi',
        'terakreditasi_sementara' => 'Terakreditasi Sementara',
        'a' => 'A',
        'b' => 'B',
        'c' => 'C',
        'tidak_terakreditasi' => 'Tidak Terakreditasi',
    ],

    'keadaan' => [
        'berlaku' => 'Berlaku',
        'segera_kedaluwarsa' => 'Segera kedaluwarsa',
        'kedaluwarsa' => 'Kedaluwarsa',
        'tidak_terakreditasi' => 'Tidak terakreditasi',
        'belum_dicatat' => 'Belum dicatat',
    ],

    'peringatan' => [
        'judul' => 'Akreditasi program studi perlu perhatian',
        'pengantar' => 'Lulusan dari program studi yang akreditasinya tidak berlaku pada tanggal lulus tidak akan mendapat nomor ijazah PISN, dan tidak ada jalur eksepsi untuk itu. Peringatan muncul :bulan bulan sebelum kedaluwarsa.',
        'baris' => ':prodi — :keadaan',
        'sampai' => 'berlaku sampai :tanggal',
        'kelola' => 'Kelola akreditasi',
    ],

    'pisn' => [
        'label' => 'Program studi terakreditasi pada tanggal lulus',
        'belum_dicatat' => 'akreditasi prodi belum dicatat',
        'tanpa_sk' => 'peringkat :peringkat tercatat tanpa SK dan masa berlaku — catat SK di master prodi',
        'berlaku' => ':peringkat (:lembaga), berlaku sampai :sampai',
        'tidak_terakreditasi' => 'SK :nomor menyatakan tidak terakreditasi pada :tanggal',
        'kedaluwarsa' => 'kedaluwarsa :sampai, sebelum tanggal lulus :tanggal',
        'belum_berlaku' => 'tidak ada SK yang berlaku pada :tanggal',
        'acuan_lulus' => 'tanggal lulus',
        'acuan_hari_ini' => 'hari ini',
    ],

    'lkps' => [
        'label' => 'Akreditasi',
        'tidak_ada' => 'Tidak ada SK akreditasi yang berlaku hari ini — catat di master prodi.',
    ],

    'ringkasan' => ':peringkat — :lembaga, SK :nomor, berlaku :mulai s.d. :sampai',

    'pesan' => [
        'dicatat' => 'SK akreditasi :nomor tercatat.',
        'ditarik' => 'SK akreditasi :nomor ditarik.',
        'sk_ganda' => 'SK :nomor sudah tercatat untuk program studi ini.',
    ],

    'validasi' => [
        'kedaluwarsa_sesudah' => 'Tanggal berlaku sampai harus sesudah tanggal SK dan tanggal mulai berlaku.',
        'berlaku_sesudah_sk' => 'Tanggal mulai berlaku tidak boleh sebelum tanggal SK.',
    ],
];
