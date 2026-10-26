<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Uji kompetensi — teks antarmuka
|--------------------------------------------------------------------------
|
| Prodi wajib ukom: lulus akademik lewat yudisium, lalu "Menunggu Uji
| Kompetensi" sampai hasil ujian yang lulus dicatat staf.
|
*/

return [

    'judul' => 'Menunggu Uji Kompetensi',
    'keterangan' => 'Kelulusan akademik sudah ditetapkan; status di PDDIKTI dilaporkan "Menunggu Uji Kompetensi" setiap semester sampai hasil ujian yang lulus dicatat di sini.',
    'kosong' => 'Tidak ada mahasiswa yang menunggu uji kompetensi.',
    'prodi_wajib' => 'Wajib uji kompetensi',
    'prodi_wajib_hint' => 'Lulusan baru dinyatakan Lulus setelah lulus uji kompetensi',
    'akm_keterangan' => 'Menunggu uji kompetensi',

    'kolom' => [
        'mahasiswa' => 'Mahasiswa',
        'yudisium' => 'Yudisium',
        'riwayat' => 'Riwayat Ujian',
        'catat' => 'Catat Hasil',
    ],

    'form' => [
        'tanggal' => 'Tanggal ujian',
        'hasil' => 'Hasil',
        'nomor_sertifikat' => 'Nomor sertifikat (opsional)',
        'simpan' => 'Catat',
        'konfirmasi_lulus' => 'Hasil lulus menetapkan :nama sebagai Lulus dan tidak dapat diubah dari layar ini. Lanjutkan?',
    ],

    'syarat_wisuda' => 'Lulus uji kompetensi',
    'syarat_wisuda_lulus' => 'Lulus :tanggal',
    'syarat_wisuda_belum' => 'Belum ada hasil lulus',

    'sukses' => [
        'lulus' => ':nama lulus uji kompetensi dan kini berstatus Lulus.',
        'belum' => 'Hasil uji kompetensi :nama dicatat; status tetap menunggu uji kompetensi.',
    ],

    'galat' => [
        'prodi_tidak_wajib' => 'Program studi :prodi tidak mewajibkan uji kompetensi.',
        'bukan_menunggu' => ':nama tidak sedang menunggu uji kompetensi (status :status).',
        'tanggal_depan' => 'Tanggal ujian tidak boleh di masa depan.',
        'tanpa_semester' => 'Tidak ada tahun akademik yang mencakup tanggal ujian, dan tidak ada tahun akademik aktif.',
    ],

    'notifikasi' => [
        'menunggu_judul' => 'Yudisium ditetapkan — menunggu uji kompetensi',
        'menunggu_ringkasan' => 'Kelulusan akademik Anda ditetapkan dengan IPK :ipk. Program studi Anda mensyaratkan lulus uji kompetensi; status Anda di PDDIKTI dilaporkan "Menunggu Uji Kompetensi" sampai hasil yang lulus dicatat.',
        'belum_lulus_judul' => 'Hasil uji kompetensi dicatat',
        'belum_lulus_ringkasan' => 'Hasil ujian tanggal :tanggal tercatat belum lulus. Status Anda tetap menunggu uji kompetensi sampai ujian berikutnya.',
    ],

    'feeder' => [
        'status_belum_dipetakan' => 'Status ":status" belum dipetakan ke kode status mahasiswa PDDIKTI (feeder_mappings, grup status_mahasiswa). Kode lokalnya tidak dikirim apa adanya.',
        'lulus_tanpa_ukom' => 'Program studi mewajibkan uji kompetensi, tetapi mahasiswa berstatus Lulus tanpa hasil uji kompetensi yang lulus.',
    ],

];
