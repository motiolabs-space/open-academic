# MODUL.md — Daftar Modul Open Academic

> Disusun 18 Agustus 2026. Yang **sudah dibuat** dibaca dari
> `app-core/app/Support/Navigation.php` dan `routes/web.php`; yang **belum**
> dibaca dari [`ROADMAP.md`](ROADMAP.md). Bukan dari ingatan.
>
> Tangkapan layar diambil dari aplikasi yang berjalan di
> `http://localhost/open-academic` dengan basis data demo, dan setiap layar
> diverifikasi **HTTP 200** — bukan sekadar "halamannya terbuka".

---

## Ringkasan

**Open Academic** adalah SIAKAD open source untuk perguruan tinggi Indonesia —
*system of record* untuk catatan akademik resmi dan transaksi administratif.

| | |
|---|---|
| Portal | 3 — Mahasiswa, Dosen, Staf |
| **Modul terpasang** | **59** — 12 mahasiswa · 14 dosen · 33 staf |
| **Belum dibuat** | 9 modul · 7 sebagian · **4 utang teknis** (4 lunas 19 Agu 2026) |
| Sengaja di luar cakupan | 8 |
| Rute GET berportal | 92 |
| Tabel domain | 109 · 48 migrasi |
| Model Eloquent | 94 · 54 enum · 85 service · 7 policy |
| Perintah artisan | 12 |
| Tes Pest | **901 hijau** (1.941 asersi) · 59 berkas |
| Desain | Midnight Executive — navy `#1E2761`, emas `#C9A961` |

Fase 0–5, SSO OAuth2, tujuh kesenjangan SIAKAD (G1–G7), dan integrasi akuntansi
sudah selesai.

---

# BAGIAN I — SUDAH DIBUAT

## Portal Mahasiswa — 12 modul

Akun demo: `mahasiswa1@demo.test` · kata sandi `password`

| Modul | Rute | Isi |
|---|---|---|
| Dasbor | `/mahasiswa` | Ringkasan semester berjalan |
| **Rencana Studi (KRS)** | `/mahasiswa/krs` | Katalog kelas + keranjang, batas SKS dari IPS, alur draf → diajukan → ditinjau wali → disetujui |
| Jadwal Kuliah | `/mahasiswa/jadwal` | Jadwal mingguan + kartu "hari ini" |
| Presensi Mandiri | `/mahasiswa/presensi` | Rekap kehadiran + pindai QR sesi |
| KHS & Transkrip | `/mahasiswa/khs` | Nilai per semester, transkrip PDF |
| Capaian Pembelajaran | `/mahasiswa/capaian` | Penguasaan CPL dari komponen bernilai |
| Tugas Akhir | `/mahasiswa/tugas-akhir` | Judul, pembimbing, log bimbingan, sidang |
| Surat & Dokumen | `/mahasiswa/surat` | Ajukan surat, unduh SKPI dwibahasa |
| Evaluasi Dosen | `/mahasiswa/edom` | Pengisian EDOM (anonim secara skema) |
| Tagihan & Pembayaran | `/mahasiswa/tagihan` | Tagihan, potongan, riwayat bayar |
| Profil Akademik | `/mahasiswa/profil` | Data diri, status, cuti |
| Aplikasi Terhubung | `/aplikasi-terhubung` | Klien OAuth2 yang diberi izin |

## Portal Dosen — 14 modul

Akun demo: `dosen1@demo.test` · kata sandi `password`

| Kelompok | Modul | Rute |
|---|---|---|
| **PENGAJARAN** | Dasbor | `/dosen` |
| | Kelas Diampu | `/dosen/kelas` |
| | Input Nilai | `/dosen/nilai` |
| | Presensi | `/dosen/presensi` |
| | RPS & Jurnal | `/dosen/rps` |
| | Analitik Kelas | `/dosen/analitik` |
| | Hasil EDOM | `/dosen/edom` |
| **PERWALIAN** | Persetujuan KRS | `/dosen/persetujuan-krs` |
| | Mahasiswa Bimbingan | `/dosen/bimbingan` |
| | Tugas Akhir | `/dosen/tugas-akhir` |
| | Aplikasi Terhubung | `/aplikasi-terhubung` |
| **KEPEGAWAIAN** | Beban Kerja (BKD) | `/dosen/bkd` |
| | Penilaian BKD | `/dosen/bkd/penilaian` |
| | Portofolio | `/dosen/portofolio` |

## Portal Staf — 33 modul

Akun demo: `admin@demo.test` · kata sandi `password`

| Kelompok | Modul | Rute |
|---|---|---|
| — | Dasbor | `/admin` |
| **AKADEMIK** | Master Akademik | `/admin/master` |
| | Jadwal & Kelas | `/admin/kelas` |
| | Padanan & Paket | `/admin/kurikulum-lanjutan` |
| | Koreksi Nilai | `/admin/koreksi-nilai` |
| | Penutupan Semester | `/admin/tutup-semester` |
| **MAHASISWA** | Data Mahasiswa | `/admin/mahasiswa` |
| | PMB | `/admin/pmb` |
| | Cuti Mahasiswa | `/admin/cuti` |
| | Konversi Kredit | `/admin/konversi` |
| | Evaluasi Studi | `/admin/evaluasi-studi` |
| | Poin Kemahasiswaan | `/admin/poin-kemahasiswaan` |
| | Tugas Akhir | `/admin/tugas-akhir` |
| | Yudisium | `/admin/yudisium` |
| | Wisuda | `/admin/wisuda` |
| | Surat & Dokumen | `/admin/surat` |
| **SDM** | Kepegawaian Dosen | `/admin/dosen` |
| | Akun Staf | `/admin/staf` |
| | Unit Kerja | `/admin/unit-kerja` |
| | Beban Kerja Dosen | `/admin/bkd` |
| | Evaluasi Dosen | `/admin/edom` |
| **KEUANGAN** | Matriks Tarif | `/admin/tarif` |
| | Tagihan & Rekonsiliasi | `/admin/keuangan` |
| | Beasiswa & Keringanan | `/admin/beasiswa` |
| | Integrasi Akuntansi | `/admin/akuntansi` |
| **PELAPORAN** | Neo Feeder PDDIKTI | `/admin/feeder` |
| | Campus Bridge | `/admin/bridge` |
| | Verifikasi Data IKU | `/admin/data-iku` |
| | Log Aktivitas | `/admin/log` |
| **SISTEM** | Rencana Kinerja | `/admin/kinerja` |
| | SPMI & Audit Mutu | `/admin/spmi` |
| | Pengumuman | `/admin/pengumuman` |
| | Pengaturan | `/admin/pengaturan` |

---

# BAGIAN II — BELUM DIBUAT

## Modul yang belum ada — 9

| Modul | Kenapa belum / catatan |
|---|---|
| **Klien SISTER** | **Tertahan di luar repo** — menunggu kredensial |
| **Adaptor payment gateway** | **Tertahan di luar repo** — kontraknya sudah ada, yang kurang kredensial merchant + endpoint notifikasi yang terverifikasi tanda tangannya. Tanpa verifikasi, siapa pun yang menjangkau endpoint itu bisa melunasi tagihan mana pun |
| Komparasi data SIAKAD ↔ Neo Feeder | Sinkron sudah jalan; pembanding selisihnya belum ada |
| Federasi ke IdP eksternal | Google / Entra / Keycloak. Open Academic sudah jadi *penerbit* identitas; jadi *konsumen* belum |
| Denda keterlambatan | Kecil secara kode, sering diminta |
| Monitoring pemakaian ruang | Bentrok jadwal sudah terdeteksi; pemanfaatan ruangnya belum terlihat |
| Career center | Lowongan, forum & agenda alumni. Tabel `alumni` sudah ada sebagai dasar — tapi instrumennya milik Open Campus |
| 2FA staf | |
| Helpdesk | |

Aplikasi mobile native **tidak direncanakan untuk v1** — web-nya responsif, dan
API-first sudah menyiapkan jalannya.

## Selesai sebagian — 7

| Hal | Yang sudah | Yang kurang |
|---|---|---|
| Payment gateway | Kontrak `PaymentGatewayInterface` | Adaptor sungguhan (lihat di atas) |
| Tracer study | `alumni.status_pekerjaan` | Instrumennya milik Open Campus |
| Seeder lewat service | Konversi, EDOM, BKD, akuntansi | Sisanya menulis langsung ke tabel, jadi aturan akademik tidak ikut ditegakkan pada data demo |
| FormRequest | Sebagian | Validasi masih banyak di controller |
| DTO | Sebagian | Menyusul per modul |
| `lang/id` | auth, validation, pagination | String modul menyusul |
| CSP | Ketat di selebihnya | Masih `'unsafe-eval'` — Alpine mengevaluasi ekspresi `x-` lewat konstruktor `Function` |

## Utang teknis

Empat yang tersisa. Empat lainnya lunas pada 19 Agustus 2026 dan dicatat di
bawahnya, supaya angkanya dapat diperiksa dan bukan diyakini.

| Hal | Keadaan |
|---|---|
| **Katalog KRS 2,2 MB** | **Paginasi diperlukan.** 1.000 baris = 1,92 s dan **2.235 KB HTML**. Pada 2.000 mahasiswa serentak itu ±4,4 GB lewat jaringan kampus dalam hitungan menit — jaringan yang menyerah lebih dulu, bukan PHP |
| Validasi muatan berisi | Sapuan rute tulis hanya mengirim muatan kosong dan id palsu: terbukti tidak ada yang meledak, **bukan** bahwa aturannya ditegakkan. Nilai di luar rentang, tanggal terbalik, SKS negatif belum pernah dicoba lewat HTTP |
| Font dari Google Fonts | Mengungkap IP pengunjung ke pihak ketiga; hosting sendiri menghapus itu sekaligus satu pengecualian CSP |
| Sisa scaffolding pembayaran | `config/payment.php` menjanjikan driver `fake` yang kelasnya tidak ada — config yang berbohong. Plus direktori kosong `app/Services/Payment/*` |

### Lunas 19 Agustus 2026

| Hal | Hasilnya |
|---|---|
| ~~Suite lambat~~ | Serial 2.574 detik → **paralel 319–670 detik**. CI beralih ke `composer test:par` sesudah *race* kunci Passport diperbaiki — sebelumnya gagal 3 dari 5 kali |
| ~~Uji beban bersamaan~~ | Perebutan kuota: 19 proses, 1 kursi → **tepat 1 pemenang**; 5 kursi → tepat 5. `lockForUpdate()` bertahan di MySQL sungguhan, sesuatu yang suite SQLite tidak akan pernah buktikan. **Beban HTTP penuh masih terbuka** |
| ~~Katalog KRS belum terukur~~ | Terukur — hasilnya jadi baris pertama tabel di atas |
| ~~Fixture skala sekali pakai~~ | Jadi perintah: `openacademic:beban-katalog` dan `openacademic:beban-kuota` |

## Sengaja di luar cakupan — 8

Bukan kesenjangan. Ini batas ekosistem; mengaburkannya akan menduplikasi data
yang harus punya satu pemilik.

| Modul | Pemiliknya |
|---|---|
| CBT / platform ujian | Produk tersendiri — sambungkan lewat Campus Bridge |
| LMS / e-learning, forum kelas, feed | Open Campus, Moodle, atau LMS mana pun |
| Tracer study, jejaring alumni, career center | Open Campus |
| **Dasbor 12 IKU, borang akreditasi (LKPS/LED)** | Open Campus — di sini hanya faktanya. Empat dari delapan IKU pun bukan data aplikasi ini |
| Perpustakaan | SLiMS atau sejenisnya |
| Payroll, presensi pegawai | HRIS |
| Inventaris, sarpras, aset | Sistem aset |
| Buku besar & jurnal akuntansi | Sistem akuntansi; di sini hanya tagihan mahasiswa dan jembatannya |

**SPMI di sini adalah AMI**, bukan borang akreditasi — batasnya turun satu
tingkat, lihat [`SPMI.md`](SPMI.md).

---

# BAGIAN III — TANGKAPAN LAYAR

52 layar, 1440×1000, dari basis data demo — 4 publik, 12 mahasiswa,
13 dosen, 23 staf. Semuanya diverifikasi **HTTP 200**.

### Publik — tanpa masuk

| | |
|---|---|
| **Landing** `/` — halaman pertama yang dilihat siapa pun | ![Landing](tangkapan-layar/01-landing.png) |
| **Masuk** `/masuk` | ![Masuk](tangkapan-layar/00-masuk.png) |
| **Verifikasi Dokumen** `/verifikasi` — untuk dokumen yang QR-nya tidak terpindai | ![Verifikasi](tangkapan-layar/02-verifikasi.png) |
| **Hasil verifikasi** `/verifikasi/{uuid}` — menampilkan data secukupnya untuk mencocokkan, tidak lebih | ![Hasil verifikasi](tangkapan-layar/03-verifikasi-hasil.png) |

### Portal Mahasiswa

| | |
|---|---|
| **Dasbor** | ![Dasbor mahasiswa](tangkapan-layar/m01-dasbor.png) |
| **Rencana Studi (KRS)** — alur persetujuan + batas SKS | ![KRS](tangkapan-layar/m02-krs.png) |
| **Jadwal Kuliah** | ![Jadwal](tangkapan-layar/m03-jadwal.png) |
| **Presensi Mandiri** | ![Presensi](tangkapan-layar/m04-presensi.png) |
| **KHS & Transkrip** | ![KHS](tangkapan-layar/m05-khs.png) |
| **Capaian Pembelajaran** | ![Capaian](tangkapan-layar/m06-capaian.png) |
| **Tugas Akhir** | ![Tugas akhir](tangkapan-layar/m07-tugas-akhir.png) |
| **Surat & Dokumen** | ![Surat](tangkapan-layar/m08-surat.png) |
| **Evaluasi Dosen** | ![EDOM](tangkapan-layar/m09-edom.png) |
| **Tagihan & Pembayaran** | ![Tagihan](tangkapan-layar/m10-tagihan.png) |
| **Profil Akademik** | ![Profil](tangkapan-layar/m11-profil.png) |
| **Aplikasi Terhubung** | ![SSO](tangkapan-layar/m12-sso.png) |

### Portal Dosen

| | |
|---|---|
| **Dasbor** | ![Dasbor dosen](tangkapan-layar/d01-dasbor.png) |
| **Kelas Diampu** | ![Kelas](tangkapan-layar/d02-kelas.png) |
| **Input Nilai** | ![Nilai](tangkapan-layar/d03-nilai.png) |
| **Presensi** | ![Presensi dosen](tangkapan-layar/d04-presensi.png) |
| **RPS & Jurnal** | ![RPS](tangkapan-layar/d05-rps.png) |
| **Analitik Kelas** | ![Analitik](tangkapan-layar/d06-analitik.png) |
| **Hasil EDOM** | ![EDOM dosen](tangkapan-layar/d07-edom.png) |
| **Persetujuan KRS** | ![Persetujuan KRS](tangkapan-layar/d08-persetujuan-krs.png) |
| **Mahasiswa Bimbingan** | ![Bimbingan](tangkapan-layar/d09-bimbingan.png) |
| **Tugas Akhir** | ![TA dosen](tangkapan-layar/d10-tugas-akhir.png) |
| **Beban Kerja (BKD)** | ![BKD](tangkapan-layar/d11-bkd.png) |
| **Penilaian BKD** | ![Penilaian BKD](tangkapan-layar/d12-bkd-penilaian.png) |
| **Portofolio** | ![Portofolio](tangkapan-layar/d13-portofolio.png) |

### Portal Staf

| | |
|---|---|
| **Dasbor** | ![Dasbor staf](tangkapan-layar/s01-dasbor.png) |
| **Master Akademik** | ![Master](tangkapan-layar/s02-master.png) |
| **Jadwal & Kelas** | ![Kelas admin](tangkapan-layar/s03-kelas.png) |
| **Padanan & Paket** | ![Kurikulum lanjutan](tangkapan-layar/s04-kurikulum-lanjutan.png) |
| **Data Mahasiswa** | ![Mahasiswa](tangkapan-layar/s05-mahasiswa.png) |
| **PMB** | ![PMB](tangkapan-layar/s06-pmb.png) |
| **Evaluasi Studi** | ![Evaluasi studi](tangkapan-layar/s07-evaluasi-studi.png) |
| **Poin Kemahasiswaan** | ![Poin](tangkapan-layar/s08-poin.png) |
| **Tugas Akhir** | ![TA admin](tangkapan-layar/s09-tugas-akhir.png) |
| **Yudisium** | ![Yudisium](tangkapan-layar/s10-yudisium.png) |
| **Surat & Dokumen** | ![Surat admin](tangkapan-layar/s11-surat.png) |
| **Kepegawaian Dosen** | ![Dosen admin](tangkapan-layar/s12-dosen.png) |
| **Unit Kerja** | ![Unit kerja](tangkapan-layar/s13-unit-kerja.png) |
| **Beban Kerja Dosen** | ![BKD admin](tangkapan-layar/s14-bkd.png) |
| **Evaluasi Dosen** | ![EDOM admin](tangkapan-layar/s15-edom.png) |
| **Tagihan & Rekonsiliasi** | ![Keuangan](tangkapan-layar/s16-keuangan.png) |
| **Beasiswa & Keringanan** | ![Beasiswa](tangkapan-layar/s17-beasiswa.png) |
| **Integrasi Akuntansi** | ![Akuntansi](tangkapan-layar/s18-akuntansi.png) |
| **Neo Feeder PDDIKTI** | ![Feeder](tangkapan-layar/s19-feeder.png) |
| **Campus Bridge** | ![Bridge](tangkapan-layar/s20-bridge.png) |
| **Verifikasi Data IKU** | ![Data IKU](tangkapan-layar/s21-iku.png) |
| **Rencana Kinerja** | ![Kinerja](tangkapan-layar/s22-kinerja.png) |
| **SPMI & Audit Mutu** | ![SPMI](tangkapan-layar/s23-spmi.png) |
| **Pengaturan** | ![Pengaturan](tangkapan-layar/s24-pengaturan.png) |

Sembilan modul staf tidak ikut dipotret karena bentuk layarnya sama dengan yang
sudah ada di atas: Koreksi Nilai, Penutupan Semester, Cuti Mahasiswa, Konversi
Kredit, Wisuda, Akun Staf, Matriks Tarif, Log Aktivitas, Pengumuman.

---

## Cara mengambil ulang tangkapan layar

Aplikasi harus berjalan (`http://localhost/open-academic` lewat XAMPP, atau
`php artisan serve`) dengan basis data demo ter-seed **dan migrasi mutakhir**.

Skripnya memakai Chrome headless lewat CDP: mengisi formulir `/masuk` dengan
`identitas` + `password`, lalu menyusuri rute tiap portal. Kuki dibersihkan
antar-portal supaya sesi sebelumnya tidak terbawa.

**Yang wajib diperiksa: status HTTP, bukan path.** Versi pertama skrip ini
melaporkan "0 gagal" sementara dua layar mengembalikan 500 — halaman galat
Laravel punya path yang sama dengan halaman sehat dan tidak menulis apa pun ke
konsol peramban. Pantau `Network.responseReceived` untuk `type === 'Document'`
dan perlakukan apa pun selain 200 sebagai gagal.

### Dua cacat yang ditemukan justru karena memotret

**1. Migrasi kuesioner tidak dapat dipasang di MySQL.** Nama indeks otomatis
untuk `kuesioner_jawaban_anonim` jadi 67 karakter — melewati batas 64 karakter
MySQL. Seluruh suite hijau karena tes berjalan di SQLite, yang tidak punya batas
itu. Diperbaiki dengan nama indeks eksplisit; aturannya kini tertulis di
`CLAUDE.md` §9.

**2. `/admin/pmb` melempar `LazyLoadingViolationException`.** View-nya merender
`prodiPilihan2` yang tidak ikut di-eager-load. Layar ini tidak pernah masuk
`SmokeLayarTest`, jadi 897 tes hijau tanpa pernah menyentuhnya. Diperbaiki dan
sekarang dipaku di sana dengan anggaran 20 kueri.
