# KEPEGAWAIAN.md — Riwayat Kepegawaian Mendalam

Melanjutkan tiga tabel riwayat dari G7 (pendidikan, jabatan fungsional,
sertifikasi) dengan enam lagi: keluarga, pangkat, mutasi, penghargaan & sanksi,
bahasa, organisasi — lalu (#39, 27 Okt 2026) delapan lagi: diklat, pengalaman
kerja, studi lanjut / tugas belajar, tugas dinas, jabatan struktural, inpassing,
kontrak, pensiun. Keempat belasnya kini punya layar dan satu alur validasi.

---

## Tabel bertipe, bukan satu "riwayat" generik

Tabel generik dengan kolom JSON tidak dapat divalidasi, tidak dapat diindeks
secara berguna, dan tidak dapat dipetakan ke sebuah field SISTER tanpa tabel
lookup yang tidak ada yang merawatnya.

---

## Diisi dosen, divalidasi kepegawaian

Setiap riwayat membawa lima kolom yang sama: `status` (diajukan → disetujui /
ditolak), `alasan_tolak`, `divalidasi_oleh`, `divalidasi_at`, `dokumen_path`.
Bawaan kolomnya **diajukan**: jalur yang lupa memilih tidak pernah terbaca
sebagai sesuatu yang sudah diperiksa. Enam tabel lama di-backfill *disetujui*
— seluruh barisnya ditulis staf atau seeder, karena layar dosen belum ada.

| Jalur | Hasil |
|---|---|
| Dosen mengajukan dari `/dosen/kepegawaian` | `diajukan`, menunggu di `/admin/kepegawaian/validasi` |
| Staf mencatat dari `/admin/dosen/{dosen}/kepegawaian` | langsung `disetujui` atas nama staf itu — dialah peninjaunya |
| Staf menyetujui | efek persetujuan berjalan saat itu, dan hanya saat itu |
| Staf menolak | alasan wajib (≥ 5 karakter); baris tetap ada dengan alasannya |
| Dosen menyunting yang ditolak/menunggu | kembali ke antrean, alasan lama dihapus |
| Dosen menyunting yang disetujui | ditolak — minta koreksi ke kepegawaian |

Satu policy (`RiwayatKepegawaianPolicy`) untuk keempat belas model, satu
pasang layar, satu `FormRequest`. Bidang tiap riwayat didefinisikan sekali di
`FormulirRiwayat` dan dibaca aturan validasi, kedua formulir, dan ringkasan
baris. Tabelnya tetap terpisah per riwayat; yang disatukan hanya kosakatanya
(`JenisRiwayat`).

Dokumen pendukung lewat `BerkasService` di disk privat, dibuka hanya lewat
`/berkas/kepegawaian/{jenis}/{uuid}` — dosen pemiliknya atau staf `dosen.view`.

**Mutasi, kontrak, dan pensiun diisi staf saja.** Mutasi memindahkan penunjuk
unit, jadi ajuan bertanggal mundur dari portal akan memindahkan orang saat
disetujui; kontrak adalah dokumen pemberi kerja; pensiun menutup akun. Dosen
membacanya di layarnya, tidak mengajukannya.

Ekspor SISTER hanya memuat baris **disetujui**. Ajuan yang belum diperiksa di
dalam berkas kementerian adalah klaim yang tidak pernah dicek — persis yang
dicegah alur ini.

---

## Hanya empat yang punya aturan

Selebihnya adalah riwayat datar: persetujuannya tidak mengubah apa pun selain
statusnya.

**Pangkat** dan **mutasi** — masing-masing punya nilai *berlaku* yang dibaca hal
lain, dan menjaga penunjuk itu tetap jujur adalah seluruh pekerjaannya.
**Tugas belajar** dan **pensiun** — keduanya mengubah kewajiban orangnya.

### Pangkat: satu yang berlaku

Pola portabel yang sama dengan `jabatan_fungsional_dosen` dan
`tugas_akhir.mahasiswa_aktif_id`: kolom nullable-unique berisi id dosen selagi
berlaku, NULL selebihnya. NULL tidak bertabrakan di MySQL maupun PostgreSQL;
partial index tidak portabel.

Penjaganya ada di **kolom**, bukan hanya di service — ada tesnya yang menyisipkan
langsung dan menuntut `UniqueConstraintViolationException`. Seeder, perbaikan
manual, dan migrasi data ikut tertahan.

Kenaikan pangkat menulis dua baris dalam satu transaksi. Kalau gagal separuh,
yang tertinggal adalah kenaikan yang belum terjadi — dapat diulang dan terlihat
— bukan dosen tanpa pangkat sama sekali.

Yang berlaku adalah pangkat **disetujui dengan TMT terakhir**, bukan yang
terakhir disetujui: dosen yang memasukkan riwayat dua puluh tahun mengajukan SK
lama paling akhir, dan menyetujuinya tidak boleh menurunkan pangkat siapa pun.
Ajuan yang belum disetujui tidak pernah menjadi berlaku. Menghapus pangkat
berlaku menyerahkan statusnya ke pangkat disetujui berikutnya.

### Mutasi: penunjuk dan riwayat ditulis bersama

`dosen.unit_kerja_id` adalah jawaban *sekarang*; `mutasi_dosen` adalah
riwayatnya. Keduanya ditulis bersama, sengaja.

Menurunkan "unit sekarang" dari baris mutasi terakhir memang menghemat satu
kolom, dan menambah satu cara untuk salah: koreksi bertanggal mundur akan
diam-diam memindahkan orang yang tidak pernah pindah, dan setiap layar membayar
subquery untuk menanyakan di mana seseorang bekerja.

Dua hal yang dijaga:

- **Unit asal diambil dari penunjuk, bukan dari formulir.** Formulir bisa
  dikirim setelah orang lain memindahkannya lebih dulu.
- **Keluar mengosongkan unitnya.** Yang sudah keluar tidak lagi tercatat di biro
  lamanya — itulah cara sebuah rekap terus menghitung orang yang sudah
  mengundurkan diri.

Mutasi yang tercatat tidak disunting dan tidak dihapus: ia sudah memindahkan
orang. Koreksinya adalah mutasi baru, sehingga keduanya tetap terlihat.

### Tugas belajar: membebaskan BKD, izin belajar tidak

`JenisStudiLanjut` membedakan **tugas belajar** (dibebaskan dari mengajar) dari
**izin belajar** (belajar sambil tetap bekerja). Tugas belajar yang **disetujui**
dan rentangnya beririsan dengan semester mengeluarkan dosen dari
`BkdService::wajibMelapor()` — juga dari daftar belum-melapor dan rekap BKD —
dan dari `Dosen::wajibBkd()`. Rentang terbuka (tanggal selesai kosong) berarti
masih berjalan. Aturannya dibaca saat ditanya, tidak disalin ke mana pun:
mengoreksi tanggal selesai langsung mengubah kewajibannya.

### Pensiun: menutup akun pada TMT-nya

Pensiun yang disetujui menutup akun begitu TMT-nya tiba — saat dicatat bila
TMT sudah lewat, selebihnya oleh `openacademic:tutup-akun-pensiun` setiap hari
pukul 00.30. Penutupannya lewat penolakan yang sama dengan tombol
"Nonaktifkan": dosen yang masih mengampu kelas semester berjalan atau menjadi
dosen wali **dibiarkan aktif dan dilaporkan**, dan dicoba lagi esok hari.
Faktanya tetap tercatat; yang tertahan hanya akunnya. Menutup akun di tengah
semester meninggalkan kelas tanpa pengisi nilai — itulah yang dicegah
`KepegawaianService::halanganNonaktifDosen()` sejak awal.

---

## Penghargaan dan sanksi: satu tabel, tidak pernah satu angka

Keduanya berbagi setiap kolom dan tidak berbagi aritmetika. Tidak ada apa pun di
aplikasi yang menjumlah, mengurangi, atau menyajikan saldonya — penghargaan
tidak menghapus teguran, dan layar yang menyiratkan sebaliknya sedang membuat
penilaian yang tidak pernah dibuat kampus.

`berkas()` mengembalikannya sebagai **dua daftar**.

Sikap yang sama dengan [`POIN-KEMAHASISWAAN.md`](POIN-KEMAHASISWAAN.md), dengan
alasan yang sama.

---

## `unit_kerja_id` pada dosen

Berbeda dari `prodi_id`. `prodi_id` menyatakan untuk siapa ia mengajar;
`unit_kerja_id` menyatakan siapa yang mempekerjakannya. Dosen yang
diperbantukan ke perpustakaan tetap mengajar, dan rekap per pemberi kerja tidak
boleh mencampur keduanya.

---

## Yang sengaja tidak ditegakkan

- **Jabatan struktural tidak mengurangi beban BKD otomatis.** Regulasi memberi
  aturan tersendiri bagi rektor, dekan, kaprodi; `config/bkd.php` sudah
  menyerahkannya ke asesor, dan riwayat struktural hanya menyediakan faktanya.
- **Kontrak yang berakhir tidak menutup akun.** Kontrak dosen tidak tetap
  lazim diperpanjang sesudah tanggalnya; menutup akun pada tanggal selesai akan
  mengunci orang yang perpanjangannya sedang diproses.
- **Tiga riwayat G7 (pendidikan, jabatan fungsional, sertifikasi) belum masuk
  alur validasi.** Tabelnya lebih tua, dibaca BKD dan portofolio, dan
  memindahkannya mengubah perilaku layar yang sudah dipakai — dikerjakan
  terpisah.
- **Riwayat tenaga kependidikan (staf).** Seluruh tabel ini milik dosen;
  pegawai non-dosen belum punya padanannya.

ADR-028 mencatat alasannya.

---

## Berkas

```
database/migrations/2026_08_18_100003_create_kepegawaian_dosen_tables.php
database/migrations/2026_10_27_160000_create_riwayat_kepegawaian_lanjutan_tables.php
app/Enums/{JenisRiwayat,StatusRiwayat,JenisDiklat,JenisStudiLanjut,JenisPensiun}.php
app/Models/Sdm/*Dosen.php                    — 14 riwayat, HasValidasiRiwayat
app/Traits/HasValidasiRiwayat.php
app/Support/Sdm/FormulirRiwayat.php          — bidang, aturan, ringkasan
app/Services/Sdm/RiwayatKepegawaianService.php
app/Policies/Sdm/RiwayatKepegawaianPolicy.php
app/Http/Requests/Sdm/{RiwayatKepegawaianRequest,TolakRiwayatRequest}.php
app/Http/Controllers/Dosen/KepegawaianController.php
app/Http/Controllers/Admin/RiwayatKepegawaianController.php
app/Console/Commands/TutupAkunPensiunCommand.php
resources/views/kepegawaian/*.blade.php      — dipakai kedua portal
lang/id/kepegawaian.php
database/seeders/Demo/RiwayatKepegawaianSeeder.php
tests/Feature/Sdm/{KepegawaianDosenTest,RiwayatKepegawaianTest}.php
```
