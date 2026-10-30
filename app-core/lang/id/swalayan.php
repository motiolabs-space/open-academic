<?php

declare(strict_types=1);

/*
 * Layanan swalayan mahasiswa (ADR-040): ajuan cuti, ajuan beasiswa, lapor
 * prestasi, dan status konversi kredit. Mahasiswa mengajukan; staf memutuskan.
 */
return [

    'diajukan_pada' => 'Diajukan :tanggal',
    'unduh_berkas' => 'Unduh berkas',
    'berkas_petunjuk' => 'PDF atau gambar (JPG/PNG), maksimal :mb MB. Disimpan di penyimpanan privat.',

    'kolom' => [
        'semester' => 'Semester',
        'jenis' => 'Jenis',
        'jenis_alasan' => 'Jenis & Alasan',
        'alasan' => 'Alasan',
        'status' => 'Status',
    ],

    'galat' => [
        'berkas_jenis' => 'Berkas harus PDF atau gambar (JPG/PNG).',
    ],

    'cuti' => [
        'judul' => 'Cuti Akademik',
        'riwayat' => 'Riwayat Pengajuan Cuti',
        'ajukan' => 'Ajukan Cuti',
        'dokumen' => 'Dokumen Pendukung',
        'tombol' => 'Kirim Pengajuan',
        'sukses' => 'Pengajuan cuti untuk :semester terkirim dan menunggu keputusan BAAK.',
        'kosong' => 'Belum ada pengajuan cuti',
        'kosong_penjelasan' => 'Pengajuan yang Anda kirim akan tampil di sini beserta keputusannya.',
        'bukan_aktif' => 'Cuti hanya dapat diajukan oleh mahasiswa berstatus Aktif. Status Anda saat ini: :status.',
        'masih_menunggu' => 'Masih ada pengajuan cuti yang menunggu keputusan BAAK. Pengajuan berikutnya dapat dikirim setelah pengajuan itu diputus.',
        'tidak_ada_semester' => 'Belum ada semester yang dapat diajukan cuti. Semester berikutnya muncul di sini setelah dibuat oleh bagian akademik.',
        'aturan' => 'Cuti dapat diajukan untuk semester berjalan atau semester reguler berikutnya, satu kali per semester, dan tidak dapat diajukan bila Anda masih memegang KRS yang diajukan atau disetujui pada semester itu. Status Anda baru berubah menjadi Cuti setelah BAAK menyetujui.',
        'belum_berjalan' => 'Semester ini belum aktif. Menyetujui sekarang langsung mengubah status mahasiswa menjadi Cuti.',

        'jenis' => [
            'akademik' => 'Cuti Akademik',
            'sakit' => 'Sakit',
            'lainnya' => 'Lainnya',
        ],

        'galat' => [
            'semester_tidak_dibuka' => 'Cuti hanya dapat diajukan dari portal untuk semester berjalan atau semester reguler berikutnya.',
            'alasan_wajib' => 'Tuliskan alasan pengajuan cuti.',
            'alasan_singkat' => 'Alasan cuti terlalu singkat; jelaskan sedikitnya dalam satu kalimat.',
        ],
    ],
];
