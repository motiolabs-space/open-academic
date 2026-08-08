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

**Belum diuji sama sekali:** beban bersamaan (misalnya 2.000 mahasiswa mengisi
KRS pada jam yang sama saat masa KRS dibuka), yang merupakan puncak beban
sesungguhnya sebuah SIAKAD; dan sinkronisasi Feeder pada volume penuh.

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
