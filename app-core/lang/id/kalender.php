<?php

declare(strict_types=1);

return [

    'judul' => 'Kalender Akademik',
    'judul_kelola' => 'Kelola Peristiwa Kalender',
    'judul_baru' => 'Peristiwa Baru',
    'judul_ubah' => 'Ubah Peristiwa',
    'konteks' => ':bulan',
    'konteks_kelola' => 'Tahun :tahun · :jumlah peristiwa',

    'jenis' => [
        'libur' => 'Libur Akademik',
        'kegiatan' => 'Kegiatan Kampus',
        'batas' => 'Batas Administrasi',
        'perkuliahan' => 'Perkuliahan',
        'krs' => 'Pengisian KRS',
        'perubahan_krs' => 'Perubahan KRS',
        'nilai' => 'Pengisian Nilai',
        'ujian' => 'Ujian',
        'edom' => 'Evaluasi Dosen',
        'yudisium' => 'Yudisium',
        'wisuda' => 'Wisuda',
        'pmb' => 'PMB',
    ],

    // Tempat tanggal itu diubah. Peristiwa turunan tidak diedit di kalender.
    'sumber' => [
        'libur' => 'Kalender Akademik',
        'kegiatan' => 'Kalender Akademik',
        'batas' => 'Kalender Akademik',
        'perkuliahan' => 'Master Akademik · Tahun Akademik',
        'krs' => 'Master Akademik · Tahun Akademik',
        'perubahan_krs' => 'Master Akademik · Tahun Akademik',
        'nilai' => 'Master Akademik · Tahun Akademik',
        'ujian' => 'Jadwal Ujian',
        'edom' => 'Evaluasi Dosen',
        'yudisium' => 'Yudisium',
        'wisuda' => 'Wisuda',
        'pmb' => 'PMB · Gelombang',
    ],

    'sasaran' => [
        'semua' => 'Semua',
        'mahasiswa' => 'Mahasiswa',
        'dosen' => 'Dosen',
        'staf' => 'Staf',
    ],

    'status' => [
        'draf' => 'Draf',
        'terbit' => 'Terbit',
    ],

    'turunan' => [
        'perkuliahan_mulai' => 'Awal perkuliahan :term',
        'perkuliahan_selesai' => 'Akhir perkuliahan :term',
        'krs' => 'Pengisian KRS :term',
        'perubahan_krs' => 'Perubahan KRS :term',
        'nilai' => 'Pengisian nilai :term',
        'ujian' => 'Masa :jenis :term',
        'ujian_keterangan' => ':jumlah sesi ujian terbit',
        'sesi_keterangan' => ':kode · Kelas :kelas',
        'edom' => 'Evaluasi dosen: :nama',
        'yudisium' => 'Yudisium',
        'yudisium_keterangan' => ':jumlah lulusan ditetapkan',
        'wisuda' => 'Wisuda: :nama',
        'pmb' => 'PMB: :nama',
    ],

    'hari' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],

    'field' => [
        'bulan' => 'Bulan',
        'tampilan' => 'Tampilan',
        'judul' => 'Judul',
        'keterangan' => 'Keterangan',
        'jenis' => 'Jenis',
        'tanggal_mulai' => 'Tanggal mulai',
        'tanggal_selesai' => 'Tanggal selesai',
        'sasaran' => 'Sasaran',
        'prodi' => 'Program studi',
        'status' => 'Status',
        'tahun' => 'Tahun',
    ],

    'petunjuk' => [
        'tanggal_selesai' => 'Kosongkan bila hanya sehari.',
        'prodi' => 'Kosongkan bila berlaku untuk seluruh program studi.',
        'sasaran' => 'Mahasiswa hanya melihat peristiwa untuk Semua atau Mahasiswa; dosen tidak melihat peristiwa khusus Staf.',
        'status' => 'Draf hanya terlihat oleh pengelola kalender dan tidak ikut ke tautan langganan.',
        'libur' => 'Libur akademik yang terbit memunculkan peringatan pada jadwal ujian di hari itu, dan dilewati oleh penyusun jadwal ujian massal.',
        'turunan' => 'Tanggal KRS, nilai, ujian, EDOM, yudisium, wisuda, dan PMB dibaca langsung dari modulnya. Ubah di layar sumbernya — kalender langsung mengikuti.',
    ],

    'tampilan' => [
        'bulan' => 'Bulan',
        'daftar' => 'Daftar',
    ],

    'kolom' => [
        'tanggal' => 'Tanggal',
        'peristiwa' => 'Peristiwa',
        'jenis' => 'Jenis',
        'sasaran' => 'Sasaran',
        'prodi' => 'Prodi',
        'sumber' => 'Sumber',
        'status' => 'Status',
    ],

    'aksi' => [
        'tampilkan' => 'Tampilkan',
        'sebelumnya' => '‹ Sebelumnya',
        'berikutnya' => 'Berikutnya ›',
        'bulan_ini' => 'Bulan ini',
        'baru' => 'Peristiwa Baru',
        'kelola' => 'Kelola Peristiwa',
        'simpan' => 'Simpan',
        'ubah' => 'Ubah',
        'hapus' => 'Hapus',
        'batal' => 'Batal',
        'buat_tautan' => 'Buat Tautan Langganan',
        'buat_ulang' => 'Buat Ulang Tautan',
        'cabut' => 'Cabut Tautan',
        'salin' => 'Salin',
        'tersalin' => 'Tersalin',
    ],

    'keterangan' => [
        'kosong' => 'Tidak ada peristiwa pada bulan ini',
        'kosong_deskripsi' => 'Coba bulan lain atau longgarkan filter.',
        'kelola_kosong' => 'Belum ada peristiwa pada tahun ini',
        'kelola_kosong_deskripsi' => 'Tambahkan libur akademik, kegiatan kampus, atau batas administrasi.',
        'lainnya' => '+:jumlah lainnya',
        'semua_prodi' => 'Semua prodi',
        'pribadi' => 'Jadwal Anda',
        'konfirmasi_hapus' => 'Hapus peristiwa ini dari kalender?',
        'konfirmasi_cabut' => 'Cabut tautan langganan? Kalender yang berlangganan berhenti diperbarui.',
    ],

    'langganan' => [
        'judul' => 'Langganan Kalender (.ics)',
        'deskripsi' => 'Tambahkan kalender ini ke Google Calendar, Outlook, atau aplikasi kalender lain lewat "Tambah dari URL". Isinya tanpa data pribadi: tanpa nama, NIM, atau surel — hanya peristiwa yang Anda lihat di sini.',
        'aktif' => 'Tautan aktif sejak :tanggal.',
        'dipakai' => 'Terakhir diambil :tanggal.',
        'belum_dipakai' => 'Belum pernah diambil aplikasi kalender.',
        'tidak_ada' => 'Belum ada tautan langganan.',
        'baru' => 'Tautan langganan Anda',
        'baru_petunjuk' => 'Salin sekarang — tautan ini tidak disimpan dan tidak dapat ditampilkan ulang. Siapa pun yang memegangnya dapat membaca kalender ini; bila tersebar, cabut atau buat ulang.',
    ],

    'ics' => [
        'nama' => 'Kalender Akademik :kampus',
        'sumber' => 'Sumber: :sumber',
    ],

    'sukses' => [
        'simpan' => 'Peristiwa ":judul" disimpan.',
        'ubah' => 'Peristiwa ":judul" diperbarui.',
        'hapus' => 'Peristiwa ":judul" dihapus dari kalender.',
        'langganan' => 'Tautan langganan baru dibuat. Tautan lama, bila ada, sudah tidak berlaku.',
        'cabut' => 'Tautan langganan dicabut.',
    ],

    'galat' => [
        'rentang' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        'terlalu_panjang' => 'Satu peristiwa paling lama :hari hari.',
        'jenis_turunan' => ':jenis tidak ditulis di kalender — tanggalnya milik modulnya sendiri.',
    ],

];
