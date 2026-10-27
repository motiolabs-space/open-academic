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
| Portal | 4 — Mahasiswa, Dosen, Staf, Pendaftar PMB |
| **Modul terpasang** | **61** — 12 mahasiswa · 14 dosen · 35 staf |
| **Belum dibuat** | 8 modul · 7 sebagian · **1 utang teknis** (7 lunas 19 Agu 2026) · **1 risiko terbuka** |
| Sengaja di luar cakupan | 8 |
| Rute GET berportal | 92 |
| Tabel domain | 109 · 48 migrasi |
| Model Eloquent | 94 · 54 enum · 85 service · 7 policy |
| Perintah artisan | 12 |
| Tes Pest | **1.007 hijau** (2.200 asersi) · 69 berkas |
| Desain | Midnight Executive — navy `#1E2761`, emas `#C9A961` |

Fase 0–6, SSO OAuth2, tujuh kesenjangan SIAKAD (G1–G7), dan integrasi akuntansi
sudah selesai. Fase 6 — pelaporan lanjutan — menambah pembanding PDDIKTI,
ekspor SISTER, borang LKPS, dan laporan KIP Kuliah.

---

# BAGIAN I — SUDAH DIBUAT

## Portal Mahasiswa — 13 modul

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
| Wisuda | `/mahasiswa/wisuda` | Periode yang dibuka, daftar periksa syarat yang sama dengan layar staf, daftar dan batal sendiri selama periode dibuka |
| Surat & Dokumen | `/mahasiswa/surat` | Ajukan surat, unduh SKPI dwibahasa |
| Evaluasi Dosen | `/mahasiswa/edom` | Pengisian EDOM (anonim secara skema) |
| Tagihan & Pembayaran | `/mahasiswa/tagihan` | Tagihan, potongan, denda keterlambatan beserta penjelasannya, riwayat bayar |
| Profil Akademik | `/mahasiswa/profil` | Data diri, status, cuti |
| Aplikasi Terhubung | `/aplikasi-terhubung` | Klien OAuth2 yang diberi izin |

## Portal Dosen — 16 modul

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
| | Riwayat Kepegawaian | `/dosen/kepegawaian` — 14 riwayat, diajukan untuk divalidasi |
| | Angka Kredit | `/dosen/angka-kredit` — buku besar Kum A–E sendiri |

## Portal Staf — 37 modul

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
| | PMB | `/admin/pmb` — gelombang, prodi ditawarkan, jadwal, impor nilai, pengumuman di `/admin/pmb/gelombang/{uuid}`; detail pendaftar di `/admin/pmb/pendaftar/{uuid}` |
| | Cuti Mahasiswa | `/admin/cuti` |
| | Konversi Kredit | `/admin/konversi` |
| | Evaluasi Studi | `/admin/evaluasi-studi` |
| | Poin Kemahasiswaan | `/admin/poin-kemahasiswaan` |
| | Tugas Akhir | `/admin/tugas-akhir` |
| | Yudisium | `/admin/yudisium` |
| | Wisuda | `/admin/wisuda` |
| | Surat & Dokumen | `/admin/surat` |
| **SDM** | Kepegawaian Dosen | `/admin/dosen` — berkas per dosen di `/admin/dosen/{uuid}/kepegawaian` |
| | Validasi Riwayat | `/admin/kepegawaian/validasi` |
| | Akun Staf | `/admin/staf` |
| | Unit Kerja | `/admin/unit-kerja` |
| | Beban Kerja Dosen | `/admin/bkd` |
| | Angka Kredit | `/admin/angka-kredit` — rekap per dosen/jenjang, lembar penyetaraan .xlsx |
| | Evaluasi Dosen | `/admin/edom` |
| **KEUANGAN** | Matriks Tarif | `/admin/tarif` |
| | Tagihan & Rekonsiliasi | `/admin/keuangan` |
| | Beasiswa & Keringanan | `/admin/beasiswa` |
| | Biaya Formulir PMB | `/admin/pmb/formulir` |
| | Integrasi Akuntansi | `/admin/akuntansi` |
| **PELAPORAN** | Neo Feeder PDDIKTI | `/admin/feeder` |
| | Campus Bridge | `/admin/bridge` |
| | Verifikasi Data IKU | `/admin/data-iku` |
| | **Borang LKPS** | `/admin/lkps` |
| **SISTEM** | **Verifikasi Dua Langkah** | `/admin/dua-langkah` |
| | Log Aktivitas | `/admin/log` |
| **SISTEM** | Rencana Kinerja | `/admin/kinerja` |
| | SPMI & Audit Mutu | `/admin/spmi` |
| | Pengumuman | `/admin/pengumuman` |
| | Pengaturan | `/admin/pengaturan` |

## Portal Pendaftar PMB

Akun demo: `calon.seleksi@demo.test` (dan lima lainnya di tiap tahap) · kata
sandi `password` · masuk di `/pmb/masuk`, bukan `/masuk`. Guard sendiri
(`pendaftar`), tidak dapat membuka portal lain. Rincian: [PMB.md](PMB.md).

| Modul | Rute | Isi |
|---|---|---|
| Beranda PMB (publik) | `/pmb` | Gelombang terbuka, prodi, biaya, daya tampung |
| Akun | `/pmb/daftar` · `/pmb/masuk` · `/pmb/lupa-sandi` | Daftar dengan verifikasi surel, lupa sandi, batas laju |
| Pendaftaran Saya | `/pmb/portal` | Daftar pendaftaran, mulai pendaftaran, pemberitahuan |
| Satu Pendaftaran | `/pmb/portal/{uuid}` | Biodata siap Feeder, berkas, biaya formulir, jadwal seleksi, kartu ujian PDF, hasil |

## Denda keterlambatan

Bagian dari **Tagihan & Rekonsiliasi** (`/admin/keuangan`), bukan modul
tersendiri. Bekerja penuh pada pembayaran manual yang dicatat kasir — tidak
menunggu payment gateway. **Bawaan nonaktif**; aturannya kebijakan kampus di
`.env` (`PAYMENT_DENDA_*`, lihat `config/payment.php`).

| Hal | Bagaimana |
|---|---|
| Bentuk | Nominal tetap per hari/minggu, atau persen dari **sisa tagihan pokok** per periode (bulat ke bawah, tanpa float). Masa tenggang dan batas maksimum per tagihan |
| Di mana dendanya | Satu baris `tagihan_item` berjenis `denda` per tagihan; `tagihan.total` tetap jumlah barisnya. `tagihan.denda` menyimpan denda neto yang sedang ada di `total` |
| Siapa yang menghitung | `openacademic:hitung-denda`, terjadwal 01:00 (sebelum pengingat 07:00). `--kering` menunjukkan siapa terkena dan berapa tanpa menulis; `--hari=` untuk menghitung pada tanggal lain |
| Idempoten | **Akrual, bukan hitung ulang.** Tiap jalan hanya menagih periode yang dimulai sesudah `denda_dihitung_sampai`, pada sisa pokok *hari itu*. Jalan dua kali sehari → nol periode baru → tidak ada perubahan. Pembayaran sebagian tidak menyusutkan denda yang sudah berjalan |
| Berhenti | Pokok lunas (walau denda belum), dispensasi aktif (jam denda mulai dari akhir dispensasi), mahasiswa tidak aktif, maksimum tercapai, atau sudah dibebaskan |
| Pembebasan | `DendaService::bebaskan()` — baris potongan negatif bernama "Pembebasan denda keterlambatan" dengan alasan wajib (≥ 5 karakter), nama staf, dan waktu. Denda bruto, pembebasan, dan neto semuanya tetap terlihat; penghitungan berhenti untuk selamanya. Policy `waiveLateFee` (`keuangan.manage`), `BebaskanDendaRequest` |
| Audit | `late_fee_applied` tiap penambahan, `late_fee_waived` tiap pembebasan — lewat `HasLogAktivitas`. Watermark harian dikecualikan dari log supaya jejaknya tidak tenggelam |
| Gerbang KRS | **Tidak diperketat.** `memenuhiSyaratKrs()` mengukur ambang terhadap `pokok()` = `total − denda`. Mahasiswa yang lolos 50 % sebelum denda tetap lolos sesudahnya |
| Yang dilihat mahasiswa | Baris denda berlabel, lencana penjelasan aturan (bila aktif), denda berjalan, atau pembebasan beserta alasannya. Pengingat jatuh tempo menyebut aturan dan denda yang sudah berjalan |
| Belum | Denda **tidak dijurnalkan** ke integrasi akuntansi — pendapatan denda perlu kode akun yang belum ada di bagan; menyusul bersama keputusan perlakuannya |

---

# BAGIAN II — BELUM DIBUAT

## Modul yang belum ada — 8

| Modul | Kenapa belum / catatan |
|---|---|
| ~~**Klien SISTER**~~ | **Dicabut 22 Okt 2026** — SISTER kini terpusat dan menarik pengajaran, pembimbingan, dan pengujian dari PDDIKTI; tidak ada endpoint per kampus. Jalurnya Feeder — [`ROADMAP.md`](ROADMAP.md) §Peta Jalan Lanjutan #19–22 |
| **Adaptor payment gateway** | **Tertahan di luar repo** — kontraknya sudah ada, yang kurang kredensial merchant + endpoint notifikasi yang terverifikasi tanda tangannya. Tanpa verifikasi, siapa pun yang menjangkau endpoint itu bisa melunasi tagihan mana pun |
| Federasi ke IdP eksternal | Google / Entra / Keycloak. Open Academic sudah jadi *penerbit* identitas; jadi *konsumen* belum |
| ~~Denda keterlambatan~~ | **Selesai 24 Okt 2026** — lihat §Denda keterlambatan di Bagian I |
| Monitoring pemakaian ruang | Bentrok jadwal sudah terdeteksi; pemanfaatan ruangnya belum terlihat |
| Career center | Lowongan, forum & agenda alumni. Tabel `alumni` sudah ada sebagai dasar — tapi instrumennya milik Open Campus |
| ~~**Enam formulir SISTER**~~ | **Selesai 27 Okt 2026 (#39)** — keenamnya plus delapan riwayat baru diisi dosen di `/dosen/kepegawaian` dan divalidasi di `/admin/kepegawaian/validasi`. Lihat [`KEPEGAWAIAN.md`](KEPEGAWAIAN.md) |
| ~~Helpdesk~~ | **Selesai 24 Okt 2026** — `/mahasiswa/helpdesk`, `/dosen/helpdesk`, `/admin/helpdesk`, FAQ publik `/bantuan`. Lihat [`HELPDESK.md`](HELPDESK.md) |

Aplikasi mobile native **tidak direncanakan untuk v1** — web-nya responsif, dan
API-first sudah menyiapkan jalannya.

## Risiko terbuka — 0 (1 ditutup 22 Okt 2026)

Bukan modul yang belum dibuat, melainkan perilaku yang sudah berjalan dan
menghasilkan akibat di luar kampus. Ditaruh di sini supaya tidak hanya terbaca
oleh yang membuka [`PELAPORAN.md`](PELAPORAN.md).

| Hal | Keadaan |
|---|---|
| ~~**Nomor ijazah diberikan lokal**~~ | **Ditutup 22 Okt 2026.** Bawaan `ACADEMIC_IJAZAH_SUMBER=pin`: pembangkit nomor ditolak, nomor dicatat dari hasil reservasi PIN di layar Wisuda, dan layar Yudisium menunjukkan kesiapan reservasi per lulusan (#28). Catatan aslinya: `wisuda_peserta.nomor_ijazah` diisi dari dalam aplikasi. Penomoran ijazah nasional bekerja terbalik: kampus **meminta** nomor ke layanan PIN, dan nomor itulah yang dicetak lalu terverifikasi publik lewat SIVIL. Ijazah bernomor lokal akan tercetak rapi, diserahkan pada wisuda, dan **gagal saat diperiksa** — biasanya oleh calon pemberi kerja, bertahun-tahun kemudian, dan menjadi persoalan alumninya. Perlu **keputusan kampus**, bukan tebakan: apakah ijazah dinomori lewat PIN. Bila ya, kolom itu harus berhenti diisi sendiri |

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

**Satu yang tersisa.** Tujuh lainnya lunas pada 19 Agustus 2026 dan dicatat di
bawahnya berikut angkanya, supaya dapat diperiksa dan bukan sekadar diyakini.

| Hal | Keadaan |
|---|---|
| Beban HTTP penuh saat pembukaan KRS | **Sebagian terjawab 22 Agu 2026.** Diukur terhadap Apache sungguhan (mpm_winnt, 150 utas) sampai 40 permintaan serentak: **nol galat di setiap tingkat**. Bukan antrean, bukan kolam koneksi, bukan sesi — ketiga tersangkanya tidak tersentuh. Permintaan mengantre tertib pada langit-langit ~2,2 RPS, dan ~70% waktu tiap permintaan habis di bootstrap PHP sebelum kode aplikasi bekerja. **Pengungkitnya OPcache**, bukan perubahan kode. Yang **masih terbuka**: ribuan permintaan pada perangkat keras produksi — angka mesin pengembangan tidak boleh dipakai menentukan ukuran server. Rinciannya di [`KAPASITAS.md`](KAPASITAS.md) |

### Lunas 19 Agustus 2026

| Hal | Hasilnya |
|---|---|
| ~~Suite lambat~~ | Serial 2.574 detik → **paralel 319–670 detik**. CI beralih ke `composer test:par` sesudah *race* kunci Passport diperbaiki — sebelumnya gagal 3 dari 5 kali |
| ~~Uji beban bersamaan~~ | Perebutan kuota: 19 proses, 1 kursi → **tepat 1 pemenang**; 5 kursi → tepat 5. `lockForUpdate()` bertahan di MySQL sungguhan, sesuatu yang suite SQLite tidak akan pernah buktikan. **Beban HTTP penuh masih terbuka** |
| ~~Katalog KRS belum terukur~~ | Terukur, lalu **dipaginasi**: 2.235 KB → **105 KB**, 1,92 s → 0,79 s. Pada 2.000 mahasiswa serentak, ±4,4 GB menjadi ±210 MB |
| ~~Fixture skala sekali pakai~~ | Jadi perintah: `openacademic:beban-katalog` dan `openacademic:beban-kuota` |
| ~~Font dari Google Fonts~~ | Di-host sendiri lewat `@fontsource`. Dua pengecualian CSP dicabut. Menemukan bug tersembunyi: Vite menulis URL aset absolut dari akar domain, jadi **seluruh pemasangan subfolder** akan kehilangan hurufnya — diperbaiki dengan `base: './'` |
| ~~Sisa scaffolding pembayaran~~ | `config/payment.php` dipangkas ke yang benar-benar dibaca. Ternyata **dua** kebohongan, bukan satu: `allow_partial` juga tak pernah dibaca padahal pembayaran sebagian memang berjalan — kampus yang mematikannya tetap menerima cicilan |
| ~~Validasi muatan berisi~~ | 107 aturan numerik disapu. Dua cacat nyata: `target`/`nilai` kinerja tanpa batas (MySQL longgar **memotong diam-diam** 1e15 jadi 9.999.999.999,99), dan capaian bertanggal di luar periodenya diterima. Keduanya dipaku 7 tes |

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
