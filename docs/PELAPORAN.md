# PELAPORAN.md — Kewajiban Lapor Kampus & Posisi Open Academic

> Disusun 21 Agustus 2026. Kolom "sudah ada di sini" dibaca dari
> `app-core/app/Models/`, `app-core/app/Services/`, dan migrasi — bukan dari
> ingatan. Sebaliknya, nama sistem dan iramanya **tidak** dibaca dari kode dan
> harus diperiksa ulang ke regulasi berjalan; nomenklatur kementerian dan nama
> aplikasinya berubah beberapa kali dalam tiga tahun terakhir.

Kampus tidak melapor ke satu tempat. PDDIKTI hanya yang paling terlihat.
Dokumen ini memetakan sisanya, menilai mana yang **datanya sudah ada di
Open Academic**, dan menetapkan bagaimana integrasi dipasang supaya pergantian
platform di hulu tidak membongkar modulnya.

---

## Prinsip: ekspor dulu, adaptor kemudian

Satu aturan yang sudah dipakai modul BKD dan berlaku untuk semua tujuan baru:

**Setiap tujuan pelaporan wajib punya ekspor yang bekerja tanpa kredensial apa
pun, sebelum adaptornya ditulis.**

Alasannya bukan kehati-hatian. Ekspor adalah satu-satunya bagian yang tetap
hidup ketika endpoint berubah bentuk, kredensial kedaluwarsa, servernya mati di
minggu terakhir pelaporan, atau seorang dekan meminta angkanya dalam lembar
kerja. Adaptor yang mulus pun tidak menghapus kebutuhan itu.

Konsekuensinya: sebuah tujuan berstatus "berguna" jauh sebelum berstatus
"terintegrasi", dan tidak ada pekerjaan yang menganggur menunggu kredensial.

---

## Peta kewajiban lapor

| Tujuan | Yang dilaporkan | Irama | Data di Open Academic |
|---|---|---|---|
| **PDDIKTI / Neo Feeder** | Mahasiswa, kelas, KRS, nilai, aktivitas kuliah, lulusan | Per semester | **Lengkap & tersambung** |
| **SISTER** | Biodata dosen, riwayat pendidikan, jabfung, pangkat, sertifikasi, penugasan | Semester & insidental | **Lengkap, adaptor belum ada** |
| **BKD** (lewat SISTER) | Beban kerja per semester; penentu tunjangan sertifikasi | Per semester | **Dihitung, bukan diketik** |
| **PIN & SIVIL** | Penomoran ijazah nasional & verifikasi publik | Per kelulusan | **Timpang — lihat Risiko** |
| **KIP Kuliah** (Puslapdik) | Status aktif, IPK, SKS penerima | Per semester | **Terakit & terekspor** |
| **Akreditasi LKPS** (BAN-PT / LAM) | Tabel kuantitatif kinerja prodi | Per siklus | **Sebagian besar ada** |
| **IKU PT** | Kinerja perguruan tinggi | Tahunan | Sumbernya ada & terverifikasi |
| **MBKM** | Kegiatan luar kampus & konversi SKS | Per semester | Ada seluruhnya |
| **Tracer Study** | Keterserapan lulusan | Tahunan | Kerangka saja |
| **BIMA** (eks Simlitabmas) | Usulan & laporan penelitian/PkM | Tahunan | **Bukan data aplikasi ini** |
| **SINTA** | Indeks publikasi & penulis | Sinambung | **Bukan data aplikasi ini** |
| **SNPMB** | Kuota & hasil seleksi nasional (PTN) | Tahunan | Jalur mandiri saja |
| **SAKTI / SIASN / BMN** | Keuangan & kepegawaian negara (PTN) | Bulanan | **Di luar cakupan** |
| **EMIS** (Kemenag) | Padanan PDDIKTI untuk PTKI | Per semester | Setara PDDIKTI bila digarap |

---

## Tiga lapis kelayakan

### Lapis 1 — datanya sudah ada, tinggal adaptor

Pekerjaan di sini bukan mengumpulkan data. Data itu sudah terkumpul sebagai
efek samping menjalankan semester.

**SISTER.** Dua belas tabel di `app/Models/Sdm/` berpadanan langsung dengan
kelompok data SISTER: `RiwayatPendidikanDosen`, `JabatanFungsionalDosen`,
`PangkatDosen`, `SertifikasiDosen`, `PenghargaanSanksiDosen`,
`OrganisasiDosen`, `KeluargaDosen`, `BahasaDosen`, `MutasiDosen`,
`PenugasanDosen`, `BkdLaporan`, `BkdBaris`.

> **Koreksi 21 Agustus 2026.** Kalimat semula di sini berbunyi "yang belum ada
> hanya kliennya". Itu keliru, dan ketahuan saat ekspornya dikerjakan: **enam
> kelompok tidak punya layar pengisian sama sekali** — penghargaan & sanksi,
> bahasa, organisasi profesi, keluarga (tanpa apa pun), serta pangkat dan
> mutasi (servisnya ada, pemanggilnya tidak). Skemanya ada; datanya tidak.
>
> Perbedaannya penting: satu berarti menulis adaptor, satu lagi berarti
> menulis formulir lebih dahulu.

Tetap pengembalian besar per satuan kerja — enam kelompok siap tanpa pekerjaan
pengumpulan data apa pun, dan keenamnya sudah dapat diekspor sekarang.

**KIP Kuliah.** Yang diminta per semester — status keaktifan, IPK, SKS tempuh —
persis isi `StatusMahasiswa`, `PerolehanAkademik`, dan modul beasiswa. Belum
pernah dirakit menjadi satu keluaran.

**MBKM.** `aktivitas_mahasiswa` menyimpan jenis, mitra, lokasi, tanggal, dan
`sks_konversi`; modul Konversi Kredit menutup sisi akademiknya.

### Lapis 2 — perlu lapisan agregasi, bukan data baru

**LKPS akreditasi.** Tabel kuantitatif LKPS sebagian besar adalah pertanyaan
yang jawabannya sudah tersimpan: daya tampung dan pendaftar (PMB), rasio
dosen–mahasiswa (`PenugasanDosen`), EWMP (BKD), sebaran IPK dan masa studi
(`Yudisium`), kelulusan tepat waktu (`EvaluasiStudi`), kepuasan mahasiswa
(EDOM). Yang belum ada adalah perakitnya.

Ini sasaran paling bernilai yang belum diklaim siapa pun sesudah PDDIKTI — dan
yang paling banyak memakan waktu manusia hari ini, karena dikerjakan dengan
menyalin antar-tab menjelang tenggat.

**IKU.** Catatan sumbernya ada dan sudah lewat verifikasi bernama
(`IkuRecordController`). Agregasi dan dasbornya milik Open Campus — lihat
[`KINERJA.md`](KINERJA.md); yang perlu dijaga di sini adalah Bridge tetap
menyajikan barisnya, bukan angkanya.

### Lapis 3 — bukan data aplikasi ini

**BIMA dan SINTA** menuntut sistem manajemen penelitian: usulan, review,
kontrak, luaran, kekayaan intelektual. `penugasan_dosen` menyimpan kegiatan
yang **dilaporkan sendiri oleh dosen** untuk keperluan BKD dan IKU — itu bukan
basis data penelitian, dan memperlakukannya sebagai basis data penelitian akan
menghasilkan usulan yang ditolak beserta keyakinan palsu bahwa kampus sudah
punya sistemnya.

**Tracer study, SNPMB, SAKTI/SIASN** — masing-masing sudah punya pemilik; yang
pertama Open Campus, dua sisanya bukan milik SIAKAD mana pun.

---

## Risiko yang ditemukan saat kajian

**Nomor ijazah diberikan secara lokal.** `wisuda_peserta.nomor_ijazah` diisi
dari dalam aplikasi. Penomoran ijazah nasional bekerja terbalik: kampus
**meminta** nomor ke layanan PIN, dan nomor itulah yang dicetak, lalu
terverifikasi publik lewat SIVIL.

Ijazah yang bernomor lokal tanpa pernah melewati PIN akan tercetak rapi,
diserahkan pada wisuda, dan **gagal saat diperiksa** — biasanya oleh calon
pemberi kerja, bertahun-tahun kemudian, dan menjadi persoalan alumni yang
bersangkutan. Kegagalannya sunyi di sisi kampus dan mahal di sisi lulusan.

Ini perlu keputusan, bukan tebakan: apakah kampus sasaran menomori ijazah lewat
PIN. Bila ya, kolom itu harus berhenti diisi sendiri dan mulai diisi dari hasil
permintaan.

---

## Arsitektur bertahap

Rangkanya **sudah ada** — dibangun untuk Neo Feeder, dan kebetulan berbentuk
persis yang dibutuhkan untuk bertahan melewati pergantian platform:

```
FeederClientInterface   ← angkutan; palsu dipakai suite & kampus demo
FeederMapper            ← bentuk muatan per entitas
FeederMapping (tabel)   ← kode lokal → kode nasional, di data bukan di kode
FeederSyncService       ← buku besar, hash, ulang-aman
```

Tabel `FeederMapping` itu yang menentukan. Kampus dengan build Feeder lebih
lama mengubah satu baris tabel, bukan satu baris kode. Perbedaan kode antar
platform tidak perlu menyentuh program sama sekali.

### Empat tahap

**Tahap 0 — ekspor.** Berkas untuk setiap tujuan baru. Tanpa kredensial,
berguna sejak hari pertama, tetap hidup saat integrasi mati.

**Tahap 1 — adaptor eksisting.** Satu kontrak per tujuan, mengikuti
`FeederClientInterface`; satu implementasi palsu; satu implementasi sungguhan
di belakang kredensial. Layarnya menyatakan terus terang mana yang belum
tersambung, alih-alih menyamarkannya sebagai "terintegrasi".

**Tahap 2 — muatan kanonis.** Mapper hari ini menulis langsung ke kosakata
Feeder. Untuk tujuan berikutnya, mapper menghasilkan bentuk **internal**, lalu
penerjemah per-platform mengubahnya ke bentuk tujuan. Platform baru berarti
satu penerjemah baru — bukan modul baru.

> Catatan jujur: `FeederMapper::act()` mengembalikan nama aksi RPC gaya Feeder
> (`InsertBiodataMahasiswa`). Itu pas untuk Feeder dan **tidak akan pas** untuk
> platform REST atau GraphQL. Di situlah `act()` naik dari kelas dasar ke
> penerjemah — perubahan kecil bila dikerjakan saat menambah tujuan kedua, dan
> perubahan besar bila ditunda sampai tujuan kelima.

**Tahap 3 — peralihan berdampingan.** Dua adaptor menyala bersamaan selama masa
transisi, dipilih lewat config, plus **pembanding** yang melaporkan di mana
keduanya berbeda.

Pembanding itu sudah tercatat sebagai belum ada di [`ROADMAP.md`](ROADMAP.md)
("Komparasi data SIAKAD ↔ Neo Feeder"). Kajian ini menaikkan kepentingannya:
tanpa alat itu, peralihan platform adalah perpindahan tanpa cara memeriksa
apakah yang tiba sama dengan yang berangkat — dan pelaporan yang salah
diam-diam lebih buruk daripada pelaporan yang gagal berisik.

---

## Urutan yang disarankan

| # | Pekerjaan | Alasan |
|---|---|---|
| 1 | ~~Pembanding PDDIKTI~~ | **Selesai 21 Agu 2026** — lihat di bawah |
| 2 | ~~Ekspor SISTER~~ | **Selesai 21 Agu 2026** — 8 dari 12 kelompok; 4 sisanya perlu formulir dulu |
| 3 | ~~Perakit LKPS~~ | **Selesai 21 Agu 2026** — kalkulator kanonis + perakit borang; delapan definisinya menunggu keputusan kampus |
| 4 | **Keputusan PIN** | Risiko, bukan fitur — dan jawabannya menentukan apakah nomor ijazah boleh terus diisi sendiri |
| 5 | **Muatan kanonis** | Dikerjakan saat menambah tujuan kedua, bukan kelima |
| 6 | ~~Ekspor KIP Kuliah~~ | **Selesai 21 Agu 2026** — lihat di bawah |

Ketiganya tidak menunggu kredensial siapa pun dan tidak mengandaikan bentuk
platform PDDIKTI berikutnya.

Pekerjaan baru yang muncul dari nomor 3: **delapan definisi harus diputuskan
kampus** sebelum angka LKPS boleh masuk borang sungguhan — lihat
[`LKPS-DEFINISI.md`](LKPS-DEFINISI.md). Kalkulatornya sudah jalan memakai
bawaan yang konservatif, dan layarnya menyatakan sendiri bahwa definisinya
masih sementara.

Pekerjaan baru yang muncul dari nomor 2: **formulir untuk enam kelompok SISTER
yang belum dapat diisi** — penghargaan & sanksi, bahasa, organisasi profesi,
keluarga (keempatnya tanpa apa pun), serta pangkat dan mutasi (keduanya sudah
bersevis, tinggal layarnya). Kecil masing-masing, tetapi tanpa keenamnya
portofolio SISTER tidak akan pernah lengkap — adaptor yang mulus di atas tabel
kosong tetap mengirim kosong.

---

## Pembanding PDDIKTI — selesai 21 Agustus 2026

`FeederRekonsiliasi` membaca Feeder kembali dan melaporkan empat keadaan:
**hanya di SIAKAD**, **hanya di Feeder**, **isinya berbeda** (beserta field
mana), dan **tidak dapat dicocokkan**.

Keempatnya dipisah karena penyebab dan penanganannya berbeda. Yang paling
penting adalah yang kedua: baris yang **hanya ada di Feeder** — diketik
langsung di sana, atau tertinggal dari sistem sebelumnya — tidak akan pernah
terlihat oleh sinkronisasi satu arah, sebaik apa pun buku besarnya dijaga.
Buku besar mencatat apa yang berangkat; ia tidak dapat mencatat apa yang ada
di seberang.

### Tiga aturan yang menjaganya tetap jujur

Sebuah pembanding gagal dengan cara yang khas: ia melaporkan "nol selisih"
padahal sebetulnya tidak memeriksa apa pun. Nol selisih dan tidak pernah
diperiksa terlihat persis sama di layar, dan hanya satu di antaranya yang
berarti aman.

1. **Entitas tanpa aksi pembacaan menolak dibandingkan** — dan berkata
   "belum dapat dibandingkan" di layarnya, bukan diam-diam terbaca bersih.
2. **Galat dari Feeder menghentikan perbandingan.** Nama aksi yang salah
   akan mengembalikan nol baris, dan nol baris tampak seperti kesepakatan
   sempurna.
3. **Baris lokal yang kuncinya tidak lengkap dilaporkan, bukan dibuang.**
   Baris yang tak dapat dicocokkan bukan baris yang cocok.

Ketiganya diuji dengan cara dilumpuhkan satu per satu: masing-masing membuat
tes yang bersangkutan — dan hanya itu — menjadi merah.

### Yang sengaja dibatasi

Baru tiga entitas yang dapat dibandingkan: **kelas kuliah, aktivitas kuliah,
dan KRS** — yang dapat disaring per semester dan paling sering berselisih.

Biodata mahasiswa **sengaja tidak** dibandingkan. Satu-satunya fieldnya yang
unik adalah NIK, dan mencocokkan lewat NIK berarti menyalin NIK setiap
mahasiswa ke tabel selisih dan ke layar yang menampilkannya berderet. Riwayat
pendidikan membawa NIM dan mencakup mahasiswa yang sama tanpa itu — jalur itu
yang perlu ditempuh, bukan NIK.

Nilai perkuliahan belum dapat dibandingkan karena Feeder menyaringnya per
kelas, bukan per semester: satu permintaan per kelas kuliah. Layak, tetapi
bentuk pekerjaannya berbeda.

### Kesiapan menghadapi platform berikutnya

Nama aksi dan field kuncinya ada di `config/feeder.php` bagian `reconcile`,
bukan di dalam kode — perbedaan antar build Feeder diselesaikan dengan
menyunting config, sama seperti `FeederMapping` menyelesaikan perbedaan kode
di data. Nama aksi yang keliru tidak menghasilkan laporan kosong yang
menenangkan; ia menghasilkan galat berisi pesan Feeder, jadi ia mengoreksi
dirinya sendiri pada percobaan pertama.

---

## Ekspor SISTER — selesai 21 Agustus 2026

`EksporSister` menghasilkan satu CSV per kelompok data SISTER, dan tabel
katalognya ada di layar BKD (`/admin/bkd`).

| Kelompok | Keadaan |
|---|---|
| Biodata dosen | ✅ tanpa NIK & alamat rumah |
| Riwayat pendidikan | ✅ |
| Jabatan fungsional | ✅ berikut angka kredit |
| Pangkat & golongan | ⚠️ ekspornya jalan, layar pengisiannya belum |
| Sertifikasi & pelatihan | ✅ |
| Mutasi & penempatan | ⚠️ ekspornya jalan, layar pengisiannya belum |
| Penugasan tridarma | ✅ sudah ada sebelumnya |
| Rekap BKD | ✅ sudah ada sebelumnya |
| Penghargaan & sanksi | ⬜ belum ada layar pengisian |
| Kemampuan bahasa | ⬜ belum ada layar pengisian |
| Organisasi profesi | ⬜ belum ada layar pengisian |
| Anggota keluarga | ⛔ **sengaja tidak diekspor** |

### Dua penolakan yang disengaja

**Kelompok yang tidak dapat direkam tidak diekspor**, dan tidak pula
disembunyikan dari daftar. Berkas berisi baris judul saja terbaca sebagai
"kampus ini tidak punya dosen dengan keanggotaan profesi" — padahal artinya
"aplikasi ini belum bisa menyimpannya". Rutenya membalas 404, bukan berkas
kosong, dan layarnya menuliskan alasannya.

**Anggota keluarga tidak masuk ekspor sama sekali.** SISTER menyimpannya;
CSV yang beredar lewat surel adalah saluran yang berbeda dari pengiriman ke
kementerian, dan nama serta tanggal lahir anak seorang dosen tidak punya
urusan di saluran yang ceroboh. Aturan yang sama sudah menahan NIK keluar
dari bentuk yang dibagikan.

Perbedaan antara ⬜ dan ⛔ dijaga di katalog karena keduanya menuntut
penanganan yang berbeda: yang pertama pekerjaan yang belum dilakukan, yang
kedua keputusan yang tidak untuk dibatalkan diam-diam.

## Perakit LKPS — selesai 21 Agustus 2026

Dua lapis, dan pemisahannya yang menentukan.

**`IndikatorLkps`** menghitung besaran kanonisnya sekali: corong penerimaan dan
keteketatan, mahasiswa aktif, DTPS dan rasionya, sebaran IPK, masa studi,
ketepatan waktu, putus studi.

**`PerakitBorang`** menempatkannya ke tabel. Lapisan ini tipis dengan sengaja —
LAM kedua adalah blok config kedua, bukan perakit kedua.

Layarnya di `/admin/lkps`, beserta unduhan CSV.

### Tiga hal yang dijaga

**Nomor tabel tidak dikarang.** `lkps.borang.*.nomor` dikirim kosong. Nomor
tabel berbeda antar-LAM dan berubah antar-revisi instrumen, dan nomor yang
tampak masuk akal lebih berbahaya daripada yang kosong: seseorang akan
menyalinnya ke borang sungguhan tanpa memeriksa.

**Definisinya tercetak di layar.** Kelima aturan yang dipakai menghitung muncul
di atas tabelnya. Orang yang membaca angkanya adalah orang yang perlu tahu
aturan mana yang menghasilkannya, dan ia tidak akan membuka dokumen untuk
mencari tahu.

**Sel kosong ditulis `—`, bukan nol.** Prodi tanpa lulusan menghasilkan IPK
rata-rata yang TIDAK ADA, bukan 0,00 — dan 0,00 di kolom IPK borang akreditasi
adalah tuduhan terhadap prodinya. Berlaku pula untuk rasio tanpa penyebut.

Tabel yang tidak dapat diisi — tracer study, penelitian/PkM, kepuasan layanan —
muncul beserta alasannya, termasuk di dalam CSV-nya. Menghilangkannya dari
berkas lebih buruk daripada tidak berguna: yang menempelkannya ke borang akan
mendapati kelompok itu hilang dan mengira memang tidak ditanyakan.

### Satu gejala yang tersingkap

`kecualikan_alih_jenjang` bergantung pada tabel `FeederMapping`: pembedaan alih
jenjang tidak tersimpan langsung, melainkan lewat `jalur_masuk` yang dipetakan
ke kode `jenis_daftar` PDDIKTI. Kampus yang belum mengisi pemetaan itu tidak
dapat membedakannya, sehingga pengecualian yang diminta config diam-diam tidak
terjadi. Kalkulatornya mengembalikan angkanya beserta catatan bahwa pemisahan
gagal — bukan menebak.

---

### Angka benar, kesimpulan salah

Pangkat dan mutasi menandai jenis kekeliruan yang ketiga, dan yang paling
halus. Ekspornya berfungsi dan `RiwayatKepegawaianService` dapat menulisnya —
tetapi **tak satu pun layar memanggil servis itu**. Kampus membuka daftarnya,
membaca "0 baris", lalu menyimpulkan tidak ada kenaikan pangkat tercatat.
Angkanya benar; kesimpulannya salah.

Karena itu catatannya menempel pada barisnya, bukan pada dokumentasi yang
tidak akan dibuka orang saat membaca angka nol.

**Jadi enam kelompok, bukan empat, yang belum punya layar pengisian.** Empat
tanpa apa-apa, dua sudah bersevis. Itu pekerjaan yang harus mendahului
adaptor SISTER mana pun — adaptor yang mulus di atas tabel kosong tetap
mengirim kosong.

---

## Ekspor KIP Kuliah — selesai 21 Agustus 2026

Laporan semester Puslapdik: NIM, prodi, status keaktifan, semester ke-, SKS
semester dan kumulatif, IPS, IPK. Seluruhnya sudah ada di `status_mahasiswa`;
tidak ada data baru yang perlu dikumpulkan. Layarnya menumpang pada
`/admin/beasiswa`.

### Tiga hal yang dilaporkan, bukan dirapikan

**Penerima tanpa baris status semester ini tetap masuk berkas**, dengan
keterangan `TIDAK ADA STATUS SEMESTER INI` pada barisnya. Membuangnya adalah
cara seseorang terus menerima dana sementara tidak ada yang menyadari ia
berhenti kuliah. Keterangannya di dalam berkas, bukan di surat pengantar: yang
mengunggahnya tidak memegang surat itu, dan sel kosong di samping sebuah nama
terbaca sebagai fakta tentang orangnya.

**Nilai yang belum final ditandai.** `is_final` false berarti nilainya masih
bergerak. Melaporkannya tanpa keterangan berarti mengirim angka yang akan
dibantah kampusnya sendiri dua minggu kemudian.

**Skema yang belum ditetapkan menolak berjalan.** Aplikasi ini tidak tahu skema
mana yang KIP Kuliah — tabel `beasiswa` hanya membedakan internal dan
eksternal. Rutenya membalas 404 dan layarnya menyebut sebabnya. Kode yang salah
ketik muncul sebagai "kode ini tidak ada di tabel beasiswa", bukan sebagai
laporan tanpa siapa pun di dalamnya.

### Privasi

KIP Kuliah berbasis kemampuan ekonomi, jadi penghasilan orang tua dan alamat
rumah adalah data yang paling menggoda untuk ikut disertakan — dan berkas ini
beredar lewat surel serta folder bersama, saluran yang berbeda dari pengiriman
resmi. Keduanya tidak masuk. Pengenalnya NIM saja, konvensi yang sama dipakai
kontak akuntansi, dan tesnya membuktikan NIK maupun alamat memang tidak ada di
berkasnya.

### Satu bug yang tertangkap tes

`status_mahasiswa.status` adalah enum, dan `fputcsv` tidak dapat mengubah enum
menjadi teks: seluruh unduhan akan gagal di produksi. Ketahuan hanya karena tes
privasi benar-benar membaca isi berkasnya alih-alih memeriksa HTTP 200.
Diperbaiki menjadi kode hurufnya, yang memang konvensi pelaporan.

### Konfigurasi lewat .env

`KIPK_BEASISWA_KODE`, bukan dengan menyunting `config/kipk.php` — berkas config
terlacak Git, dan kampus yang mengubahnya akan bertabrakan setiap kali menarik
pembaruan. Konflik semacam itu biasanya diselesaikan dengan mengambil versi
hulu, yang diam-diam mengosongkan kembali skemanya.
