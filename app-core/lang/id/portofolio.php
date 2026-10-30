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

    'verifikasi' => [
        'bukti' => 'Bukti',
        'unduh_bukti' => 'Buka bukti',
        'tanpa_bukti' => 'Tanpa bukti',
        'iku_2026' => 'IKU 2026',
    ],
];
