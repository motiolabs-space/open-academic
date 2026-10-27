<?php

declare(strict_types=1);

return [

    'judul' => 'Pemakaian Ruang',
    'konteks' => ':term · :jumlah ruang',

    'jenis_ruang' => [
        'kelas' => 'Ruang Kelas',
        'laboratorium' => 'Laboratorium',
        'aula' => 'Aula',
    ],

    'urut' => [
        'gedung' => 'Gedung & kode',
        'terpenuh' => 'Paling penuh',
        'terkosong' => 'Paling kosong',
    ],

    'jenis_pakai' => [
        'kuliah' => 'Kuliah',
        'uts' => 'UTS',
        'uas' => 'UAS',
    ],

    'field' => [
        'term' => 'Semester',
        'gedung' => 'Gedung',
        'jenis' => 'Jenis ruang',
        'urut' => 'Urutan',
        'ruang' => 'Ruang',
    ],

    'aksi' => [
        'tampilkan' => 'Tampilkan',
        'ekspor' => 'Ekspor CSV',
        'cari_kosong' => 'Cari Ruang Kosong',
        'peta' => 'Peta',
        'semua_ruang' => 'Semua ruang (rerata)',
    ],

    'bingkai' => 'Dihitung dari jadwal kuliah dan jadwal ujian terbit. Jam operasional :hari, :jam · :minggu minggu perkuliahan · :libur hari libur kampus tidak dihitung sebagai jam tersedia.',

    'kartu' => [
        'ruang' => 'Ruang',
        'rerata' => 'Rerata pemanfaatan',
        'kelebihan' => 'Kelebihan peserta',
        'jarang' => 'Jarang dipakai',
        'jarang_meta' => 'di bawah :ambang% jam tersedia',
        'tabel' => 'Pemanfaatan per ruang',
        'tabel_meta' => 'Jam per minggu, rata-rata sepanjang semester',
        'peta' => 'Peta panas hari × jam',
        'peta_meta' => 'Bagian minggu perkuliahan ruang terpakai pada jam itu',
        'daftar_kelebihan' => 'Kelebihan peserta',
        'daftar_kelebihan_meta' => 'Peserta KRS disetujui melebihi kapasitas ruang',
        'daftar_jarang' => 'Jarang dipakai',
        'daftar_jarang_meta' => 'Ruang aktif dengan pemanfaatan di bawah :ambang%',
    ],

    'kolom' => [
        'ruang' => 'Ruang',
        'jenis' => 'Jenis',
        'kapasitas' => 'Kapasitas',
        'tersedia' => 'Tersedia',
        'terpakai' => 'Terpakai',
        'kosong' => 'Kosong',
        'pemanfaatan' => 'Pemanfaatan',
        'okupansi' => 'Okupansi kursi',
        'di_luar' => 'Di luar jam',
        'pemakaian' => 'Kuliah / ujian',
        'kelas' => 'Kelas',
        'waktu' => 'Waktu',
        'peserta' => 'Peserta',
        'selisih' => 'Selisih',
        'hari' => 'Hari',
        'gedung' => 'Gedung',
        'dipakai_oleh' => 'Dipakai oleh',
    ],

    'keterangan' => [
        'kosong' => 'Belum ada ruang',
        'kosong_deskripsi' => 'Tidak ada ruang yang cocok dengan filter ini. Ruang ditambahkan di Master Akademik.',
        'tanpa_kelebihan' => 'Tidak ada pemakaian yang melebihi kapasitas ruang.',
        'tanpa_jarang' => 'Tidak ada ruang aktif di bawah ambang.',
        'nonaktif' => 'Nonaktif',
        'jarang' => 'Jarang',
        'kelebihan' => ':jumlah kelebihan',
        'jam' => ':jam jam',
        'pemakaian' => ':kuliah slot · :ujian sesi',
        'tak_diketahui' => 'Kapasitas belum diisi',
        'legenda' => 'Kosong',
        'legenda_penuh' => 'Selalu terpakai',
        'tidak_tersedia' => 'Tidak ada hari tersedia',
    ],

    'kosong' => [
        'judul' => 'Cari Ruang Kosong',
        'petunjuk' => 'Hanya baca — tidak ada ruang yang dipesan. Jadwal kuliah dan jadwal ujian yang disimpan tetap diperiksa bentroknya sendiri.',
        'petunjuk_tanggal' => 'Isi tanggal untuk ujian atau kuliah pengganti: hari mengikuti tanggal, dan ujian terbit serta hari libur pada tanggal itu ikut diperiksa.',
        'mulai' => 'Isi hari (atau tanggal) dan jam untuk mencari',
        'mulai_deskripsi' => 'Ruang aktif yang tidak dipakai jadwal kuliah semester ini pada jam itu, dengan kapasitas minimum yang Anda tentukan.',
        'libur' => 'Tanggal ini libur kampus (:judul) — kuliah mingguan tidak berjalan.',
        'di_luar' => 'Jam ini berada di luar jam operasional ruang (:hari, :jam).',
        'tersedia' => 'Ruang kosong',
        'tersedia_meta' => 'Kapasitas terkecil yang mencukupi lebih dulu',
        'terpakai' => 'Terpakai pada jam itu',
        'tanpa_hasil' => 'Tidak ada ruang kosong yang memenuhi kapasitas minimum pada jam itu.',
        'cari' => 'Cari',
        'ringkasan' => ':waktu · kapasitas minimum :kapasitas',
        'field' => [
            'term' => 'Semester',
            'hari' => 'Hari',
            'tanggal' => 'Tanggal',
            'jam_mulai' => 'Jam mulai',
            'jam_selesai' => 'Jam selesai',
            'kapasitas_min' => 'Kapasitas minimum',
            'gedung' => 'Gedung',
            'jenis' => 'Jenis ruang',
        ],
    ],

    // Header CSV, berurutan sesuai kolom.
    'csv' => [
        'gedung' => 'Gedung',
        'kode' => 'Kode Ruang',
        'nama' => 'Nama Ruang',
        'jenis' => 'Jenis',
        'kapasitas' => 'Kapasitas',
        'tersedia' => 'Jam Tersedia per Minggu',
        'terpakai' => 'Jam Terpakai per Minggu',
        'kosong' => 'Jam Kosong per Minggu',
        'pemanfaatan' => 'Pemanfaatan (%)',
        'okupansi' => 'Okupansi Kursi (%)',
        'di_luar' => 'Jam di Luar Operasional per Minggu',
        'slot_kuliah' => 'Slot Kuliah per Minggu',
        'sesi_ujian' => 'Sesi Ujian',
        'kelebihan' => 'Kelebihan Peserta',
        'jarang' => 'Jarang Dipakai',
    ],

];
