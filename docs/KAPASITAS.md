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

Belum ada perintah bawaan untuk membangkitkan data skala besar — fixture-nya
dibuat sekali pakai. Menjadikannya perintah artisan
(`openacademic:seed-skala --mahasiswa=5000`) layak dikerjakan agar angka di
halaman ini dapat diverifikasi ulang siapa pun, dan tercatat sebagai utang
teknis di [`ROADMAP.md`](ROADMAP.md).
