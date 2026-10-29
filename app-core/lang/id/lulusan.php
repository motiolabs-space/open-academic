<?php

declare(strict_types=1);

/*
 * Data lulusan untuk tracer study nasional (ADR-037 §2). Kuesioner tracer dan
 * perhitungan IKU milik Open Campus; layar ini hanya menyiapkan daftar
 * lulusan, kontaknya, dan menerima kembali status pekerjaannya.
 */

return [

    'nav' => 'Lulusan & Tracer Study',
    'judul' => 'Lulusan & Tracer Study',
    'konteks' => 'Kontak lulusan, ekspor tracer nasional, dan impor status pekerjaan',

    'pengantar' => 'Tracer study nasional dijawab alumni sendiri di laman Kemdiktisaintek dengan NIM dan data yang diverifikasi kampus. Layar ini menyiapkan data itu: periksa kontak lulusan, unduh daftarnya, lalu impor kembali hasil status pekerjaannya. Kuesioner dan perhitungan IKU tidak dibuat di sini.',

    'filter' => [
        'tahun_lulus' => 'Tahun lulus',
        'semua_tahun' => 'Semua tahun',
        'prodi' => 'Program Studi',
        'semua_prodi' => 'Semua program studi',
        'hanya_perlu' => 'Hanya yang perlu diverifikasi',
        'terapkan' => 'Tampilkan',
    ],

    'ringkas' => [
        'lulusan' => ':jumlah lulusan',
        'lengkap' => ':jumlah kontak lengkap',
        'perlu' => ':jumlah perlu diverifikasi',
        'ditelusuri' => ':jumlah sudah ditelusuri',
    ],

    'kolom' => [
        'nim' => 'NIM',
        'nama' => 'Nama',
        'prodi_kode' => 'Kode Prodi',
        'prodi' => 'Program Studi',
        'jenjang' => 'Jenjang',
        'tahun_masuk' => 'Tahun Masuk',
        'tahun_lulus' => 'Tahun Lulus',
        'tanggal_lulus' => 'Tanggal Lulus',
        'surel' => 'Surel',
        'telepon' => 'Telepon',
        'kontak_diverifikasi' => 'Kontak Diverifikasi',
        'catatan' => 'Catatan',
        'kontak' => 'Kontak',
        'status_pekerjaan' => 'Status Pekerjaan',
    ],

    'kosong' => 'Belum ada lulusan untuk filter ini.',
    'ada' => 'ada',
    'tidak_ada' => 'kosong',
    'tersamar' => 'Kontak hanya terlihat oleh staf yang mengelola data lulusan.',

    'kontak_status' => [
        'lengkap' => 'Lengkap',
        'perlu_verifikasi' => 'Perlu verifikasi',
        'kosong' => 'Tanpa kontak',
    ],

    'tanda' => [
        'surel_kosong' => 'Surel pribadi kosong',
        'surel_kampus' => 'Hanya surel kampus — bisa tidak aktif setelah lulus',
        'surel_tidak_sah' => 'Surel tidak sah',
        'telepon_kosong' => 'Telepon kosong',
        'telepon_tidak_sah' => 'Nomor telepon tidak berbentuk nomor Indonesia',
        'belum_diverifikasi' => 'Kontak belum pernah diverifikasi',
        'verifikasi_lama' => 'Verifikasi kontak lebih dari :bulan bulan lalu',
        'tidak_dibagikan' => 'Lulusan menolak kontaknya dibagikan',
    ],

    'status' => [
        'bekerja' => 'Bekerja',
        'wirausaha' => 'Berwirausaha',
        'studi_lanjut' => 'Studi lanjut',
        'mencari_kerja' => 'Sedang mencari kerja',
        'belum_memungkinkan' => 'Belum memungkinkan bekerja',
        'belum' => 'Belum ditelusuri',
    ],

    'sumber' => [
        'tracer_nasional' => 'Tracer study nasional',
        'open_campus' => 'Open Campus',
        'survei_kampus' => 'Survei kampus',
    ],

    'verifikasi' => [
        'tombol' => 'Verifikasi kontak',
        'surel' => 'Surel pribadi',
        'telepon' => 'Telepon',
        'tidak_dibagikan' => 'Lulusan menolak kontaknya dibagikan untuk tracer study',
        'simpan' => 'Simpan & tandai terverifikasi',
        'tersimpan' => 'Kontak :nama disimpan dan ditandai terverifikasi.',
        'terakhir' => 'Diverifikasi :tanggal',
    ],

    'ekspor' => [
        'judul' => 'Ekspor untuk Tracer Study Nasional',
        'penjelasan' => 'Daftar lulusan pada filter di atas: NIM, nama, prodi, tahun masuk dan lulus, serta kontak yang boleh dibagikan. NIK, alamat, dan tanggal lahir tidak ikut. Setiap unduhan dicatat di Log Aktivitas beserta keperluannya.',
        'keperluan' => 'Keperluan',
        'keperluan_placeholder' => 'mis. unggah data lulusan 2026 ke tracer study nasional',
        'format' => 'Format',
        'tombol' => 'Unduh Daftar Lulusan',
        'konfirmasi' => 'Berkas ini berisi data pribadi lulusan. Unduh dan catat di log aktivitas?',
        'log' => 'Ekspor :jumlah lulusan untuk tracer study (:format). Keperluan: :keperluan',
    ],

    'impor' => [
        'judul' => 'Impor Status Pekerjaan',
        'penjelasan' => 'Berkas CSV hasil tracer study nasional atau dari Open Campus. Wajib berkolom nim dan status; opsional instansi, jabatan, mulai_bekerja, masa_tunggu_bulan, tanggal_survei. Berkas diperiksa dulu — tidak ada yang tersimpan sebelum Anda menerapkannya.',
        'berkas' => 'Berkas CSV',
        'sumber' => 'Sumber data',
        'periksa' => 'Periksa Berkas',
        'pratinjau' => 'Hasil pemeriksaan',
        'diterima' => ':jumlah baris akan diterapkan',
        'tidak_berubah' => ':jumlah baris sama dengan data yang ada',
        'ditolak' => ':jumlah baris ditolak',
        'terapkan' => 'Terapkan :jumlah Baris',
        'konfirmasi' => 'Terapkan status pekerjaan dari berkas ini? Baris yang ditolak tidak ikut.',
        'batal' => 'Batalkan',
        'diterapkan' => ':jumlah status pekerjaan diperbarui dari :sumber.',
        'kedaluwarsa' => 'Berkas pemeriksaan sudah tidak tersedia. Unggah ulang.',
        'baris' => 'Baris',
        'alasan' => 'Alasan',
    ],

    'tolak' => [
        'kolom_kurang' => 'Kolom wajib tidak ditemukan: :kolom.',
        'berkas_kosong' => 'Berkas tidak berisi baris data.',
        'terlalu_banyak' => 'Berkas melebihi :maks baris.',
        'nim_kosong' => 'NIM kosong.',
        'nim_tidak_dikenal' => 'NIM tidak dikenal.',
        'bukan_lulusan' => 'Mahasiswa ini belum tercatat sebagai lulusan.',
        'nim_ganda' => 'NIM sudah muncul di baris :baris.',
        'status_tidak_dikenal' => 'Status ":status" tidak dikenali.',
        'tanggal_tidak_sah' => ':kolom bukan tanggal yang sah.',
        'tanggal_masa_depan' => 'Tanggal survei di masa depan.',
        'masa_tunggu_tidak_sah' => 'Masa tunggu harus bilangan bulat 0–120 bulan.',
        'lebih_lama' => 'Data yang ada lebih baru (:tanggal).',
    ],
];
