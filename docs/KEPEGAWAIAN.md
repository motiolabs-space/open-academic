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

---

# Angka kredit — buku besar Kum A–E (#40)

Buku besar, **bukan dasbor dan bukan penilaian.** Penilaian PAK terjadi di
platform kementerian, oleh tim penilai yang jabatannya minimal satu tingkat di
atas yang dinilai, dan disahkan dengan TTE. Tidak ada alur tim penilai, plotting,
atau tanda tangan di sini, dan tidak akan ada. Yang dikerjakan aplikasi ini:
menyimpan baris yang akan ditanyakan platform itu, menjumlahkannya seperti
borangnya menjumlah, dan menyatakan seberapa jauh jumlah itu dari syarat jenjang
berikutnya.

## Lima unsur, bukan empat

| Kum | Unsur | Sumber di Open Academic |
|---|---|---|
| A | Pendidikan (ijazah) | `riwayat_pendidikan_dosen` — S2 150, S3 200 (`config/angka_kredit.php`) |
| B | Pendidikan & pengajaran | unsur *pendidikan* BKD yang disahkan |
| C | Penelitian | unsur *penelitian* BKD yang disahkan |
| D | Pengabdian | unsur *pengabdian* BKD yang disahkan |
| E | Penunjang | unsur *penunjang* BKD yang disahkan |

Unsur "pendidikan" BKD adalah **Kum B**, bukan Kum A — satu-satunya pemetaan
yang mudah keliru, jadi ia tinggal di `UnsurAngkaKredit::dariUnsurBkd()` saja.

## Empat sumber baris

| Sumber | Ditulis oleh | Disunting? |
|---|---|---|
| BKD disahkan | `BkdService::sahkan()` → `catatBkd()` | Tidak — mengikuti laporannya |
| Ijazah (portofolio) | simpan riwayat pendidikan → `catatPendidikan()` | Tidak — mengikuti ijazahnya |
| Konversi BKD lama | staf, layar buku besar | Hapus dan catat ulang |
| Penyesuaian | staf, dengan keterangan wajib; boleh negatif | Hapus dan catat ulang |

"Disetujui" untuk BKD berarti **disahkan** — ditandatangani lembaga — bukan
sekadar diajukan atau dinilai. Baris turunan disusun ulang di tempat
(`openacademic:susun-angka-kredit`, tombol "Susun ulang"); menyusun dua kali
memperbarui baris yang sama, dijaga indeks unik per sumber per unsur. Baris
manual tidak pernah disentuh penyusunan ulang.

## Konversi: SKS BKD × 4, hanya sebelum 2026

Kinerja yang **mulai sebelum 1 Januari 2026** dikonversi `SKS × 4 = AK`
(ketentuan layanan PAK 2026). 20251 (mulai Agustus 2025) masih terkonversi;
20252 (Februari 2026) tidak. Sesudahnya, kinerja diklaim langsung dari LKD/BKD
di platform kementerian — buku besar tetap mencatatnya dengan faktor kampus
(`ANGKA_KREDIT_FAKTOR_SESUDAH_RATUS`, bawaan 400) supaya kesiapan dapat
diperkirakan, tetapi barisnya ditandai **perkiraan** di layar.

Konversi manual ditolak untuk semester sejak 2026, dan untuk semester × unsur
yang sudah terhitung dari BKD disahkan — menghitung pekerjaan yang sama dua kali
adalah kesalahan paling mungkin saat kampus mengisi arsip lama.

Semua angka dalam **perseratus** sebagai integer, alasan yang sama dengan
`BKD_*_RATUS`. Float hanya ada di sel ekspor.

## Hanya sesudah TMT jabatan berlaku

Rekap menjumlah **AK kumulatif terakhir** (angka kredit pada jabatan berlaku)
dengan baris yang kinerjanya selesai **sesudah TMT** jabatan itu — yang sebelumnya
sudah terhitung di dalamnya. Ijazah dibandingkan per tahun dan hanya tahun yang
lebih kemudian yang dihitung: ijazah yang lulus pada tahun pengangkatan hampir
pasti yang mengantarkannya. Kesalahan yang lebih aman adalah meremehkan kesiapan.

## Batas Kum D dan E: titik tetap, bukan sepuluh persen dari total mentah

Pengabdian dan penunjang masing-masing paling banyak **10 % dari total DUPAK**.
Ditegakkan pada **rekap**, bukan pada baris: buku besar menyimpan yang dikerjakan,
rekap menyajikan yang dapat diakui, dan layar menampilkan keduanya.

Sepuluh persen dari *total sesudah dibatasi* — angka yang tertulis di lembar
dan diperiksa saat diunggah. A+B+C = 100 dan D = 50: 10 % dari 150 adalah 15,
tetapi 15 dari total 115 adalah 13 %. `batasiUnsur()` menurunkan D dan E sampai
tidak satu pun melebihi bagiannya dari jumlah yang memuat keduanya — (S+E)/9
bila hanya D yang terbatas, S/8 bila keduanya — dan tidak pernah menaikkan
unsur di atas yang sebenarnya dikerjakan.

## Kesiapan usulan

Per jabatan **tujuan**, dari `config/angka_kredit.php`:

- AK kumulatif minimum (bawaan `JabatanFungsional::angkaKreditMinimum()`);
- bagian minimum Kum C dari AK yang **masih dibutuhkan** untuk naik (bawaan 0 /
  35 / 40 / 45 % untuk AA / L / LK / GB) — tafsir pedoman yang wajib diperiksa
  kampus sendiri, sikap yang sama dengan rubrik BKD.

"Siap" = selisih nol **dan** bagian penelitian terpenuhi. Itu pernyataan tentang
angka, bukan janji persetujuan.

## Lembar penyetaraan (.xlsx)

Untuk dosen tetap berjabatan AA/L/LK dengan TMT paling lambat 31 Desember 2023
(`ANGKA_KREDIT_PENYETARAAN_TMT`). Bahwa kampusnya PTNBH/PTS adalah syarat
lembaga, tidak diperiksa per baris.

| Kolom | Isi | Sel |
|---|---|---|
| NUPTK | `dosen.nuptk` | teks |
| Nama | `dosen.nama`, tanpa gelar | teks |
| Jabatan Fungsional | AA / L / LK | teks |
| TMT Jabatan Fungsional | `TTTT-BB-HH` | teks |
| AK Kumulatif Terakhir | AK jabatan berlaku | angka, 2 desimal |
| Kum A … Kum E | yang diakui (D/E sesudah dibatasi) | angka |
| Total DUPAK | A+…+E diakui | angka |
| AK Penyetaraan | AK terakhir + total DUPAK | angka |

NUPTK dan TMT ditulis sebagai **sel teks**: ditulis sebagai angka, Excel
"membantu" NUPTK menjadi notasi ilmiah dan TMT menjadi nomor seri tanggal, lalu
unggahan yang mencocokkannya dengan PDDIKTI menolak barisnya. Dosen tanpa NUPTK
**tidak dimasukkan** dan disebut di layar — satu baris tak cocok adalah unggahan
gagal yang ditemukan sesudahnya. Penulisnya `LembarKerjaXlsx`, ±150 baris di atas
ext-zip, bukan pustaka spreadsheet. DUPAK PDF per dosen dan nomor surat tetap
disiapkan di luar aplikasi.

## Berkas

```
database/migrations/2026_10_27_170000_create_angka_kredit_dosen_table.php  — + dosen.nuptk
config/angka_kredit.php
app/Enums/{UnsurAngkaKredit,SumberAngkaKredit}.php
app/Models/Sdm/AngkaKreditDosen.php
app/DTOs/Sdm/{RekapAngkaKredit,AngkaKreditManualData}.php
app/Services/Sdm/AngkaKreditService.php
app/Support/LembarKerjaXlsx.php
app/Policies/Sdm/AngkaKreditDosenPolicy.php
app/Http/Controllers/{Admin,Dosen}/AngkaKreditController.php
app/Console/Commands/SusunAngkaKreditCommand.php
resources/views/angka-kredit/*.blade.php
lang/id/angka_kredit.php
database/seeders/Demo/AngkaKreditSeeder.php
tests/Feature/Sdm/AngkaKreditTest.php
```
