<?php

declare(strict_types=1);

return [

    'judul' => 'Jadwal Ujian',
    'judul_susun' => 'Susun Jadwal Ujian',
    'judul_baru' => 'Jadwal Ujian Baru',
    'judul_ubah' => 'Ubah Jadwal Ujian',
    'konteks' => ':term · :jumlah sesi',
    'konteks_portal' => ':term · :jumlah sesi terbit',

    'jenis' => [
        'uts' => 'Ujian Tengah Semester',
        'uas' => 'Ujian Akhir Semester',
    ],

    'jenis_singkat' => [
        'uts' => 'UTS',
        'uas' => 'UAS',
    ],

    'status' => [
        'draf' => 'Draf',
        'terbit' => 'Terbit',
    ],

    'kolom' => [
        'kelas' => 'Kelas',
        'jenis' => 'Jenis',
        'waktu' => 'Waktu',
        'ruang' => 'Ruang',
        'kursi' => 'Kursi',
        'peserta' => 'Peserta',
        'pengawas' => 'Pengawas',
        'status' => 'Status',
        'bentrok' => 'Bentrok',
        'mahasiswa' => 'Mahasiswa',
        'kehadiran' => 'Kehadiran',
        'keuangan' => 'Keuangan',
        'kelayakan' => 'Kelayakan',
        'peran' => 'Peran',
        'alasan' => 'Alasan',
    ],

    'field' => [
        'term' => 'Semester',
        'prodi' => 'Program studi',
        'semua_prodi' => 'Semua program studi',
        'jenis' => 'Jenis ujian',
        'kelas' => 'Kelas kuliah',
        'tanggal' => 'Tanggal',
        'jam_mulai' => 'Jam mulai',
        'jam_selesai' => 'Jam selesai',
        'ruang' => 'Ruang',
        'kursi' => 'Kursi',
        'pengawas' => 'Pengawas',
        'ruang_pengawas' => 'Mengawasi di ruang',
        'catatan' => 'Catatan',
        'tanggal_mulai' => 'Ujian mulai',
        'tanggal_selesai' => 'Ujian selesai',
        'sesi' => 'Sesi per hari',
        'ruang_dipakai' => 'Ruang yang dipakai',
        'sertakan_sabtu' => 'Pakai hari Sabtu',
        'pengampu_mengawas' => 'Dosen pengampu menjadi pengawas bila tidak bentrok',
    ],

    'petunjuk' => [
        'kursi' => 'Kosongkan untuk memakai kapasitas ruang. Isi lebih kecil bila ujian memakai jarak duduk.',
        'ruang_dipakai' => 'Kosongkan untuk memakai semua ruang aktif yang kapasitasnya tercatat.',
        'draf' => 'Draf hanya terlihat oleh BAAK. Bentrok diperiksa ulang dan ditolak saat diterbitkan.',
        'terbit_ubah' => 'Jadwal ini sudah terbit. Perubahan yang menimbulkan bentrok ditolak, dan mahasiswa, dosen, serta pengawas diberi tahu.',
        'susun' => 'Kelas yang belum memiliki jadwal ujian jenis ini ditempatkan pada sesi paling awal yang tidak membuat mahasiswanya mengikuti dua ujian bersamaan dan ruangnya cukup. Belum ada yang disimpan sampai Anda menyimpan sebagai draf.',
        'peserta' => 'Peserta diurutkan menurut NIM dan dibagi ke ruang sesuai urutan dan jumlah kursi.',
    ],

    'aksi' => [
        'tampilkan' => 'Tampilkan',
        'baru' => 'Jadwal baru',
        'susun' => 'Susun massal',
        'pratinjau' => 'Pratinjau',
        'simpan_draf' => 'Simpan sebagai draf',
        'simpan' => 'Simpan',
        'ubah' => 'Ubah',
        'hapus' => 'Hapus draf',
        'buka' => 'Buka',
        'terbitkan' => 'Terbitkan :jumlah draf',
        'tambah_ruang' => '+ Ruang',
        'tambah_pengawas' => '+ Pengawas',
        'tambah_sesi' => '+ Sesi',
        'lepas' => 'Lepas',
        'kartu' => 'Cetak kartu ujian',
        'kembali' => 'Kembali',
    ],

    'kartu' => [
        'ringkasan_draf' => 'Draf',
        'ringkasan_terbit' => 'Terbit',
        'ringkasan_bentrok' => 'Bentrok penghalang',
        'ringkasan_tanpa_jadwal' => 'Kelas tanpa jadwal',
        'daftar' => 'Daftar sesi',
        'bentrok' => 'Bentrok yang harus diselesaikan',
        'peserta' => 'Daftar peserta',
        'usulan' => 'Usulan jadwal',
        'tidak_terjadwal' => 'Kelas yang tidak dapat ditempatkan',
        'parameter' => 'Kerangka penyusunan',
        'mengawasi' => 'Sesi yang Anda awasi',
        'mengampu' => 'Ujian kelas yang Anda ampu',
    ],

    'keterangan' => [
        'kosong' => 'Belum ada jadwal ujian',
        'kosong_deskripsi' => 'Susun massal per program studi, atau buat satu per satu dari tombol di atas.',
        'portal_kosong' => 'Jadwal ujian belum terbit',
        'portal_kosong_deskripsi' => 'Jadwal tampil di sini setelah diterbitkan BAAK. Anda juga akan menerima pemberitahuan.',
        'tanpa_bentrok' => 'Tidak ada bentrok yang terdeteksi.',
        'tanpa_peserta' => 'Belum ada mahasiswa dengan KRS disetujui di kelas ini.',
        'tanpa_kursi' => 'Tanpa kursi',
        'usulan_kosong' => 'Tidak ada kelas yang perlu dijadwalkan dalam cakupan ini.',
        'peringatan_bentrok' => 'Disimpan sebagai draf, tetapi masih ada bentrok: :daftar. Jadwal ini tidak dapat diterbitkan sebelum diselesaikan.',
        'memenuhi' => 'Memenuhi',
        'di_bawah_ambang' => 'Di bawah :minimum%',
        'lunas' => 'Lunas',
        'menunggak' => 'Menunggak',
        'layak' => 'Layak',
        'tidak_layak' => 'Tidak layak',
        'ruang_anda' => 'Ruang Anda',
        'pengawas_utama' => 'Pengawas',
        'pengampu' => 'Pengampu',
    ],

    'sukses' => [
        'simpan' => 'Jadwal :jenis :kelas disimpan sebagai draf.',
        'ubah' => 'Jadwal :jenis :kelas diperbarui.',
        'hapus' => 'Draf jadwal dihapus.',
        'terbit' => ':jumlah jadwal diterbitkan. Mahasiswa, dosen pengampu, dan pengawas diberi tahu.',
        'susun' => ':jumlah jadwal disimpan sebagai draf. Periksa daftarnya, lalu terbitkan.',
    ],

    'galat' => [
        'jam' => 'Jam selesai harus setelah jam mulai.',
        'di_luar_term' => 'Tanggal ujian berada di luar masa semester :term.',
        'sudah_ada' => ':kelas sudah memiliki jadwal :jenis. Ubah jadwal yang ada alih-alih membuat yang kedua.',
        'jenis_tetap' => 'Jenis ujian tidak dapat diubah. Buat jadwal baru untuk jenis yang lain.',
        'ruang_ganda' => 'Ruang yang sama tercantum dua kali.',
        'pengawas_ganda' => 'Pengawas yang sama tercantum dua kali.',
        'ruang_pengawas' => 'Pengawas ditempatkan di ruang yang tidak dipakai sesi ini.',
        'ubah_bentrok' => 'Jadwal yang sudah terbit tidak dapat diubah menjadi bentrok: :daftar.',
        'hapus_terbit' => 'Jadwal yang sudah terbit tidak dapat dihapus karena sudah tercantum di kartu ujian mahasiswa. Pindahkan waktunya atau ruangnya; perubahan itu akan diberitahukan.',
        'tidak_ada_draf' => 'Tidak ada draf jadwal ujian dalam cakupan ini.',
        'terbit_bentrok' => 'Tidak ada yang diterbitkan: :jumlah bentrok penghalang masih ada, antara lain :contoh. Selesaikan seluruhnya pada daftar di bawah, lalu terbitkan lagi.',
    ],

    'bentrok' => [
        'ruang' => 'ruang :ruang sudah dipakai :sesi pada jam yang sama',
        'pengawas' => ':nama sudah mengawasi :sesi pada jam yang sama',
        'mahasiswa' => ':jumlah mahasiswa juga mengikuti :sesi pada jam yang sama (NIM :nim)',
        'tanpa_ruang' => 'belum ada ruang untuk sesi ini',
        'kursi' => ':peserta peserta, tetapi hanya :kursi kursi',
        'kapasitas' => 'ruang :ruang diberi :kursi kursi, melebihi kapasitasnya (:kapasitas)',
    ],

    'susun' => [
        'tanpa_peserta' => 'Belum ada mahasiswa dengan KRS disetujui.',
        'tanpa_slot' => 'Rentang tanggal tidak memuat satu pun sesi ujian.',
        'tidak_muat' => 'Tidak ada sesi yang bebas bentrok bagi :jumlah pesertanya dengan ruang yang cukup. Perpanjang rentang tanggal, tambah sesi, atau tambah ruang.',
    ],

    'kelayakan' => [
        'kehadiran' => 'Kehadiran di bawah :minimum%.',
        'tunggakan' => 'Masih ada tagihan yang belum lunas.',
    ],

    'kartu_ujian' => [
        'judul_jenis' => 'Kartu :jenis',
        'belum_terbit' => 'Jadwal belum terbit',
        'tidak_layak_kehadiran' => '— tidak memenuhi syarat kehadiran',
        'status_keuangan' => 'Status keuangan',
    ],

    'log' => [
        'terbit' => 'Jadwal :jenis diterbitkan: :waktu.',
        'ubah' => 'Jadwal ujian yang sudah terbit diubah menjadi :waktu.',
    ],

    'notifikasi' => [
        'terbit_judul' => 'Jadwal :jenis :term telah terbit',
        'terbit_mahasiswa' => 'Jadwal :jenis Anda sudah dapat dilihat: :jumlah ujian. Periksa tanggal, jam, dan ruang Anda.',
        'terbit_pengajar' => 'Jadwal :jenis sudah terbit. Anda tercantum pada :jumlah sesi sebagai pengampu atau pengawas.',
        'ubah_judul' => 'Jadwal :jenis :mk berubah',
        'ubah_ringkasan' => ':kelas kini dijadwalkan :waktu, ruang :ruang.',
        'ajakan' => 'Lihat Jadwal Ujian',
    ],

];
