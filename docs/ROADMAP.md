# ROADMAP.md — Peta Modul & Rencana Kerja

> **Dokumen ini menghadap ke depan.** Isinya: apa yang sudah ada, apa yang belum,
> dan apa yang dikerjakan berikutnya beserta alasannya.
>
> **Riwayat per sesi ada di [`STATUS.md`](STATUS.md)** — apa yang dikerjakan
> kapan, cacat apa yang ditemukan, dan keputusan apa yang diambil. Sampai
> Agustus 2026 dokumen ini menampung keduanya, dan berkas 957 baris yang
> sembilan persepuluhnya riwayat sudah berhenti dapat dipakai sebagai rencana.
>
> Status di sini hasil pemeriksaan, bukan niat: ✅ berarti ada kode yang
> mengeksekusinya dan sudah dijalankan.

**Legenda** — ✅ Selesai · 🟡 Sebagian · ⬜ Belum · 🚫 Sengaja di luar cakupan

Diverifikasi terhadap kode dan basis data pada **16 Agustus 2026**.

---

## Apa Sistem Ini, dan Apa Bukan

Open Academic adalah **sistem catatan akademik**: orang, perkuliahan, dan rekam
jejaknya. Tiga hal itu yang dikejar sampai dalam.

| Poros | Isinya |
|---|---|
| **Data mahasiswa** | Biodata, status per semester, cuti, aktivitas, kelulusan, alumni |
| **Data dosen** | Kepegawaian, portofolio SISTER, beban kerja, penugasan, evaluasi |
| **Perkuliahan** | Kurikulum, kelas, jadwal, KRS, presensi, nilai, transkrip, tugas akhir |

Ditambah tiga penopang yang tidak dapat dipisahkan darinya: **keuangan
mahasiswa** (karena menggerbang KRS dan kelulusan), **pelaporan** (Feeder,
SISTER, IKU), dan **integrasi** (Bridge, SSO).

**Bukan** platform ujian, **bukan** platform belajar. Keduanya kategori produk
tersendiri dengan siklus hidup, beban, dan model datanya sendiri — lihat
§Sengaja Bukan di Sini.

---

## Ringkasan

| | |
|---|---|
| Tabel domain | 88 |
| Migrasi | 40 |
| Model Eloquent | 68 |
| Enum | 44 |
| Service | 69 |
| Policy | 7 |
| Layar (rute GET berportal) | 83 |
| Endpoint Campus Bridge | 11 |
| Perintah artisan | 8 |
| **Tes Pest** | **743 hijau (1.632 asersi)** |

Seluruh fase (0–5), SSO OAuth2, tujuh kesenjangan SIAKAD (G1–G7), dan integrasi
akuntansi sudah selesai. `php artisan migrate:fresh --seed` membangun kampus demo
utuh dalam ±25 detik.

**Repo di bawah Git sejak 11 Agustus 2026. Belum ada remote** — itu keputusan
pemilik repo, bukan pekerjaan yang tertinggal.

---

## Peta Cakupan Modul

Apa yang ada hari ini, dikelompokkan menurut siapa yang memakainya.

### Akademik inti

| Modul | | Catatan |
|---|---|---|
| Master akademik (fakultas, prodi, kurikulum, MK, ruang, semester) | ✅ | Prasyarat berlingkar ditolak; penghapusan berelasi dijaga |
| Jadwal & kelas | ✅ | Bentrok ruang/dosen menghalangi; bentrok sekohor hanya memperingatkan |
| KRS & persetujuan dosen wali | ✅ | Batas SKS dari IPS semester terakhir yang final |
| Penilaian & finalisasi | ✅ | Komponen berbobot 100; koreksi pasca-final ter-audit |
| Presensi + sesi QR | ✅ | 16 pertemuan; ambang kelayakan UAS |
| KHS, transkrip, PDF | ✅ | Konversi kredit ditandai terpisah |
| Penutupan semester | ✅ | Pembekuan catatan yang menyalakan tangga batas SKS |
| RPS & SAP | ✅ | Peta MK→CPL yang membuat penguasaan materi terukur — [`RPS-ANALITIK.md`](RPS-ANALITIK.md) |
| Jurnal perkuliahan (BAP) | ✅ | Terlaksana dan berjurnal dilaporkan terpisah; jaraknya adalah temuannya |
| Analitik kelas & mahasiswa | ✅ | Kehadiran, penilaian, penguasaan CPL. Fakta dan aturan — tanpa prediksi |
| Padanan mata kuliah | ✅ | Terarah dan transitif; mendarat di satu tempat — `PrasyaratChecker` |
| Kurikulum konsentrasi | ✅ | Menggerbang katalog KRS; mata kuliah bersama tetap terbuka untuk semua |
| Kuliah paket | ✅ | Mendelegasi ke `KrsService`, jadi tak satu pun aturan dilewati |
| **Evaluasi mahasiswa (peringatan & DO)** | ⬜ | Ada penutupan semester, belum ada aturan evaluasi bertingkat |
| **Monitoring pemakaian ruang** | ⬜ | Bentrok terdeteksi; pemanfaatannya belum terlihat |
| **Cetak KTM, kartu ujian, absensi** | ⬜ | Pola PDF sudah ada dari transkrip/SKPI/BKD |
| **Kustomisasi templat dokumen** | ⬜ | Transkrip, ijazah, KHS — kini masih Blade, bukan templat per kampus |

### Kemahasiswaan

| Modul | | Catatan |
|---|---|---|
| Data & profil mahasiswa | ✅ | Kelengkapan PDDIKTI ditandai per baris |
| Cuti | ✅ | Status berpindah otomatis, dan kembali saat cuti berakhir |
| Tugas akhir (G1) | ✅ | Satu TA aktif dijaga indeks; panel sidang wajib berisi non-pembimbing |
| Aktivitas & MBKM | ✅ | Terverifikasi staf, jadi bukan klaim tak diperiksa |
| Konversi kredit / RPL (G5) | ✅ | Plafon persentase menjaga arti gelar |
| Yudisium & wisuda | ✅ | Judul diambil dari catatan TA, bukan diketik ulang |
| Surat & SKPI (G3) | ✅ | Verifikasi publik ber-QR yang benar-benar memverifikasi |
| PMB | ✅ | Generator NIM + provisioning akun |
| **Poin kemahasiswaan & pelanggaran** | ⬜ | Banyak kampus mewajibkan |
| **Career center** (lowongan, forum & agenda alumni) | ⬜ | Tabel `alumni` ada sebagai dasar |
| **Tracer study** | 🚫→🟡 | Instrumennya milik Open Campus; di sini hanya `alumni.status_pekerjaan` |

### Keuangan

| Modul | | Catatan |
|---|---|---|
| Matriks tarif | ✅ | Baris paling spesifik menang, tidak dijumlahkan |
| Penerbitan tagihan massal | ✅ | Idempoten dan dapat dipratinjau |
| Pembayaran & rekonsiliasi | ✅ | Total diturunkan dari baris, tidak pernah ditambahkan |
| Beasiswa, keringanan, potongan (G4) | ✅ | Tak pernah negatif, tak pernah menelan pembayaran |
| Gerbang pembayaran KRS | ✅ | Ambang + dispensasi berbatas waktu |
| Integrasi akuntansi (Easy Accounting) | ✅ | Opsional; bawaan `nonaktif` |
| **Payment gateway aktif** | 🟡 | Kontrak ada, adaptor tidak — lihat §Menunggu di Luar Repo |
| **Denda keterlambatan** | ⬜ | Kecil secara kode, sering diminta |

### SDM & pelaporan dosen

| Modul | | Catatan |
|---|---|---|
| Kepegawaian dosen & staf | ✅ | |
| Portofolio SISTER (G7) | ✅ | Riwayat pendidikan, jabatan + angka kredit, sertifikasi |
| BKD (G7) | ✅ | Unsur pendidikan dihitung dari kelas/bimbingan/pengujian/perwalian |
| EDOM (G6) | ✅ | Anonimitas ditegakkan skema, bukan kedisiplinan |
| **Kepegawaian mendalam** | ⬜ | Keluarga, pangkat, mutasi, penghargaan & sanksi, bahasa, organisasi |
| **Manajemen unit kerja** | ⬜ | `staff.unit` masih kolom teks, bukan hirarki terkelola |

### Integrasi

| Modul | | Catatan |
|---|---|---|
| Neo Feeder PDDIKTI | ✅ | Idempoten, ledger, validator pra-kirim |
| Campus Bridge | ✅ | 11 endpoint ber-scope, webhook HMAC, spec OpenAPI |
| SSO OAuth2 (Passport) | ✅ | Kampus jadi penerbit identitas |
| Notifikasi (G2) | ✅ | Kategori wajib tidak dapat dimatikan |
| **Komparasi data SIAKAD ↔ Neo Feeder** | ⬜ | Sinkron ada; pembanding selisih belum |
| **Klien SISTER** | ⬜ | Menunggu kredensial |
| **Federasi ke IdP eksternal** | ⬜ | Google/Entra/Keycloak |

### Platform

| | | |
|---|---|---|
| Peran, izin, kebijakan | ✅ | 7 policy; izin mengikat, bukan hiasan |
| Log aktivitas | ✅ | Tanpa rute ubah/hapus |
| Unggah berkas privat | ✅ | Disk publik ditolak service |
| Portabilitas basis data | ✅ | MySQL/PostgreSQL — [`BASIS-DATA.md`](BASIS-DATA.md) |
| Header keamanan, aksesibilitas | ✅ | [`SECURITY.md`](../SECURITY.md) |
| **CBT** | 🚫 | Platform ujian — di luar cakupan, lihat §Sengaja Bukan di Sini |
| **LMS / forum kelas** | 🚫 | Platform belajar — idem |
| **Helpdesk** | ⬜ | |
| **Kuesioner umum** | ⬜ | EDOM bertujuan tetap |
| **Aplikasi mobile native** | ⬜ | Web responsif saja |
| **2FA staf** | ⬜ | |

---

## Perbandingan dengan Pembanding Komersial

Diperiksa terhadap **[SEVIMA siAkadCloud](https://sevima.com/siakadcloud/fitur/)**
pada 15 Agustus 2026 — pembanding yang wajar: 200+ kampus, 15+ tahun.

> **Halaman pemasaran bukan daftar isi produk.** Yang tidak tercantum di sana
> belum tentu tidak ada. Yang dibandingkan di bawah adalah apa yang mereka
> *iklankan* melawan apa yang kita *punya dan teruji*.

### Yang mereka punya dan kita tidak — **di dalam cakupan kita**

Hanya baris-baris ini yang dihitung sebagai kesenjangan. Yang di luar poros
akademik ada di tabel berikutnya.

| Modul | Poros | Beratnya |
|---|---|---|
| **Payment gateway aktif** | Keuangan | **Besar** — kita hanya punya pencatatan manual |
| **Padanan mata kuliah** | Perkuliahan | Sedang — dibutuhkan tiap kurikulum berganti |
| **Kurikulum konsentrasi** | Perkuliahan | Sedang |
| **Kuliah paket** | Perkuliahan | Sedang — normal di vokasi/diploma |
| **Evaluasi mahasiswa (peringatan & DO)** | Mahasiswa | Sedang |
| **Poin kemahasiswaan & pelanggaran** | Mahasiswa | Sedang — banyak kampus mewajibkan |
| **Kepegawaian mendalam & unit kerja** | Dosen | Sedang — sebagian ditutup G7 |
| **Kuesioner umum** | Perkuliahan | Sedang — kita hanya punya EDOM |
| **Komparasi data SIAKAD ↔ Neo Feeder** | Pelaporan | Sedang |
| **Cetak KTM, kartu ujian, absensi, jurnal** | Perkuliahan | Kecil–sedang |
| **Kustomisasi templat dokumen** | Perkuliahan | Kecil–sedang |
| **Denda keterlambatan** | Keuangan | Kecil–sedang |
| **Aplikasi mobile native** | Semua | Sedang — web responsif sudah jalan |

### Yang mereka punya dan **sengaja tidak kita kejar**

Bukan kalah. Batas produk.

| Modul mereka | Kenapa di luar |
|---|---|
| **CBT** | Platform ujian: bank soal, sesi serentak ribuan peserta, anti-curang, penilaian otomatis. Beban dan model datanya tidak ada hubungannya dengan sistem catatan akademik |
| **EdLink (LMS)** | Platform belajar: materi, tugas, diskusi, progres |
| Career center, forum & agenda alumni | Milik Open Campus |
| AkreditasiCloud / SPMI / SAPTO | Produk terpisah di sisi mereka juga; di sini hanya faktanya |
| Helpdesk | Dapat dibeli terpisah dari mana pun |
| Backup data | Urusan infrastruktur |

Sikapnya sama untuk keduanya: **kalau kampus butuh CBT atau LMS, sambungkan
lewat Campus Bridge dan SSO.** Nilai akhirnya masuk ke sini sebagai nilai;
sesi ujiannya tidak perlu.

### Yang kita punya dan tidak mereka iklankan

| | |
|---|---|
| **Campus Bridge** | REST ber-scope + webhook HMAC + OpenAPI. Produk mereka tertutup; integrasi lewat ekosistem sendiri |
| **Server SSO OAuth2** | Kampus jadi penerbit identitas, bukan konsumen |
| **EDOM anonim secara struktural** | Kolom penghubungnya tidak ada. Kuesioner mereka lebih umum; jaminan anonimitasnya tidak disebut |
| **BKD terhitung otomatis** | Mereka punya angka kredit; unsur pendidikan yang ditarik sendiri tidak diiklankan |
| **Konversi RPL berplafon** | Menjaga arti gelar |
| **Verifikasi surat publik** | QR + halaman yang benar-benar memverifikasi |
| **Integrasi akuntansi terbuka** | Mereka mengarah ke produk sendiri (EduFin) |
| **Sumber terbuka & portabel** | Tanpa kunci vendor |

### Penilaian

**Di dalam poros akademik, hanya satu yang benar-benar kalah: payment gateway
yang jalan.** Sisanya adalah kedalaman, bukan ketiadaan — kita punya
perkuliahan, kita belum punya RPS-nya; kita punya kurikulum, kita belum punya
padanannya.

Kalau kampus membandingkan berdampingan, mereka akan menang di **CBT dan LMS**.
Itu diterima: dua produk yang dibundel, bukan satu produk yang lebih dalam. Yang
harus dijawab bukan "kapan kita punya CBT", melainkan **"seberapa mudah CBT
mana pun disambungkan"** — dan itu sudah dijawab Campus Bridge dan SSO.

Yang murah tetapi selalu muncul di daftar periksa kampus: **denda, cetak
KTM/kartu ujian, padanan MK, dan poin kemahasiswaan.**

---

## Urutan Kerja Berikutnya

Disusun menurut poros, bukan menurut daftar pesaing. Yang memperdalam
perkuliahan didahulukan atas yang melebarkan cakupan.

### P0 — satu-satunya kekalahan nyata

| # | Pekerjaan | Alasan |
|---|---|---|
| 1 | **Adaptor payment gateway** | Kontraknya sudah ada; yang kurang kredensial merchant + endpoint notifikasi **terverifikasi tanda tangannya**. Menerima notifikasi tanpa verifikasi berarti siapa pun yang menjangkau endpoint itu dapat melunasi tagihan mana pun di kampus. Tertahan di luar repo — lihat §Menunggu di Luar Repo |

### P1 — memperdalam perkuliahan

Inti produk. Semuanya menempel pada kelas dan kurikulum yang sudah ada.

| # | Pekerjaan | Kenapa, dan apa yang sudah menopangnya |
|---|---|---|
| ~~2~~ | ~~**RPS & SAP**~~ | ✅ 16 Agustus 2026 — memetakan MK ke CPL, yang membuat penguasaan materi dapat diukur |
| ~~3~~ | ~~**Berita acara & jurnal perkuliahan**~~ | ✅ 16 Agustus 2026 |
| ~~4~~ | ~~**Analitik perkuliahan**~~ | ✅ 16 Agustus 2026 — kehadiran, penilaian, penguasaan CPL |
| ~~5~~ | ~~**Padanan mata kuliah**~~ | ✅ 17 Agustus 2026 |
| ~~6~~ | ~~**Kuliah paket**~~ | ✅ 17 Agustus 2026 |
| ~~7~~ | ~~**Kurikulum konsentrasi**~~ | ✅ 17 Agustus 2026 |
| 8 | **Cetak KTM, kartu ujian, absensi, jurnal** | Pola PDF sudah ada dari transkrip/SKPI/BKD. Jurnal kini punya isinya |
| 9 | **Pengaturan dokumen** | Kop, logo, penandatangan, dan catatan kaki per jenis dokumen. **Bukan** templat Blade yang dapat disunting pengguna — itu berarti mengeksekusi kode yang tersimpan di basis data, dan risikonya tidak sebanding dengan keluwesan yang didapat |

### P2 — memperdalam data mahasiswa & dosen

| # | Pekerjaan | Kenapa |
|---|---|---|
| 9 | **Evaluasi mahasiswa (peringatan & DO)** | Aturan bertingkat atas IPK/SKS per evaluasi. Penutupan semester sudah membekukan angkanya — yang belum ada aturan yang membacanya, dan surat peringatan yang mengikutinya. `SuratService` sudah menyediakan penerbitannya |
| 10 | **Poin kemahasiswaan & pelanggaran** | SKPI sudah menyediakan tempat menampilkan prestasi; poin dan pelanggaran melengkapi sisi lainnya |
| 11 | **Kepegawaian mendalam** | Keluarga, pangkat, mutasi, penghargaan & sanksi, bahasa, organisasi — melanjutkan tiga tabel riwayat dari G7 |
| 12 | **Manajemen unit kerja** | `staff.unit` masih kolom teks. Hirarki terkelola dibutuhkan disposisi dan pelaporan |
| 13 | **Kuesioner umum** | Generalisasi mesin EDOM **tanpa merusak anonimitasnya** — tabel jawaban yang tidak punya penghubung ke pengisi adalah properti yang harus dipertahankan, bukan disederhanakan |

### P3 — pelaporan & platform

| # | Pekerjaan |
|---|---|
| 14 | Komparasi data SIAKAD ↔ Neo Feeder (selisih, bukan hanya kirim) |
| 15 | Denda keterlambatan |
| 16 | 2FA staf; federasi IdP eksternal |
| 17 | Aplikasi mobile native |
| 18 | Helpdesk |

### Bersih-bersih yang sudah tercatat

| Item | Rujukan |
|---|---|
| Driver `fake` pembayaran yang dijanjikan `config/payment.php` tetapi tidak pernah ditulis | §Utang Teknis |
| Direktori kosong `app/Services/Payment/{Contracts,Gateways}` | idem |

---

## Sengaja Bukan di Sini

Bukan kesenjangan. Ini batas ekosistem, dan mengaburkannya akan menduplikasi
data yang harus punya satu pemilik.

| Modul | Pemiliknya |
|---|---|
| **CBT / platform ujian** | Produk tersendiri — sambungkan lewat Bridge |
| **LMS / e-learning, forum kelas, feed** | Open Campus, Moodle, atau LMS mana pun |
| Tracer study, jejaring alumni, career center | Open Campus |
| Dasbor 12 IKU, borang akreditasi, SPMI | Open Campus — di sini hanya faktanya |
| Perpustakaan | SLiMS atau sejenisnya |
| Payroll, presensi pegawai | HRIS |
| Inventaris, sarpras, aset | Sistem aset |
| Buku besar & jurnal akuntansi | Sistem akuntansi; di sini hanya tagihan mahasiswa dan jembatannya |
| Backup & pemulihan | Infrastruktur, bukan aplikasi |

### Kenapa CBT dan LMS tidak akan masuk

Bukan karena berat. Karena **bentuk datanya berlawanan.**

Sistem catatan akademik menyimpan sedikit fakta yang harus benar selama puluhan
tahun: nilai, SKS, kelulusan. Platform ujian dan platform belajar menghasilkan
banyak sekali data berumur pendek — tiap klik jawaban, tiap unggahan tugas, tiap
denyut sesi — dengan puncak beban serentak yang tidak ada hubungannya dengan
puncak beban SIAKAD.

Menyatukan keduanya berarti satu basis data melayani dua pola akses yang saling
merugikan, dan satu jadwal rilis yang harus memuaskan keduanya. Yang sudah
dibangun untuk menghindari itu ada dua: **Campus Bridge** (nilai akhir masuk
sebagai nilai; sesi ujiannya tidak perlu ikut) dan **SSO OAuth2** (mahasiswa
masuk ke LMS mana pun dengan identitas kampus).

Pembanding komersial **membundel** keduanya. Itu pilihan yang sah dan membuat
mereka menang saat dibandingkan berdampingan. Batas ini tetap dipilih sadar.

---

## Menunggu di Luar Repo

Bukan pekerjaan yang tertinggal. Semuanya tertahan pada akses atau keputusan
yang tidak ada di sini.

| Item | Tertahan pada |
|---|---|
| Adaptor Midtrans | Kredensial merchant + endpoint notifikasi terverifikasi |
| Klien SISTER | Kredensial kementerian. Tidak dibuat mode `fake` seperti Neo Feeder: kontraknya belum dapat diuji, dan menulisnya melawan tebakan akan membekukan tebakan itu ke dalam model data |
| Endpoint pembayaran Easy Accounting | Belum ada di API v1 mereka. Bila lahir, yang berubah satu metode di `PenjurnalanService` |
| Federasi IdP eksternal | Keputusan pemetaan guard |
| **Remote Git / rilis publik v1.0** | Keputusan pemilik repo |

---

## Utang Teknis

| Item | | Kenapa penting |
|---|---|---|
| Seeder menulis lewat service | 🟡 | Sebagian sudah (konversi, EDOM, BKD, akuntansi); sisanya menulis langsung ke tabel, sehingga aturan akademik tidak ikut ditegakkan pada data demo |
| Driver `fake` pembayaran | ⬜ | `config/payment.php` menjanjikannya; kelasnya tidak ada dan tidak ada binding. Config yang berbohong |
| Direktori kosong `app/Services/Payment/*` | ⬜ | Sisa scaffolding Fase 1; kontrak sungguhannya di `Services/Keuangan/Contracts` |
| FormRequest | 🟡 | Validasi masih banyak di controller |
| DTO | 🟡 | Menyusul per modul |
| `lang/id` | 🟡 | auth/validation/pagination sudah; string modul menyusul |
| Perintah pembangkit data skala | ⬜ | Fixture 5.000 mahasiswa untuk [`KAPASITAS.md`](KAPASITAS.md) dibuat sekali pakai |
| Katalog KRS pada ±1.000 kelas | ⬜ | Diukur pada 63 kelas; kemungkinan perlu paginasi |
| Uji beban bersamaan | ⬜ | Puncak beban SIAKAD adalah jam pembukaan KRS; belum diuji sama sekali |
| CSP masih `'unsafe-eval'` | 🟡 | Alpine mengevaluasi ekspresi `x-` lewat konstruktor `Function` |
| Font dari Google Fonts | ⬜ | Mengungkap IP pengunjung ke pihak ketiga; hosting sendiri menghapus itu sekaligus pengecualian CSP |

---

## Isi Basis Data Demo

`open_academic` · MariaDB 10.4 · utf8mb4 · 84 tabel domain.

| Tabel | Baris | | Tabel | Baris |
|---|---|---|---|---|
| `mahasiswa` | 50 | | `edom_jawaban` | 712 |
| `dosen` | 7 | | `edom_partisipasi` | 89 |
| `staff` | 4 | | `presensi` | 990 |
| `prodi` | 2 | | `krs_detail` | 405 |
| `mata_kuliah` | 42 | | `nilai` | 229 |
| `kelas_kuliah` | 63 | | `tagihan_item` | 154 |
| `krs` | 116 | | `tagihan` | 49 |
| `pmb_pendaftar` | 110 | | `pembayaran` | 43 |
| `akuntansi_dokumen` | 99 | | `tugas_akhir` | 15 |
| `akuntansi_pemetaan` | 33 | | `bkd_baris` | 17 |
| `surat` | 13 | | `yudisium` / `alumni` | 9 / 9 |
| `konversi_kredit` | 6 | | `beasiswa_penerima` | 6 |
| `penugasan_dosen` | 5 | | `bkd_laporan` | 3 |

Muat ulang kapan saja: `php artisan migrate:fresh --seed` (±25 detik).

---

## Yang Bisa Diperiksa Sekarang

`php artisan serve`, lalu `http://127.0.0.1:8000`. Kata sandi seluruh akun demo:
`password`.

| Alur | Akun | Yang layak dicermati |
|---|---|---|
| KHS & Transkrip | `mahasiswa1@demo.test` | **Cek silang IPS/IPK terhadap tabel nilai** |
| EDOM | idem | Pernyataan anonimitas, dan gerbang KRS-nya |
| Tagihan | idem | Rincian dari matriks tarif, potongan beasiswa |
| Hasil EDOM | `dosen1@demo.test` | Ambang responden — kelas kecil memang kosong |
| BKD | idem | Unsur pendidikan sudah terisi tanpa diketik |
| Portofolio | idem | Bahan SISTER |
| Integrasi Akuntansi | `admin@demo.test` | Antrean, nilai belum terbukukan, ekspor jurnal |
| Data Mahasiswa | idem | Kolom **"Siap PDDIKTI"** — satu baris sengaja dibuat gagal |
| Verifikasi surat | — (publik) | Nomor + NIM keduanya wajib |
| Mobile | ketiganya | Bottom nav + sheet "Lainnya" pada 375px |

**Tiga konfigurasi yang wajib disepakati sebelum data produksi masuk** — murah
diubah sekarang, mahal nanti:

1. **Skala nilai huruf** (`config/academic.php`) — kalau salah, seluruh transkrip salah.
2. **Matriks batas SKS** dari IPS.
3. **Ambang pembayaran KRS** — saat ini 50%.

Ditambah, untuk modul yang lebih baru: **bagan akun** (`config/akuntansi.php`)
dan **rubrik SKS BKD** (`config/bkd.php`). Keduanya kebijakan kampus, bukan
fakta.

---

## Riwayat Ringkas

Rinciannya — termasuk cacat yang ditemukan dan keputusan yang diambil — ada di
[`STATUS.md`](STATUS.md).

| | Selesai |
|---|---|
| Fase 0 Fondasi · Fase 1 Inti Akademik | 6 Agustus 2026 |
| Fase 2 Neo Feeder · Fase 3 Campus Bridge · Fase 4 Data IKU · Fase 5 Rilis | 6 Agustus 2026 |
| SSO OAuth2 + enam modul administrasi | 7 Agustus 2026 |
| Finalisasi semester, Jadwal & Kelas, Tarif, Wisuda, Pengumuman, Berkas | 7 Agustus 2026 |
| G1 Tugas Akhir | 8 Agustus 2026 |
| G2 Notifikasi | 9 Agustus 2026 |
| G3 Surat & SKPI | 10 Agustus 2026 |
| G5 Konversi Kredit · Git diinisialisasi | 11 Agustus 2026 |
| G4 Keringanan & Beasiswa | 12 Agustus 2026 |
| G6 EDOM | 13 Agustus 2026 |
| G7 SISTER & BKD | 14 Agustus 2026 |
| Integrasi akuntansi Easy Accounting | 15 Agustus 2026 |
| RPS, jurnal perkuliahan, analitik | 16 Agustus 2026 |
