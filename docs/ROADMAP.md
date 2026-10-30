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

Diverifikasi terhadap kode pada **24 Oktober 2026**; jumlah tabel dan isi basis
data demo dari pemeriksaan 16 Agustus 2026.

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
| Tabel domain | 109 |
| Migrasi | 55 |
| Model Eloquent | 100 |
| Enum | 60 |
| Service | 111 (termasuk 25 mapper Feeder) |
| Policy | 7 |
| Layar (rute GET berportal) | 94 |
| Endpoint Campus Bridge | 14 |
| Perintah artisan | 13 |
| **Tes Pest** | **1.136 hijau (2.689 asersi)** — paralel 581 s di mesin pengembang, 24 Okt 2026 |

Seluruh fase (0–5), SSO OAuth2, tujuh kesenjangan SIAKAD (G1–G7), dan integrasi
akuntansi sudah selesai. `php artisan migrate:fresh --seed` membangun kampus demo
utuh dalam ±25 detik.

**Repo di bawah Git sejak 11 Agustus 2026, dan publik di GitHub sejak 22 Oktober
2026** — rahasia dan riwayatnya diaudit sebelum dibuka.

---

## Peta Cakupan Modul

Apa yang ada hari ini, dikelompokkan menurut siapa yang memakainya.

### Akademik inti

| Modul | | Catatan |
|---|---|---|
| Master akademik (fakultas, prodi, kurikulum, MK, ruang, semester) | ✅ | Prasyarat berlingkar ditolak; penghapusan berelasi dijaga |
| Jadwal & kelas | ✅ | Bentrok ruang/dosen menghalangi; bentrok sekohor hanya memperingatkan |
| KRS & persetujuan dosen wali | ✅ | Batas SKS dari IPS semester terakhir yang final. Penetapan dosen wali per mahasiswa, per baris, dan bagi merata per prodi/angkatan baru ada 28 Okt 2026 — sebelumnya hanya seeder yang menulis wali (ADR-035 §1) |
| Penilaian & finalisasi | ✅ | Komponen berbobot 100; koreksi pasca-final ter-audit |
| Presensi + sesi QR | ✅ | 16 pertemuan; ambang kelayakan UAS |
| KHS, transkrip, PDF | ✅ | Konversi kredit ditandai terpisah. KHS per semester kini juga PDF, dan staf mencetak keduanya dari halaman detail mahasiswa (28 Okt 2026, ADR-035 §5) |
| Penutupan semester | ✅ | Pembekuan catatan yang menyalakan tangga batas SKS |
| RPS & SAP | ✅ | Peta MK→CPL yang membuat penguasaan materi terukur — [`RPS-ANALITIK.md`](RPS-ANALITIK.md) |
| Jurnal perkuliahan (BAP) | ✅ | Terlaksana dan berjurnal dilaporkan terpisah; jaraknya adalah temuannya |
| Analitik kelas & mahasiswa | ✅ | Kehadiran, penilaian, penguasaan CPL. Fakta dan aturan — tanpa prediksi |
| Padanan mata kuliah | ✅ | Terarah dan transitif; mendarat di satu tempat — `PrasyaratChecker` |
| Kurikulum konsentrasi | ✅ | Menggerbang katalog KRS; mata kuliah bersama tetap terbuka untuk semua |
| Kuliah paket | ✅ | Mendelegasi ke `KrsService`, jadi tak satu pun aturan dilewati |
| Evaluasi mahasiswa (peringatan & DO) | ✅ | Sistem menghitung, manusia memutuskan — [`EVALUASI-STUDI.md`](EVALUASI-STUDI.md) |
| Monitoring pemakaian ruang | ✅ | Laporan baca dari jadwal kuliah + ujian terbit; libur kampus keluar dari jam tersedia — ADR-031 |
| Cetak KTM, kartu ujian, absensi, jurnal | ✅ | Kolom tanda tangan sengaja kosong; KTM tanpa NIK maupun alamat — [`CETAK.md`](CETAK.md) |
| Pengaturan dokumen | ✅ | Kop, penandatangan, catatan kaki per jenis. **Bukan** templat Blade yang dapat disunting |

### Kemahasiswaan

| Modul | | Catatan |
|---|---|---|
| Data & profil mahasiswa | ✅ | Kelengkapan PDDIKTI ditandai per baris. Halaman detail per mahasiswa untuk staf sejak 28 Okt 2026 — sebelumnya hanya daftar (ADR-035 §5) |
| Cuti | ✅ | Status berpindah otomatis, dan kembali saat cuti berakhir |
| Tugas akhir (G1) | ✅ | Satu TA aktif dijaga indeks; panel sidang wajib berisi non-pembimbing |
| Aktivitas & MBKM | ✅ | Terverifikasi staf, jadi bukan klaim tak diperiksa |
| Konversi kredit / RPL (G5) | ✅ | Plafon persentase menjaga arti gelar |
| Yudisium & wisuda | ✅ | Judul diambil dari catatan TA, bukan diketik ulang |
| Surat & SKPI (G3) | ✅ | Verifikasi publik ber-QR yang benar-benar memverifikasi |
| PMB | ✅ | Generator NIM + provisioning akun; pendaftaran daring oleh calon (#32, portal `/pmb`) |
| Poin kemahasiswaan & pelanggaran | ✅ | Dua buku besar, tidak pernah dijumlahkan — [`POIN-KEMAHASISWAAN.md`](POIN-KEMAHASISWAAN.md) |
| Laporan SIMKATMAWA | ✅ | Empat tabel (kompetisi, rekognisi, sertifikasi, pembimbing) dari prestasi terverifikasi, baris yang akan ditolak dilaporkan sebelum unduh CSV/XLSX. Ekspor saja, tanpa pengiriman — ADR-037 §1 |
| Career center (lowongan, forum & agenda alumni) | 🚫 | Milik Open Campus (lihat §Sengaja Bukan di Sini). Tabel `alumni` di sini hanya sumber data lulusan, disajikan lewat Campus Bridge |
| **Tracer study** | 🟡 | Kuesioner dan perhitungan milik Open Campus. Di sini: daftar lulusan dengan kelengkapan kontak, ekspor untuk tracer nasional (tercatat di log), dan impor hasil status pekerjaan ke `alumni` yang mengisi `iku_2026` — ADR-037 §2 |

### Keuangan

| Modul | | Catatan |
|---|---|---|
| Matriks tarif | ✅ | Baris paling spesifik menang, tidak dijumlahkan |
| Penerbitan tagihan massal | ✅ | Idempoten dan dapat dipratinjau |
| Pembayaran & rekonsiliasi | ✅ | Total diturunkan dari baris, tidak pernah ditambahkan |
| Beasiswa, keringanan, potongan (G4) | ✅ | Tak pernah negatif, tak pernah menelan pembayaran |
| Gerbang pembayaran KRS | ✅ | Ambang + dispensasi berbatas waktu. **Koreksi 28 Okt 2026**: dispensasi dibaca gerbang dan denda sejak Fase 1 tetapi tidak ada yang dapat memberikannya; kini meja dispensasi keuangan dan permohonan dari layar tagihan mahasiswa (ADR-035 §2) |
| Integrasi akuntansi (Easy Accounting) | ✅ | Opsional; bawaan `nonaktif` |
| Payment gateway | 🟡 | **Opsional sejak 24 Oktober 2026** (ADR-014). Transfer manual yang dicatat kasir adalah jalur utama; kontrak adaptor ada, implementasinya dibangun hanya bila ada kampus yang membawa kredensial merchant — lihat §Menunggu di Luar Repo |
| Denda keterlambatan | ✅ | Baris tagihan tersendiri, akrual harian idempoten, pembebasan beralasan; tidak memperketat gerbang KRS. Bawaan nonaktif — [`MODUL.md`](MODUL.md) §Denda keterlambatan |

### SDM & pelaporan dosen

| Modul | | Catatan |
|---|---|---|
| Kepegawaian dosen & staf | ✅ | |
| Portofolio SISTER (G7) | ✅ | Riwayat pendidikan, jabatan + angka kredit, sertifikasi |
| BKD (G7) | ✅ | Unsur pendidikan dihitung dari kelas/bimbingan/pengujian/perwalian |
| EDOM (G6) | ✅ | Anonimitas ditegakkan skema, bukan kedisiplinan |
| Kepegawaian mendalam | ✅ | Enam riwayat; hanya pangkat & mutasi yang berat — [`KEPEGAWAIAN.md`](KEPEGAWAIAN.md) |
| Manajemen unit kerja | ✅ | Pohon dengan penjaga lingkaran; kolom teks lama tetap sebagai bukti — [`UNIT-KERJA.md`](UNIT-KERJA.md) |
| Rencana kinerja | ✅ | Sasaran per unit + cascade; realisasi dihitung bila datanya ada di sini — [`KINERJA.md`](KINERJA.md) |
| SPMI — Audit Mutu Internal | ✅ | Standar + AMI + temuan + tindak lanjut. **Borang akreditasi tetap di Open Campus** — [`SPMI.md`](SPMI.md) |

### Integrasi

| Modul | | Catatan |
|---|---|---|
| Neo Feeder PDDIKTI | ✅ | Idempoten, ledger, validator pra-kirim. Dua puluh lima entitas sejak 24 Okt 2026: + dosen pengajar kelas, + Lulus/DO, + komponen evaluasi, + nilai per komponen, + 7 entitas aktivitas (MBKM dan tugas akhir beserta anggota, pembimbing, penguji), + prestasi, transfer kredit, konversi MBKM, + kurikulum, mata kuliah, matkul kurikulum, + skala nilai prodi, periode perkuliahan; `Delete*` untuk yang sudah tiada di sini |
| Campus Bridge | ✅ | 14 endpoint ber-scope, webhook HMAC, spec OpenAPI — sejak 26 Okt 2026 dipaku tes: setiap rute `bridge.*` beserta scope-nya harus ada di spec. Ketujuh event kini benar-benar terbit: `student.enrolled` baru diterbitkan 28 Okt 2026, sekali per mahasiswa (ADR-035 §4) |
| SSO OAuth2 (Passport) | ✅ | Kampus jadi penerbit identitas |
| Layar konsumen Campus Bridge | ✅ | 30 Okt 2026 (Sesi 69, ADR-039 §2) — daftar konsumen, scope, token tampil sekali, cabut per token, terakhir dipakai, URL dan rahasia webhook (rotasi, terenkripsi). Izin `integrasi.kredensial`, peran `tik`. Sebelumnya hanya `openacademic:bridge-token` |
| Layar klien SSO | ✅ | 30 Okt 2026 (Sesi 69, ADR-039 §3) — daftar klien HTTPS, rahasia tampil sekali dan rotasi, pihak pertama, cabut beserta refresh token, jumlah pengguna mengizinkan; hanya bila `SSO_ENABLED`. `SSO_FIRST_PARTY` yang selama ini tidak pernah dibaca kini dihormati. Sebelumnya hanya `openacademic:sso-client` |
| Notifikasi (G2) | ✅ | Kategori wajib tidak dapat dimatikan |
| **Komparasi data SIAKAD ↔ Neo Feeder** | ✅ | FeederRekonsiliasi 21 Agu 2026: membaca Feeder kembali dan melaporkan hanya-lokal / hanya-Feeder / berbeda isinya. Entitas tanpa aksi pembacaan dinyatakan belum dapat dibandingkan, bukan cocok |
| **Klien SISTER** | 🚫 | Tidak ada lagi web service SISTER per kampus: pengajaran, pembimbingan, dan pengujian turun ke SISTER **dari PDDIKTI**. Jalurnya Feeder — §Peta Jalan Lanjutan #19–22 |
| Federasi ke IdP eksternal | ✅ | 24 Okt 2026 — klien OIDC terhadap Google/Entra/Keycloak; staf (opsional dosen) tertaut lewat surel terverifikasi lalu `idp_subject`; TOTP lokal tetap ditantang — [`SSO.md`](SSO.md) |

### Platform

| | | |
|---|---|---|
| Peran, izin, kebijakan | ✅ | 7 policy; izin mengikat, bukan hiasan |
| Log aktivitas | ✅ | Tanpa rute ubah/hapus |
| Unggah berkas privat | ✅ | Disk publik ditolak service |
| Portabilitas basis data | ✅ | MySQL/PostgreSQL — [`BASIS-DATA.md`](BASIS-DATA.md) |
| Header keamanan, aksesibilitas | ✅ | [`SECURITY.md`](../SECURITY.md); CSP tanpa `'unsafe-inline'` untuk skrip sejak 27 Okt 2026 |
| Review keamanan v1.0 | ✅ | 27 Okt 2026 — 3 tinggi + 8 sedang diperbaiki, 16 rendah ditunda: [`KEAMANAN-AUDIT-2026-10.md`](KEAMANAN-AUDIT-2026-10.md) |
| **CBT** | 🚫 | Platform ujian — di luar cakupan, lihat §Sengaja Bukan di Sini |
| **LMS / forum kelas** | 🚫 | Platform belajar — idem |
| **Helpdesk** | ✅ | Tiket administratif ke unit kerja, SLA ditandai bukan diputus, FAQ publik — [`HELPDESK.md`](HELPDESK.md). Sejak 30 Okt 2026 staf mengajukan tiket ke unit lain dan petugas mencatat atas nama mahasiswa/dosen (Sesi 69, ADR-039 §4). Forum dan chat tetap wilayah Open Campus |
| Kuesioner umum | ✅ | Dua tabel jawaban; anonimitas jadi properti skema — [`KUESIONER.md`](KUESIONER.md). **Koreksi:** sampai 30 Okt 2026 hanya mesinnya; tidak ada controller, rute, layar, maupun menu. Layar staf (tulis, terbit, hasil, CSV) dan pengisian dari tiga portal masuk Sesi 69 (ADR-039 §1) |
| **Aplikasi mobile native** | ⬜ | Web responsif saja |
| 2FA staf | ✅ | TOTP + kode pemulihan, 22 Agu 2026 — `/admin/dua-langkah`, ditantang di `/masuk/dua-langkah` |
| Pengumuman | ✅ | Terbit, berakhir, sasaran per portal, sematan. Sejak 28 Okt 2026 tampil di dasbor dan halaman daftar **ketiga** portal — sebelumnya hanya dasbor mahasiswa, jadi pengumuman untuk dosen dan staf tidak pernah terlihat (ADR-035 §3) |

---

## Perbandingan dengan Pembanding Komersial

Diperiksa terhadap halaman fitur publik satu SIAKAD komersial yang dipakai ratusan
kampus, pada 15 Agustus 2026 — pembanding yang wajar.

> **Halaman pemasaran bukan daftar isi produk.** Yang tidak tercantum di sana
> belum tentu tidak ada. Yang dibandingkan di bawah adalah apa yang mereka
> *iklankan* melawan apa yang kita *punya dan teruji*.

### Yang mereka punya dan kita tidak — **di dalam cakupan kita**

Hanya baris-baris ini yang dihitung sebagai kesenjangan. Yang di luar poros
akademik ada di tabel berikutnya.

| Modul | Poros | Beratnya |
|---|---|---|
| Kanal bayar terintegrasi | Keuangan | **Model berbeda, bukan ketiadaan** — kasir + transfer tercatat adalah jalur utama (ADR-014); adaptor gateway opsional |
| ~~**Denda keterlambatan**~~ | Keuangan | ~~Kecil–sedang~~ ✅ 24 Oktober 2026 (#15) |
| **Aplikasi mobile native** | Semua | Sedang — web responsif sudah jalan |

### Yang mereka punya dan **sengaja tidak kita kejar**

Bukan kalah. Batas produk.

| Modul mereka | Kenapa di luar |
|---|---|
| **CBT** | Platform ujian: bank soal, sesi serentak ribuan peserta, anti-curang, penilaian otomatis. Beban dan model datanya tidak ada hubungannya dengan sistem catatan akademik |
| **LMS bawaan** | Platform belajar: materi, tugas, diskusi, progres |
| Career center, forum & agenda alumni | Milik Open Campus |
| Modul akreditasi / SPMI / ekspor SAPTO | Produk terpisah di sisi mereka juga; di sini hanya faktanya |
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
| **Integrasi akuntansi terbuka** | Mereka mengarah ke produk keuangan mereka sendiri |
| **Sumber terbuka & portabel** | Tanpa kunci vendor |

### Penilaian

**Di dalam poros akademik tidak ada yang benar-benar kalah.** Payment gateway
sempat dicatat sebagai satu-satunya kekalahan; sejak 24 Oktober 2026 ia
dinyatakan opsional (ADR-014) — transfer manual yang dicatat kasir adalah jalur
utama, dan adaptornya tinggal menunggu kampus yang membawa kredensial. Sisanya
adalah kedalaman, bukan ketiadaan — kita punya perkuliahan, kita belum punya
RPS-nya; kita punya kurikulum, kita belum punya padanannya.

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

### P0 — dikosongkan 24 Oktober 2026

| # | Pekerjaan | Alasan |
|---|---|---|
| ~~1~~ | ~~**Adaptor payment gateway**~~ | **Diturunkan ke P3 sebagai butir opsional** (ADR-014). Sampai 24 Oktober 2026 baris ini disebut "satu-satunya kekalahan nyata"; pemilik repo memutuskan transfer manual yang dicatat kasir adalah jalur utama, dan kanal bayar terintegrasi bukan syarat v1.0 |

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
| ~~8~~ | ~~**Cetak KTM, kartu ujian, absensi, jurnal**~~ | ✅ 17 Agustus 2026 |
| ~~9~~ | ~~**Pengaturan dokumen**~~ | ✅ 17 Agustus 2026 — kop, penandatangan, catatan kaki per jenis. Templat Blade yang dapat disunting pengguna ditolak sebagai rancangan: itu berarti mengeksekusi kode yang tersimpan di basis data |

**P1 perkuliahan tuntas.** Yang berikutnya P2 — memperdalam data mahasiswa dan
dosen. Payment gateway, yang saat itu disebut satu-satunya kekalahan kompetitif,
kini opsional (ADR-014).

### P2 — memperdalam data mahasiswa & dosen

| # | Pekerjaan | Hasil |
|---|---|---|
| ~~9~~ | ~~**Evaluasi mahasiswa (peringatan & DO)**~~ | ✅ 18 Agustus 2026 — sistem menghitung, manusia memutuskan; cuti tidak dihitung sebagai semester tempuh. [`EVALUASI-STUDI.md`](EVALUASI-STUDI.md) |
| ~~10~~ | ~~**Poin kemahasiswaan & pelanggaran**~~ | ✅ 18 Agustus 2026 — dua buku besar yang tidak pernah dijumlahkan. [`POIN-KEMAHASISWAAN.md`](POIN-KEMAHASISWAAN.md) |
| ~~12~~ | ~~**Manajemen unit kerja**~~ | ✅ 18 Agustus 2026 — dikerjakan **sebelum** #11 karena mutasi merujuk unit. [`UNIT-KERJA.md`](UNIT-KERJA.md) |
| ~~11~~ | ~~**Kepegawaian mendalam**~~ | ✅ 18 Agustus 2026 — enam riwayat; hanya pangkat & mutasi yang punya aturan. [`KEPEGAWAIAN.md`](KEPEGAWAIAN.md) |
| ~~13~~ | ~~**Kuesioner umum**~~ | ✅ 18 Agustus 2026 — dua tabel jawaban, karena anonimitas harus properti skema. [`KUESIONER.md`](KUESIONER.md). Layarnya baru menyusul 30 Oktober 2026 (Sesi 69); tanda ✅ sebelum itu keliru |

**P2 tuntas.** Yang tersisa P3; payment gateway ikut di sana sebagai butir
opsional (ADR-014).

### P3 — pelaporan & platform

| # | Pekerjaan |
|---|---|
| ~~15~~ | ~~**Denda keterlambatan**~~ — ✅ 24 Oktober 2026: akrual harian yang idempoten, bekerja penuh pada pembayaran manual; gerbang KRS tetap mengukur tagihan pokok. [`MODUL.md`](MODUL.md) §Denda keterlambatan |
| ~~16~~ | ~~2FA staf; federasi IdP eksternal~~ | ✅ 2FA 22 Agustus 2026; federasi IdP 24 Oktober 2026 — ADR-019 |
| 17 | Aplikasi mobile native |
| ~~18~~ | ~~Helpdesk~~ ✅ 24 Oktober 2026 — tiket mahasiswa/dosen ke unit kerja; disposisi, catatan internal, SLA per kategori, FAQ publik tanpa login |
| 1 | Adaptor payment gateway — **opsional** (ADR-014). Kontrak `PaymentGatewayInterface` sudah ada; dibangun bila ada kampus yang membawa kredensial merchant dan endpoint notifikasi yang terverifikasi tanda tangannya. Transfer manual tetap jalur utama sesudahnya |

### Bersih-bersih yang sudah tercatat

| Item | Rujukan |
|---|---|
| ~~Driver `fake` pembayaran yang dijanjikan `config/payment.php` tetapi tidak pernah ditulis~~ | ✅ Janjinya dicabut dari config 20 Agustus 2026; dicatat lunas 24 Oktober 2026 — §Utang Teknis |
| ~~Direktori kosong `app/Services/Payment/{Contracts,Gateways}`~~ | ✅ Tidak ada di repo (Git tidak melacak direktori kosong); dicatat lunas 24 Oktober 2026 |

---

## Peta Jalan Lanjutan — 22 Oktober 2026

Disusun dari dua kajian pada Oktober 2026: daftar fitur publik pembanding
komersial yang sama dengan §Perbandingan, dan perilaku terkini dua platform
kementerian — aplikasi pelaporan PDDIKTI (Neo Feeder) dan SISTER. Hanya yang di
dalam poros akademik yang dicatat; sisanya ada di §Yang dipelajari dan sengaja
tidak diikuti.

**Temuan yang mengubah premis: SISTER tidak lagi punya web service per kampus.**
Pengajaran, pembimbingan, dan pengujian dosen masuk ke SISTER *dari PDDIKTI*.
Jadi "Klien SISTER menunggu kredensial" bukan lagi pekerjaan yang tertahan —
itu pekerjaan yang salah alamat. Alamat yang benar adalah entitas Feeder yang
belum pernah dikirim.

### P4 — Pelaporan Feeder yang ternyata belum tertutup

Saat daftar ini disusun, enam entitas dikirim: biodata, riwayat pendidikan,
aktivitas kuliah, KRS, kelas, nilai. Semuanya `Insert`. Sejak 24 Oktober 2026
jumlahnya dua puluh lima, ditambah `Delete*` (lihat §Integrasi). Yang belum dikirim semuanya **sudah
punya tabel lokal** — ini pekerjaan mapper dan validator, bukan model data baru.

| # | Pekerjaan | Kenapa, dan apa yang sudah menopangnya |
|---|---|---|
| ~~19~~ | ~~**Dosen pengajar kelas → Feeder**~~ | ✅ 22 Oktober 2026. `kelas_dosen` ada, mapper tidak. Padahal inilah penghitung rasio dosen:mahasiswa di PDDIKTI, pembagi SKS pada *team teaching*, dan **sumber "pengajaran" di SISTER**. Tanpa ini, BKD unsur pendidikan yang dihitung otomatis di sini tidak pernah sampai ke tempat yang menilainya |
| ~~20~~ | ~~**Komponen evaluasi kelas & nilai per komponen → Feeder**~~ | ✅ 22 Oktober 2026 — `komponen_nilai` kini punya `jenis_evaluasi`; ambang IKU 7 dihitung validator. Rencana evaluasi wajib dilaporkan sejak 2024 gasal. `komponen_nilai` dan `nilai_komponen` sudah berbobot per kelas — tinggal dipetakan. Satu aturan validator pra-kirim baru: **IKU 7 diakui PDDIKTI hanya bila bobot partisipatif + proyek ≥ 50 %** — kelas ber-flag *case method* yang bobotnya di bawah itu harus ditandai *sebelum* sinkron, bukan ditemukan saat rekonsiliasi IKU |
| ~~20b~~ | ~~**Layar rencana evaluasi kelas**~~ | ✅ 22 Oktober 2026 — panel di layar Input Nilai: nama, bobot, jenis per komponen; total dan ambang IKU 7 dihitung langsung di layar dan diulangi server. Komponen yang berisi nilai dapat diubah, tidak dapat dihapus |
| ~~24b~~ | ~~**Skala nilai prodi dan periode perkuliahan → Feeder**~~ | ✅ 24 Oktober 2026 — keputusannya: **tabel per prodi yang diturunkan** dari skala kampus dan kalender semester (ADR-016). `skala_nilai_prodi` satu baris per prodi per huruf (batas atas = batas bawah huruf di atasnya, huruf teratas 100); `periode_perkuliahan` satu baris per prodi per semester, angka PMB-nya dihitung dari pendaftar semester itu dan kosong bila tidak ada gelombang. Diturunkan saat validasi/sinkron dan di seeder demo; baris yang sudah ada tidak pernah ditimpa, jadi prodi yang berbeda menyunting barisnya. Dua entitas referensi paling awal; kunci pembaruan `id_bobot_nilai` dan (`id_prodi`, `id_semester`) |
| ~~21~~ | ~~**Lulus/DO → Feeder**~~ | ✅ 22 Oktober 2026. Yudisium ada; status di PDDIKTI tetap *Aktif*. Itu salah satu dari lima penyebab tersering reservasi nomor ijazah gagal (#28) |
| ~~22~~ | ~~**Aktivitas MBKM beserta anggota, pembimbing, penguji → Feeder**~~ | ✅ 22 Oktober 2026 — tujuh entitas: aktivitas MBKM, anggota, pembimbing; tugas akhir sebagai aktivitas, anggota, pembimbing, penguji. Kode jenis aktivitas dan kategori kegiatan **tidak di-seed**: kampus memetakannya sesudah tarik referensi. **Konversi MBKM** (`InsertKonversiKampusMerdeka`) dan klaim program flagship belum — digeser ke #23 |
| ~~23~~ | ~~**Prestasi, transfer kredit, dan konversi MBKM → Feeder**~~ | ✅ 22 Oktober 2026 — tiga entitas. Prestasi hanya yang terverifikasi; jenis dan tingkatnya dari tarikan referensi, tidak di-seed. Konversi MBKM dikunci pada anggota aktivitas (#22), id-nya di `krs_detail.feeder_konversi_id` di samping id KRS |
| ~~24~~ | ~~**Kurikulum, mata kuliah, matkul kurikulum → Feeder**~~ | ✅ 22 Oktober 2026 — tiga entitas referensi, dijalankan paling awal. Yang tersingkap: payload kelas dan KRS selama ini mengirim **kode lokal** sebagai `id_matkul`; kini id Feeder begitu katalog terkirim, dan validator memperingatkan bila belum. **Skala nilai prodi dan periode perkuliahan belum**: keduanya per prodi, dan tidak ada tabel lokal per prodi untuk menjadi barisnya — butuh keputusan pemodelan, dicatat sebagai #24b |
| ~~25~~ | ~~**Jenis daftar riwayat pendidikan yang lengkap**~~ | ✅ 22 Oktober 2026 — enum `JenisDaftar` dan empat kolom di `mahasiswa` (jenis, PT asal, prodi asal, SKS diakui); mapper riwayat mengirimnya, validator menolak pindahan/RPL tanpa asal atau SKS diakui. Hanya "peserta didik baru" yang di-seed kodenya. **Belum ada layar pengisiannya** — itu #33, intake pindahan |
| ~~26~~ | ~~**`Update*` / `Delete*` untuk koreksi**~~ | ✅ 22 Oktober 2026 — `Update*` sudah berjalan lewat hash sejak Agustus; yang baru `Delete*`: `hapusYangTerhapus()` membaca ledger, mencocokkan dengan baris yang masih ada (terhapus lunak = tiada), dan mengirim hapus untuk selisihnya. Tombol "Hapus yang tiada" per entitas dan `openacademic:feeder-hapus`. Entitas tanpa aksi hapus dinyatakan, bukan ditebak |
| ~~27~~ | ~~**Perubahan Data Mahasiswa & Perubahan Jenis Keluar**~~ | ✅ 24 Oktober 2026 — bukan dikunci di layar, melainkan **dicatat saat berubah** (ADR-017): observer pada `mahasiswa`, `yudisium`, `wisuda_peserta`, `evaluasi_studi` menulis `feeder_change_requests` begitu kolom terkunci berubah pada mahasiswa yang sudah ber-`feeder_id` (PDM) atau ber-`feeder_keluar_id` (PJK). Validator **menolak mengirim ulang** biodata / Lulus/DO selama ada permintaan jalur PDDIKTI admin yang terbuka. NIM dan periode masuk dikirim lewat `UpdateRiwayatPendidikanMahasiswa` (kunci `id_registrasi_mahasiswa`); yang lain ditampilkan di konsol sebagai "harus diajukan lewat PDDIKTI admin" dengan status belum diajukan → diajukan (nomor surat) → selesai. **Cetak surat permohonannya** dari modul surat belum — dicatat sebagai #27b |
| ~~27b~~ | ~~**Surat permohonan PDM/PJK dari modul surat**~~ | ✅ 26 Oktober 2026 — `JenisSurat::PermohonanPdm` / `PermohonanPjk` (kode PDM/PJK, urutan nomor sendiri lewat `PenomoranSurat`, tanpa masa berlaku, tidak dapat diajukan mahasiswa). Terbit dari panel Perubahan Data di `/admin/feeder`: satu surat per mahasiswa per prosedur memuat seluruh permintaan yang masih menunggu surat — jalur PDDIKTI admin, atau jalur web service yang ditolak (penolakannya menjadi alasan). Isi dibekukan dari permintaan: kolom, nilai tercatat di PDDIKTI → seharusnya, alasan, dan lampiran yang diturunkan dari kolomnya (SPTJM selalu). Nomor surat dan `surat_id` tercatat balik, permintaan berpindah ke *diajukan*, PDF dapat diunduh dari konsol |

Juga dicatat, bukan pekerjaan: sinkronisasi dari UI Feeder ke PDDIKTI memerlukan
otorisasi OTP oleh operator — lewat surel sejak Neo Feeder 3.0.1 (sebelumnya
WhatsApp; ADR-036 §3) — jadi tidak pernah dapat diotomasi penuh. Yang diotomasi
di sini adalah dorongan ke Neo Feeder lokal lewat web service; monitor Feeder
harus menyatakan langkah sinkron ke pusat itu, bukan berpura-pura selesai.

### P5 — Kelulusan elektronik: menutup risiko nomor ijazah

Risiko terbuka di [`MODUL.md`](MODUL.md) — nomor ijazah diberikan lokal —
sekarang punya rencana.

| # | Pekerjaan | Kenapa |
|---|---|---|
| ~~28~~ | ~~**Daftar periksa kesiapan PIN per calon lulusan**~~ | ✅ 22 Oktober 2026 — `KesiapanPinService`, delapan butir per lulusan (NIK, identitas PDM, registrasi, AKM tiap semester, nilai final, SKS, SK yudisium, Lulus/DO terkirim), ditampilkan di kolom "Siap PIN" layar Yudisium. SKS diakui pindahan menunggu #25/#33 |
| ~~29~~ | ~~**`nomor_ijazah` menjadi nilai yang diimpor, bukan dibangkitkan**~~ | ✅ 22 Oktober 2026 — `ACADEMIC_IJAZAH_SUMBER=pin` bawaan: pembangkit ditolak, nomor dicatat per baris dari hasil reservasi (NIM, nomor), tidak pernah ditimpa. `lokal` tetap ada untuk institusi di luar skema. Bila kampus memakai PIN (wajib sejak akhir 2020), kolom itu berhenti diisi sendiri; verifikasi publik ijazah adalah SIVIL, bukan QR lokal. QR lokal tetap untuk surat dan SKPI. Butuh satu setelan kampus, dan bawaannya harus yang aman: *tidak membangkitkan* |
| ~~30~~ | ~~**Pendaftaran wisuda oleh mahasiswa**~~ | ✅ 26 Oktober 2026 — layar `/mahasiswa/wisuda`: periode yang dibuka, daftar periksa syarat, daftar dan batal sendiri selama periode dibuka, notifikasi pada kedua arah. Syaratnya satu, `SyaratWisudaService`, dibaca layar mahasiswa, antrean staf, dan `WisudaService` sebelum menulis — staf kini juga tidak dapat mendaftarkan lulusan yang menunggak. Denda yang belum dibebaskan dihitung tunggakan; kesiapan PIN tampil sebagai informasi, tidak menghalangi. **Bebas pinjaman tidak ada** karena modul perpustakaan tidak ada; baris yang selalu lolos tidak ditambahkan |
| ~~31~~ | ~~**Status "Menunggu Uji Kompetensi"**~~ | ✅ 26 Oktober 2026 — `StudentStatus::MenungguUkom` berkode **lokal** `U`: dikirim ke Feeder hanya lewat `feeder_mappings`, tanpa jatuh balik ke kode lokal; validator menolak baris AKM bila belum dipetakan. Flag prodi `wajib_ukom`; yudisium prodi itu berhenti di menunggu ukom (SK, IPK, predikat final; tanggal lulus kosong, tanpa alumni dan webhook lulus). Hasil ukom dicatat staf per sitting di layar Yudisium (`uji_kompetensi`); yang lulus meluluskan pada tanggal ujian, dan Lulus/DO terutang pada semester ujian itu. Penutupan semester menulis catatan AKM beku berstatus menunggu ukom; evaluasi studi melewatinya dan tidak menghitungnya sebagai semester tempuh. Validator menolak Lulus pada prodi wajib ukom tanpa hasil lulus. ADR-022 |

### P6 — Kesenjangan pembanding yang tersisa di dalam poros

| # | Pekerjaan | Catatan |
|---|---|---|
| ~~32~~ | ~~**Pendaftaran PMB daring oleh calon**~~ | ✅ 26 Oktober 2026 — portal pendaftar `/pmb` di atas guard kelima `pendaftar` (tabel `pmb_akun`, verifikasi surel, lupa sandi dengan tabel token sendiri, batas laju daftar/masuk/surel; tidak dikenal SSO, tidak dapat membuka portal mahasiswa sekalipun sudah ber-NIM). Gelombang dibuka daring per gelombang (`is_daring`, bawaan mati) dengan prodi ditawarkan, daya tampung, biaya formulir, ambang, dan berkas wajib per prodi. Alur draf → ajukan → bayar formulir (buku besar `pmb_pembayaran` dicatat kasir, bukan `tagihan` — ADR-024) → verifikasi berkas → seleksi → kartu ujian PDF → pengumuman lewat surel dan portal, terpisah dari keputusan. Biodata divalidasi seperti `FeederValidator` dan disalin utuh saat daftar ulang. Nilai seleksi **diimpor** dari CSV CBT mana pun, semua-atau-tidak-sama-sekali; kelulusan otomatis per ambang sebagai opsi (`PMB_KELULUSAN_OTOMATIS`, bawaan mati). Penanda calon KIP. Pindahan/RPL diteruskan ke intake #33. docs/PMB.md |
| ~~33~~ | ~~**Mahasiswa pindahan**~~ | ✅ 26 Oktober 2026 — layar Pindahan & RPL dengan dua pintu (pendaftar PMB gelombang transfer/RPL, dan pendaftaran langsung oleh BAAK), PT/prodi asal disarankan dari referensi `GetAllPT` dan intake sebelumnya, lalu langsung ke usulan konversi dari transkrip asal. `sks_diakui` tidak pernah diketik: jumlah konversi yang disetujui, ditulis `KonversiService` pada setiap keputusan. Semester masuk dan batas SKS KRS pertama membacanya; masa studi dari semester asal opsional (ADR-021) |
| ~~34~~ | ~~**Jadwal ujian (UTS/UAS)**~~ | ✅ 26 Oktober 2026 — `jadwal_ujian` per kelas per jenis (UTS/UAS), beberapa ruang dengan kursi per ruang, pengawas dosen atau staf, draf → terbit. Bentrok ruang, pengawas, kursi, dan **mahasiswa** (dari KRS disetujui) dilaporkan sekaligus; draf boleh bentrok, terbit semua-atau-tidak. Penyusunan massal per prodi/semester dengan pratinjau dan kapasitas ruang. Kelayakan UAS dari ambang kehadiran dan, bila kampus memilih, tunggakan — di daftar peserta dan kartu. Kartu ujian dicetak dari jadwal ujian begitu ada yang terbit. Portal mahasiswa dan dosen; notifikasi saat terbit dan berubah. ADR-025 |
| ~~35~~ | ~~**Kalender akademik**~~ | ✅ 27 Oktober 2026 — `KalenderService` membaca langsung dari pemilik tanggalnya, tanpa menyalin: perkuliahan, KRS, perubahan KRS, dan nilai dari `tahun_akademik` (kolom yang sama dengan gerbangnya), masa UTS/UAS per prodi dari sesi **terbit** #34, EDOM yang aktif, yudisium sebagai jumlah per hari per prodi, wisuda, dan gelombang PMB (staf saja). Tabel `kalender_peristiwa` hanya untuk yang tidak punya sumber lain — libur akademik, kegiatan kampus, batas administrasi — dengan sasaran (semua/mahasiswa/dosen/staf), prodi opsional, draf → terbit, dikelola BAAK (`kalender.manage`). Tampilan bulan dan daftar di ketiga portal dengan filter jenis, sasaran, dan prodi; mahasiswa dipaku ke prodinya dan melihat sesi ujiannya sendiri, dosen sesi yang diampu/diawasi. Langganan **iCalendar** per orang lewat URL bertoken (hanya hash disimpan, tampil sekali, dapat dicabut/dibuat ulang, mati bersama akunnya), tanpa nama/NIM/surel. Libur terbit memberi peringatan non-penghalang pada jadwal ujian dan dilewati penyusun massal; gerbang KRS dan nilai tidak disentuh. ADR-027 |
| ~~36~~ | ~~**Pengingat mahasiswa aktif yang belum KRS**~~ | ✅ Sudah ada sejak G2 (9 Agustus 2026); dicatat keliru sebagai kesenjangan pada 22 Oktober 2026. `openacademic:kirim-pengingat` mengirim `BatasKrsMendekat` pada H-7, H-3, H-1 (`config/notifikasi.php`) **hanya** kepada mahasiswa aktif tanpa KRS diajukan/disetujui pada semester aktif — [`NOTIFIKASI.md`](NOTIFIKASI.md) |
| ~~37~~ | ~~**KKN / PKL sebagai kegiatan terkelola**~~ | ✅ 27 Oktober 2026 — setiap peserta satu baris `aktivitas_mahasiswa` (jenis baru `kkn`/`pkl`, atau `membangun_desa`/`magang` bila dijalankan sebagai MBKM), jadi dikirim lewat rantai aktivitas–anggota–bimbing–konversi #22/#23 tanpa entitas Feeder baru; `untuk_kampus_merdeka` hanya untuk jenis MBKM, SK periode ikut sebagai `sk_tugas`. Periode, lokasi (desa/instansi, kuota kelompok), kelompok dengan DPL dan pembimbing mitra, logbook/laporan berkala dengan lampiran yang diperiksa DPL. Kelompok manual atau otomatis (campur prodi/jenis kelamin sebagai aturan per periode, ukuran diratakan). Nilai DPL + mitra berbobot, dikonversi per kelas ke mata kuliah KKN/PKL lewat baris KRS bertanda aktivitas; baris itu dikeluarkan dari lembar nilai dosen. Tiga portal. ADR-029 |
| ~~38~~ | ~~**Tahap proposal tugas akhir**~~ | ✅ 27 Oktober 2026 — saklar per prodi `wajib_proposal_ta` (bawaan mati), dikunci pada tugas akhir saat judul diajukan sehingga yang sudah berjalan tidak berubah. Mahasiswa mengunggah dokumen proposal berversi (disk privat), calon pembimbing atau staf tugas akhir menelaah dengan catatan (revisi/diterima/ditolak); versi dan telaah tidak pernah ditimpa. Diterima membuka persetujuan judul, lalu pembimbing dan seminar proposal yang sudah ada; ditolak menutup tugas akhir dan membebaskan slot. ADR-029 |
| ~~39~~ | ~~**Riwayat kepegawaian yang tersisa**~~ | ✅ 27 Oktober 2026 — delapan riwayat baru (diklat, pengalaman kerja, studi lanjut / tugas belajar, tugas dinas, jabatan struktural, inpassing, kontrak, pensiun) dan layar untuk enam yang tabelnya sudah ada. Satu alur untuk keempat belasnya: dosen mengajukan di `/dosen/kepegawaian` dengan dokumen lewat `BerkasService`, staf menyetujui atau menolak dengan alasan di `/admin/kepegawaian/validasi`; isian staf langsung disetujui. Mutasi, kontrak, dan pensiun diisi staf saja. Aturan hanya di tempat yang memang beraturan: pangkat berlaku = disetujui ber-TMT terakhir; tugas belajar disetujui membebaskan BKD selama beririsan dengan semester (izin belajar tidak); pensiun disetujui menutup akun pada TMT-nya lewat penolakan yang sama dengan tombol nonaktif (`openacademic:tutup-akun-pensiun` harian). Ekspor SISTER kini memuat penghargaan & sanksi, bahasa, organisasi, diklat, inpassing, jabatan struktural, studi lanjut, pengalaman kerja — hanya baris disetujui. ADR-028, docs/KEPEGAWAIAN.md |
| ~~40~~ | ~~**Buku besar angka kredit (Kum A–E)**~~ | ✅ 27 Oktober 2026 — `angka_kredit_dosen` per butir per unsur dalam perseratus, tanpa total tersimpan. Kum B–E dari BKD **disahkan** (dikreditkan dalam transaksi pengesahan), Kum A dari ijazah S2/S3 portofolio; staf mencatat konversi semester lama dan penyesuaian beralasan. Kinerja yang mulai sebelum 2026: SKS × 4; sesudahnya faktor kampus bertanda *perkiraan*. DUPAK = baris sesudah TMT jabatan berlaku; batas Kum D/E 10 % ditegakkan pada rekap sebagai titik tetap terhadap total sesudah pembatasan. Rekap per dosen dan per jenjang, kesiapan usulan (selisih AK dan bagian Kum C, syarat di `config/angka_kredit.php`), dan **lembar penyetaraan .xlsx** (NUPTK, nama, AA/L/LK, TMT `TTTT-BB-HH`, AK terakhir, Kum A–E, total DUPAK, AK penyetaraan) untuk dosen tetap ber-TMT ≤ 2023; dosen tanpa NUPTK disebut, tidak ditulis. `dosen.nuptk` baru. Tanpa alur tim penilai/TTE — penilaian di platform kementerian. ADR-028 |
| ~~41~~ | ~~**Tarif per SKS / per mata kuliah**~~ | ✅ 27 Oktober 2026 — komponen tarif kini enum `KomponenTarif` dengan dua komponen baru: `per_sks` (tarif per SKS, matriks prodi/angkatan/jalur/golongan, paling spesifik menang) dan `mata_kuliah` (nominal tetap untuk satu mata kuliah, mis. praktikum berbayar/KKN). Keduanya tidak ikut penerbitan massal: ditagih ke tagihan semester yang sama saat Dosen Wali menyetujui KRS, dicocokkan per mata kuliah. Revisi KRS disetujui (`bukaRevisi`, selama jendela KRS) ditagih selisihnya saat disetujui ulang; kelas yang dibatalkan dijawab baris koreksi — negatif sebelum batas, Rp0 beralasan sesudahnya (`PAYMENT_KRS_BATAS_PENGURANGAN`). Gerbang KRS diukur terhadap komponen tetap saja (potongan dibagi proporsional), jadi tidak melingkar. Beasiswa, keringanan, denda, dan jurnal akuntansi berlaku pada baris baru. Demo: Sistem Informasi. ADR-030 |
| ~~42~~ | ~~**Monitoring pemakaian ruang**~~ | ✅ 27 Oktober 2026 — laporan, bukan pemesanan: `PemanfaatanRuangService` membaca jadwal kuliah dan jadwal ujian **terbit** semester terpilih, ditelusuri per tanggal sehingga libur kampus dari kalender (#35) keluar dari jam tersedia dan terpakai, libur satu prodi hanya meniadakan kuliah prodi itu. Per ruang: jam tersedia/terpakai/kosong per pekan terhadap jam operasional kampus (`config/ruang.php`), pemakaian di luar jam dilaporkan terpisah, okupansi kursi dari peserta KRS disetujui (ujian: kursi diisi berurutan per ruang). Tabel per gedung/ruang (paling penuh, paling kosong), peta panas hari × jam per ruang atau rerata, daftar kelebihan peserta dan jarang dipakai, filter semester/gedung/jenis, ekspor CSV. Pencarian ruang kosong per hari atau tanggal dengan kapasitas minimum, hanya baca. Enam kueri per laporan berapa pun ukurannya. Izin baru `ruang.view` (BAAK, pimpinan, peran baru `sarpras`). ADR-031 |
| ~~43~~ | ~~Denda, helpdesk~~ | ✅ 24 Oktober 2026 — #15 dan #18 |
| ~~44~~ | ~~**Adopsi mahasiswa yang sudah ada di PDDIKTI**~~ | ✅ 26 Oktober 2026 — `FeederAdopsi` (ADR-020): membaca daftar riwayat pendidikan dan biodata dari Feeder per prodi + periode masuk, mencocokkan NIM lalu NIK (NIK mengalahkan nama), mengisi `feeder_id`/`feeder_registrasi_id` dan buku besar (`direction = adopt`) tanpa mengirim apa pun, sehingga dorongan berikutnya mencatat *Skipped*. Laporan empat keadaan di `/admin/feeder/adopsi` dan `openacademic:feeder-adopsi`; yang ambigu ditautkan atau dipisahkan manual dengan alasan, dan biodatanya ditolak validator sampai diputuskan. NIK tidak disimpan |
| ~~44b~~ | ~~**Klaim mahasiswa lampau**~~ | ✅ 27 Oktober 2026 — `FeederKlaimLampau` (ADR-032): membaca `GetListDaftarLampau` per prodi + periode masuk dan menilai setiap baris dengan pencocok adopsi yang sama (`PencocokMahasiswaFeeder`, NIM lalu NIK) — cocok, ambigu, atau tidak ada di SIAKAD — di `/admin/feeder/lampau`. Staf memilih baris; validasi pra-kirim membaca ulang PDDIKTI dan menahan seluruh klaim bila satu baris bermasalah, dengan semua masalah sekaligus. `KlaimMahasiswaLampau` dikirim per baris, dicatat di buku besar (`direction = claim`): yang sukses tidak pernah dikirim ulang, yang ditolak dapat dicoba lagi. Klaim bukan penautan: adopsi #44 langsung dijalankan sesudahnya, jadi yang cocok teradopsi dan yang ambigu (hanya boleh diklaim dengan alasan) menunggu keputusan di layar adopsi. Validator menahan biodata yang diklaim tetapi belum teradopsi. NIK tidak disimpan |
| ~~45~~ | ~~**Klaim program flagship MBKM**~~ | ✅ 26 Oktober 2026 — daftar program flagship ditarik sebagai referensi (`GetListAktivitasMBKMFlagship`, tipe `program_flagship`), tidak di-seed; penandanya `aktivitas_mahasiswa.flagship_kode`, dipilih per aktivitas di layar Verifikasi Data IKU. Entitas ke-26 `flagship_mbkm` (`InsertAktivitasMBKMFlagship`) bergantung pada `anggota_mbkm`; validator menolak aktivitas atau anggota yang belum terkirim dan program yang hilang dari referensi terakhir. Klaim dialamatkan pada `id_aktivitas`, jadi perpindahan program dikirim sebagai update; penanda yang klaimnya sudah diterima tidak dapat dicabut dari sini |
| ~~58~~ | ~~**Kurikulum berbasis capaian sampai CPMK (OBE)**~~ | ✅ 29 Oktober 2026 — CPL prodi → CPMK per mata kuliah → sub-CPMK, berversi per kurikulum (draf → berlaku → diarsipkan; yang berlaku beku, revisi = versi baru) dengan bobot sub-CPMK→CPMK dan CPMK→CPL per seratus yang wajib menutup 100 sebelum terbit. Disusun prodi (`/admin/cpmk`) dan dosen pengampu dari RPS dengan satu penyunting. Komponen evaluasi memetakan ke sub-CPMK/CPMK berporsi; rencana evaluasi memperingatkan sub-CPMK yang tidak diukur. Saat nilai final, capaian per CPMK dan per CPL per mahasiswa dihitung dan **disimpan** (bukan dihitung ulang tiap tampil), diaudit, dan ikut bergeser lewat koreksi nilai per komponen. RPS memakai versi CPMK yang sama (CPL diturunkan, pertemuan menautkan sub-CPMK). Rekap kelas (dosen), prodi per angkatan (staf), dan per mahasiswa, ekspor CSV; tabel capaian pembelajaran di perakit LKPS. Bukan dasbor IKU. ADR-038 |

### P7 — Perubahan regulasi 2025–2026 (ADR-036)

Ditemukan komparasi modul 28 Oktober 2026. Setiap butir diverifikasi terhadap
sumber resmi sebelum kode diubah; nomor regulasinya di ADR-036.

| # | Item | Status |
|---|---|---|
| ~~46~~ | ~~**Penomoran IKU 2026 (Kepmendiktisaintek 358/M/KEP/2025)**~~ | ✅ 29 Oktober 2026 — `GET /iku-data` mendapat blok `iku_2026` (IKU 1 AEE per tahun akademik dan jenjang, 2 populasi tracer study, 3 kegiatan + prestasi dengan pita SKS 0–5/6–10/11+, 4–6 luaran dosen) dan peta `ketersediaan` kedua belas IKU. Kunci delapan IKU lama dibekukan dan usang sampai versi mayor Bridge. Layar tidak lagi menyebut "IKU 7" / "IKU 4" untuk kelas kolaboratif dan praktisi |
| ~~47~~ | ~~**Data IKU 8 — keterlibatan dosen dalam penyusunan kebijakan**~~ | ✅ 30 Oktober 2026 — jenis `penugasan_dosen` baru `penyusunan_kebijakan`: bentuk (tim penyusun, narasumber/ahli resmi, kontributor kajian), tingkat (nasional, daerah, industri, plus internasional yang dicatat apa adanya), lembaga pemberi, jenis dan nomor dokumen resmi, berkas bukti wajib. Diisi dosen di portofolio, diverifikasi di `/admin/data-iku` dengan tautan bukti. `iku_2026.iku_8_penyusunan_kebijakan` (dosen sekali per orang, dosen tetap terpisah dengan penyebutnya); `ketersediaan` IKU 8 `tersedia`. ADR-041 §1 |
| ~~48~~ | ~~**Kuartil & indeks publikasi untuk IKU 6**~~ | ✅ 30 Oktober 2026 — dicatat di sini sebagai fakta yang dinyatakan dosen dan diverifikasi staf; penilaian bukti tetap di Open Campus. Luaran jurnal/prosiding mendapat indeks (Scopus, WoS, SINTA 1–6, lainnya), kuartil Q1–Q4 (hanya jurnal Scopus/WoS), DOI ternormalkan, tautan, penerbit, dan penanda kolaborasi internasional; tampil di portofolio dan layar verifikasi. `iku_2026.iku_6_publikasi` menambah cacahan per artikel (baris ber-DOI sama digabung). Tanpa panggilan ke API pengindeks; bila kelak perlu, di balik interface. ADR-041 §2 |
| ~~49~~ | ~~**PISN menggantikan PIN**~~ | ✅ 29 Oktober 2026 — istilah di layar dan dokumen, `KesiapanPisnService` mengikuti validator PISN sejak 1 November 2025 (akreditasi, IPK dan SKS per jenjang, beban per semester dan antara, masa studi, semester masuk), struktur nomor 21 digit diperiksa saat dicatat, `ACADEMIC_IJAZAH_SUMBER=pisn` bawaan dengan `pin` sebagai alias usang. ADR-036 §2 |
| ~~50~~ | ~~**Tanggal berlaku akreditasi prodi**~~ | ✅ 30 Oktober 2026 — riwayat SK di `akreditasi_prodi` (lembaga BAN-PT/LAM, peringkat, nomor dan tanggal SK, masa berlaku, berkas); SK baru tidak menimpa yang lama, salah ketik ditarik. `KesiapanPisnService` memakai SK yang berlaku pada tanggal lulus dan menandai lulusan yang lulus saat akreditasi kedaluwarsa. Peringatan di master prodi dan dasbor staf `ACADEMIC_AKREDITASI_PERINGATAN_BULAN` (bawaan 6) bulan sebelum kedaluwarsa; LKPS menuliskan SK yang berlaku. `prodi.akreditasi` kini cache peringkat terbaru. ADR-041 §3 |
| ~~51~~ | ~~**Neo Feeder 3.0.1 / 3.1 — validasi pra-kirim**~~ | ✅ 29 Oktober 2026 — nomor HP mahasiswa wajib (galat), nomor yang tidak berbentuk telepon atau dipakai dua mahasiswa diperingatkan; Lulus/DO tanpa satu pun AKM ditolak. AKM sudah menghitung SKS konversi aktivitas (baris KRS beraktivitas) dan SKS transfer (pada total) — kini dipaku tes. ADR-036 §3 |
| 52 | **Nama medan HP pada `InsertBiodataMahasiswa`** | ⬜ Mapper mengirim `telepon_seluler`; kamus web service Neo Feeder (aksi `GetDictionary` pada instalasi kampus) perlu diperiksa apakah medan HP bernama lain. Butuh Neo Feeder hidup, bukan dokumen |
| 53 | **Tanggal ijazah pada Lulus/DO** | ⬜ Neo Feeder 3.0.1 dilaporkan menambah tanggal terbit ijazah pada data kelulusan; nama medannya belum terverifikasi, jadi belum dikirim. Nomor ijazah memang tidak dikirim (PISN yang menulisnya, #49) |
| 54 | **Changelog resmi Neo Feeder 3.1** | ⬜ Rilis Mei 2026 menurut sumber sekunder; tidak ada catatan rilis publik dari Pusdatin. Butir yang dilaporkan dan sudah diterapkan sebagai validator: AKM wajib sebelum Lulus/DO, nomor HP unik. Periksa ulang begitu catatan resminya ada atau begitu instalasi kampus memakai 3.1 |
| 55 | **Jenis daftar baru dan alur re-NIM / masuk kembali** | ⬜ Dilaporkan baru di 3.0.1 (sekunder). Kode jenis daftar dipetakan lewat referensi yang ditarik, jadi kode baru tiba sendiri; yang belum ada adalah alur lokal untuk mahasiswa yang berganti NIM atau aktif kembali |

### P8 — Pelaporan ke sistem Kemdiktisaintek (ADR-037)

Ditemukan pemeriksaan kompatibilitas 29 Oktober 2026 dengan enam belas sistem
Kemdiktisaintek: dua belum didukung sama sekali, dan keduanya sumber IKU 2026.

| # | Item | Status |
|---|---|---|
| ~~56~~ | ~~**Ekspor SIMKATMAWA**~~ | ✅ 29 Oktober 2026 — layar `/admin/simkatmawa`: periode dan prodi, empat tabel (prestasi kompetisi, rekognisi/karya, sertifikasi kompetensi, dosen pembimbing), validasi pra-ekspor seperti Feeder, unduh CSV/XLSX baris yang siap saja. Bidang yang kurang (tingkat SIMKATMAWA, peringkat, penyelenggara, jumlah PT/negara, tautan bukti, pembimbing, …) ditambahkan ke `poin_mahasiswa` dan diisi dari layar Lengkapi. Tanpa pengiriman otomatis. ADR-037 §1 |
| ~~57~~ | ~~**Data lulusan untuk tracer study nasional**~~ | ✅ 29 Oktober 2026 — layar `/admin/lulusan`: kelengkapan dan verifikasi kontak lulusan, ekspor daftar lulusan tanpa NIK (izin `alumni.ekspor`, keperluan wajib, tercatat di log), impor CSV status pekerjaan dengan laporan baris ditolak; `iku_2026.iku_2_lulusan` dan `GET /graduates` kini memuat status, sumber, dan tanggal surveinya. Kuesioner tetap milik Open Campus. ADR-037 §2 |

### Urutan yang disarankan

~~**#19 dan #21 dulu.**~~ Selesai 22 Oktober 2026 — keduanya memperbaiki sesuatu
yang **salah di luar kampus**: rasio dosen yang tidak terhitung dan lulusan yang
masih *Aktif*. Yang tersingkap saat mengerjakannya: `dosen.feeder_id` tidak
pernah diisi siapa pun, karena dosen didaftarkan di PDDIKTI admin, bukan lewat
Feeder. Kini diisi dari pembacaan `GetListDosen` yang dicocokkan NIDN. **#20 selesai**
di hari yang sama; yang tersingkap di sana: komponen nilai tidak pernah punya
*jenis*, jadi IKU 7 selama ini hanya sebuah flag tanpa bukti. **#22 dan #28
selesai** menyusul di PR terpisah, lalu **#29**: nomor ijazah kini dicatat dari
PIN, bukan dibangkitkan — risiko terbuka di MODUL.md tertutup. **#20b selesai**
(layar rencana evaluasi), lalu **#26**: Feeder akhirnya bisa diminta melupakan
apa yang sudah tidak ada di sini, dan **#23–#25 selesai** di PR terpisah. **#24b
dan #27 selesai** 24 Oktober 2026: P4 tuntas, menyisakan #27b (surat permohonan).
Berikutnya **#44**, lalu **#33**, baru **#32**. #44 didahulukan karena tanpa
itu kampus yang pindah dari SIAKAD lain tidak dapat memulai sinkronisasi sama
sekali: sinkronisasi pertama akan mencoba mendaftarkan ulang mahasiswa yang
sudah ada di PDDIKTI. #33 murah karena datanya sudah ada sejak #25. #32 adalah
pekerjaan terbesar dan satu-satunya yang membuka portal keempat (calon
mahasiswa); kerjakan setelah Feeder tuntas, supaya pendaftar yang diterima
langsung punya riwayat pendidikan yang benar (#25). **#32 selesai** 26 Oktober
2026 (Sesi 56, ADR-024).

### Yang dipelajari dan sengaja tidak diikuti

| Yang ada di pembanding | Kenapa tidak di sini |
|---|---|
| Forum kelas, unggah materi & tugas, konsultasi daring | Platform belajar — Campus Bridge dan SSO |
| CBT untuk seleksi PMB | Platform ujian; #32 mengimpor nilainya |
| Career center, kuesioner alumni | Open Campus |
| Simulasi nilai akreditasi, ekspor SAPTO, inventaris dokumen mutu | Borang di Open Campus; AMI di sini |
| Anggaran, hutang, aset tetap, standar akuntansi nirlaba | Sistem akuntansi; di sini hanya tagihan mahasiswa dan jembatannya |
| Persuratan umum & disposisi | Bukan catatan akademik; surat mahasiswa sudah ada |
| Dasbor manajemen puncak | Rekap operasional ada; IKU di Open Campus |
| Aplikasi mobile native, hosting terkelola, cadangan data | Web responsif; infrastruktur |

---

## Sengaja Bukan di Sini

Bukan kesenjangan. Ini batas ekosistem, dan mengaburkannya akan menduplikasi
data yang harus punya satu pemilik.

| Modul | Pemiliknya |
|---|---|
| **CBT / platform ujian** | Produk tersendiri — sambungkan lewat Bridge |
| **LMS / e-learning, forum kelas, feed** | Open Campus, Moodle, atau LMS mana pun |
| Tracer study, jejaring alumni, career center | Open Campus |
| Dasbor 12 IKU, **borang akreditasi (LKPS/LED)** | Open Campus — di sini hanya faktanya. Empat dari delapan IKU pun bukan data aplikasi ini, dan itulah alasan batasnya bertahan; lihat [`KINERJA.md`](KINERJA.md) |
| Perpustakaan | SLiMS atau sejenisnya |
| Payroll, presensi pegawai | HRIS |
| Inventaris, sarpras, aset | Sistem aset |
| Buku besar & jurnal akuntansi | Sistem akuntansi; di sini hanya tagihan mahasiswa dan jembatannya |
| Backup & pemulihan | Infrastruktur, bukan aplikasi |

### Batas SPMI yang turun satu tingkat, 11 Agustus 2026

Baris ini dulu berbunyi "SPMI penuh". Ternyata satu kata itu menutupi dua hal
yang bentuk datanya berbeda, jadi batasnya diletakkan lebih tepat — **bukan
digeser**:

| | Subjeknya | Datanya | Di mana |
|---|---|---|---|
| **AMI** — audit mutu internal | Unit kerja | Kualitatif: temuan, akar masalah, tindak lanjut | **Open Academic** sejak 11 Agu 2026 |
| **Borang akreditasi** | Institusi / prodi | Kuantitatif lintas domain: penelitian, PkM, keuangan, alumni | Open Campus, tetap |

Subjek AMI adalah pohon unit kerja — yang sudah dimiliki aplikasi ini, dan yang
tidak dimiliki Open Campus. Borang meminta angka yang tidak pernah lewat sini.
Lihat [`SPMI.md`](SPMI.md).

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
| Adaptor Midtrans | Kredensial merchant + endpoint notifikasi terverifikasi. **Opsional** sejak 24 Oktober 2026 (ADR-014); bukan syarat v1.0 |
| ~~Klien SISTER~~ | **Dicabut 22 Oktober 2026.** SISTER kini terpusat dan menarik data pengajaran, pembimbingan, dan pengujian dari PDDIKTI; tidak ada endpoint per kampus untuk dituju. Yang tersisa untuk SISTER adalah ekspor berkas (sudah ada) dan pengiriman entitas yang kurang ke Feeder (#19–22) |
| Endpoint pembayaran Easy Accounting | Belum ada di API v1 mereka. Bila lahir, yang berubah satu metode di `PenjurnalanService` |
| ~~Federasi IdP eksternal~~ | **Selesai 24 Oktober 2026.** Keputusan pemetaannya diambil sempit (ADR-019): staf saja secara bawaan, subject menang atas surel, ambigu ditolak |
| **PHP di server produksi ≥ 8.4.27 / 8.5.12** | Celah inti PHP 21 Oktober 2026 (CVE-2026-109618, CVE-2026-109619) memungkinkan eksekusi kode tanpa login dan **tidak** tertambal oleh pembaruan Composer. Produksi memakai PHP 8.4 atau 8.5, jadi setiap server harus berada di 8.4.27 / 8.5.12 atau lebih baru; mitigasi sementara: `session.upload_progress.enabled = Off`. CI aman karena setup-php mengambil patch terbaru, dan sejak 26 Oktober 2026 menguji 8.2, 8.4, dan 8.5 (ADR-023). XAMPP pengembang dinaikkan dari 8.2.12 ke 8.4.27 pada hari yang sama |
| **Rilis publik v1.0** | Keputusan pemilik repo. Remote sudah ada: repo publik di GitHub sejak 22 Oktober 2026 |

---

## Utang Teknis

| Item | | Kenapa penting |
|---|---|---|
| Seeder menulis lewat service | 🟡 | Sebagian sudah (konversi, EDOM, BKD, akuntansi); sisanya menulis langsung ke tabel, sehingga aturan akademik tidak ikut ditegakkan pada data demo |
| ~~Driver `fake` pembayaran~~ | ✅ | Janji `gateway`/`fake` dicabut dari `config/payment.php` 20 Agustus 2026, bukan dibangun; baris ini baru dicatat lunas 24 Oktober 2026. Gateway kini opsional (ADR-014) |
| ~~Direktori kosong `app/Services/Payment/*`~~ | ✅ | Tidak ada di repo — Git tidak melacak direktori kosong, jadi clone mana pun tidak memilikinya. Kontrak sungguhannya di `Services/Keuangan/Contracts` |
| FormRequest | 🟡 | Validasi masih banyak di controller |
| DTO | 🟡 | Menyusul per modul |
| `lang/id` | 🟡 | auth/validation/pagination sudah; string modul menyusul |
| Perintah pembangkit data skala | ✅ | Perintah openacademic:beban-katalog & beban-kuota; fixture 5.000 mahasiswa untuk [`KAPASITAS.md`](KAPASITAS.md) dibuat sekali pakai |
| Katalog KRS pada ±1.000 kelas | ✅ | Diukur 19 Agu 2026: 1.000 baris = 1,92 s dan 2,2 MB HTML. **Sudah dipaginasi**: 105 KB, 0,79 s — lihat KAPASITAS.md |
| Uji beban bersamaan | ✅ | Perebutan kuota diuji 19 Agu 2026: 19 proses, 1 kursi, tepat 1 pemenang. Beban HTTP penuh masih terbuka |
| Audit N+1 menjelang v1.0 | ✅ | Lunas 27 Okt 2026 (ADR-034). Setiap layar GET berportal dan endpoint Bridge dihitung kuerinya di demo lalu pada kampus yang ditumbuhkan (kelas ×2, mahasiswa ×3, antrean lain ×3–4). Tujuh layar tumbuh bersama data — terberat pratinjau penutupan semester, 61 → 212 kueri — kini datar dan dipaku `AnggaranKueriSkalaTest` (38 layar). Dua daftar yang memuat semua baris dipaginasi; tidak ada indeks baru yang diperlukan. Rincian di [`KAPASITAS.md`](KAPASITAS.md) §Audit N+1 |
| ~~Lima alur setengah jadi (inventaris 28 Okt 2026)~~ | ✅ | Lunas 28 Okt 2026 (Sesi 65, ADR-035). Dibaca kode atau dijanjikan dokumen, tetapi tanpa jalur tulis atau tampilan: penetapan dosen wali (KRS tidak dapat disetujui di kampus nyata), dispensasi pembayaran, pengumuman untuk dosen/staf, event `student.enrolled`, dan detail mahasiswa + KHS/transkrip di sisi staf |
| ~~Empat layar pengelolaan (inventaris 28 Okt 2026)~~ | ✅ | Lunas 30 Okt 2026 (Sesi 69, ADR-039): kuesioner umum, konsumen Bridge, klien SSO, tiket helpdesk oleh staf |
| Urutan baris jawaban kuesioner anonim | 🟡 | `kuesioner_jawaban_anonim.id` dan `created_at` berurutan sejalan dengan `kuesioner_partisipasi`, sehingga pemegang akses basis data langsung dapat menjajarkan jawaban dengan pengisinya. Layar dan ekspor tidak pernah memperlihatkannya (ADR-039 §1). Kandidat: tanpa `created_at`, kunci acak, atau penulisan berbatch |
| CSP masih `'unsafe-eval'` | 🟡 | Alpine mengevaluasi ekspresi `x-` lewat konstruktor `Function`. `'unsafe-inline'` untuk skrip sudah dicabut 27 Okt 2026 (ADR-033) |
| Temuan rendah audit keamanan Oktober 2026 | 🟡 | 16 butir ditunda, D-1 s.d. D-16 di [`KEAMANAN-AUDIT-2026-10.md`](KEAMANAN-AUDIT-2026-10.md). Yang perlu keputusan proses lebih dulu: pemisahan pengaju dan penyetuju konversi kredit dan poin kemahasiswaan (D-1), perubahan kelas sesudah semester dikunci (D-2), telaah pendidikan dan jabatan fungsional yang dientri dosen (D-7). Sisanya pekerjaan kecil |
| **CI merah sejak 22 Agustus 2026** | ✅ | Lunas 22 Okt 2026. Bukan 8 tes: **170**. CI menjalankan Pest *sebelum* `npm run build`, dan sejak tata letak dipisah (`build/` di root, di-ignore) setiap layar membalas 500 "Vite manifest not found". Di mesin pengembang `build/` selalu ada, jadi suite terlihat hijau dua bulan. Ditambah 14 tes SSO yang hanya lolos bila `.env` menyetel `SSO_ENABLED=true` — rute SSO didaftarkan saat boot, jadi `config()` di dalam tes terlambat. Keduanya diperbaiki di suite, bukan di CI: `TestCase::setUp()` memanggil `withoutVite()`, dan `phpunit.xml` menyetel `SSO_ENABLED=true` |
| 8 tes gagal di `main` sejak Oktober 2026 | ✅ | Lunas 22 Okt 2026. Bukan regresi kode: fixture `KurikulumLanjutanTest` dan `KonversiKreditTest` membuka jendela KRS dengan menggeser `krs_mulai`/`krs_selesai` ke hari ini, tetapi `TahunAkademik::krsDibuka()` menutup jendela pada `krs_perubahan_selesai` — yang tetap 15 Sep 2026 dari factory. Hijau sampai tanggal itu lewat. Kini lewat state factory `krsDibuka()` yang menjangkarkan ketiga tanggal ke hari ini |
| Font dari Google Fonts | ✅ | Di-host sendiri lewat `@fontsource` 19 Agu 2026; `style-src`/`font-src` pihak ketiga dicabut. Menyingkap bug terpendam: Vite menulis URL aset absolut dari akar domain, jadi setiap pemasangan subfolder kehilangan hurufnya — `base: './'` |
| Paket rentan Oktober 2026 | ✅ | Lunas 26 Okt 2026. `laravel/framework` 12.65.0 → 12.70.1 menutup CVE-2026-114509 (bypass TokenGuard; tidak terjangkau di sini karena tak ada guard ber-driver `token`), CVE-2026-114508 (inklusi relasi JSON:API; Bridge memakai `JsonResource` biasa), dan CVE-2026-102279 (XSS halaman debug, hanya bila `APP_DEBUG=true`). Ikut diperbarui: `symfony/http-foundation` (ReDoS lewat `Cache-Control`, terjangkau anonim), `league/commonmark`, `phpseclib/phpseclib`, `league/flysystem`, `symfony/yaml`, dan rantai npm dev. `composer audit` dan `npm audit` bersih |
| ~~**Spec OpenAPI Bridge tertinggal**~~ | ✅ | Lunas 26 Okt 2026. `GET /classes/{uuid}` dan `GET /iku-data` masuk spec dengan skema, scope, dan contoh dari respons sebenarnya; skema `CourseClass` yang ikut tertinggal (mode, status nilai, jadwal, NIDN dan instansi pengajar) dilengkapi. Yang tersingkap: berkasnya **tidak pernah sah sebagai YAML** (koma di deskripsi sebaris) — tak satu alat pun pernah membacanya. `SpecOpenApiTest` kini mem-parse-nya dan memaku tiga hal: setiap rute `bridge.*` punya path, metode, dan scope persis seperti middleware-nya (scope di `security` per operasi); tidak ada path yang sudah tidak dilayani; medan respons kedua endpoint tadi sama dengan skemanya |

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
| G5 Konversi Kredit · Git diinisialisasi · Rencana kinerja · SPMI/AMI | 11 Agustus 2026 |
| G4 Keringanan & Beasiswa | 12 Agustus 2026 |
| G6 EDOM | 13 Agustus 2026 |
| G7 SISTER & BKD | 14 Agustus 2026 |
| Integrasi akuntansi Easy Accounting | 15 Agustus 2026 |
| RPS, jurnal perkuliahan, analitik | 16 Agustus 2026 |
| P1 perkuliahan (padanan, paket, konsentrasi, cetak, pengaturan dokumen) | 17 Agustus 2026 |
| P2 (evaluasi studi, poin, unit kerja, kepegawaian, kuesioner) | 18 Agustus 2026 |
| Uji beban HTTP · verifikasi dua langkah staf | 22 Agustus 2026 |
| Repo publik · P4 Feeder #19–26, #20b · P5 PIN #28–29 | 22 Oktober 2026 |
| P4 tuntas: #24b skala & periode per prodi, #27 PDM/PJK | 24 Oktober 2026 |
| P5 tuntas: #30 pendaftaran wisuda mandiri, #31 menunggu uji kompetensi | 26 Oktober 2026 |
| #32 PMB daring: portal pendaftar, seleksi impor, pengumuman | 26 Oktober 2026 |
| Review keamanan v1.0 (Fase 5) | 27 Oktober 2026 |
| Lima celah P0: dosen wali, dispensasi, pengumuman tiga portal, `student.enrolled`, detail mahasiswa staf | 28 Oktober 2026 |
