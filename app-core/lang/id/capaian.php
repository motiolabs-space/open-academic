<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kurikulum berbasis capaian (OBE) — CPL → CPMK → sub-CPMK
|--------------------------------------------------------------------------
|
| ADR-038. Dipakai layar staf (kelola CPMK, rekap prodi), dosen (CPMK dari
| RPS, pemetaan rencana evaluasi, rekap kelas), dan mahasiswa (capaian).
|
*/

return [

    'nav' => [
        'cpmk' => 'Capaian Mata Kuliah',
        'rekap' => 'Rekap Capaian',
        'kelas' => 'Capaian Kelas',
    ],

    'istilah' => [
        'cpl' => 'CPL',
        'cpmk' => 'CPMK',
        'sub' => 'Sub-CPMK',
        'versi' => 'Versi :versi',
        'ambang' => 'ambang :ambang',
        'cakupan' => 'cakupan :persen%',
    ],

    'galat' => [
        'bukan_kurikulum' => 'Mata kuliah ini tidak termasuk dalam kurikulum yang dipilih.',
        'cpmk_tidak_lengkap' => 'Setiap CPMK harus berkode dan berumusan.',
        'kode_ganda' => 'Kode CPMK :kode dipakai lebih dari sekali.',
        'kode_sub_ganda' => 'Kode sub-CPMK pada :kode harus terisi dan tidak boleh berulang.',
        'cpl_prodi_lain' => 'CPL yang dipilih bukan milik program studi kurikulum ini.',
        'sudah_berlaku' => 'Versi CPMK yang sudah berlaku tidak dapat disunting. Buat versi baru — nilai yang sudah diukur terhadap rumusan lama tidak boleh berubah artinya.',
        'belum_terbit' => 'CPMK belum dapat diterbitkan.',
        'balapan' => 'Versi CPMK mata kuliah ini baru saja diterbitkan orang lain. Muat ulang halaman.',
        'tanpa_versi' => 'Mata kuliah ini belum punya CPMK yang berlaku. Minta program studi atau dosen pengampu menerbitkannya lebih dulu.',
        'komponen_asing' => 'Komponen yang dipetakan bukan milik kelas ini.',
        'sasaran_asing' => 'CPMK atau sub-CPMK yang dipilih bukan milik versi yang berlaku untuk kelas ini.',
        'porsi_bukan_seratus' => 'Porsi pemetaan komponen :komponen berjumlah :jumlah%, seharusnya 100% (atau kosongkan seluruhnya).',
        'sub_bukan_versi_rps' => 'Sub-CPMK pada salah satu pertemuan bukan milik versi CPMK yang dipakai RPS ini.',
        'cpl_dari_cpmk' => 'CPL pada RPS ini mengikuti CPMK yang berlaku dan tidak disunting terpisah.',
    ],

    'kekurangan' => [
        'kosong' => 'Belum ada CPMK.',
        'tanpa_cpl' => ':kode belum dikaitkan ke CPL mana pun.',
        'bobot_sub' => 'Bobot sub-CPMK pada :kode berjumlah :jumlah%, seharusnya 100%.',
        'bobot_cpl' => 'Bobot CPMK yang mendukung :kode berjumlah :jumlah%, seharusnya 100%.',
        'rps_versi_usang' => 'RPS ini memakai versi CPMK yang sudah tidak berlaku. Buka ulang halaman susun untuk memindahkannya.',
    ],

    'audit' => [
        'dihitung' => 'Capaian dihitung saat finalisasi: :jumlah mahasiswa, :cpmk baris CPMK, :cpl baris CPL.',
    ],

    'sukses' => [
        'draf' => 'Rumusan CPMK tersimpan sebagai draf.',
        'terbit' => 'Versi :versi diterbitkan dan berlaku. Versi sebelumnya diarsipkan; kelas yang sudah dinilai dengannya tidak berubah.',
        'pemetaan' => 'Pemetaan capaian tersimpan: :jumlah komponen terpetakan.',
    ],

    'kelola' => [
        'judul' => 'Capaian Mata Kuliah',
        'konteks' => ':prodi · :kurikulum',
        'tanpa_kurikulum' => 'Program studi ini belum punya kurikulum.',
        'pengantar' => 'CPMK menjabarkan janji setiap mata kuliah terhadap CPL program studi; sub-CPMK menjabarkan CPMK menjadi bagian yang dapat dinilai. Satu sumber untuk RPS dan rencana evaluasi.',
        'kolom_mk' => 'Mata Kuliah',
        'kolom_smt' => 'Smt',
        'kolom_status' => 'Versi Berlaku',
        'kolom_draf' => 'Draf',
        'kolom_cpmk' => 'CPMK',
        'belum' => 'belum ada',
        'buka' => 'Kelola',
        'filter_prodi' => 'Program Studi',
        'filter_kurikulum' => 'Kurikulum',
        'tampilkan' => 'Tampilkan',
    ],

    'editor' => [
        'judul' => 'Rumusan CPMK',
        'berlaku_sekarang' => 'Versi berlaku: :versi, diterbitkan :tanggal oleh :oleh.',
        'belum_berlaku' => 'Belum ada versi yang berlaku untuk mata kuliah ini.',
        'sedang_draf' => 'Anda menyunting draf versi :versi. Draf tidak memengaruhi RPS maupun penilaian sampai diterbitkan.',
        'kode' => 'Kode',
        'rumusan' => 'Rumusan',
        'bobot' => 'Bobot %',
        'cpl_didukung' => 'CPL yang didukung (bobot kontribusi %)',
        'pilih_cpl' => '— pilih CPL —',
        'tambah_cpl' => 'Tambah CPL',
        'tambah_sub' => 'Tambah sub-CPMK',
        'tambah_cpmk' => 'Tambah CPMK',
        'hapus' => 'Hapus',
        'jumlah_sub' => 'Jumlah bobot sub-CPMK',
        'tanpa_sub' => 'Tanpa sub-CPMK, komponen nilai memetakan langsung ke CPMK ini.',
        'simpan' => 'Simpan Draf',
        'terbitkan' => 'Terbitkan Versi',
        'konfirmasi_terbit' => 'Terbitkan versi ini? Setelah berlaku, rumusannya dibekukan — revisi berarti versi baru.',
        'kekurangan' => 'Belum dapat diterbitkan',
        'aturan_bobot' => 'Bobot sub-CPMK dalam satu CPMK berjumlah 100%. Untuk setiap CPL, bobot CPMK yang mendukungnya berjumlah 100%.',
        'riwayat' => 'Riwayat Versi',
        'beku' => 'Versi ini berlaku dan dibekukan. Menyimpan perubahan membuat draf versi baru.',
        'mulai_revisi' => 'Mulai Revisi',
    ],

    'pemetaan' => [
        'judul' => 'Pemetaan Capaian',
        'meta' => 'CPMK :mk · versi :versi',
        'pengantar' => 'Isi porsi (%) setiap komponen yang mengukur sub-CPMK atau CPMK. Porsi satu komponen berjumlah 100%, atau kosongkan bila komponen itu tidak dipetakan.',
        'tanpa_versi' => 'Mata kuliah ini belum punya CPMK yang berlaku, sehingga komponen belum dapat dipetakan ke capaian. Nilai tetap dapat diisi seperti biasa.',
        'kelola_cpmk' => 'Susun CPMK',
        'versi_lama' => 'Pemetaan kelas ini memakai versi :versi yang sudah diarsipkan. Menyimpan akan memetakan ulang ke versi yang berlaku.',
        'tak_terukur' => ':jumlah sub-CPMK/CPMK belum diukur komponen mana pun: :daftar.',
        'komponen_tanpa' => 'Komponen tanpa pemetaan: :daftar.',
        'lengkap' => 'Setiap sub-CPMK diukur oleh sedikitnya satu komponen.',
        'komponen' => 'Komponen',
        'jumlah' => 'Jumlah',
        'simpan' => 'Simpan Pemetaan',
        'terkunci' => 'Nilai kelas ini sudah final; pemetaan dibekukan bersama capaian yang dihitung darinya.',
    ],

    'rekap' => [
        'kelas_judul' => 'Capaian Kelas',
        'kelas_belum' => 'Capaian dihitung dan disimpan saat nilai kelas difinalisasi. Sampai saat itu yang tersedia hanya cakupan rencana evaluasi.',
        'kelas_tanpa' => 'Kelas ini difinalisasi tanpa pemetaan komponen ke CPMK, sehingga tidak ada capaian yang tercatat.',
        'prodi_judul' => 'Rekap Capaian Prodi',
        'prodi_kosong' => 'Belum ada capaian tersimpan untuk pilihan ini. Capaian muncul setelah kelas yang komponennya dipetakan ke CPMK difinalisasi.',
        'semua_angkatan' => 'Semua angkatan',
        'angkatan' => 'Angkatan',
        'mahasiswa' => 'Mahasiswa',
        'nim' => 'NIM',
        'nama' => 'Nama',
        'rerata' => 'Rerata',
        'terukur' => 'Terukur',
        'tercapai' => 'Mencapai ambang',
        'ringkasan' => 'Ringkasan per CPL',
        'ringkasan_cpmk' => 'Ringkasan per CPMK',
        'per_mahasiswa' => 'Per Mahasiswa',
        'ekspor' => 'Unduh CSV',
        'cara_baca' => 'Angka capaian adalah rerata terbobot nilai komponen yang mengukur capaian itu, dihitung sekali saat nilai difinalisasi dan hanya berubah lewat koreksi nilai ter-audit. Capaian CPL lintas mata kuliah dibobot SKS.',
        'bukan_dasbor' => 'Rekap operasional untuk program studi — bukan dasbor IKU dan bukan borang.',
        'lihat_kelas' => 'Lihat capaian',
        'tanpa_kelas' => 'Anda tidak mengampu kelas pada semester ini.',
        'status_final' => 'final',
        'status_belum' => 'belum final',
    ],

    'mahasiswa' => [
        'judul_cpl' => 'Capaian CPL (nilai final)',
        'judul_cpmk' => 'Capaian CPMK per Mata Kuliah',
        'diukur_di' => 'Diukur di :daftar',
        'kosong' => 'Belum ada capaian dari nilai final. Capaian tercatat setelah dosen memetakan komponen nilai ke CPMK dan nilai kelasnya difinalisasi.',
        'berjalan' => 'Pengukuran Berjalan',
        'berjalan_meta' => 'pemetaan komponen langsung ke CPL',
    ],

    'rps' => [
        'pengantar' => 'RPS ini memakai CPMK dan sub-CPMK yang sama dengan yang diukur komponen nilai. CPL di atas diturunkan dari CPMK, bukan dipilih terpisah.',
        'kelola' => 'Lihat / revisi CPMK',
        'tanpa_cpmk' => 'Mata kuliah ini belum punya CPMK yang berlaku; CPL dipilih langsung seperti sebelumnya.',
        'susun' => 'Susun CPMK',
    ],

    'koreksi' => [
        'lewat_komponen' => 'Koreksi lewat komponen',
        'simpan' => 'Simpan Koreksi Komponen',
        'alasan' => 'Alasan koreksi (wajib, min. 10 huruf)',
        'konfirmasi' => 'Ubah nilai komponen :nama? Nilai akhir, IPK, dan capaian CPMK/CPL-nya dihitung ulang dan tercatat permanen.',
        'catatan_komponen' => 'Mengoreksi skor komponen menghitung ulang nilai akhir dan capaian CPMK/CPL mahasiswa. Mengoreksi nilai akhir saja tidak mengubah capaian, karena capaian diukur dari komponen.',
        'sukses_komponen' => 'Komponen nilai :nama pada :mk dikoreksi; nilai akhir kini :huruf dan capaiannya dihitung ulang. Semua perubahan tercatat pada jejak audit.',
    ],

    'lkps' => [
        'judul' => 'Capaian Pembelajaran Lulusan',
        'kolom' => ['Kode CPL', 'Capaian Pembelajaran', 'Mahasiswa Terukur', 'Rerata Capaian', 'Mencapai Ambang', '% Mencapai'],
        'catatan' => 'Dihitung dari capaian tersimpan pada nilai final kelas tahun akademik :tahun; ambang :ambang. Capaian lintas mata kuliah dibobot SKS.',
        'tanpa_data' => 'Belum ada capaian CPL tersimpan: komponen nilai belum dipetakan ke CPMK pada kelas yang sudah final dalam rentang tahun ini.',
    ],

];
