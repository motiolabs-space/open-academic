# Open Academic

**Sistem informasi akademik (SIAKAD) open source untuk perguruan tinggi Indonesia.**
Berperan sebagai *system of record* kampus — dari penerimaan mahasiswa baru sampai wisuda —
dan dirancang sejak awal untuk terhubung dengan sistem Kementerian Pendidikan Tinggi
melalui **Neo Feeder PDDIKTI**.

Dibuat dan dirawat oleh **[Humanix Academy](https://humanix.id)**.

Bagian dari ekosistem **Humanix Open Education**, bersanding dengan
[Open Campus](https://github.com/motiolabs-space/open-campus) sebagai lapisan
ekosistem & engagement.

Lisensi MIT · Laravel 12 · PHP 8.2+ · MySQL 8 / MariaDB 10.11 / PostgreSQL 13+

Mesin basis data adalah pilihan konfigurasi — seluruh akses data lewat Eloquent,
tanpa SQL khas satu mesin. Lihat [`docs/BASIS-DATA.md`](docs/BASIS-DATA.md).

---

## Kenapa Open Academic

Sebagian besar SIAKAD open source berhenti di pencatatan. Open Academic menangani
bagian yang paling menyita waktu kampus kecil:

1. **Neo Feeder Sync sebagai modul kelas satu** — pelaporan PDDIKTI yang idempotent,
   ter-antre, punya buku besar sinkron, **validasi pra-kirim** yang menampilkan
   baris bermasalah *sebelum* sinkronisasi dijalankan, dan **pembanding** yang
   membaca kembali isi Feeder untuk melaporkan di mana kedua sisi berbeda.
2. **Campus Bridge** — kontrak REST + webhook bertanda tangan HMAC, sehingga
   sistem lain membaca data akademik tanpa pernah menyentuh basis data.
3. **Verifikasi dua langkah untuk staf** — TOTP dengan kode pemulihan, dan
   penjagaan yang biasanya terlewat: kode yang sudah dipakai ditolak, tantangan
   kedaluwarsa sendiri, dan pemasangan belum berlaku sampai satu kode terbukti.
   Intinya diuji terhadap vektor resmi RFC 6238 — bukan terhadap dirinya
   sendiri.
4. **SSO OAuth2** — Open Academic menjadi sumber identitas kampus. Aplikasi lain
   (LMS, repositori, perpustakaan, Open Campus) memakai satu akun yang sama, dan
   menonaktifkan satu akun langsung memutus aksesnya di semua aplikasi.
   Panduannya di [`docs/SSO.md`](docs/SSO.md).
5. **IKU Data Provider** — kebenaran transaksional untuk IKU 2026
   (Kepmendiktisaintek 358/M/KEP/2025: IKU 1–6, berikut peta ketersediaan
   kedua belas IKU) lewat `GET /api/bridge/v1/iku-data`; kunci penomoran lama
   tetap dilayani sebagai medan usang. Cacahan fakta, **bukan kalkulator**: tanpa skor,
   ambang, maupun target. Angka yang bergantung peraturan menteri dikembalikan per
   bucket agar keputusannya tetap milik konsumen. Dasbor dan review evidence-nya
   milik Open Campus.
6. **Arsitektur service-layer** — business logic di Services + DTO + Enum, bukan di Blade.

## Batas Tanggung Jawab

| Open Academic (repo ini) | Open Campus |
|---|---|
| PMB & siklus hidup mahasiswa | Jejaring sosial kampus |
| Kurikulum, mata kuliah, jadwal | Review evidence berbantuan AI |
| KRS/KHS, nilai, transkrip | Dasbor & tata kelola 12 IKU |
| Presensi perkuliahan | Talent marketplace & industri |
| Keuangan (tagihan, pencatatan bayar) | Analitik eksekutif |
| Sinkronisasi Neo Feeder PDDIKTI | Network mode multi-kampus |
| Sumber identitas (SSO) | |

**Aturan batas:** catatan akademik resmi & transaksi administratif → repo ini.
Engagement, evidence, analitik, jejaring → Open Campus. Jangan duplikasi fitur.

---

## Daftar Fitur

Yang sudah ada di `main` dan dapat dipakai hari ini. Pengguna: **S** staf ·
**D** dosen · **M** mahasiswa · **P** pendaftar PMB · **Pub** publik ·
**API** sistem lain lewat Campus Bridge.

### Akademik

| Fitur | Pengguna |
|---|---|
| Master akademik: tahun akademik, fakultas, prodi beserta riwayat akreditasi bertanggal, kurikulum berversi, mata kuliah + prasyarat, CPL, gedung & ruang | S |
| Kelas & jadwal kuliah dengan deteksi bentrok ruang dan dosen; pemanfaatan ruang dan pencarian ruang kosong | S; D, M melihat |
| Padanan mata kuliah, kuliah paket, kurikulum konsentrasi | S, M |
| KRS: katalog, batas SKS dari IPS, cek prasyarat & kuota, revisi, persetujuan dosen wali, kunci pembayaran minimum | M, D |
| Penetapan dosen wali perorangan & massal, beserta riwayatnya | S; D melihat anak wali |
| Presensi sesi dengan QR, minimum kehadiran untuk UAS | D, M |
| Penilaian: rencana evaluasi berbobot, finalisasi, koreksi nilai ter-audit | D, S |
| RPS, jurnal & berita acara perkuliahan, analitik kelas dan capaian CPL | D; M melihat capaian |
| Kurikulum berbasis capaian (OBE): CPL → CPMK → sub-CPMK berversi per kurikulum, komponen nilai dipetakan ke sub-CPMK dengan peringatan cakupan, capaian CPMK/CPL tersimpan saat nilai final, rekap kelas, prodi per angkatan, dan mahasiswa + CSV | S, D; M melihat capaian |
| KHS, transkrip PDF, penutupan semester (IPS/IPK dibekukan) | M, S |
| Jadwal UTS/UAS: pengawas, kursi, laporan bentrok, kartu ujian | S; D, M |
| Kalender akademik + langganan iCalendar | S, D, M, Pub |
| Cetak KTM, kartu ujian, daftar hadir, jurnal perkuliahan | S, D, M |

### Kemahasiswaan

| Fitur | Pengguna |
|---|---|
| Data mahasiswa dengan halaman detail staf: identitas, status, dosen wali, KRS/KHS/IPK, tagihan, kesiapan PDDIKTI | S; M profil |
| Cuti dan perubahan status mahasiswa; ajuan cuti oleh mahasiswa beserta dokumen pendukung, diputus BAAK | S, M |
| Evaluasi studi: peringatan dan putus studi | S; M menerima notifikasi |
| Poin kemahasiswaan, pelanggaran, prestasi; lapor prestasi oleh mahasiswa ke antrean verifikasi; ekspor SIMKATMAWA dengan validasi pra-ekspor | S; M melihat poin & melapor |
| Konversi kredit, RPL, intake mahasiswa pindahan; permintaan dokumen ke mahasiswa | S; M melihat status & mengunggah |
| Registrasi ulang (re-NIM): pindah prodi internal dan masuk kembali dengan NIM baru yang tertaut ke registrasi lama, aktif kembali dengan NIM yang sama | S |
| KKN/PKL: kelompok, DPL, logbook, nilai masuk ke KRS | S, D, M |
| SKPI | S, M |

### Tugas Akhir & Kelulusan

| Fitur | Pengguna |
|---|---|
| Tugas akhir: tahap proposal per prodi, pembimbing, seminar & sidang | M, D, S |
| Yudisium dengan daftar periksa kesiapan nomor ijazah dan uji kompetensi | S |
| Nomor ijazah dari PISN (impor) | S |
| Wisuda: dikelola staf atau didaftar sendiri oleh mahasiswa | S, M |
| Data lulusan untuk tracer study nasional (ekspor) | S |

### Penerimaan Mahasiswa Baru

| Fitur | Pengguna |
|---|---|
| Portal pendaftar: akun + verifikasi surel, biodata, unggah berkas, kartu ujian, pengumuman hasil | Pub, P |
| Gelombang, penawaran prodi, jadwal seleksi, impor nilai seleksi (CSV), daftar ulang + penerbitan NIM | S |

### Keuangan

| Fitur | Pengguna |
|---|---|
| Matriks tarif: UKT, komponen biaya, per SKS, per mata kuliah | S |
| Tagihan massal dan tagihan per SKS dari KRS yang disetujui | S; M melihat |
| Pencatatan pembayaran kasir; transfer manual sebagai jalur utama | S |
| Denda keterlambatan beserta pembebasannya (bawaan mati) | S |
| Dispensasi pembayaran untuk membuka KRS | S; M melihat |
| Beasiswa, keringanan, potongan; periode pendaftaran dan ajuan mahasiswa, ditetapkan staf; laporan semester KIP Kuliah | S, M |
| Integrasi akuntansi lewat outbox jurnal (opsional, bawaan mati) | S |

### SDM & Dosen

| Fitur | Pengguna |
|---|---|
| Akun dosen & staf, unit kerja | S |
| Riwayat kepegawaian 14 jenis: diajukan dosen, divalidasi staf | D, S |
| Portofolio dan penugasan dosen, termasuk publikasi berindeks & kuartil dan keterlibatan dalam penyusunan kebijakan | D, S |
| BKD dengan asesor dan pengesahan; ekspor berkas untuk SISTER | D, S |
| Buku besar angka kredit (unsur A–E) dan ekspor penyetaraan | S; D melihat |
| EDOM: kuesioner dosen oleh mahasiswa, jawaban anonim | S, M, D |
| Kuesioner umum: penyusunan, sasaran per peran/prodi, hasil teragregasi, ekspor | S, D, M |

### Pelaporan & Integrasi

| Fitur | Pengguna |
|---|---|
| Neo Feeder PDDIKTI: 26 entitas, Insert/Update/Delete, validasi pra-kirim, buku besar idempotent, pembanding selisih, adopsi & klaim data lampau, PDM/PJK beserta suratnya | S |
| Pemeriksa kamus web service Neo Feeder: membandingkan medan yang dikirim setiap entitas dengan kamus Feeder kampus, lalu menyarankan nama medan HP dan tanggal ijazah yang dapat diatur | S |
| Borang LKPS per prodi | S |
| Data IKU 2026 (Kepmendiktisaintek 358/M/KEP/2025) dengan layar verifikasi bukti | S, API |
| Campus Bridge: Read API ber-scope, webhook bertanda tangan HMAC, OpenAPI; konsumen dan token dikelola dari layar staf | S, API |
| SSO OAuth2 sebagai sumber identitas kampus, federasi IdP kampus; klien SSO dikelola dari layar staf (bawaan mati) | semua portal |
| SPMI/AMI dan rencana kinerja | S |

### Layanan & Platform

| Fitur | Pengguna |
|---|---|
| Surat akademik dengan verifikasi QR publik | M, S, Pub |
| Helpdesk dengan SLA dan FAQ publik; tiket dapat dibuka mahasiswa, dosen, dan staf | M, D, S, Pub |
| Pengumuman ke seluruh portal | S mengelola |
| Notifikasi surel dan pengingat tenggat terjadwal | semua |
| Berkas privat, verifikasi dua langkah (TOTP) untuk staf, log audit, branding, pengaturan dokumen | S |
| Login dengan NIM, NIDN, NIP, atau surel; portal ditentukan otomatis | semua |
| Wizard setup awal kampus: identitas PT, warna dan logo, super admin pertama, struktur awal opsional; terkunci setelah selesai | S |
| Notifikasi instalasi ke Humanix: opsional, bawaannya mati, sepuluh medan tanpa data pribadi, dapat dimatikan di Pengaturan atau `.env` | S |

Rencana kerja per modul — termasuk yang sengaja ditunda atau di luar cakupan —
ada di [`docs/ROADMAP.md`](docs/ROADMAP.md).

---

## Pelaporan ke Kementerian

Kampus tidak melapor ke satu tempat. Peta kewajibannya — beserta penilaian mana
yang datanya sudah ada di sini — ada di [`docs/PELAPORAN.md`](docs/PELAPORAN.md).

| Tujuan | Keadaan |
|---|---|
| **PDDIKTI / Neo Feeder** | Sinkron penuh, idempotent, berbuku besar; **plus pembanding selisih** |
| **SISTER** | Ekspor CSV per kelompok data. Adaptornya belum ada — kredensial belum tersedia |
| **Borang LKPS** (akreditasi) | Kalkulator indikator + perakit tabel di `/admin/lkps` |
| **KIP Kuliah** (Puslapdik) | Laporan semester penerima, siap unggah |

### Satu aturan yang berlaku di keempatnya

**Tidak ada yang menghasilkan berkas kosong.** Tujuan yang belum dapat diisi
menolak berjalan dan menyebut sebabnya — di layar dan di dalam berkasnya
sendiri — alih-alih mengeluarkan CSV berisi baris judul saja.

Alasannya sama di setiap kasus: berkas nol baris terbaca sebagai fakta tentang
kampusnya, bukan sebagai keterangan bahwa sistemnya belum diberi tahu sesuatu.
Nol penerima KIP Kuliah, nol dosen dengan keanggotaan profesi, nol penelitian
di borang akreditasi — ketiganya pernyataan yang salah, dan ketiganya tampak
rapi.

Yang berlaku sebaliknya juga: angka yang tidak terukur ditulis `—`, bukan nol,
dan rasio tanpa penyebut dibiarkan kosong alih-alih dipaksa menjadi 1,00.

### Yang masih menunggu keputusan kampus, bukan kode

- **SISTER** — enam kelompok data punya tabel tanpa layar pengisian. Adaptor
  yang mulus di atas tabel kosong tetap mengirim kosong.
- **LKPS** — delapan definisi (siapa "pendaftar", siapa "dosen tetap", apakah
  cuti menambah masa studi) menentukan angkanya. Bawaannya konservatif dan
  layarnya menyatakan sendiri bahwa definisinya masih sementara. Daftar
  pertanyaannya di [`docs/LKPS-DEFINISI.md`](docs/LKPS-DEFINISI.md).
- **KIP Kuliah** — satu baris `.env`: `KIPK_BEASISWA_KODE`, karena aplikasi ini
  tidak dapat menebak skema beasiswa mana yang KIP Kuliah.

---

## Instalasi

Prasyarat: PHP 8.2+, disarankan 8.4 atau 8.5 seperti produksi — CI menguji ketiganya
(`pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`),
Composer, Node 20+, MySQL 8 atau MariaDB 10.11.

> **Tata letak.** Aplikasinya ada di `app-core/`; root repo hanya berisi berkas
> yang boleh dilayani web (`index.php`, `.htaccess`, `build/`). Pola ini dipakai
> agar repo dapat diunggah apa adanya ke `public_html` pada hosting bersama yang
> tidak mengizinkan document root diarahkan ke subfolder. **Semua perkakas
> dijalankan dari `app-core/`.** Rinciannya — termasuk empat sambungan yang harus
> diubah — di [`docs/TATA-LETAK.md`](docs/TATA-LETAK.md).

```bash
git clone https://github.com/motiolabs-space/open-academic.git
cd open-academic/app-core
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Buat basis data lalu sesuaikan `.env`:

```bash
mysql -u root -e "CREATE DATABASE open_academic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Jalankan migrasi beserta kampus demo, lalu build aset:

```bash
php artisan migrate --seed
```

```bash
npm run build
```

Aset mendarat di `../build`, karena root repo itulah direktori publiknya.
Arahkan Apache/Nginx ke root repo — bukan ke `app-core` — lalu buka alamatnya.

Server bawaan Laravel mengikuti sendiri: `serve` memakai `public_path()`, yang
sudah menunjuk root repo.

```bash
php artisan serve
```

### Setup Awal Kampus

Instalasi tanpa kampus demo, yaitu `APP_ENV=production` atau sesudah
`openacademic:demo-hapus`, membuka **wizard setup** di `/setup` pada kunjungan
pertama. Wizard ini meminta lima hal:

1. identitas perguruan tinggi (nama, kode PT PDDIKTI, alamat, kontak);
2. warna dan logo;
3. akun super admin pertama;
4. fakultas, prodi, dan semester berjalan, bila ingin diisi sekarang;
5. persetujuan notifikasi instalasi.

Setelah selesai, `/setup` membalas 404 untuk selamanya. Wizard hanya dapat
dibuka lagi lewat `php artisan openacademic:setup-ulang`. Kampus demo dan
instalasi yang sudah punya akun staf dianggap sudah ter-setup.

> **Notifikasi instalasi ke Humanix: opsional, bawaannya mati.** Di langkah
> terakhir wizard, kampus dapat mengizinkan satu kabar singkat ke humanix.id
> saat dipasang dan setiap kali versinya berubah. Isinya hanya nama dan kode
> PT, kota/provinsi, versi aplikasi/PHP, jenis mesin basis data, ID instalasi
> acak, dan tanggal. Kabar itu tidak pernah memuat data mahasiswa, dosen,
> kredensial, atau `APP_KEY`. Pengiriman dapat dimatikan di Pengaturan, atau
> untuk selamanya dengan `HUMANIX_TELEMETRI=false`. Rinciannya ada di
> [`docs/TELEMETRI.md`](docs/TELEMETRI.md).

> **Worker antrean wajib menyala.** Jejak audit, sinkronisasi Feeder, dan
> pengiriman webhook semuanya ter-antre. Tanpa worker, ketiganya **gagal diam-diam**
> — perubahan nilai tidak akan pernah tercatat di `log_aktivitas`. Di produksi
> jalankan lewat Supervisor/systemd; saat pengembangan cukup:
>
> ```bash
> php artisan queue:work
> ```

### Kampus Demo

`php artisan migrate --seed` menghasilkan kampus utuh yang langsung bisa didemokan:
1 fakultas, 2 program studi, kurikulum berversi dengan ±22 mata kuliah tiap prodi
(lengkap dengan prasyarat), 7 dosen (termasuk satu praktisi industri), 4 staf,
50 mahasiswa, **tiga semester** — dua semester tertutup lengkap dengan nilai final
sehingga IPK dan batas SKS berbasis IPS benar-benar terpakai, plus semester berjalan
dengan KRS campuran, presensi, tagihan, dan data PMB.

Kata sandi seluruh akun demo: `password`

| Peran | Akun |
|---|---|
| Super Admin | `admin@demo.test` |
| BAAK | `baak@demo.test` |
| Keuangan | `keuangan@demo.test` |
| Operator PDDIKTI | `pddikti@demo.test` |
| Dosen (wali) | `dosen1@demo.test` |
| Dosen praktisi | `praktisi@demo.test` |
| Mahasiswa | `mahasiswa1@demo.test` |

Memasang dan menghapusnya kembali:

```bash
php artisan openacademic:demo-pasang
```

```bash
php artisan openacademic:demo-hapus
```

Keduanya menghapus seluruh isi basis data — demo ini satu kampus utuh, bukan
sekumpulan baris yang bisa disisipkan atau dicabut satu per satu. Karena itu
penjaganya yang penting: `demo-hapus` menolak basis data yang tidak ditandai
sebagai pasangan demo, jadi ia tidak pernah bisa menghapus kampus sungguhan.
Rinciannya di [`docs/DEMO.md`](docs/DEMO.md).

> **Peringatan.** `DemoCampusSeeder` menolak berjalan bila `APP_ENV=production`.
> Jangan pernah menjalankan `--seed` pada instalasi produksi.

Bisa masuk dengan NIM, NIDN, NIP, maupun alamat surel — sistem menentukan
portalnya sendiri.

---

## Perintah Pengembangan

```bash
composer lint     # Laravel Pint (cek saja)
composer fix      # Laravel Pint (perbaiki)
composer test     # Pest
composer fresh    # migrate:fresh --seed
npm run dev       # Vite dev server
```

### Pelaporan PDDIKTI

```bash
php artisan openacademic:feeder-refs                  # tarik tabel referensi Feeder
php artisan openacademic:feeder-validate              # periksa tanpa mengirim apa pun
php artisan openacademic:feeder-sync semua            # kirim berurutan sesuai dependensi
php artisan openacademic:feeder-sync nilai --term=20261
php artisan openacademic:feeder-kamus                 # bandingkan medan kiriman dengan kamus Feeder kampus
```

Sinkronisasi **idempotent**: menjalankannya ulang membandingkan sidik jari
payload terhadap yang terakhir diterima Feeder dan mencatat baris yang tidak
berubah sebagai *dilewati*, bukan mengirimnya lagi. Sinkronisasi yang terputus
di tengah selalu aman diulang.

Setel `FEEDER_DRIVER=fake` untuk menjalankan seluruh modul — validator, ledger,
penanganan galat — tanpa instalasi Neo Feeder. Ini yang dipakai kampus demo.
Sinkronisasi sendiri baru berjalan setelah `FEEDER_ENABLED=true`; bawaannya
`false`, dan `feeder-sync` berhenti dengan pesan itu, bukan diam.

**Pembanding** dijalankan dari konsolnya di `/admin/feeder`, per entitas. Ia
membaca kembali isi Feeder dan melaporkan empat keadaan: hanya di SIAKAD, hanya
di Feeder, isinya berbeda, dan tidak dapat dicocokkan. Yang kedua itu alasannya
ada — baris yang diketik langsung di Feeder tidak akan pernah terlihat oleh
sinkronisasi satu arah, sebaik apa pun bukunya dijaga.

Entitas yang aksi pembacaannya belum ditetapkan pada `config/feeder.php`
dinyatakan **belum dapat dibandingkan**, bukan terbaca cocok.

**Kamus web service** (`/admin/feeder/kamus` atau `feeder-kamus`) membaca
`GetDictionary` Neo Feeder kampus untuk setiap aksi yang dipakai dan melaporkan
medan yang tidak dikenal, medan wajib yang tidak dikirim, dan beda nama. Ia
hanya melapor; nama medan HP dan tanggal ijazah diubah lewat `FEEDER_MEDAN_HP`
dan `FEEDER_MEDAN_TANGGAL_IJAZAH` (bawaannya: tanggal ijazah tidak dikirim).
Jalankan sesudah memasang atau memperbarui Neo Feeder. Isi kamusnya tetap di
basis data kampus, tidak pernah masuk repo.

### Ekspor SISTER, LKPS & KIP Kuliah

| Berkas | Tempatnya |
|---|---|
| Kelompok data SISTER (CSV per kelompok) | `/admin/bkd` |
| Borang LKPS per prodi | `/admin/lkps` |
| Laporan semester KIP Kuliah | `/admin/beasiswa` |

### Campus Bridge

Konsumen, scope, token, dan webhook dikelola di **Campus Bridge → Kelola**
(`/admin/bridge`, izin `integrasi.kredensial`; peran `super-admin` dan `tik`).
Token dan rahasia webhook ditampilkan sekali. Perintah berikut setara:

```bash
php artisan openacademic:bridge-token open-campus         # terbitkan token
php artisan openacademic:bridge-token open-campus --cabut # cabut seluruh token
```

```bash
curl -H "Authorization: Bearer <token>" \
     -H "Accept: application/json" \
     https://akademik.kampus.ac.id/api/bridge/v1/students?per_page=25
```

### SSO — Akun Kampus untuk Aplikasi Lain

```bash
php artisan passport:keys                                    # sekali per instalasi
php artisan openacademic:sso-client "Open Campus" \
    --redirect=https://campus.kampus.ac.id/auth/callback
php artisan openacademic:sso-client --daftar
```

Nonaktif secara bawaan; setel `SSO_ENABLED=true`. Konsumen menempuh
authorization-code lalu menanyakan pemegang token:

```bash
curl -H "Authorization: Bearer <token>" https://akademik.kampus.ac.id/api/sso/userinfo
```

`sub` yang dikembalikan adalah **UUID, bukan angka** — tiga tabel identitas
kampus punya id yang bertabrakan, jadi subject berbasis `id` akan memberi satu
identitas yang sama kepada tiga orang berbeda. Selengkapnya di
[`docs/SSO.md`](docs/SSO.md).

Kontrak lengkapnya ada di [`docs/openapi/bridge.yaml`](docs/openapi/bridge.yaml)
dan bersifat **spec-first**: ubah spec dulu, baru kodenya.

Setiap endpoint menyatakan scope yang dibutuhkannya, dan daftar scope melekat
pada aplikasi konsumen — bukan pada token. Mencabut scope berlaku seketika
tanpa perlu menerbitkan token baru.

Webhook ditandatangani HMAC-SHA256 atas `{timestamp}.{body}`. Verifikasi di
sisi konsumen wajib memakai perbandingan waktu-tetap:

```php
$sah = hash_equals(
    hash_hmac('sha256', $timestamp.'.'.$body, $secret),
    $request->header('X-OpenAcademic-Signature'),
);
```

---

## Konfigurasi Utama

| Berkas | Isi |
|---|---|
| `config/academic.php` | Batas SKS per IPS, aturan presensi, skala nilai huruf, syarat kelulusan, pola NIM |
| `config/feeder.php` | Koneksi Neo Feeder, tabel referensi, urutan entitas sinkron |
| `config/bridge.php` | Scope token, daftar event webhook, backoff percobaan ulang |
| `config/payment.php` | Tenggat tagihan semester. **Bukan** gateway pembayaran — adaptornya belum ada, dan config yang menjanjikannya sudah dicabut |
| `config/lkps.php` | Delapan definisi perhitungan LKPS + susunan tabel borang per LAM |
| `config/kipk.php` | Kode skema beasiswa yang merupakan KIP Kuliah (lewat `KIPK_BEASISWA_KODE`) |
| `config/branding.php` | Identitas institusi & warna (dapat ditimpa lewat menu Pengaturan) |
| `config/instalasi.php` | Versi aplikasi dan notifikasi instalasi ke Humanix (opsional, bawaan mati; [`docs/TELEMETRI.md`](docs/TELEMETRI.md)) |

Aturan bisnis yang berbeda antar kampus — batas SKS, skala huruf, persentase
kehadiran minimum, ambang pembayaran KRS — semuanya konfigurasi, bukan kode.

---

## Status Pengembangan

| Fase | Cakupan | Status |
|---|---|---|
| **0 — Fondasi** | Skema akademik penuh, enum PDDIKTI, model, autentikasi 3 guard, otorisasi Policy, shell UI, seeder kampus demo, CI | ✅ Selesai |
| **1 — Inti Akademik** | Engine KRS/KHS, penilaian berbobot, presensi + QR, transkrip PDF, portal mahasiswa & dosen | ✅ Selesai |
| **2 — Feeder** | `NeoFeederClient`, tarik referensi, 6 mapper + ledger idempotent, validator pra-kirim, UI sinkron, 3 perintah artisan | ✅ Selesai |
| **3 — Campus Bridge** | Read API ber-scope (12 endpoint), webhook bertanda tangan HMAC, OpenAPI, konsol, **SSO OAuth2** | ✅ Selesai |
| **4 — Data IKU** | Yudisium & alumni, layar verifikasi bukti, endpoint cacahan IKU 1/2/3/4/7/11 | ✅ Selesai |
| **5 — Polish & Rilis** | Audit N+1 + anggaran kueri, review keamanan & pengerasan, aksesibilitas, dokumen rilis | ✅ Selesai |
| **6 — Pelaporan Lanjutan** | Pembanding SIAKAD↔Feeder, ekspor SISTER per kelompok, kalkulator & borang LKPS, laporan KIP Kuliah | ✅ Selesai |
| **7 — Pengerasan** | Uji beban HTTP bersamaan terhadap Apache sungguhan, verifikasi dua langkah untuk akun staf (TOTP + kode pemulihan) | ✅ Selesai |

Terukur pada **5.000 mahasiswa · 631.220 baris presensi · basis data 288 MB**:
portal harian mahasiswa dan dosen berada di kisaran 200–300 ms. Angka lengkap,
batas ujinya, dan rekomendasi perangkat keras ada di
[`docs/KAPASITAS.md`](docs/KAPASITAS.md).

Rencana kerja per modul beserta statusnya — terverifikasi terhadap kode dan
basis data, bukan sekadar rencana — ada di [`docs/ROADMAP.md`](docs/ROADMAP.md).
Rincian sesi di [`docs/STATUS.md`](docs/STATUS.md); keputusan arsitektur
non-obvious di [`docs/DECISIONS.md`](docs/DECISIONS.md).

---

## Deploy ke Produksi

Baca [`SECURITY.md`](SECURITY.md#wajib-dilakukan-sebelum-produksi) lebih dulu —
daftar itu bukan saran — lalu ikuti
[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

Dua proses latar wajib menyala, dan keduanya gagal dalam senyap bila tidak:
worker antrean (`php artisan queue:work`) dan satu entri cron penjadwal. Tanpa
yang pertama tidak ada notifikasi maupun jejak audit yang tercatat; tanpa yang
kedua tidak ada pengingat tenggat. Lihat
[`docs/NOTIFIKASI.md`](docs/NOTIFIKASI.md).

Satu hal lagi yang harus diputuskan sebelum surat pertama terbit, bukan sesudah:
**pola nomor surat** (`SURAT_POLA_NOMOR`). Mengubahnya kemudian berarti dua
konvensi hidup berdampingan di lemari arsip yang sama —
[`docs/SURAT.md`](docs/SURAT.md).

Begitu pula dua pengaturan EDOM, yang keduanya adalah posisi dan bukan sekadar
konfigurasi: **gerbang pengisian** (`EDOM_GERBANG`, bawaan `krs` — menahan KHS
memakai nilai yang sudah diperoleh mahasiswa sebagai alat tukar) dan **siapa
membaca komentar bebas** (`EDOM_KOMENTAR`, bawaan `prodi`). Anonimitas jawabannya
sendiri tidak dapat dikonfigurasi: ia melekat pada bentuk tabelnya —
[`docs/EDOM.md`](docs/EDOM.md).

**Integrasi akuntansi (Easy Accounting) bersifat opsional dan mati sampai
dinyalakan.** Bawaan `AKUNTANSI_DRIVER=nonaktif` berarti tidak ada yang dicatat,
tidak ada menu, dan tidak ada proses terjadwal — penagihan berjalan sama persis
tanpanya. Kampus yang memakainya menyepakati dulu **bagan akun** di
[`config/akuntansi.php`](config/akuntansi.php); kode di sana harus sudah ada di
seberang, dan itu penyebab kegagalan tersering —
[`docs/AKUNTANSI.md`](docs/AKUNTANSI.md).

Untuk **BKD**, yang perlu disepakati sebelum semester pertama dilaporkan adalah
**rubrik ekuivalensi SKS** di [`config/bkd.php`](config/bkd.php). Itu tafsir
kampus atas pedoman yang berubah, bukan fakta — Open Academic menjamin cacahnya
benar dan menyerahkan pembobotannya. Sambungan ke **SISTER belum ada** (menunggu
kredensial); yang sudah siap adalah datanya, beserta ekspor PDF/CSV/JSON —
[`docs/BKD-SISTER.md`](docs/BKD-SISTER.md).

## Kontribusi

Baca [`CONTRIBUTING.md`](CONTRIBUTING.md) dan [`CLAUDE.md`](CLAUDE.md) — keduanya
berlaku untuk kontributor manusia maupun agen AI. Ringkasnya: controller tipis,
business logic di Service, migrasi *append-only* setelah masuk `main`,
kode/komentar/commit dalam bahasa Inggris, string UI dalam Bahasa Indonesia, dan
setiap PR fitur membawa minimal satu feature test.

Kerentanan keamanan **jangan** dilaporkan lewat issue publik — lihat
[`SECURITY.md`](SECURITY.md#melaporkan-kerentanan).

## Dibuat oleh Humanix Academy

Open Academic dibuat dan dirawat oleh **[Humanix Academy](https://humanix.id)**,
dan dirilis penuh dengan lisensi MIT — bukan versi terbatas dari produk berbayar.
Yang ada di repo ini adalah seluruh aplikasinya, termasuk bagian yang paling
mahal dibangun: pelaporan.

Alasannya sederhana. Pekerjaan yang paling menyita waktu kampus kecil — melapor
ke PDDIKTI, menyiapkan borang akreditasi, merapikan berkas SISTER dan KIP
Kuliah — bentuknya hampir sama di semua kampus. Tidak masuk akal setiap kampus
membayar untuk menyelesaikan persoalan yang sama sendiri-sendiri.

### Mari berkolaborasi

Kontribusi yang paling berguna untuk proyek ini bukan yang paling besar,
melainkan yang paling sulit didapat dari luar kampus:

- **Anda menjalankannya di kampus sungguhan.** Ceritakan apa yang tidak cocok.
  Aturan akademik berbeda antar kampus, dan yang kami anggap konfigurasi bisa
  jadi ternyata masih tertanam di kode.
- **Borang LAM Anda berbeda.** Perakit LKPS sengaja dipisah dua lapis: menambah
  LAM berarti menambah satu blok config, bukan menulis perakit baru. Kirimkan
  susunan tabel LAM Anda.
- **Anda punya akses Neo Feeder atau SISTER sungguhan.** Sebagian nama aksi
  masih dugaan terbaik kami. Satu laporan galat dari instalasi asli lebih
  berharga daripada seratus baris kode dari kami.
- **Anda menemukan angka yang salah.** Terutama pada laporan. Angka yang salah
  di berkas akreditasi atau KIP Kuliah menimpa orang sungguhan, dan kami ingin
  tahu sebelum asesornya yang menemukan.

Cara mengirimnya ada di [`CONTRIBUTING.md`](CONTRIBUTING.md). Issue berisi
"di kampus kami begini" sama diterimanya dengan pull request.

### Butuh dukungan cloud atau pendampingan?

Menjalankan SIAKAD sendiri berarti mengurus server, cadangan, pembaruan, dan
menyiapkan orang yang paham saat tenggat pelaporan tinggal seminggu. Sebagian
kampus memang punya tim untuk itu; sebagian lagi lebih baik memakai waktunya
untuk hal lain.

Kalau kampus Anda termasuk yang kedua, **[humanix.id](https://humanix.id)**
menyediakan dukungan cloud dan pendampingan implementasi untuk Open Academic.

Aplikasinya tetap MIT dan tetap milik Anda. Yang ditawarkan adalah waktu dan
tanggung jawab operasionalnya — bukan kunci untuk membuka fitur, karena tidak
ada fitur yang dikunci.

---

## Lisensi

MIT. Lihat [`LICENSE`](LICENSE).
