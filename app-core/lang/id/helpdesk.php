<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Helpdesk — seluruh teks antarmuka modul
|--------------------------------------------------------------------------
|
| Dipakai oleh layar pemohon (mahasiswa & dosen), layar staf, dan halaman FAQ
| publik. Label enum status/prioritas tetap di enumnya sendiri karena dipakai
| juga di luar Blade (notifikasi, perintah artisan).
|
*/

return [

    'judul' => 'Bantuan & Helpdesk',
    'konteks_pemohon' => ':terbuka tiket masih terbuka',
    'konteks_staf' => ':terbuka tiket terbuka · :lewat lewat SLA',

    'validasi' => [
        'kategori' => 'Pilih kategori yang sesuai agar tiket langsung sampai ke unit yang tepat.',
        'judul_pendek' => 'Judul terlalu singkat untuk dikenali petugas.',
        'isi_pendek' => 'Uraikan masalahnya setidaknya dalam satu kalimat agar petugas tidak perlu bertanya balik.',
        'balasan_kosong' => 'Isi balasan tidak boleh kosong.',
        'disposisi_kosong' => 'Disposisi wajib menyebut unit kerja atau petugas yang menangani.',
        'alasan_tutup' => 'Menutup tiket wajib disertai alasan yang dapat dibaca pemohon.',
    ],

    'pesan' => [
        'tiket_dibuat' => 'Tiket :nomor dibuat. Anda akan diberi tahu saat petugas membalas.',
        'balasan_terkirim' => 'Balasan terkirim.',
        'catatan_disimpan' => 'Catatan internal disimpan; pemohon tidak melihatnya.',
        'didisposisi' => 'Tiket diteruskan ke :unit dan pemohon diberi tahu.',
        'status_diubah' => 'Status tiket diubah menjadi :status.',
        'kategori_disimpan' => 'Kategori disimpan.',
        'faq_disimpan' => 'FAQ disimpan.',
        'faq_dihapus' => 'FAQ dihapus.',
        'tiket_dicatat' => 'Tiket :nomor dicatat atas nama :nama. Pemohon diberi tahu dan dapat mengikutinya dari portalnya.',
    ],

    // Staf sebagai pemohon, dan pencatatan atas nama mahasiswa/dosen.
    'staf' => [
        'nav' => 'Tiket Bantuan Saya',
        'judul' => 'Tiket Bantuan Saya',
        'konteks' => 'Tiket Anda ke unit lain · :terbuka masih terbuka',
        'unit_sendiri' => 'Kategori ini ditangani unit Anda sendiri. Kerjakan lewat antrean helpdesk, bukan sebagai tiket.',
        'atas_nama_judul' => 'Catat atas nama',
        'atas_nama_hint' => 'Untuk mahasiswa atau dosen yang datang ke loket atau menelepon. Tiket menjadi milik mereka; nama Anda tercatat sebagai pencatat.',
        'atas_nama_jenis' => 'Pemohon',
        'atas_nama_diri' => 'Saya sendiri',
        'atas_nama_identitas' => 'NIM / NIDN',
        'atas_nama_tidak_ditemukan' => 'Mahasiswa atau dosen dengan nomor itu tidak ditemukan.',
        'atas_nama_wajib' => 'Isi NIM atau NIDN pemohon.',
        'atas_nama_bukan_staf' => 'Hanya tiket mahasiswa atau dosen yang dapat dicatat atas nama orang lain.',
        'dicatat_oleh' => 'Dicatat oleh :nama',
        'pemohon_staf' => 'Staf',
        'tangani_sendiri' => 'Tiket ini Anda ajukan sendiri; penanganannya oleh petugas lain di unit tujuan.',
    ],

    'tiket' => [
        'nomor' => 'Nomor',
        'judul' => 'Judul',
        'kategori' => 'Kategori',
        'status' => 'Status',
        'prioritas' => 'Prioritas',
        'unit' => 'Unit Penanganan',
        'petugas' => 'Petugas',
        'pemohon' => 'Pemohon',
        'dibuat' => 'Dibuat',
        'aktivitas_terakhir' => 'Aktivitas terakhir',
        'tenggat_sla' => 'Tenggat tanggapan',
        'lewat_sla' => 'Lewat SLA',
        'belum_disposisi' => 'Belum didisposisi',
        'belum_ada_petugas' => 'Belum ada petugas',
        'tanpa_sla' => 'Tanpa tenggat',
        'lampiran' => 'Lampiran',
        'unduh_lampiran' => 'Unduh lampiran',
        'tindakan' => 'Tindakan',
        'buka' => 'Buka',
        'ditutup_info' => 'Tiket ini sudah ditutup dan tidak menerima balasan. Buka tiket baru bila masalahnya berlanjut.',
        'selesai_info' => 'Tiket ini dinyatakan selesai. Bila jawabannya belum menyelesaikan masalah Anda, balas di bawah dan tiket akan dibuka kembali.',
    ],

    'form' => [
        'buat_judul' => 'Buat Tiket Baru',
        'kategori' => 'Kategori',
        'kategori_pilih' => 'Pilih kategori',
        'judul' => 'Judul',
        'judul_placeholder' => 'Ringkas masalahnya dalam satu baris',
        'isi' => 'Uraian',
        'isi_placeholder' => 'Ceritakan apa yang terjadi, kapan, dan apa yang sudah Anda coba.',
        'prioritas' => 'Prioritas',
        'lampiran' => 'Lampiran (opsional)',
        'lampiran_hint' => 'PDF atau gambar, maksimum :kb KB. Tangkapan layar sangat membantu.',
        'kirim' => 'Kirim Tiket',
        'balas' => 'Balas',
        'balas_judul' => 'Tulis Balasan',
        'balasan_placeholder' => 'Tulis balasan Anda…',
        'internal' => 'Catatan internal — hanya terlihat oleh staf',
        'kirim_balasan' => 'Kirim Balasan',
        'simpan_catatan' => 'Simpan Catatan',
        'sla_info' => 'Kategori ini berjanji ditanggapi dalam :jam jam.',
        'sla_tanpa' => 'Kategori ini tidak menetapkan tenggat tanggapan.',
    ],

    'daftar' => [
        'judul' => 'Tiket Saya',
        'kosong' => 'Belum ada tiket',
        'kosong_deskripsi' => 'Tiket yang Anda ajukan akan muncul di sini beserta balasan petugas.',
        'filter_status' => 'Status',
        'terapkan' => 'Terapkan',
        'reset' => 'Reset',
        'semua_status' => 'Semua status',
    ],

    'percakapan' => [
        'judul' => 'Percakapan',
        'pemohon' => 'Pemohon',
        'petugas' => 'Petugas',
        'internal' => 'Catatan internal',
        'pesan_awal' => 'Pesan awal',
        'belum_ada' => 'Belum ada balasan.',
    ],

    'antrean' => [
        'judul' => 'Antrean Tiket',
        'cari' => 'Cari',
        'cari_placeholder' => 'Nomor atau judul',
        'unit' => 'Unit',
        'kategori' => 'Kategori',
        'status' => 'Status',
        'hanya_lewat_sla' => 'Hanya yang lewat SLA',
        'termasuk_tertutup' => 'Termasuk yang selesai/ditutup',
        'kosong' => 'Antrean kosong',
        'kosong_deskripsi' => 'Tidak ada tiket yang menunggu unit Anda.',
        'lingkup_semua' => 'Anda melihat tiket seluruh kampus.',
        'lingkup_unit' => 'Anda melihat tiket unit Anda dan yang ditugaskan kepada Anda.',
        'pengaturan' => 'Kategori & FAQ',
    ],

    'tangani' => [
        'disposisi_judul' => 'Disposisi',
        'disposisi_hint' => 'Teruskan ke unit lain atau tunjuk petugas. Pemohon diberi tahu unit yang menanganinya.',
        'unit' => 'Unit kerja',
        'unit_pilih' => 'Ikuti petugas',
        'petugas' => 'Petugas',
        'petugas_pilih' => 'Belum ditentukan',
        'prioritas' => 'Prioritas',
        'prioritas_tetap' => 'Tetap',
        'catatan' => 'Catatan untuk unit tujuan',
        'disposisikan' => 'Disposisikan',
        'status_judul' => 'Ubah Status',
        'status_hint' => 'Transisi yang tidak sah ditolak sistem.',
        'status' => 'Status baru',
        'status_catatan' => 'Catatan untuk pemohon',
        'status_catatan_hint' => 'Wajib saat menutup. Saat menyatakan selesai, inilah jawaban yang dibaca pemohon.',
        'ubah' => 'Ubah Status',
        'tanpa_akses' => 'Anda dapat membaca tiket ini, tetapi penanganannya milik unit lain.',
        'ditutup' => 'Tiket sudah ditutup; tidak ada tindakan tersisa.',
    ],

    'pengaturan' => [
        'judul' => 'Kategori & FAQ Helpdesk',
        'konteks' => 'Ke mana tiket diteruskan, dan pertanyaan yang dijawab sebelum ditanyakan',
        'kategori' => 'Kategori Tiket',
        'kategori_tambah' => 'Tambah Kategori',
        'nama' => 'Nama',
        'deskripsi' => 'Deskripsi',
        'unit_bawaan' => 'Unit tujuan bawaan',
        'unit_tanpa' => 'Kosongkan untuk antrean umum (disposisi manual).',
        'sla_jam' => 'SLA (jam)',
        'sla_hint' => 'Kosong = ikut bawaan :jam jam; 0 = tanpa tenggat.',
        'urutan' => 'Urutan',
        'aktif' => 'Aktif',
        'jumlah_tiket' => 'Tiket',
        'simpan' => 'Simpan',
        'faq' => 'FAQ',
        'faq_tambah' => 'Tambah FAQ',
        'pertanyaan' => 'Pertanyaan',
        'jawaban' => 'Jawaban',
        'terbit' => 'Terbit',
        'draf' => 'Draf',
        'hapus' => 'Hapus',
        'kategori_faq' => 'Kategori',
        'tanpa_kategori' => 'Kosongkan untuk FAQ umum.',
        'faq_kosong' => 'Belum ada FAQ',
        'faq_kosong_deskripsi' => 'Setiap FAQ yang terbit adalah satu tiket yang tidak perlu diajukan.',
        'lihat_publik' => 'Lihat halaman publik',
    ],

    'faq' => [
        'judul' => 'Pusat Bantuan',
        'subjudul' => 'Pertanyaan yang sering diajukan',
        'pengantar' => 'Jawaban untuk pertanyaan yang paling sering sampai ke unit layanan kampus. Bila jawaban Anda tidak ada di sini, masuk ke portal dan ajukan tiket.',
        'umum' => 'Umum',
        'kosong' => 'Belum ada FAQ yang diterbitkan.',
        'masuk' => 'Masuk ke Portal',
        'ajukan' => 'Tidak menemukan jawabannya? Masuk ke portal Anda dan ajukan tiket — unit terkait akan membalas di sana.',
        'lihat_semua' => 'Lihat semua FAQ',
        'panel' => 'Sebelum mengajukan tiket',
    ],

];
