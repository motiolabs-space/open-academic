<?php

declare(strict_types=1);

return [

    'judul' => 'Penetapan Dosen Wali',
    'konteks' => ':jumlah mahasiswa · :tanpa belum memiliki dosen wali',

    'cara' => [
        'perorangan' => 'Perorangan',
        'merata' => 'Pembagian merata',
        'manual' => 'Penetapan massal',
    ],

    'filter' => [
        'prodi' => 'Program Studi',
        'angkatan' => 'Angkatan',
        'semua_angkatan' => 'Semua angkatan',
        'tanpa_wali' => 'Hanya yang belum memiliki dosen wali',
        'terapkan' => 'Tampilkan',
    ],

    'pilih_prodi' => 'Pilih program studi untuk menampilkan mahasiswanya.',
    'pilih_prodi_judul' => 'Belum ada program studi dipilih',
    'kosong' => 'Tidak ada mahasiswa yang cocok dengan filter ini.',
    'tanpa_calon' => 'Program studi ini belum memiliki dosen aktif ber-homebase di sini, sehingga tidak ada yang dapat ditetapkan sebagai dosen wali.',

    'kolom' => [
        'nim' => 'NIM',
        'nama' => 'Nama',
        'angkatan' => 'Angkatan',
        'status' => 'Status',
        'wali_saat_ini' => 'Dosen Wali Saat Ini',
        'wali_baru' => 'Tetapkan ke',
        'bimbingan' => 'Mahasiswa bimbingan',
    ],

    'belum_ada' => 'Belum ada',
    'tidak_berubah' => '— tidak berubah —',
    'krs_menunggu' => ':jumlah KRS menunggu persetujuan',

    'aturan' => 'Dosen wali harus dosen aktif ber-homebase di program studi mahasiswa. KRS yang sudah diajukan dan belum diputus berpindah ke dosen wali baru begitu penetapan disimpan.',

    'massal' => [
        'judul' => 'Penetapan per baris',
        'tombol' => 'Simpan Penetapan',
        'konfirmasi' => 'Simpan penetapan dosen wali untuk baris yang diubah? Mahasiswa yang bersangkutan akan menerima pemberitahuan.',
    ],

    'merata' => [
        'judul' => 'Bagi Merata',
        'penjelasan' => 'Mahasiswa yang tampil pada filter di atas dibagi rata kepada dosen yang dicentang. Mahasiswa yang sudah dibimbing salah satu dosen terpilih dipertahankan selama kuotanya belum penuh, sehingga menjalankan ulang tidak mengubah apa pun.',
        'dosen' => 'Dosen wali',
        'tombol' => 'Bagi Merata',
        'konfirmasi' => 'Bagi mahasiswa pada filter ini secara merata kepada dosen yang dicentang?',
    ],

    'alasan' => 'Alasan (opsional)',
    'alasan_placeholder' => 'mis. dosen wali sebelumnya tugas belajar',

    'riwayat' => [
        'judul' => 'Riwayat Dosen Wali',
        'kosong' => 'Belum ada perubahan dosen wali yang tercatat.',
        'baris' => ':lama → :baru',
        'krs' => ':jumlah KRS dialihkan',
    ],

    'hasil' => 'Penetapan disimpan: :diubah mahasiswa berganti dosen wali, :tetap tidak berubah.',
    'hasil_krs' => ':jumlah KRS yang menunggu persetujuan dialihkan ke dosen wali baru.',
    'hasil_satu' => ':dosen kini menjadi dosen wali :mahasiswa.',
    'sudah_wali' => ':dosen sudah menjadi dosen wali mahasiswa ini; tidak ada yang diubah.',

    'galat' => [
        'dosen_tidak_aktif' => ':dosen tidak aktif dan tidak dapat ditetapkan sebagai dosen wali.',
        'beda_prodi' => ':dosen ber-homebase di program studi lain; dosen wali harus dari program studi mahasiswa (:prodi).',
        'mahasiswa_tidak_berwali' => ':mahasiswa berstatus :status; dosen wali hanya ditetapkan untuk mahasiswa yang masih terdaftar.',
        'mahasiswa_beda_prodi' => 'Sebagian mahasiswa yang dipilih bukan mahasiswa program studi ini.',
        'dosen_bukan_prodi' => 'Dosen yang dicentang harus dosen ber-homebase di program studi ini.',
        'tanpa_dosen' => 'Centang setidaknya satu dosen untuk pembagian merata.',
        'tanpa_mahasiswa' => 'Tidak ada mahasiswa pada filter ini yang dapat dibagi.',
    ],

    'notifikasi' => [
        'judul_mahasiswa' => 'Dosen wali Anda ditetapkan',
        'judul_mahasiswa_ganti' => 'Dosen wali Anda diganti',
        'ringkasan_mahasiswa' => ':dosen kini menjadi dosen wali Anda. Persetujuan KRS dan konsultasi akademik diajukan kepadanya.',
        'ringkasan_mahasiswa_krs' => ':dosen kini menjadi dosen wali Anda. KRS Anda yang sedang menunggu persetujuan telah dialihkan kepadanya.',
        'judul_dosen' => 'KRS mahasiswa wali baru menunggu persetujuan',
        'ringkasan_dosen' => 'Anda ditetapkan sebagai dosen wali :mahasiswa (:nim). :jumlah KRS yang sudah diajukan kini menunggu keputusan Anda.',
        'ajakan' => 'Buka KRS',
    ],

    'nav' => 'Dosen Wali',

];
