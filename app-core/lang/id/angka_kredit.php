<?php

declare(strict_types=1);

return [

    'judul' => 'Buku Besar Angka Kredit',
    'judul_dosen' => 'Angka Kredit Saya',
    'konteks' => ':jumlah dosen aktif · :siap siap diusulkan',

    'pengantar' => 'Buku besar ini menjumlahkan angka kredit dari BKD yang disahkan, ijazah pada portofolio, '
        .'dan isian bagian kepegawaian. Ini bukan penilaian: angka kredit ditetapkan tim penilai di platform '
        .'kementerian. "Siap" hanya berarti angkanya mencukupi syarat yang diatur kampus.',
    'pengantar_dosen' => 'Angka di halaman ini bahan usulan kenaikan jabatan, bukan penetapan. Kinerja sebelum '
        .'2026 dikonversi SKS BKD × 4; kinerja sesudahnya ditandai sebagai perkiraan karena diklaim langsung '
        .'dari LKD/BKD di platform kementerian.',

    'unsur' => [
        'pendidikan' => 'Pendidikan',
        'pengajaran' => 'Pendidikan & Pengajaran',
        'penelitian' => 'Penelitian',
        'pengabdian' => 'Pengabdian kepada Masyarakat',
        'penunjang' => 'Penunjang',
    ],

    'sumber' => [
        'bkd' => 'BKD disahkan',
        'pendidikan_formal' => 'Ijazah (portofolio)',
        'konversi_manual' => 'Konversi BKD lama',
        'penyesuaian' => 'Penyesuaian',
    ],

    'keterangan' => [
        'bkd' => 'BKD :semester',
    ],

    'kolom' => [
        'dosen' => 'Dosen',
        'jabatan' => 'Jabatan',
        'tmt' => 'TMT',
        'ak_terakhir' => 'AK terakhir',
        'dupak' => 'DUPAK',
        'ak_kumulatif' => 'AK kumulatif',
        'berikutnya' => 'Jenjang berikutnya',
        'syarat' => 'Syarat',
        'selisih' => 'Selisih',
        'kesiapan' => 'Kesiapan',
        'jumlah' => 'Dosen',
        'siap' => 'Siap diusulkan',
        'tanggal' => 'Tanggal',
        'semester' => 'Semester',
        'unsur' => 'Unsur',
        'sumber' => 'Sumber',
        'sks' => 'SKS',
        'nilai' => 'AK',
        'keterangan' => 'Keterangan',
    ],

    'field' => [
        'sumber' => 'Jenis isian',
        'unsur' => 'Unsur',
        'semester_kode' => 'Kode semester',
        'sks' => 'SKS BKD',
        'nilai' => 'Angka kredit',
        'tanggal' => 'Tanggal kinerja',
        'keterangan' => 'Keterangan',
    ],

    'petunjuk' => [
        'semester_kode' => 'Kode PDDIKTI, mis. 20231. Hanya semester sebelum 2026.',
        'sks' => 'Dikonversi × 4 menjadi angka kredit.',
        'nilai' => 'Boleh negatif untuk koreksi. Wajib disertai keterangan.',
        'penyetaraan' => 'Dosen tetap berjabatan AA, L, atau LK dengan TMT paling lambat :tanggal. Dosen tanpa NUPTK tidak dimasukkan ke lembar — lengkapi NUPTK-nya lebih dulu.',
        'batas' => 'Kum D dan E masing-masing dibatasi :persen % dari total DUPAK. Angka di kurung adalah yang dikerjakan sebelum dibatasi.',
        'sesudah_tmt' => 'Hanya kinerja sesudah TMT jabatan berlaku yang masuk DUPAK; sebelumnya sudah terhitung dalam AK terakhir.',
        'perkiraan' => 'Memuat kinerja sejak 2026 yang dihitung dengan faktor kampus sebagai perkiraan.',
    ],

    'kesiapan' => [
        'siap' => 'Siap diusulkan',
        'belum' => 'Kurang :selisih',
        'penelitian' => 'Kum C kurang dari :minimum',
        'puncak' => 'Jenjang tertinggi',
    ],

    'aksi' => [
        'ekspor' => 'Ekspor lembar penyetaraan (.xlsx)',
        'susun' => 'Susun ulang',
        'susun_semua' => 'Susun ulang semua',
        'catat' => 'Catat',
        'hapus' => 'Hapus',
        'buka' => 'Buku besar',
    ],

    'pesan' => [
        'disusun' => 'Buku besar :nama disusun ulang dari BKD dan portofolio.',
        'disusun_semua' => 'Buku besar :jumlah dosen disusun ulang.',
        'dicatat' => 'Baris angka kredit tercatat.',
        'dihapus' => 'Baris angka kredit dihapus.',
        'sumber_turunan' => 'Baris dari BKD dan ijazah mengikuti sumbernya dan tidak dicatat atau dihapus dengan tangan.',
        'konversi_lengkap' => 'Konversi membutuhkan kode semester dan SKS BKD lebih dari nol.',
        'konversi_sesudah' => 'Konversi × 4 hanya untuk kinerja sebelum 2026. Semester sesudahnya masuk lewat BKD yang disahkan.',
        'konversi_ganda' => 'Semester dan unsur ini sudah terhitung dari BKD yang disahkan — mengonversinya lagi menghitungnya dua kali.',
        'penyesuaian_lengkap' => 'Penyesuaian membutuhkan angka kredit bukan nol dan tanggal kinerja.',
        'alasan_wajib' => 'Tuliskan keterangan penyesuaian, minimal lima karakter.',
        'tanpa_nuptk' => 'NUPTK kosong',
        'nuptk_format' => 'NUPTK terdiri dari 16 digit angka.',
        'nuptk_disimpan' => 'NUPTK tersimpan.',
        'tertahan' => ':jumlah dosen memenuhi syarat penyetaraan tetapi tidak dimasukkan ke lembar:',
    ],

    'kosong' => [
        'buku' => 'Belum ada baris angka kredit.',
        'rekap' => 'Belum ada dosen aktif.',
    ],

    'per_jenjang' => 'Rekap per Jenjang',
    'per_dosen' => 'Rekap per Dosen',
    'buku' => 'Baris Buku Besar',
    'catat_manual' => 'Catat Baris Manual',
    'tanpa_jabatan' => 'Tenaga Pengajar',
    'perkiraan' => 'perkiraan',
    'tidak_dihitung' => 'sebelum TMT',

];
