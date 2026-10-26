<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mahasiswa Pindahan & RPL — teks antarmuka intake
|--------------------------------------------------------------------------
|
| Dipakai layar daftar, formulir intake (dari PMB maupun pendaftaran langsung),
| dan halaman profil pindahan. Label jenis daftar tetap di enum JenisDaftar
| karena dipakai juga oleh validator Feeder dan mapper.
|
*/

return [

    'judul' => 'Mahasiswa Pindahan & RPL',
    'konteks' => ':jumlah mahasiswa pindahan/RPL',
    'judul_baru' => 'Intake Mahasiswa Pindahan',
    'konteks_baru_pmb' => 'Dari pendaftar :nomor',
    'konteks_baru_langsung' => 'Pendaftaran langsung oleh BAAK',

    'aksi' => [
        'daftarkan' => 'Daftarkan Mahasiswa Pindahan',
        'lanjutkan_intake' => 'Lanjutkan intake',
        'buka' => 'Buka',
        'simpan_intake' => 'Daftarkan & susun konversi',
        'simpan_asal' => 'Simpan perubahan asal',
        'tambah_baris' => '+ Tambah baris',
        'hapus_baris' => 'Hapus baris',
        'ajukan_transkrip' => 'Catat seluruh usulan',
        'putuskan' => 'Putuskan di layar Konversi Kredit',
        'cari' => 'Cari',
        'profil_pindahan' => 'Profil pindahan',
        'intake_pmb' => 'Intake Pindahan',
    ],

    'kolom' => [
        'mahasiswa' => 'Mahasiswa',
        'jenis_daftar' => 'Jenis daftar',
        'pt_asal' => 'Perguruan tinggi asal',
        'prodi_asal' => 'Program studi asal',
        'term_masuk_asal' => 'Semester masuk di asal',
        'term_masuk' => 'Semester masuk di sini',
        'prodi' => 'Program studi tujuan',
        'sks_diakui' => 'SKS diakui',
        'menunggu' => 'Usulan menunggu',
        'nama' => 'Nama lengkap',
        'email' => 'Surel',
        'nik' => 'NIK',
        'tempat_lahir' => 'Tempat lahir',
        'tanggal_lahir' => 'Tanggal lahir',
        'jenis_kelamin' => 'Jenis kelamin',
        'nama_ibu' => 'Nama ibu kandung',
        'telepon' => 'Telepon',
        'alamat' => 'Alamat',
        'pendaftar' => 'Pendaftar',
        'gelombang' => 'Gelombang',
        'matkul_tujuan' => 'Mata kuliah di sini',
        'nama_asal' => 'Mata kuliah asal',
        'sks_asal' => 'SKS asal',
        'nilai_asal' => 'Nilai asal',
        'status' => 'Status',
        'bukti' => 'Transkrip asal',
    ],

    'petunjuk' => [
        'cari' => 'Nama atau NIM',
        'pt_asal' => 'Ketik untuk mencari dari referensi PDDIKTI. Bila belum ditarik, tulis nama resminya.',
        'prodi_asal' => 'Nama program studi sebagaimana tertulis di transkrip asal.',
        'term_masuk_asal' => 'Kode PDDIKTI, mis. 20231. Hanya dipakai bila masa studi dihitung dari asal.',
        'term_masuk' => 'Menentukan angkatan, NIM, dan periode masuk yang dilaporkan.',
        'nama_ibu' => 'Sebagian aturan Feeder mewajibkannya.',
        'bukti' => 'Satu berkas untuk seluruh baris. Konversi tanpa bukti hanyalah klaim.',
        'sks_tidak_diketik' => 'SKS diakui tidak diisi di sini. Angkanya adalah jumlah konversi yang disetujui, dibatasi :persen% dari syarat kelulusan program studi, dan tercatat otomatis setiap kali usulan diputuskan.',
        'sesudah_simpan' => 'Sesudah disimpan, NIM diterbitkan dan Anda langsung dibawa ke penyusunan usulan konversi dari transkrip asal.',
    ],

    'kartu' => [
        'menunggu_registrasi' => 'Pendaftar PMB Jalur Pindahan/RPL',
        'menunggu_registrasi_meta' => 'Sudah lulus seleksi, belum diregistrasi',
        'daftar' => 'Mahasiswa Pindahan & RPL',
        'pendaftar' => 'Pendaftar',
        'identitas' => 'Identitas',
        'asal' => 'Asal Pendidikan',
        'penempatan' => 'Penempatan',
        'kesiapan_feeder' => 'Kesiapan Feeder',
        'transkrip' => 'Usulan Konversi dari Transkrip Asal',
        'konversi' => 'Konversi Tercatat',
        'ubah_asal' => 'Ubah Asal Pendidikan',
        'asal_singkat' => 'Mahasiswa :jenis dari :pt',
    ],

    'statistik' => [
        'diakui' => 'SKS diakui',
        'batas' => 'Batas pengakuan',
        'sisa' => 'Sisa kuota',
        'menunggu' => 'Menunggu keputusan',
        'semester_awal' => 'Semester masuk',
        'batas_krs' => 'Batas SKS semester pertama',
        'semester_asal' => 'Semester di asal',
        'sks' => 'SKS',
    ],

    'keterangan' => [
        'semester_awal' => '1 + SKS diakui ÷ :per (dibulatkan ke bawah). Menentukan paket kuliah yang ditawarkan.',
        'batas_krs_ips' => 'Dari rata-rata nilai konversi yang disetujui (:ips).',
        'batas_krs_bawaan' => 'Bawaan, karena belum ada konversi bernilai yang disetujui.',
        'masa_studi_dihitung' => 'Dihitung ke batas masa studi pada instalasi ini.',
        'masa_studi_tidak' => 'Tidak dihitung ke batas masa studi pada instalasi ini.',
        'usulan_melebihi' => 'Usulan yang menunggu (:menunggu SKS) melebihi sisa kuota (:sisa SKS). Tidak semuanya dapat disetujui.',
        'siap_feeder' => 'Data intake lengkap untuk dilaporkan ke PDDIKTI.',
        'terkunci_feeder' => 'Riwayat pendidikan sudah tercatat di PDDIKTI. Perubahan asal harus lewat pembaruan riwayat di Feeder.',
        'belum_dipetakan' => 'Jenis daftar berikut belum dipetakan ke kode PDDIKTI: :jenis. Intake tetap dapat dicatat, tetapi sinkronisasinya akan ditolak sampai pemetaan diisi.',
        'tidak_ada_pendaftar' => 'Tidak ada pendaftar jalur pindahan/RPL yang menunggu registrasi.',
        'kosong' => 'Belum ada mahasiswa pindahan atau RPL',
        'kosong_deskripsi' => 'Daftarkan mahasiswa yang membawa studi dari perguruan tinggi lain atau pengalaman yang diakui.',
        'konversi_kosong' => 'Belum ada usulan konversi. Masukkan baris-baris transkrip asal di atas.',
        'mata_kuliah_kosong' => 'Kurikulum program studi ini belum memiliki mata kuliah untuk dipetakan.',
        'baris_transkrip' => 'Satu baris per mata kuliah asal. Pemetaan diputuskan oleh penilai; batas pengakuan berlaku saat persetujuan.',
    ],

    'sukses' => [
        'intake' => ':nama terdaftar dengan NIM :nim. Lanjutkan dengan transkrip asalnya.',
        'tagihan' => 'Tagihan awal :nominal diterbitkan.',
        'tanpa_tagihan' => 'Belum ada tarif yang berlaku, sehingga tagihan awal belum diterbitkan.',
        'asal' => 'Asal pendidikan diperbarui.',
        'transkrip' => ':jumlah usulan konversi dicatat dan menunggu keputusan.',
    ],

    'validasi' => [
        'jenis' => 'Pilih jenis daftar yang sesuai.',
        'pt_asal' => 'Mahasiswa pindahan dan RPL wajib membawa nama perguruan tinggi asal — Feeder menolak riwayat pendidikan tanpanya.',
        'prodi_asal' => 'Mahasiswa pindahan dan RPL wajib membawa nama program studi asal — Feeder menolak riwayat pendidikan tanpanya.',
        'term_asal_format' => 'Semester masuk di asal ditulis sebagai kode PDDIKTI: tahun lalu 1 (ganjil) atau 2 (genap), mis. 20231.',
        'term_asal_sesudah' => 'Semester masuk di asal harus sebelum semester masuk di sini.',
        'nik' => 'NIK harus 16 digit angka. Feeder menolak biodata tanpa NIK yang sah.',
        'email_dipakai' => 'Surel ini sudah dipakai akun mahasiswa lain.',
        'pendaftar' => 'Pendaftar ini tidak lagi menunggu registrasi.',
        'transkrip_kosong' => 'Isi setidaknya satu baris transkrip asal.',
        'matkul_tujuan' => 'Pilih mata kuliah tujuan dari kurikulum program studi mahasiswa ini.',
        'matkul_ganda' => 'Satu mata kuliah tujuan hanya boleh dipetakan sekali dalam satu transkrip.',
        'nama_asal' => 'Tulis nama mata kuliah asal sebagaimana tertera di transkrip.',
    ],

];
