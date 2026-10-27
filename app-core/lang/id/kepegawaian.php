<?php

declare(strict_types=1);

return [

    'judul' => 'Riwayat Kepegawaian',
    'judul_antrean' => 'Validasi Riwayat Kepegawaian',
    'konteks_antrean' => ':jumlah ajuan menunggu',

    'pengantar' => 'Riwayat di halaman ini adalah isi "Profil Dosen" yang diminta SISTER. Setiap isian '
        .'diperiksa bagian kepegawaian sebelum dianggap tercatat; yang sudah disetujui tidak dapat '
        .'disunting lagi dari sini — minta koreksi kepada bagian kepegawaian.',
    'pengantar_admin' => 'Isian dari bagian kepegawaian langsung tercatat sebagai disetujui. Ajuan dosen '
        .'menunggu di bawah dan dapat disetujui atau ditolak dengan alasan.',

    'status' => [
        'diajukan' => 'Menunggu Validasi',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
    ],

    'jenis' => [
        'pangkat' => 'Pangkat & Golongan',
        'inpassing' => 'Inpassing',
        'jabatan_struktural' => 'Jabatan Struktural',
        'tugas_belajar' => 'Studi Lanjut / Tugas Belajar',
        'diklat' => 'Diklat & Pelatihan',
        'pengalaman_kerja' => 'Pengalaman Kerja',
        'tugas_dinas' => 'Tugas Dinas',
        'penghargaan_sanksi' => 'Penghargaan & Sanksi',
        'organisasi' => 'Organisasi Profesi',
        'bahasa' => 'Kemampuan Bahasa',
        'keluarga' => 'Anggota Keluarga',
        'mutasi' => 'Mutasi & Penempatan',
        'kontrak' => 'Kontrak Kerja',
        'pensiun' => 'Pensiun & Pemberhentian',
    ],

    'diklat' => [
        'prajabatan' => 'Prajabatan',
        'kepemimpinan' => 'Kepemimpinan',
        'fungsional' => 'Fungsional',
        'teknis' => 'Teknis',
        'lainnya' => 'Lainnya',
    ],

    'studi_lanjut' => [
        'tugas_belajar' => 'Tugas Belajar',
        'izin_belajar' => 'Izin Belajar',
    ],

    'pensiun' => [
        'batas_usia' => 'Batas Usia Pensiun',
        'atas_permintaan_sendiri' => 'Atas Permintaan Sendiri',
        'meninggal_dunia' => 'Meninggal Dunia',
        'diberhentikan' => 'Diberhentikan',
    ],

    'penghargaan_sanksi' => [
        'penghargaan' => 'Penghargaan',
        'sanksi' => 'Sanksi',
    ],

    'kemampuan' => [
        'dasar' => 'Dasar',
        'menengah' => 'Menengah',
        'mahir' => 'Mahir',
        'penutur_asli' => 'Penutur asli',
    ],

    'hubungan' => [
        'pasangan' => 'Suami/Istri',
        'anak' => 'Anak',
        'ayah' => 'Ayah',
        'ibu' => 'Ibu',
    ],

    'mutasi' => [
        'masuk' => 'Masuk',
        'pindah' => 'Pindah unit',
        'keluar' => 'Keluar',
    ],

    'field' => [
        'golongan' => 'Golongan',
        'tmt' => 'TMT',
        'tmt_selesai' => 'TMT selesai',
        'nomor_sk' => 'Nomor SK',
        'tanggal_sk' => 'Tanggal SK',
        'angka_kredit' => 'Angka kredit',
        'nama_jabatan' => 'Nama jabatan',
        'unit_kerja_id' => 'Unit kerja',
        'jenis' => 'Jenis',
        'jenjang' => 'Jenjang',
        'perguruan_tinggi' => 'Perguruan tinggi',
        'program_studi' => 'Program studi',
        'negara' => 'Negara',
        'sumber_biaya' => 'Sumber biaya',
        'tanggal_mulai' => 'Mulai',
        'tanggal_selesai' => 'Selesai',
        'nama' => 'Nama',
        'penyelenggara' => 'Penyelenggara',
        'tingkat' => 'Tingkat',
        'jumlah_jam' => 'Jumlah jam',
        'nomor_sertifikat' => 'Nomor sertifikat',
        'instansi' => 'Instansi',
        'jabatan' => 'Jabatan',
        'bidang' => 'Bidang',
        'keterangan' => 'Keterangan',
        'tujuan' => 'Tempat tujuan',
        'instansi_tujuan' => 'Instansi tujuan',
        'keperluan' => 'Keperluan',
        'nomor_surat_tugas' => 'Nomor surat tugas',
        'is_luar_negeri' => 'Luar negeri',
        'pemberi' => 'Pemberi',
        'tanggal' => 'Tanggal',
        'peran' => 'Peran',
        'tahun_mulai' => 'Tahun mulai',
        'tahun_selesai' => 'Tahun selesai',
        'bahasa' => 'Bahasa',
        'kemampuan' => 'Kemampuan',
        'sertifikat' => 'Sertifikat',
        'skor' => 'Skor',
        'hubungan' => 'Hubungan',
        'tanggal_lahir' => 'Tanggal lahir',
        'pekerjaan' => 'Pekerjaan',
        'is_tanggungan' => 'Tanggungan',
        'unit_tujuan_id' => 'Unit tujuan',
        'nomor_kontrak' => 'Nomor kontrak',
        'dokumen' => 'Dokumen pendukung',
        'alasan' => 'Alasan penolakan',
    ],

    'petunjuk' => [
        'tanggal_selesai' => 'Kosongkan bila masih berjalan.',
        'tugas_belajar' => 'Tugas belajar membebaskan kewajiban BKD selama masa studinya; izin belajar tidak.',
        'mutasi' => 'Mutasi memindahkan unit kerja dosen saat dicatat. Unit asal diambil dari unit sekarang.',
        'pensiun' => 'Akun dosen ditutup pada TMT pensiun, kecuali masih mengampu kelas semester berjalan '
            .'atau menjadi dosen wali — alihkan dulu tanggung jawab itu.',
        'diisi_staf' => 'Dicatat oleh bagian kepegawaian.',
        'dokumen' => 'SK, sertifikat, atau surat tugas — dasar bagian kepegawaian memvalidasi.',
        'tanggungan' => 'Masuk daftar tanggungan',
        'luar_negeri' => 'Di luar negeri',
    ],

    'aksi' => [
        'ajukan' => 'Ajukan',
        'simpan' => 'Simpan',
        'catat' => 'Catat',
        'ubah' => 'Sunting',
        'batal' => 'Batal',
        'hapus' => 'Hapus',
        'setujui' => 'Setujui',
        'tolak' => 'Tolak',
        'unduh' => 'Dokumen',
        'buka' => 'Buka berkas',
        'antrean' => 'Antrean validasi',
        'tampilkan' => 'Tampilkan',
    ],

    'pesan' => [
        'diajukan' => ':jenis diajukan dan menunggu validasi bagian kepegawaian.',
        'dicatat' => ':jenis tercatat.',
        'diubah' => ':jenis diperbarui dan diajukan ulang.',
        'dihapus' => 'Isian dihapus.',
        'disetujui' => 'Ajuan disetujui.',
        'ditolak' => 'Ajuan ditolak dan dikembalikan kepada dosen.',
        'akun_ditutup' => 'Akun :nama ditutup karena pensiun.',
        'akun_tertahan' => 'Pensiun tercatat, tetapi akun :nama belum dapat ditutup: :halangan.',
        'sudah_disetujui' => 'Isian yang sudah disetujui tidak dapat diubah dari portal dosen. Minta koreksi kepada bagian kepegawaian.',
        'bukan_isian_dosen' => ':jenis dicatat oleh bagian kepegawaian, bukan dari portal dosen.',
        'alasan_wajib' => 'Tuliskan alasan penolakan, minimal lima karakter — dosen perlu tahu apa yang harus diperbaiki.',
        'bahasa_ganda' => 'Bahasa ini sudah tercatat. Sunting isian yang ada.',
        'sudah_divalidasi' => 'Ajuan ini sudah divalidasi.',
    ],

    'kosong' => [
        'riwayat' => 'Belum ada isian.',
        'antrean' => 'Tidak ada ajuan yang menunggu validasi.',
    ],

    'kolom' => [
        'dosen' => 'Dosen',
        'jenis' => 'Jenis',
        'isian' => 'Isian',
        'diajukan' => 'Diajukan',
        'status' => 'Status',
        'aksi' => 'Aksi',
    ],

    'sekarang' => 'sekarang',
    'nonaktif' => 'Nonaktif',
    'luar_negeri' => 'luar negeri',
    'tanggungan' => 'tanggungan',
    'jam' => ':jumlah jam',
    'dari' => 'dari :unit',
    'ke' => 'ke :unit',
    'divalidasi_oleh' => 'Divalidasi :nama, :tanggal',
    'alasan_tolak' => 'Alasan penolakan: :alasan',
    'ringkasan_status' => ':disetujui disetujui · :diajukan menunggu · :ditolak ditolak',
    'tugas_belajar_aktif' => 'Sedang tugas belajar — tidak wajib BKD selama masa studi.',

];
