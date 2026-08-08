# Portabilitas Basis Data

Open Academic tidak menulis SQL mentah yang terikat satu mesin. Seluruh akses
data lewat Eloquent dan query builder Laravel, sehingga mesin basis datanya
adalah **pilihan konfigurasi**, bukan keputusan yang tertanam di kode.

Halaman ini menyatakan apa yang benar-benar didukung, apa yang sudah diuji, dan
apa yang masih menjadi tanggung jawab Anda bila memindahkannya.

---

## Dukungan

| Mesin | Status | Catatan |
|---|---|---|
| **MySQL 8** / MariaDB 10.11 | ✅ Utama | Yang dipakai pengembangan dan kampus demo |
| **PostgreSQL 13+** | ✅ Didukung | Driver bawaan Laravel; lihat §Perbedaan Perilaku |
| **SQLite** | ✅ Untuk pengujian | Dipakai seluruh suite tes (in-memory) |
| **SQL Server 2019+** | 🟡 Secara teori | Driver bawaan Laravel, **belum pernah kami jalankan** |
| **Oracle** | ⚠️ Butuh paket pihak ketiga | Lihat bagian di bawah — jangan anggap ini tinggal ganti `.env` |

### Oracle — dinyatakan terbuka

**Laravel tidak menyertakan driver Oracle.** Framework ini resmi mendukung
MySQL/MariaDB, PostgreSQL, SQLite, dan SQL Server; Oracle memerlukan paket
komunitas [`yajra/laravel-oci8`](https://github.com/yajra/laravel-oci8) beserta
ekstensi PHP `oci8`.

Artinya untuk Oracle Anda perlu:

1. Memasang `yajra/laravel-oci8` dan ekstensi `oci8`.
2. Menjalankan seluruh migrasi pada instance Oracle sungguhan dan memeriksa
   hasilnya. Panjang nama objek adalah risiko utamanya: beberapa nama indeks
   yang dibangkitkan Laravel cukup panjang, dan Oracle di bawah 12.2 membatasi
   identifier pada 30 karakter.
3. Menguji ulang tipe `TIME`. Oracle tidak punya tipe TIME tersendiri;
   `jadwal_kuliah.jam_mulai` dan `jam_selesai` akan dipetakan berbeda, dan
   deteksi bentrok jadwal membandingkan kolom itu secara langsung.
4. Menjalankan seluruh suite tes terhadap koneksi Oracle tersebut.

Kami tidak menyatakan Oracle "didukung" karena kami belum menjalankannya. Klaim
dukungan tanpa pernah menjalankannya adalah janji yang ditagih orang lain.

---

## Konfigurasi

Ganti mesin lewat `.env` saja:

```env
# MySQL / MariaDB
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=open_academic
DB_USERNAME=open_academic
DB_PASSWORD=

# PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=open_academic
DB_USERNAME=open_academic
DB_PASSWORD=
```

Lalu:

```bash
php artisan migrate --seed
```

Tidak ada langkah lain. Tidak ada berkas SQL khusus mesin, tidak ada skrip
migrasi terpisah.

---

## Perbedaan Perilaku yang Sudah Ditangani

Portabilitas yang gagal jarang berupa galat. Ia berupa perilaku yang berubah
diam-diam.

### Pencarian teks — `LIKE` vs `ILIKE`

Pada MySQL dengan kolasi `utf8mb4_unicode_ci`, `LIKE` **tidak** peka huruf
besar-kecil. Pada PostgreSQL, `LIKE` **peka**. Kotak pencarian yang ditulis
dengan `like` mentah akan berhenti menemukan "budi" ketika datanya tertulis
"Budi" — tanpa galat apa pun. Staf hanya akan menyimpulkan mahasiswanya tidak
ada di sistem.

Seluruh pencarian karenanya melewati satu tempat:

```php
Mahasiswa::cari($request->string('cari'), ['nama', 'nim'])
```

`App\Traits\DapatDicari` memakai `whereLike(..., caseSensitive: false)` bawaan
Laravel, yang menghasilkan `ilike` pada PostgreSQL dan `like` pada MySQL.

Satu tes menjaga agar tidak ada lagi `'like'` literal yang menyelinap masuk ke
`app/` — lihat `tests/Feature/PortabilitasBasisDataTest.php`.

**Pengecualian yang disengaja:** `NimGenerator` memakai
`whereLike(..., caseSensitive: true)`. Awalan NIM adalah string persis, dan
"IF" tidak boleh mencocoki baris yang diawali "if".

### Tipe kolom

`year()` dan `time()` terlihat khas MySQL, tetapi grammar Laravel sudah
menerjemahkannya untuk PostgreSQL, SQLite, dan SQL Server. Tidak ada yang perlu
dikerjakan untuk keempat mesin itu; Oracle tetap perlu diverifikasi sendiri.

### Membandingkan kolom tanggal — `whereDate()`, bukan kesamaan biasa

Mesin tidak sepakat tentang apa yang duduk di dalam kolom tanggal. SQLite tidak
punya tipe DATE sama sekali dan menyimpan apa pun yang diberikan kepadanya,
sehingga cast `date` Laravel menuliskan `2026-08-15 00:00:00`. Kueri
`where('tanggal', '2026-08-15')` kemudian **tidak mencocoki apa pun** — sementara
kueri yang sama berjalan benar di MySQL dan PostgreSQL yang punya DATE asli.

Pakai `whereDate()`, yang dikompilasi per-mesin oleh Laravel.

Ini pernah benar-benar terjadi di sini, pada pemeriksaan bentrok jadwal sidang
(Sesi 17). Yang membuatnya berbahaya bukan kesalahannya, melainkan **arah
kegagalannya**: pemeriksaan itu gagal *terbuka*. Nol baris terbaca sebagai "tidak
ada bentrok", dan sidang dijadwalkan ke ruang yang sudah terisi.

Perbedaan portabilitas yang menyebabkan sesuatu **berhenti bekerja** akan segera
ketahuan. Yang menyebabkan penjaga menjawab "aman" tidak akan.

### Kolom uang

Rupiah disimpan sebagai **integer**, bukan desimal atau float. Ini portabel ke
mana pun dan menghindarkan seluruh persoalan pembulatan — galat satu sen pada
lima ribu tagihan adalah angka yang nyata.

### SQL mentah

Ada beberapa `selectRaw`/`orderByRaw`, semuanya memakai konstruksi ANSI standar
(`COUNT(*)`, `SUM()`, `MAX()`, `CASE WHEN`). Tidak ada fungsi khas satu mesin,
dan tidak ada interpolasi nilai — parameter selalu ter-bind.

---

## Yang Masih Menjadi Tanggung Jawab Anda

- **Menjalankan tes pada mesin tujuan.** Suite berjalan di SQLite. Sebelum
  memindahkan kampus sungguhan ke PostgreSQL, jalankan
  `php artisan test` dengan `DB_CONNECTION=pgsql` pada basis data terpisah.
- **Kolasi dan pengurutan.** Urutan `ORDER BY` untuk huruf beraksen berbeda
  antar-mesin. Tidak memengaruhi kebenaran, tetapi daftar nama bisa tersusun
  sedikit berbeda.
- **Cadangan.** Perintah cadangan di [`DEPLOYMENT.md`](DEPLOYMENT.md) memakai
  `mysqldump`. Untuk PostgreSQL gantilah dengan `pg_dump`.
