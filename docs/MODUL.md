# MODUL.md — Daftar Modul Open Academic

> Disusun 18 Agustus 2026. Daftar modul dibaca langsung dari
> `app-core/app/Support/Navigation.php` dan `routes/web.php` — bukan dari
> ingatan. Tangkapan layar diambil dari aplikasi yang berjalan di
> `http://localhost/open-academic` dengan basis data demo.

---

## Ringkasan

**Open Academic** adalah SIAKAD open source untuk perguruan tinggi Indonesia —
*system of record* untuk catatan akademik resmi dan transaksi administratif.
Lihat [`CLAUDE.md`](../CLAUDE.md) §Batas tanggung jawab untuk apa yang **bukan**
cakupannya.

| | |
|---|---|
| Portal | 3 — Mahasiswa, Dosen, Staf |
| Modul (item menu) | **59** — 12 mahasiswa · 14 dosen · 33 staf |
| Rute GET berportal | 92 |
| Tabel domain | 109 · 48 migrasi |
| Model Eloquent | 94 · 54 enum · 85 service · 7 policy |
| Perintah artisan | 10 |
| Berkas tes Pest | 58 |
| Desain | Midnight Executive — navy `#1E2761`, emas `#C9A961` |

Peran ditentukan guard: `mahasiswa`, `dosen`, `staff`. Izin per modul memakai
Spatie Permission, terpisah per guard.

---

## Portal Mahasiswa

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
| Surat & Dokumen | `/mahasiswa/surat` | Ajukan surat, unduh SKPI |
| Evaluasi Dosen | `/mahasiswa/edom` | Pengisian EDOM (anonim secara skema) |
| Tagihan & Pembayaran | `/mahasiswa/tagihan` | Tagihan, potongan, riwayat bayar |
| Profil Akademik | `/mahasiswa/profil` | Data diri, status, cuti |
| Aplikasi Terhubung | `/aplikasi-terhubung` | Klien OAuth2 yang diberi izin |

---

## Portal Dosen

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

---

## Portal Staf

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
| | Akun Staf | `/admin/staff` |
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

## Tangkapan Layar

49 layar, 1440×1000, dari basis data demo. Semuanya diverifikasi **HTTP 200** —
bukan sekadar "halamannya terbuka".

### Masuk

![Masuk](tangkapan-layar/00-masuk.png)

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

## Cara mengambil ulang

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
itu. Diperbaiki dengan nama indeks eksplisit.

**2. `/admin/pmb` melempar `LazyLoadingViolationException`.** View-nya merender
`prodiPilihan2` yang tidak ikut di-eager-load. Layar ini tidak pernah masuk
`SmokeLayarTest`, jadi 897 tes hijau tanpa pernah menyentuhnya. Diperbaiki dan
sekarang dipaku di sana dengan anggaran 20 kueri.
