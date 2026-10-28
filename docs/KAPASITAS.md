# Kapasitas & Kinerja

Angka di halaman ini **hasil pengukuran**, bukan perkiraan. Diukur 7 Agustus
2026 pada satu mesin pengembangan (XAMPP, MariaDB 10.4, PHP 8.2, `artisan
serve` — server pengembangan bawaan, satu proses, tanpa OPcache produksi,
tanpa cache konfigurasi/rute).

**Instalasi produksi akan lebih cepat dari ini**, bukan lebih lambat: Nginx +
PHP-FPM dengan `config:cache` dan `route:cache` biasanya memangkas 50–100 ms
overhead per permintaan yang di sini ikut terhitung.

---

## Beban Uji

| | Jumlah |
|---|---|
| Mahasiswa | 5.000 |
| KRS (3 semester) | 15.000 |
| Baris KRS (`krs_detail`) | 118.937 |
| Nilai | 118.575 |
| **Presensi** | **631.220** |
| Ukuran basis data | **288 MB** |

Presensi adalah tabel terbesar dengan selisih jauh — 197 MB dari 288 MB, dan
138 MB di antaranya indeks. Satu mahasiswa purnawaktu menghasilkan ±128 baris
presensi per semester (8 mata kuliah × 16 pertemuan).

### Proyeksi ukuran

| Mahasiswa aktif | Per semester | 4 tahun (8 semester) |
|---|---|---|
| 1.000 | ~40 MB | ~320 MB |
| 5.000 | ~200 MB | ~1,6 GB |
| 10.000 | ~400 MB | ~3,2 GB |
| 20.000 | ~800 MB | ~6,4 GB |

Basis data sebesar ini biasa saja untuk satu VPS. Yang menentukan bukan
ukurannya, melainkan apakah kuerinya memakai indeks.

---

## Hasil Pengukuran (5.000 mahasiswa)

Median dari 5 permintaan, setelah satu permintaan pemanasan.

| Layar | Median | Keterangan |
|---|---|---|
| Dasbor mahasiswa | 210 ms | |
| KHS | 221 ms | |
| KRS (katalog) | 262 ms | |
| Dasbor dosen | 239 ms | |
| Persetujuan KRS | 264 ms | |
| Presensi (daftar) | 244 ms | |
| Daftar kelas dosen | 319 ms | sebelumnya **17.917 ms** |
| Dasbor admin | 227 ms | |
| Data mahasiswa | 251 ms | |
| Verifikasi data IKU | 219 ms | |
| Transkrip PDF | 824 ms | dompdf, wajar untuk render PDF |
| Yudisium (kohor) | 1.176 ms | sebelumnya **6.338 ms** |
| Konsol Feeder | 1.844 ms | layar operator, dipakai menjelang pelaporan |

**Kesimpulan:** portal harian mahasiswa dan dosen berada di kisaran 200–300 ms
pada 5.000 mahasiswa. Dua layar administratif berada di atas satu detik, dan
keduanya memang layar yang dibuka sesekali, bukan setiap hari.

---

## Tiga Perbaikan yang Lahir dari Pengukuran Ini

Ketiganya tidak terlihat pada kampus demo 50 mahasiswa. Semuanya baru muncul
setelah datanya dinaikkan ke skala nyata.

### 1. Daftar kelas dosen — 17.917 ms → 319 ms

Layar ini memanggil `rekapKelas()` sekali per kelas. Tiap panggilan menjalankan
subkueri pendaftaran atas seluruh tabel KRS, lalu **menarik seluruh baris
presensi kelas itu ke PHP** hanya untuk menghitungnya.

Diganti `PresensiService::rawanAbsensi()`: tiga kueri agregat untuk berapa pun
jumlah kelas, dan penghitungan dilakukan basis data.

### 2. Indeks presensi — agregat 6.227 ms → 20 ms

Setelah perbaikan di atas, sisa waktunya ada di satu kueri agregat. Indeks unik
`presensi` adalah `(pertemuan_kelas_id, mahasiswa_id)` — **`status` tidak ikut**,
sehingga mesin harus membaca setiap baris dari tabel hanya untuk membuang yang
alpa.

Ditambahkan indeks penutup `(pertemuan_kelas_id, status, mahasiswa_id)`. Pada
tabel terbesar di sistem, indeks yang sekadar "membantu" tidak cukup — kuerinya
harus bisa dijawab dari indeks saja.

### 3. Yudisium — 6.338 ms → 1.176 ms

`kandidat()` menjalankan daftar periksa kelulusan penuh atas **seluruh** 5.000
mahasiswa aktif, yang berarti membaca hampir seluruh tabel nilai untuk
menyimpulkan bahwa hampir tidak ada yang mendekati lulus.

Sekarang disaring lebih dulu dengan satu agregat SKS, baru daftar periksa penuh
dijalankan atas daftar pendek yang lolos. Penyaring itu sengaja **melebihkan**
(pengulangan terhitung ganda): itu arah yang aman untuk sebuah penyaring —
tidak ada yang memenuhi syarat terbuang, dan kelebihannya ditolak daftar periksa
sungguhan.

> Jebakannya: penyaring harus memakai ambang SKS **terendah yang berlaku**, bukan
> ambang global. Sebagian prodi menetapkan `sks_lulus` di bawah default, dan
> menyaring dengan ambang global membuat seluruh mahasiswa prodi tersebut hilang
> dari layar yudisium tanpa jejak. Dijaga oleh tes.

---

## Batas Uji Ini — Baca Sebelum Menyimpulkan

Data sintetis tidak berbentuk persis seperti kampus sungguhan. Dua arah
penyimpangan, keduanya perlu diketahui:

**Diuji jauh lebih berat dari kenyataan.** Data uji menaruh ±1.900 mahasiswa per
kelas, karena 5.000 mahasiswa dibagi hanya 21 kelas. Kampus sungguhan berisi
30–50 mahasiswa per kelas. Artinya layar yang bekerja per kelas — grid presensi,
input nilai, daftar peserta — teruji pada beban puluhan kali lipat dari
kenyataan. Angkanya sangat konservatif.

**Diuji lebih ringan dari kenyataan.** Sebaliknya, kampus 5.000 mahasiswa
membutuhkan ±1.000 kelas per semester, bukan 21. Layar yang panjangnya mengikuti
**jumlah kelas** belum benar-benar diuji:

- **Katalog KRS** (`/mahasiswa/krs`) menampilkan kelas yang ditawarkan pada
  kurikulum mahasiswa. Pada 21 kelas ia 262 ms; pada 1.000 kelas ia belum
  terukur dan kemungkinan besar butuh paginasi atau penyaringan.
- **Konsol Feeder** menghitung baris antre per entitas.

Keduanya perlu diukur ulang dengan katalog kelas berskala penuh sebelum
dipasarkan ke kampus besar.

**Belum diuji sama sekali:** sinkronisasi Feeder pada volume penuh.

---

## Katalog KRS pada Skala Penuh — diukur 19 Agustus 2026

Diukur dengan `openacademic:beban-katalog`, server dev PHP satu utas, 1.200
kelas se-kampus. Waktu terbaik dari tiga permintaan berturut-turut.

| Baris katalog | Waktu | HTML |
|---|---|---|
| 10 (data demo) | 0,28 s | 60 KB |
| 574 | 1,14 s | 1.312 KB |
| 604 | 1,44 s | 1.377 KB |
| **1.000** | **1,92 s** | **2.235 KB** |

**Yang perlu diluruskan lebih dulu:** katalog KRS **sudah tersaring per
kurikulum**, bukan per kampus. Seorang mahasiswa tidak pernah melihat 1.000
kelas se-kampus; ia melihat kelas kurikulumnya sendiri. Kekhawatiran lama di
dokumen ini tentang "1.000 kelas" karena itu salah sasaran — yang menentukan
berat layarnya adalah **kelas per kurikulum**, dan angka di atas menumbuhkan
tepat itu.

Waktunya tumbuh mendekati linear di luar biaya tetap, ±1,7 ms per baris. Server
produksi dengan PHP-FPM dan `config:cache` akan lebih cepat.

**Ukuran HTML-nya yang tidak punya pembelaan lingkungan.** 2,2 MB tetap 2,2 MB
di server mana pun. Pada jam pembukaan KRS dengan 2.000 mahasiswa, itu ±4,4 GB
yang harus melewati jaringan kampus dalam hitungan menit — dan jaringan itu,
bukan PHP, yang akan menyerah lebih dulu.

**Kesimpulan: katalog perlu paginasi atau penyaringan sebelum dipakai prodi
besar.** Dugaan lama di dokumen ini benar; sekarang ada angkanya.

### Sesudah paginasi — diukur ulang 19 Agustus 2026

Katalog kini disaring, diurutkan, dan dipaginasi di SQL; 25 baris per halaman.
Diukur pada katalog yang sama, 1.000 kelas terlihat, server dan cara yang sama:

| | Sebelum | Sesudah | |
|---|---|---|---|
| HTML | 2.235 KB | **105 KB** | **21× lebih kecil** |
| Waktu | 1,92 s | **0,79 s** | 2,4× lebih cepat |
| Baris dirender | 1.000 | 25 | |

Halaman ke-20 dan hasil pencarian sama-sama tetap 105 KB — ukuran halaman
tidak lagi bergantung pada besarnya katalog.

Pada 2.000 mahasiswa serentak, ±4,4 GB tadi menjadi **±210 MB**. Itu memindahkan
titik gagal dari jaringan kampus kembali ke aplikasi, tempat ia dapat diukur
dan ditangani.

Sisa 0,79 detik bukan lagi milik katalog: 25 baris tidak mungkin memakan
segitu. Yang tersisa adalah biaya tetap layar ini — sesi, hitungan notifikasi,
panel keranjang KRS, dan satu kueri hitung milik paginasi. Menekannya lebih
jauh berarti menyentuh hal lain, dan belum tentu sepadan.

---

## Perebutan Kuota Bersamaan — diuji 19 Agustus 2026

`KrsService::tambahKelas()` mengunci baris kelas dengan `lockForUpdate()` di
dalam transaksi. Suite tidak dapat membuktikan itu bekerja: ia berjalan di
SQLite in-memory, satu proses, tanpa penguncian baris sungguhan.

Diuji dengan `openacademic:beban-kuota` — proses PHP terpisah, berjalan
bersamaan, berebut satu baris di MySQL:

| Percobaan | Kuota | Hasil | Baris kelas sesudahnya |
|---|---|---|---|
| 19 proses bersamaan | 1 | **1 dapat, 18 penuh** | `terisi=1 kuota=1 detail=1` |
| 19 proses bersamaan | 5 | **5 dapat, 14 penuh** | `terisi=5 kuota=5 detail=5` |

Tidak ada kursi terjual berlebih, dan `terisi` tidak pernah kehilangan kenaikan.
Penguncian bertahan.

**Catatan cara membacanya:** percobaan pertama menghasilkan **nol** pemenang dan
sempat terlihat seperti penguncian yang bekerja sangat baik. Ternyata seluruh
prosesnya ditolak sebelum sempat berebut — mahasiswa demo sudah lulus mata
kuliah itu, atau KRS-nya sudah diajukan. Penolakan yang sah, tapi bukan yang
sedang diuji. Karena itu perintahnya menyiapkan medannya sendiri (mata kuliah
karangan, mahasiswa aktif, KRS dikembalikan ke draf) dan membedakan `PENUH` dari
`TOLAK` di keluarannya. **Nol pemenang tanpa membaca alasannya bukan bukti apa
pun.**

---

## Beban HTTP Bersamaan — diukur 22 Agustus 2026

Utang terakhir dari [`MODUL.md`](MODUL.md). Suite Pest tidak dapat menutupnya
dan `artisan serve` juga tidak: satu utas menjawab satu per satu berapa pun
yang datang, sehingga uji beban terhadapnya mengukur kliennya sendiri.

Yang dipakai di sini **Apache XAMPP** — mpm_winnt dengan 150 utas pekerja, yaitu
antrean yang sungguhan. Harness-nya `app-core/scripts/beban-http.cjs`: login
sebagai sejumlah mahasiswa berbeda, lalu mengulang permintaan katalog KRS pada
konkurensi menaik.

### Hasilnya

Apa adanya (tanpa cache konfigurasi, `APP_DEBUG=true`, **opcache mati**):

| Serentak | Sukses | Gagal | RPS | p50 | p95 |
|---|---|---|---|---|---|
| 1 | 40 | 0 | 0,8 | 1.279 ms | 1.435 ms |
| 5 | 40 | 0 | 2,3 | 2.007 ms | 2.573 ms |
| 10 | 40 | 0 | 2,2 | 4.191 ms | 5.387 ms |
| 20 | 40 | 0 | 2,3 | 7.330 ms | 10.548 ms |
| 40 | 40 | 0 | 2,1 | 18.099 ms | 18.581 ms |

Dengan `config:cache` + `route:cache` + `view:cache`: 2,4 RPS — **naik sekitar
sepersepuluh, dan langit-langitnya tidak bergeser.**

### Tiga hal yang dijawabnya

**Tidak ada yang patah.** Nol galat di setiap tingkat. Bukan antrean Apache yang
penuh, bukan `max_connections` MySQL yang habis, bukan pula perebutan sesi —
padahal ketiganya yang disebut sebagai tersangka. Permintaan hanya **mengantre
dengan tertib**.

**Langit-langitnya ~2,2 permintaan/detik, dan tidak bergerak.** Menambah
konkurensi hanya menambah waktu tunggu, persis Hukum Little: 40 ÷ 2,2 = 18,2
detik, dan yang terukur 18,1 detik. Ini tanda khas server yang jenuh pada
kemampuan eksekusi, bukan pada salah satu batas konfigurasinya.

**Biayanya di bootstrap, bukan di modul KRS.** Halaman login — yang nyaris tidak
melakukan apa pun — berbiaya p50 **755 ms**, sementara katalog KRS 745 ms pada
probe yang sama. Sekitar 70% waktu tiap permintaan habis sebelum kode aplikasi
mulai bekerja.

> Catatan pengukuran: probe `/mahasiswa/krs` dijalankan tanpa cookie sehingga
> yang terukur adalah pengalihannya, bukan halaman penuhnya. Kesimpulannya tidak
> berubah — permintaan yang nyaris tidak mengerjakan apa-apa pun berbiaya ~750
> ms — tetapi angka itu bukan biaya katalog KRS.

### Apa yang harus dilakukan

**Nyalakan OPcache.** Tanpanya setiap permintaan mengompilasi ulang ratusan
berkas PHP, dan itulah keseluruhan langit-langit di atas. Ini satu baris di
`php.ini` dan **pengungkit terbesar dari semuanya** — jauh melampaui perubahan
kode mana pun. Tidak dinyalakan saat pengukuran ini karena `php.ini` XAMPP
berlaku untuk seluruh proyek di mesin tersebut.

**Cache konfigurasi tetap wajib di produksi**, meski di sini hanya 10%: tanpanya
setiap permintaan membaca ulang `.env` dari disk. Selama pengukuran, dua
permintaan gagal dengan `SQLiteDatabaseDoesNotExistException` — `.env` gagal
terbaca lalu `config/database.php` jatuh ke bawaannya, `'default' => env('DB_CONNECTION', 'sqlite')`.
Tidak terulang sesudah `config:cache`.

**Perhatikan 150 lawan 151.** Apache menyediakan 150 utas; `max_connections`
MySQL 151. Selisihnya satu. Selama langit-langitnya masih ~2 RPS hal ini tidak
pernah tersentuh — tetapi begitu OPcache menaikkannya, konkurensi nyata dapat
mencapai 150 dan **kolam koneksi menjadi dinding berikutnya**. Naikkan
`max_connections` sebelum, bukan sesudah.

### Yang masih terbuka

Ini mesin pengembangan: Apache + mod_php, MariaDB lokal, tanpa OPcache. **Ribuan
permintaan bersamaan pada perangkat keras produksi belum diuji**, dan angka di
atas tidak boleh dipakai untuk menentukan ukuran server.

Yang sudah didapat adalah **bentuknya**: tidak ada yang runtuh, semuanya
mengantre, dan hambatannya eksekusi PHP — bukan ketiga hal yang semula dicurigai.
Bentuk itu bertahan meski angkanya berubah.

---

## Audit N+1, Oktober 2026

Fase 5, menjelang rilis v1.0. Pertanyaannya bukan "berapa kueri layar ini",
melainkan **"apakah jumlah kuerinya tumbuh bersama jumlah baris"** — dan itu
tidak dapat dijawab dari kampus demo. Pratinjau penutupan semester menjalankan
61 kueri pada 50 mahasiswa, di dalam anggaran mana pun yang ditulis untuknya.
Pada tiga kali jumlah mahasiswa ia menjalankan 212.

### Cara mengukur

1. **Inventaris.** Setiap rute GET di belakang `auth:staff`, `auth:dosen`,
   `auth:mahasiswa`, dan `auth:pendaftar`, ditambah setiap endpoint Bridge —
   174 rute, parameter model diisi dari baris demo milik aktornya (tujuh
   berparameter non-model dilewati).
2. **Dua kali buka.** Sekali di demo, sekali sesudah kampusnya ditumbuhkan:
   kelas ×2, mahasiswa ×3, lalu pendaftar PMB, tiket helpdesk, peristiwa
   kalender, sesi ujian, ruang, tugas akhir, surat, penugasan dosen, aktivitas
   mahasiswa, baris tagihan, dan pembayaran ×3–4. Penumbuhnya
   `Tests\Support\PenggandaBaris`: menyalin baris beserta seluruh turunannya
   lewat graf kunci asing, sehingga mahasiswa salinan membawa KRS, nilai,
   presensi, dan tagihannya sendiri, dan kelas salinan mengisi rencana studi
   mahasiswa yang sama.
3. **Tiga alat ukur.** Selisih jumlah kueri (N+1); bentuk kueri yang berulang
   dalam satu permintaan (N+1 yang tersembunyi di balik halaman yang sudah
   penuh); dan rasio ukuran HTML (daftar tanpa batas). Ditambah `EXPLAIN QUERY
   PLAN` atas setiap SELECT selama audit, untuk mencari pemindaian penuh.

`Model::preventLazyLoading()` menyala sepanjang audit, jadi lazy load mana
pun akan muncul sebagai galat — tidak ada yang muncul. Seluruh N+1 di bawah
adalah jenis kedua yang disebut `SmokeLayarTest`: kueri baru per baris, bukan
relasi yang dimuat malas.

### Yang ditemukan dan diperbaiki

Jumlah kueri satu permintaan, **demo → kampus ditumbuhkan** (kelas ×2,
mahasiswa ×3, dosen ×2, penugasan dan aktivitas ×4, sesi ujian ×3).

| Layar | Sebelum | Sesudah | Penyebab → perbaikan |
|---|---|---|---|
| Penutupan semester `/admin/tutup-semester` | 61 → **212** | 12 → 13 | Kelas tertunda dicari sekali **per catatan mahasiswa** → satu kueri untuk seluruh semester, dikelompokkan per mahasiswa |
| Katalog KRS `/mahasiswa/krs` | 29 → 39 | 19 → 19 | `PrasyaratChecker` meminta ulang prasyarat per baris katalog padahal relasinya sudah di-eager-load → baca relasi yang dimuat |
| Mahasiswa bimbingan `/dosen/bimbingan` | 16 → 32 | 9 → 9 | KRS semester berjalan dicari per mahasiswa bimbingan → eager load bersyarat |
| Analitik kelas `/dosen/analitik/{kelas}` | 34 → 66 | 20 → 20 | Kehadiran dua kueri per peserta, dua kali → dibaca dari rekap kelas |
| Kartu ujian `/mahasiswa/kartu-ujian`, cetak staf | 24 → 32 | 18 → 18 | Kehadiran dihitung dua kali per kelas → `persenKehadiranPerKelas()`, dua kueri untuk seluruh kartu |
| Ekspor kegiatan dosen `/admin/bkd/ekspor/kegiatan` | 10 → 17 | 4 → 4 | Satu kueri per dosen → `kegiatanPerDosen()` |
| Input nilai `/dosen/nilai/{kelas}` | 20 | 18 | Satu `EXISTS` per komponen → `loadExists('nilai')` |
| Grid presensi, pembukaan pertama | 80 | 65 | Dosen pengampu dicari 16 kali saat pertemuan disiapkan → sekali |

Dua yang terakhir tidak tumbuh bersama baris (komponen nilai dan pertemuan
dibatasi), tetapi berulang tanpa alasan.

Tidak ada yang di-cache. Setiap perbaikan menggantikan kueri per baris dengan
satu kueri per daftar, dan metode per-baris lama tetap ada dengan tes yang
memaku keduanya memberi jawaban yang sama — aturan kelayakan UAS dan
prasyarat tidak disentuh.

### Daftar tanpa batas

| Layar | HTML demo → tumbuh, sebelum | Sesudah | |
|---|---|---|---|
| Verifikasi data IKU `/admin/data-iku` | 135 → 1.379 KB | 135 → 161 KB | Dua daftar dipaginasi 25, parameter halaman terpisah |
| Jadwal ujian BAAK `/admin/jadwal-ujian` | 128 → 2.159 KB | 128 → 1.686 KB | Tabel dipaginasi 50; laporan bentrok tetap membaca seluruh semester |

Sisa ukuran jadwal ujian adalah kartu bentrok, dan itu artefak penggandaan:
sesi salinan berbagi jam dan peserta dengan aslinya, sehingga setiap sesi
bentrok dengan salinannya. Pada kampus sungguhan kartu itu pendek — dan bila
panjang, itulah yang memang harus dibaca BAAK.

Daftar yang tumbuh tetapi **dibatasi oleh hal lain** sengaja dibiarkan: grid
presensi dan input nilai (sebesar kelasnya, 30–50 orang), tagihan dan jadwal
ujian mahasiswa (milik satu orang), pemakaian ruang (sebanyak ruangnya).

### Indeks — tidak ada yang baru

`EXPLAIN QUERY PLAN` menemukan pemindaian penuh pada tabel besar hanya di
kolom kunci asing (`jadwal_kuliah.kelas_kuliah_id`,
`nilai_komponen.krs_detail_id`, `krs_detail.aktivitas_mahasiswa_id`, …).
SQLite tidak mengindeks FK; **InnoDB selalu**, karena kendala FK
mewajibkannya. Tabel terbesar — presensi, nilai, KRS, notifikasi — tidak
muncul sama sekali. Menambah indeks di sini hanya menggandakan indeks yang
sudah dibuat MySQL.

Satu predikat memang tidak dapat memakai indeks pada tabel yang tumbuh tanpa
batas: filter tanggal Log Aktivitas, `whereDate('created_at', …)`, yang di
MySQL menjadi `DATE(created_at)`. Ditulis ulang menjadi rentang setengah
terbuka pada `created_at`, yang berindeks sejak awal. `whereDate` pada sumber
kalender dibiarkan: tabelnya puluhan baris per semester, dan perbandingan
tanggal polos tidak portabel antara SQLite dan MySQL (lihat `UjianService`).

### Waktu, diukur ulang 28 Oktober 2026

MariaDB 10.4 lokal (XAMPP), basis data demo segar ditambah
`openacademic:beban-katalog` — 1.015 kelas di kampus, **304 terlihat** di
katalog KRS mahasiswa ujinya. Permintaan dijalankan di dalam proses (kernel
HTTP dipanggil langsung lewat `tinker`, tanpa server web), satu pemanasan lalu
median lima, diulang dua kali untuk setiap versi kode. Jumlah kueri di sini
mencakup sesi dan middleware, jadi lebih tinggi daripada hitungan di tes.

| Layar | Kueri sebelum → sesudah | Median sebelum | Median sesudah | Ukuran |
|---|---|---|---|---|
| Katalog KRS `/mahasiswa/krs` (304 kelas, 25 per halaman) | 56 → **31** | 79–324 ms | 64–71 ms | 113 KB |
| Penutupan semester `/admin/tutup-semester` | 73 → **24** | 80–478 ms | 46–71 ms | 124 KB |
| Mahasiswa bimbingan `/dosen/bimbingan` | 27 → **20** | 33–67 ms | 42–46 ms | 65 KB |
| Kartu ujian PDF `/mahasiswa/kartu-ujian` | 37 → **31** | 536–576 ms | 582–606 ms | 861 KB |

**Cara membacanya.** Pada laptop pengembangan, dua jalannya kode yang sama
berselisih dua sampai empat kali lipat. Selisih semacam itu lebih besar
daripada yang dapat diselamatkan oleh dua puluh lima kueri terhadap MySQL
lokal, yang masing-masing sekitar satu milidetik. Pada data demo,
**perbaikan ini terbaca pada jumlah kueri, bukan pada stopwatch.** Waktunya
baru berbeda nyata pada kampus yang besar dan pada basis data di mesin lain,
ketika setiap kueri juga membayar ongkos jaringan. Kartu ujian
didominasi render dompdf, seperti transkrip PDF di tabel pertama halaman ini.

Katalog KRS tetap datar terhadap besarnya katalog: 304 kelas terlihat, 25
dirender, 113 KB — selaras dengan 105 KB pada pengukuran 19 Agustus.
Angka milidetiknya tidak dapat dibandingkan langsung dengan 0,79 s Agustus,
karena pengukuran itu lewat server dev PHP satu utas, sedangkan yang ini di
dalam proses.

Uji beban HTTP bersamaan (22 Agustus, `scripts/beban-http.cjs`) **tidak**
diulang. Audit ini tidak mengubah biaya tetap per permintaan yang diukurnya.

Penggandaan kampus untuk pengukuran waktu dicoba di MariaDB dan dihentikan.
`PenggandaBaris` membaca kunci asing lewat `information_schema`, yang pada
server XAMPP berisi 71 basis data memakan beberapa detik per tabel. Ia alat
tes untuk SQLite in-memory, tempat langkah yang sama selesai seketika.
Pertumbuhan jumlah kueri sudah dipaku oleh `AnggaranKueriSkalaTest` di bawah.

### Yang dijaga tes

`AnggaranKueriSkalaTest` membuka 38 layar ramai di tiga portal pada demo,
menumbuhkan kampus, lalu membukanya lagi: jumlah kueri harus di bawah
anggaran **dan** tidak melebihi hitungan demo lebih dari satu langkah eager
load. Dijalankan pada kode sebelum perbaikan, tes ini gagal di tujuh layar —
persis tujuh yang tumbuh di tabel atas.

### Yang masih terbuka

- **Rincian KKN/PKL staf** merender pilihan "pindah kelompok" di setiap baris
  anggota: anggota × kelompok opsi. Pada kampus yang ditumbuhkan HTML-nya
  2,4 MB. Itu
  perubahan antarmuka, bukan kueri — kueri layarnya datar.
- Beberapa kueri berulang tetapi tetap jumlahnya: hitungan status mahasiswa di
  Bridge `iku-data` (sembilan, satu per status) dan LKPS (sembilan, satu per
  tahun), peserta kelas yang dibaca empat kali di analitik. Tidak tumbuh,
  tidak diubah.
- Dua layar GET menulis pada pembukaan pertama (grid presensi menyiapkan 16
  pertemuan, penyusun RPS menyiapkan barisnya). Perilaku lama, di luar audit
  ini.

---

## Rekomendasi Perangkat Keras

Berdasarkan angka di atas, dengan Nginx + PHP-FPM + OPcache.

| Mahasiswa aktif | vCPU | RAM | Catatan |
|---|---|---|---|
| ≤ 1.000 | 2 | 4 GB | satu VPS cukup |
| 1.000–5.000 | 4 | 8 GB | Redis untuk cache & antrean |
| 5.000–15.000 | 8 | 16 GB | basis data mulai layak dipisah |
| > 15.000 | — | — | pisahkan basis data, tambah worker antrean, ukur ulang |

**Yang jauh lebih menentukan daripada CPU:**

1. **Worker antrean menyala.** Jejak audit, sinkronisasi Feeder, dan webhook
   semuanya ter-antre.
2. **`config:cache` + `route:cache` + OPcache.** Ketiganya belum aktif saat
   pengukuran ini.
3. **Masa KRS adalah puncak bebannya.** Membuka KRS bertahap per angkatan
   menurunkan beban puncak jauh lebih efektif daripada menambah CPU.

---

## Cara Mengulang Pengukuran Ini

Dua perintah artisan menggantikan fixture sekali pakai yang dipakai pengukuran
pertama: `openacademic:beban-katalog` menumbuhkan katalog kelas (bawaan 300
kelas terlihat dari 1.000 se-kampus), dan `openacademic:beban-kuota --siapkan=N`
menyiapkan satu kelas berkuota N yang lalu diperebutkan oleh proses-proses
`openacademic:beban-kuota --mahasiswa= --kelas=` yang berjalan serentak.
Keduanya bekerja di atas kampus demo. Yang masih sekali pakai hanya fixture
5.000 mahasiswa untuk angka di bagian atas halaman ini; harness HTTP-nya
`app-core/scripts/beban-http.cjs`.
