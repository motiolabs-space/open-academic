# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/) dan
[Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

### Ditambahkan

- **SSO OAuth2 (Laravel Passport 13).** Open Academic menjadi server OAuth2:
  aplikasi kampus lain mengarahkan penggunanya ke sini untuk masuk. Alur
  authorization-code, layar persetujuan dalam design system kampus, endpoint
  `GET /api/sso/userinfo`, dan menu **Aplikasi Terhubung** agar janji "dapat
  dicabut kapan saja" pada layar persetujuan benar-benar berlaku.
- `config/sso.php` — aktif/nonaktif, masa berlaku token, daftar scope beserta
  kalimat yang dibaca pengguna, aplikasi pihak pertama, dan populasi yang boleh
  memakai SSO. Nonaktif secara bawaan.
- `php artisan openacademic:sso-client` — mendaftarkan aplikasi konsumen,
  menolak redirect non-HTTPS di luar localhost, dan menampilkan secret sekali.
- `docs/SSO.md` — panduan integrasi untuk konsumen.

### Diubah

- **Identifier autentikasi menjadi UUID**, bukan id auto-increment. Tiga tabel
  identitas punya id yang bertabrakan, sehingga subject OAuth berbasis `id`
  akan memberi satu identitas yang sama kepada tiga orang berbeda. Kolom
  `oauth_*.user_id` dan `sessions.user_id` menyesuaikan.
- Tombol "Masuk dengan Akun Kampus (SSO)" dihapus dari halaman masuk. Tombol itu
  menggambarkan arah yang salah: Open Academic *adalah* akun kampusnya.

### Diperbaiki

- `log_aktivitas.causer_id` memakai `getKey()`, bukan `getAuthIdentifier()`.
  Pasangan `causer_type`/`causer_id` adalah relasi polimorfik yang menyelesaikan
  lewat primary key; setelah identifier menjadi UUID, memakai identifier
  autentikasi akan membuat setiap catatan audit menunjuk ke bukan siapa-siapa.

### Kinerja

Diukur pada 5.000 mahasiswa dan 631.220 baris presensi (lihat
[`docs/KAPASITAS.md`](docs/KAPASITAS.md)). Tidak satu pun cacat berikut terlihat
pada kampus demo 50 mahasiswa.

- **Daftar kelas dosen: 17.917 ms → 319 ms.** Layar ini memanggil `rekapKelas()`
  sekali per kelas, yang tiap kali menarik seluruh baris presensi kelas itu ke
  PHP hanya untuk menghitungnya. Diganti `PresensiService::rawanAbsensi()` —
  tiga kueri agregat untuk berapa pun jumlah kelas.
- **Indeks penutup `presensi(pertemuan_kelas_id, status, mahasiswa_id)`.** Indeks
  unik yang ada tidak memuat `status`, sehingga setiap baris harus dibaca dari
  tabel hanya untuk membuang yang alpa. Agregat kehadiran: 6.227 ms → **20 ms**.
- **Yudisium: 6.338 ms → 1.176 ms.** `kandidat()` menjalankan daftar periksa
  kelulusan penuh atas seluruh mahasiswa aktif; kini disaring agregat SKS lebih
  dulu, dan daftar periksa penuh hanya berjalan atas daftar pendek yang lolos.
- `GradeLetter::passingValues()` — huruf lulus untuk dipakai di dalam kueri,
  diturunkan dari `isPassing()` supaya aturan lulus tidak punya salinan kedua.

### Prasyarat Baru

- Ekstensi PHP **sodium** wajib aktif (dibutuhkan `league/oauth2-server`).

---

## [1.0.0] — 2026-08-06

Rilis publik pertama. Sistem informasi akademik lengkap dari penerimaan
mahasiswa baru sampai wisuda, dengan pelaporan PDDIKTI dan kontrak integrasi
sebagai modul kelas satu.

### Ditambahkan

**Inti akademik**
- Skema akademik penuh: 47 tabel domain, 78 foreign key, 16 enum PDDIKTI.
- Autentikasi tiga guard (`staff`, `dosen`, `mahasiswa`) dengan satu halaman
  masuk yang menentukan portalnya sendiri dari identitas yang diketik.
- Otorisasi Spatie per guard: 8 peran, 38 izin, 6 Policy.
- Engine KRS: batas SKS dari IPS semester terakhir yang final, pengecekan
  prasyarat, klaim kursi atomik lewat `lockForUpdate`, alur persetujuan dosen
  wali.
- Penilaian berbobot dengan penguncian, finalisasi, dan koreksi teraudit.
- Presensi 16 pekan dengan sesi QR berputar dan aturan kelayakan UAS.
- KHS, perhitungan IPS/IPK (pengulangan dihitung sekali, nilai terbaik), dan
  transkrip PDF berkode verifikasi.
- Keuangan: tagihan, pembayaran, ambang pembayaran sebagai syarat KRS.
- Yudisium & alumni dengan daftar periksa syarat yang dihitung ulang saat
  penetapan, bukan dipercaya dari pengajuan.

**Integrasi**
- Neo Feeder PDDIKTI: klien, 6 mapper entitas, buku besar sinkron yang
  *idempotent* lewat sidik jari payload, validator pra-kirim yang menampilkan
  baris bermasalah sebelum apa pun dikirim, 3 perintah artisan, dan driver
  `fake` untuk berjalan tanpa instalasi Feeder.
- Campus Bridge: 12 endpoint baca ber-scope, webhook bertanda tangan
  HMAC-SHA256 dengan backoff bertingkat, konsol administrasi, dan spesifikasi
  OpenAPI 3.1 yang bersifat *spec-first*.
- `GET /api/bridge/v1/iku-data`: cacahan fakta untuk IKU 1/2/3/4/7/11 — tanpa
  skor, ambang, maupun target, agar keputusan yang bergantung peraturan tetap
  milik konsumen.

**Antarmuka**
- 21 layar sesuai design system "Midnight Executive", responsif hingga 375px
  dengan navigasi bawah dan lembar menu.
- Kampus demo utuh: 2 prodi, 50 mahasiswa, 7 dosen, tiga semester (dua sudah
  final) sehingga IPK dan batas SKS berbasis IPS benar-benar terpakai.

### Keamanan

- **Perbaikan otorisasi lintas objek.** Rute presensi dosen mengikat kelas dan
  pertemuan secara terpisah, tetapi hanya kelas yang diperiksa. Akibatnya dosen
  dapat menulis presensi — dan membuka sesi QR — pada kelas rekan yang tidak
  diampunya. Ditutup dengan pemeriksaan keterkaitan; regresinya dijaga
  `tests/Feature/Keamanan/LintasObjekTest.php`.
- **Webhook dengan kunci kosong dibatalkan.** Sebelumnya `BRIDGE_WEBHOOK_SECRET`
  kosong menghasilkan tanda tangan HMAC berkunci kosong yang lolos verifikasi
  di sisi konsumen — tampak aman padahal dapat dipalsukan siapa pun.
- **Satu peramban, satu identitas.** Masuk ke satu portal kini me-logout guard
  lain, sehingga tidak ada lagi sesi ganda yang membuat "siapa pengguna ini"
  ambigu.
- Header keamanan (CSP, `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`) pada seluruh respons.
- `SECURITY.md` beserta daftar wajib sebelum produksi dan keterbatasan yang
  diketahui.

### Kinerja

- `Model::preventLazyLoading()` menyala di luar produksi.
- Layar yudisium turun dari **171 kueri menjadi ≤30** — daftar periksa syarat
  kini dihitung sekali untuk seluruh kohor, bukan tiga kueri per mahasiswa.
- Konsol Feeder turun dari 50 kueri menjadi ≤30 lewat satu agregat berkelompok.
- `tests/Feature/SmokeLayarTest.php` menjaga anggaran kueri tiap layar dan
  endpoint, sehingga regresi N+1 gagal di CI, bukan di meja BAAK.

### Aksesibilitas

- Tautan lompat ke konten utama.
- `aria-expanded`/`aria-haspopup` pada dropdown, `role="dialog"` pada lembar
  menu, nama aksesibel untuk kolom pencarian dan menu akun.

### Diketahui Belum Ada

- SSO OAuth2 — tombolnya ada tetapi belum berfungsi. Disengaja: server OAuth
  setengah jadi lebih berbahaya daripada tidak ada.
- PMB (generator NIM, provisioning akun otomatis), cuti mahasiswa, rekonsiliasi
  pembayaran Midtrans, UI pengaturan/branding, penampil log aktivitas.
- 2FA untuk akun staf.

[1.0.0]: https://github.com/motiolabs-space/open-academic/releases/tag/v1.0.0
