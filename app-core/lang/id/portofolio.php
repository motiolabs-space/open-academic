<?php

declare(strict_types=1);

/*
 * Lecturer activities on the portfolio and the IKU verification screen:
 * policy drafting (IKU 8) and publication indexing (IKU 6), ADR-041.
 */
return [
    'kebijakan' => [
        'jenis' => 'Penyusunan Kebijakan',
        'judul_bagian' => 'Keterlibatan dalam penyusunan kebijakan',
        'petunjuk' => 'Diakui bila Anda ditugaskan atau diminta secara resmi — anggota tim penyusun, narasumber atau ahli yang memberi masukan tertulis, atau kontributor yang kajiannya dimuat dalam dokumen kebijakan — dan dibuktikan dengan dokumen resmi yang mencantumkan nama Anda.',

        'field' => [
            'bentuk' => 'Bentuk keterlibatan',
            'tingkat' => 'Tingkat kebijakan',
            'lembaga' => 'Lembaga pemberi tugas',
            'lembaga_hint' => 'Kementerian, pemerintah daerah, asosiasi industri, atau badan standar yang menerbitkan SK atau undangan.',
            'bukti_jenis' => 'Jenis bukti',
            'nomor_bukti' => 'Nomor SK / undangan',
        ],

        'bentuk' => [
            'tim_penyusun' => 'Anggota tim penyusun',
            'narasumber' => 'Narasumber / ahli resmi',
            'kontributor_kajian' => 'Kontributor kajian',
        ],

        'tingkat' => [
            'internasional' => 'Internasional',
            'nasional' => 'Nasional',
            'daerah' => 'Daerah',
            'industri' => 'Industri / sektor',
        ],

        'bukti' => [
            'sk_penugasan' => 'SK penugasan',
            'undangan_resmi' => 'Undangan resmi',
            'laporan_fgd' => 'Laporan FGD',
            'notulen' => 'Notulen',
            'dokumen_kebijakan' => 'Dokumen kebijakan yang mencantumkan nama',
        ],

        'wajib' => [
            'lembaga' => 'Lembaga pemberi tugas wajib diisi untuk keterlibatan dalam penyusunan kebijakan.',
            'bentuk' => 'Pilih bentuk keterlibatan dalam penyusunan kebijakan.',
            'tingkat' => 'Pilih tingkat kebijakan.',
            'bukti_jenis' => 'Pilih jenis dokumen resmi yang menjadi bukti.',
            'dokumen' => 'Keterlibatan dalam penyusunan kebijakan hanya diakui dengan dokumen resmi — unggah SK, undangan, notulen, atau dokumen kebijakannya.',
        ],
    ],

    'publikasi' => [
        'judul_bagian' => 'Indeks publikasi',
        'petunjuk' => 'Isi sesuai halaman pengindeks pada tahun terbit. Data ini Anda nyatakan dan diperiksa staf sebelum dihitung; sistem tidak mencarinya sendiri.',

        'field' => [
            'indeks' => 'Terindeks di',
            'kuartil' => 'Kuartil jurnal',
            'kuartil_hint' => 'Hanya untuk artikel jurnal terindeks Scopus atau Web of Science.',
            'doi' => 'DOI',
            'doi_hint' => 'Contoh: 10.1234/abcd.2026.01 — dipakai untuk mengenali artikel yang sama dari beberapa penulis.',
            'tautan' => 'Tautan artikel',
            'penerbit' => 'Penerbit',
            'kolaborasi' => 'Kolaborasi internasional',
            'kolaborasi_hint' => 'Ada penulis dari perguruan tinggi atau lembaga luar negeri',
        ],

        'indeks' => [
            'scopus' => 'Scopus',
            'wos' => 'Web of Science',
            'sinta_1' => 'SINTA 1',
            'sinta_2' => 'SINTA 2',
            'sinta_3' => 'SINTA 3',
            'sinta_4' => 'SINTA 4',
            'sinta_5' => 'SINTA 5',
            'sinta_6' => 'SINTA 6',
            'lainnya' => 'Pengindeks lain',
        ],

        'kolaborasi_singkat' => 'kolaborasi internasional',

        'validasi' => [
            'kuartil' => 'Kuartil hanya diisi untuk artikel jurnal yang terindeks Scopus atau Web of Science.',
            'doi' => 'DOI harus berbentuk 10.xxxx/… (boleh diawali https://doi.org/).',
        ],
    ],

    'verifikasi' => [
        'bukti' => 'Bukti',
        'unduh_bukti' => 'Buka bukti',
        'tanpa_bukti' => 'Tanpa bukti',
        'iku_2026' => 'IKU 2026',
    ],
];
