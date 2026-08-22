# Open Academic

**Sistem informasi akademik (SIAKAD) open source untuk perguruan tinggi Indonesia.**
Berperan sebagai *system of record* kampus — dari penerimaan mahasiswa baru sampai wisuda —
dan dirancang sejak awal untuk terhubung dengan sistem Kementerian Pendidikan Tinggi
melalui **Neo Feeder PDDIKTI**.

Dibuat dan dirawat oleh **[Humanix Academy](https://humanix.id)**.

Bagian dari ekosistem **Motiolabs Open Education**, bersanding dengan
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
5. **IKU Data Provider** — kebenaran transaksional untuk IKU 1/2/3/4/7/11 lewat
   `GET /api/bridge/v1/iku-data`. Cacahan fakta, **bukan kalkulator**: tanpa skor,
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

Prasyarat: PHP 8.2+ (`pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`),
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
```

Sinkronisasi **idempotent**: menjalankannya ulang membandingkan sidik jari
payload terhadap yang terakhir diterima Feeder dan mencatat baris yang tidak
berubah sebagai *dilewati*, bukan mengirimnya lagi. Sinkronisasi yang terputus
di tengah selalu aman diulang.

Setel `FEEDER_DRIVER=fake` untuk menjalankan seluruh modul — validator, ledger,
penanganan galat — tanpa instalasi Neo Feeder. Ini yang dipakai kampus demo.

**Pembanding** dijalankan dari konsolnya di `/admin/feeder`, per entitas. Ia
membaca kembali isi Feeder dan melaporkan empat keadaan: hanya di SIAKAD, hanya
di Feeder, isinya berbeda, dan tidak dapat dicocokkan. Yang kedua itu alasannya
ada — baris yang diketik langsung di Feeder tidak akan pernah terlihat oleh
sinkronisasi satu arah, sebaik apa pun bukunya dijaga.

Entitas yang aksi pembacaannya belum ditetapkan pada `config/feeder.php`
dinyatakan **belum dapat dibandingkan**, bukan terbaca cocok.

### Ekspor SISTER, LKPS & KIP Kuliah

| Berkas | Tempatnya |
|---|---|
| Kelompok data SISTER (CSV per kelompok) | `/admin/bkd` |
| Borang LKPS per prodi | `/admin/lkps` |
| Laporan semester KIP Kuliah | `/admin/beasiswa` |

### Campus Bridge

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
