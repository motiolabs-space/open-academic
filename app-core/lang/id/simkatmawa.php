<?php

declare(strict_types=1);

/*
 * Laporan SIMKATMAWA (ADR-037 §1). Kolom dan aturan ditulis ulang dari
 * Panduan SIMKATMAWA 2026 dengan kata-kata sendiri; panduannya tidak disalin.
 */

return [

    'nav' => 'Laporan SIMKATMAWA',
    'judul' => 'Laporan SIMKATMAWA',
    'konteks' => 'Prestasi, rekognisi, sertifikasi, dan pembimbing',

    'pengantar' => 'Prestasi terverifikasi yang kategorinya dilaporkan ke SIMKATMAWA, disusun menjadi empat tabel. Baris yang akan ditolak ditampilkan lebih dulu beserta alasannya dan tidak ikut terunduh; baris berperingatan tetap terunduh dengan skor yang mungkin lebih rendah. Berkas ini diunggah atau diisikan sendiri oleh kampus — Open Academic tidak mengirim apa pun ke kementerian.',

    'filter' => [
        'dari' => 'Dari tanggal',
        'sampai' => 'Sampai tanggal',
        'prodi' => 'Program Studi',
        'semua_prodi' => 'Semua program studi',
        'terapkan' => 'Tampilkan',
    ],

    'ringkas' => [
        'siap' => ':jumlah siap dilaporkan',
        'tidak_lengkap' => ':jumlah belum lengkap',
        'peringatan' => ':jumlah berperingatan',
        'menunggu' => ':jumlah prestasi pada periode ini masih menunggu verifikasi dan belum ikut dihitung.',
        'tanpa_kategori' => ':jumlah kategori prestasi aktif di katalog belum ditetapkan tabel SIMKATMAWA-nya, sehingga catatannya tidak masuk laporan ini. Tetapkan di layar Poin Kemahasiswaan.',
    ],

    'kosong' => 'Tidak ada baris pada tabel ini untuk periode yang dipilih.',
    'unduh_csv' => 'Unduh CSV',
    'unduh_xlsx' => 'Unduh XLSX',
    'unduh_catatan' => 'Hanya baris yang siap dilaporkan yang terunduh.',
    'tombol_lengkapi' => 'Lengkapi',
    'status_siap' => 'Siap',
    'status_galat' => 'Belum lengkap',

    'kategori' => [
        'kompetisi' => 'Prestasi kompetisi',
        'rekognisi' => 'Rekognisi / karya',
        'sertifikasi' => 'Sertifikasi kompetensi',
        'pembimbing' => 'Dosen pembimbing',
    ],

    'tingkat' => [
        'kabupaten_kota' => 'Kabupaten/Kota',
        'provinsi' => 'Provinsi',
        'nasional' => 'Nasional',
        'internasional' => 'Internasional',
    ],

    'peringkat' => [
        'juara_1' => 'Juara I',
        'juara_2' => 'Juara II',
        'juara_3' => 'Juara III',
        'harapan_1' => 'Juara Harapan I',
        'harapan_2' => 'Juara Harapan II',
        'harapan_3' => 'Juara Harapan III',
        'apresiasi' => 'Apresiasi / penghargaan tambahan',
        'peserta' => 'Peserta',
    ],

    'bentuk' => [
        'individu' => 'Individu',
        'kelompok' => 'Kelompok',
    ],

    'moda' => [
        'daring' => 'Daring penuh',
        'luring' => 'Luring / hibrida',
    ],

    'bidang' => [
        'riset_stem' => 'Riset & inovasi — sains dan teknologi',
        'riset_ssh' => 'Riset & inovasi — sosial dan humaniora',
        'seni_budaya' => 'Seni budaya',
        'olahraga' => 'Olahraga',
        'minat_khusus' => 'Minat khusus',
    ],

    'rekognisi' => [
        'karya' => 'Karya tepat guna, seni budaya, atau produk kreatif',
        'juri_pelatih_wasit' => 'Juri, pelatih, atau wasit',
        'keynote_konferensi' => 'Pembicara utama konferensi',
        'keynote_workshop' => 'Pembicara utama lokakarya/pelatihan',
        'pameran_seni' => 'Peserta pameran karya seni',
        'cipta_lagu_tari' => 'Karya cipta lagu atau tari',
        'penulis_buku' => 'Penulis buku ber-ISBN',
        'paten' => 'Paten / paten sederhana',
        'publikasi' => 'Publikasi artikel ilmiah',
        'duta' => 'Duta (brand ambassador)',
    ],

    // Judul kolom berkas ekspor.
    'kolom' => [
        'nim' => 'NIM',
        'nama' => 'Nama Mahasiswa',
        'prodi_kode' => 'Kode Prodi',
        'prodi' => 'Program Studi',
        'judul' => 'Nama Kegiatan / Karya',
        'bidang' => 'Bidang',
        'tingkat' => 'Tingkat',
        'moda' => 'Pelaksanaan',
        'bentuk' => 'Kepesertaan',
        'peringkat' => 'Capaian',
        'jenis_rekognisi' => 'Jenis Rekognisi',
        'penyelenggara' => 'Penyelenggara / Pemberi',
        'nomor_sertifikat' => 'Nomor Sertifikat',
        'tanggal_mulai' => 'Tanggal Mulai',
        'tanggal_selesai' => 'Tanggal Selesai',
        'jumlah_pt' => 'Jumlah PT Peserta',
        'jumlah_negara' => 'Jumlah Negara Peserta',
        'tautan_sertifikat' => 'Tautan Sertifikat',
        'tautan_bukti' => 'Tautan Bukti Kegiatan',
        'tautan_surat_tugas' => 'Tautan Surat Tugas',
        'pembimbing_nidn' => 'NIDN Pembimbing',
        'pembimbing_nuptk' => 'NUPTK Pembimbing',
        'pembimbing_nama' => 'Nama Pembimbing',
        'kategori' => 'Kategori',
    ],

    // Label isian layar Lengkapi.
    'isian' => [
        'tingkat' => 'Tingkat',
        'peringkat' => 'Capaian',
        'bentuk_kepesertaan' => 'Kepesertaan',
        'moda_pelaksanaan' => 'Pelaksanaan',
        'bidang_lomba' => 'Bidang lomba',
        'jenis_rekognisi' => 'Jenis rekognisi',
        'penyelenggara' => 'Penyelenggara / pemberi rekognisi',
        'tanggal_selesai' => 'Tanggal selesai',
        'jumlah_pt_peserta' => 'Jumlah perguruan tinggi peserta',
        'jumlah_negara_peserta' => 'Jumlah negara peserta',
        'nomor_sertifikat' => 'Nomor sertifikat',
        'tautan_sertifikat' => 'Tautan pindaian sertifikat',
        'tautan_bukti' => 'Tautan bukti (laman penyelenggara, berita, atau foto penyerahan)',
        'tautan_surat_tugas' => 'Tautan surat tugas',
        'dosen_pembimbing' => 'Dosen pembimbing/pendamping',
    ],

    'petunjuk' => [
        'tingkat' => 'Kosongkan bila sama dengan katalog (nasional/internasional).',
        'jumlah_pt_peserta' => 'Untuk tingkat kabupaten/kota sampai nasional.',
        'jumlah_negara_peserta' => 'Untuk tingkat internasional.',
        'tautan' => 'Alamat lengkap yang dapat dibuka penilai, diawali https://.',
    ],

    'galat' => [
        'tingkat_kosong' => 'Tingkat belum diisi.',
        'tingkat_internal' => 'Kegiatan internal kampus tidak dilaporkan ke SIMKATMAWA; isi tingkat bila sebenarnya lebih luas.',
        'tingkat_tidak_sah' => 'Tingkat :tingkat tidak diterima untuk :kategori.',
        'penyelenggara_kosong' => 'Penyelenggara belum diisi.',
        'sertifikat_kosong' => 'Tautan sertifikat belum diisi.',
        'tautan_tidak_sah' => ':kolom bukan alamat web yang sah.',
        'wajib_kompetisi' => ':kolom wajib untuk prestasi kompetisi.',
        'jumlah_pt_kosong' => 'Jumlah perguruan tinggi peserta belum diisi.',
        'jumlah_negara_kosong' => 'Jumlah negara peserta belum diisi.',
        'jenis_rekognisi_kosong' => 'Jenis rekognisi belum dipilih.',
        'nomor_sertifikat_kosong' => 'Nomor sertifikat belum diisi.',
        'pembimbing_tanpa_nomor' => 'Dosen pembimbing belum memiliki NIDN maupun NUPTK.',
        'prestasi_belum_lengkap' => 'Prestasi yang dibimbing sendiri belum lengkap.',
        'bukan_prestasi' => 'Hanya catatan prestasi yang dilaporkan ke SIMKATMAWA.',
        'kategori_tidak_sah' => 'Hanya kategori prestasi yang dapat dilaporkan ke SIMKATMAWA.',
    ],

    'peringatan' => [
        'tanpa_tautan_bukti' => 'Tanpa tautan bukti kegiatan, skor dapat diturunkan.',
        'tanpa_surat_tugas' => 'Tanpa surat tugas, pendamping tidak tercatat.',
        'tanpa_pembimbing' => 'Belum ada dosen pembimbing.',
        'belum_pddikti' => 'Mahasiswa belum tersinkron ke PDDikti.',
        'judul_pameran' => 'Judulnya menandai pameran (invention/innovation/exhibition/convention); tingkat internasional semacam ini dihitung sebagai rekognisi, bukan kompetisi.',
    ],

    'lengkapi' => [
        'judul' => 'Data SIMKATMAWA',
        'pengantar' => 'Data tambahan yang dinilai SIMKATMAWA. Poin, jenis, dan status verifikasi catatan tidak berubah. Isian boleh disimpan sebagian; layar laporan menunjukkan yang masih kurang.',
        'kategori_katalog' => 'Tabel SIMKATMAWA',
        'tidak_dilaporkan' => 'Kategori katalog ini belum ditetapkan tabel SIMKATMAWA-nya.',
        'simpan' => 'Simpan',
        'kembali' => 'Kembali ke laporan',
        'tersimpan' => 'Data SIMKATMAWA disimpan.',
        'temuan' => 'Yang masih kurang',
        'lengkap' => 'Data sudah lengkap untuk dilaporkan.',
    ],

    'katalog' => [
        'label' => 'Tabel SIMKATMAWA',
        'tidak_dilaporkan' => 'tidak dilaporkan',
        'tersimpan' => 'Kategori :nama kini dilaporkan sebagai: :kategori.',
        'simpan' => 'Tetapkan',
    ],
];
