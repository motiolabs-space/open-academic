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
| Tabel domain | 109 |
| Migrasi | 48 |
| Model Eloquent | 94 |
| Enum | 54 |
| Service | 85 |
| Policy | 7 |
| Layar (rute GET berportal) | 94 |
| Endpoint Campus Bridge | 14 |
| Perintah artisan | 12 |
| **Tes Pest** | **1.007 hijau (2.200 asersi)** |

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
| Evaluasi mahasiswa (peringatan & DO) | ✅ | Sistem menghitung, manusia memutuskan — [`EVALUASI-STUDI.md`](EVALUASI-STUDI.md) |
| **Monitoring pemakaian ruang** | ⬜ | Bentrok terdeteksi; pemanfaatannya belum terlihat |
| Cetak KTM, kartu ujian, absensi, jurnal | ✅ | Kolom tanda tangan sengaja kosong; KTM tanpa NIK maupun alamat — [`CETAK.md`](CETAK.md) |
| Pengaturan dokumen | ✅ | Kop, penandatangan, catatan kaki per jenis. **Bukan** templat Blade yang dapat disunting |

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
| Poin kemahasiswaan & pelanggaran | ✅ | Dua buku besar, tidak pernah dijumlahkan — [`POIN-KEMAHASISWAAN.md`](POIN-KEMAHASISWAAN.md) |
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
| Kepegawaian mendalam | ✅ | Enam riwayat; hanya pangkat & mutasi yang berat — [`KEPEGAWAIAN.md`](KEPEGAWAIAN.md) |
| Manajemen unit kerja | ✅ | Pohon dengan penjaga lingkaran; kolom teks lama tetap sebagai bukti — [`UNIT-KERJA.md`](UNIT-KERJA.md) |
| Rencana kinerja | ✅ | Sasaran per unit + cascade; realisasi dihitung bila datanya ada di sini — [`KINERJA.md`](KINERJA.md) |
| SPMI — Audit Mutu Internal | ✅ | Standar + AMI + temuan + tindak lanjut. **Borang akreditasi tetap di Open Campus** — [`SPMI.md`](SPMI.md) |

### Integrasi

| Modul | | Catatan |
|---|---|---|
| Neo Feeder PDDIKTI | ✅ | Idempoten, ledger, validator pra-kirim. Tujuh belas entitas sejak 22 Okt 2026: + dosen pengajar kelas, + Lulus/DO, + komponen evaluasi, + nilai per komponen, + 7 entitas aktivitas (MBKM dan tugas akhir beserta anggota, pembimbing, penguji) |
| Campus Bridge | ✅ | 11 endpoint ber-scope, webhook HMAC, spec OpenAPI |
| SSO OAuth2 (Passport) | ✅ | Kampus jadi penerbit identitas |
| Notifikasi (G2) | ✅ | Kategori wajib tidak dapat dimatikan |
| **Komparasi data SIAKAD ↔ Neo Feeder** | ✅ | FeederRekonsiliasi 21 Agu 2026: membaca Feeder kembali dan melaporkan hanya-lokal / hanya-Feeder / berbeda isinya. Entitas tanpa aksi pembacaan dinyatakan belum dapat dibandingkan, bukan cocok |
| **Klien SISTER** | 🚫 | Tidak ada lagi web service SISTER per kampus: pengajaran, pembimbingan, dan pengujian turun ke SISTER **dari PDDIKTI**. Jalurnya Feeder — §Peta Jalan Lanjutan #19–22 |
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
| Kuesioner umum | ✅ | Dua tabel jawaban; anonimitas jadi properti skema — [`KUESIONER.md`](KUESIONER.md) |
| **Aplikasi mobile native** | ⬜ | Web responsif saja |
| **2FA staf** | ⬜ | |

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
| **Payment gateway aktif** | Keuangan | **Besar** — kita hanya punya pencatatan manual |
| **Denda keterlambatan** | Keuangan | Kecil–sedang |
| **Aplikasi mobile native** | Semua | Sedang — web responsif sudah jalan |

### Yang mereka punya dan **sengaja tidak kita kejar**

Bukan kalah. Batas produk.

| Modul mereka | Kenapa di luar |
|---|---|
| **CBT** | Platform ujian: bank soal, sesi serentak ribuan peserta, anti-curang, penilaian otomatis. Beban dan model datanya tidak ada hubungannya dengan sistem catatan akademik |
| **LMS bawaan** | Platform belajar: materi, tugas, diskusi, progres |
| Career center, forum & agenda alumni | Milik Open Campus |
| Modul akreditasi / SPMI / ekspor SAPTO | Produk terpisah di sisi mereka juga; di sini hanya faktanya |
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
| **Integrasi akuntansi terbuka** | Mereka mengarah ke produk keuangan mereka sendiri |
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
| ~~8~~ | ~~**Cetak KTM, kartu ujian, absensi, jurnal**~~ | ✅ 17 Agustus 2026 |
| ~~9~~ | ~~**Pengaturan dokumen**~~ | ✅ 17 Agustus 2026 — kop, penandatangan, catatan kaki per jenis. Templat Blade yang dapat disunting pengguna ditolak sebagai rancangan: itu berarti mengeksekusi kode yang tersimpan di basis data |

**P1 perkuliahan tuntas.** Yang berikutnya P2 — memperdalam data mahasiswa dan
dosen, dan satu-satunya kekalahan kompetitif yang tersisa di dalam cakupan kita
tetap **payment gateway**, yang terhalang kredensial merchant.

### P2 — memperdalam data mahasiswa & dosen

| # | Pekerjaan | Hasil |
|---|---|---|
| ~~9~~ | ~~**Evaluasi mahasiswa (peringatan & DO)**~~ | ✅ 18 Agustus 2026 — sistem menghitung, manusia memutuskan; cuti tidak dihitung sebagai semester tempuh. [`EVALUASI-STUDI.md`](EVALUASI-STUDI.md) |
| ~~10~~ | ~~**Poin kemahasiswaan & pelanggaran**~~ | ✅ 18 Agustus 2026 — dua buku besar yang tidak pernah dijumlahkan. [`POIN-KEMAHASISWAAN.md`](POIN-KEMAHASISWAAN.md) |
| ~~12~~ | ~~**Manajemen unit kerja**~~ | ✅ 18 Agustus 2026 — dikerjakan **sebelum** #11 karena mutasi merujuk unit. [`UNIT-KERJA.md`](UNIT-KERJA.md) |
| ~~11~~ | ~~**Kepegawaian mendalam**~~ | ✅ 18 Agustus 2026 — enam riwayat; hanya pangkat & mutasi yang punya aturan. [`KEPEGAWAIAN.md`](KEPEGAWAIAN.md) |
| ~~13~~ | ~~**Kuesioner umum**~~ | ✅ 18 Agustus 2026 — dua tabel jawaban, karena anonimitas harus properti skema. [`KUESIONER.md`](KUESIONER.md) |

**P2 tuntas.** Yang tersisa P3 dan satu-satunya kekalahan kompetitif di dalam
cakupan: **payment gateway**, terhalang kredensial merchant.

### P3 — pelaporan & platform

| # | Pekerjaan |
|---|---|
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

Hari ini enam entitas dikirim: biodata, riwayat pendidikan, aktivitas kuliah,
KRS, kelas, nilai. Semuanya `Insert`. Yang belum dikirim semuanya **sudah punya
tabel lokal** — ini pekerjaan mapper dan validator, bukan model data baru.

| # | Pekerjaan | Kenapa, dan apa yang sudah menopangnya |
|---|---|---|
| ~~19~~ | ~~**Dosen pengajar kelas → Feeder**~~ | ✅ 22 Oktober 2026. `kelas_dosen` ada, mapper tidak. Padahal inilah penghitung rasio dosen:mahasiswa di PDDIKTI, pembagi SKS pada *team teaching*, dan **sumber "pengajaran" di SISTER**. Tanpa ini, BKD unsur pendidikan yang dihitung otomatis di sini tidak pernah sampai ke tempat yang menilainya |
| ~~20~~ | ~~**Komponen evaluasi kelas & nilai per komponen → Feeder**~~ | ✅ 22 Oktober 2026 — `komponen_nilai` kini punya `jenis_evaluasi`; ambang IKU 7 dihitung validator. Rencana evaluasi wajib dilaporkan sejak 2024 gasal. `komponen_nilai` dan `nilai_komponen` sudah berbobot per kelas — tinggal dipetakan. Satu aturan validator pra-kirim baru: **IKU 7 diakui PDDIKTI hanya bila bobot partisipatif + proyek ≥ 50 %** — kelas ber-flag *case method* yang bobotnya di bawah itu harus ditandai *sebelum* sinkron, bukan ditemukan saat rekonsiliasi IKU |
| ~~20b~~ | ~~**Layar rencana evaluasi kelas**~~ | ✅ 22 Oktober 2026 — panel di layar Input Nilai: nama, bobot, jenis per komponen; total dan ambang IKU 7 dihitung langsung di layar dan diulangi server. Komponen yang berisi nilai dapat diubah, tidak dapat dihapus |
| 24b | **Skala nilai prodi dan periode perkuliahan → Feeder** | `InsertSkalaNilaiProdi` dan `InsertPeriodePerkuliahan` adalah baris per prodi; skala nilai di sini satu untuk seluruh kampus (`config/academic.php`) dan periode adalah `tahun_akademik` tanpa dimensi prodi. Butuh tabel per prodi atau keputusan bahwa satu skala berlaku untuk semua — bukan mapper |
| ~~21~~ | ~~**Lulus/DO → Feeder**~~ | ✅ 22 Oktober 2026. Yudisium ada; status di PDDIKTI tetap *Aktif*. Itu salah satu dari lima penyebab tersering reservasi nomor ijazah gagal (#28) |
| ~~22~~ | ~~**Aktivitas MBKM beserta anggota, pembimbing, penguji → Feeder**~~ | ✅ 22 Oktober 2026 — tujuh entitas: aktivitas MBKM, anggota, pembimbing; tugas akhir sebagai aktivitas, anggota, pembimbing, penguji. Kode jenis aktivitas dan kategori kegiatan **tidak di-seed**: kampus memetakannya sesudah tarik referensi. **Konversi MBKM** (`InsertKonversiKampusMerdeka`) dan klaim program flagship belum — digeser ke #23 |
| ~~23~~ | ~~**Prestasi, transfer kredit, dan konversi MBKM → Feeder**~~ | ✅ 22 Oktober 2026 — tiga entitas. Prestasi hanya yang terverifikasi; jenis dan tingkatnya dari tarikan referensi, tidak di-seed. Konversi MBKM dikunci pada anggota aktivitas (#22), id-nya di `krs_detail.feeder_konversi_id` di samping id KRS |
| ~~24~~ | ~~**Kurikulum, mata kuliah, matkul kurikulum → Feeder**~~ | ✅ 22 Oktober 2026 — tiga entitas referensi, dijalankan paling awal. Yang tersingkap: payload kelas dan KRS selama ini mengirim **kode lokal** sebagai `id_matkul`; kini id Feeder begitu katalog terkirim, dan validator memperingatkan bila belum. **Skala nilai prodi dan periode perkuliahan belum**: keduanya per prodi, dan tidak ada tabel lokal per prodi untuk menjadi barisnya — butuh keputusan pemodelan, dicatat sebagai #24b |
| ~~25~~ | ~~**Jenis daftar riwayat pendidikan yang lengkap**~~ | ✅ 22 Oktober 2026 — enum `JenisDaftar` dan empat kolom di `mahasiswa` (jenis, PT asal, prodi asal, SKS diakui); mapper riwayat mengirimnya, validator menolak pindahan/RPL tanpa asal atau SKS diakui. Hanya "peserta didik baru" yang di-seed kodenya. **Belum ada layar pengisiannya** — itu #33, intake pindahan |
| ~~26~~ | ~~**`Update*` / `Delete*` untuk koreksi**~~ | ✅ 22 Oktober 2026 — `Update*` sudah berjalan lewat hash sejak Agustus; yang baru `Delete*`: `hapusYangTerhapus()` membaca ledger, mencocokkan dengan baris yang masih ada (terhapus lunak = tiada), dan mengirim hapus untuk selisihnya. Tombol "Hapus yang tiada" per entitas dan `openacademic:feeder-hapus`. Entitas tanpa aksi hapus dinyatakan, bukan ditebak |
| 27 | **Perubahan Data Mahasiswa & Perubahan Jenis Keluar** | Enam kolom identitas (NIM, nama, nama ibu, tempat & tanggal lahir, periode masuk) dan tujuh kolom keluar dikunci PDDIKTI setelah sinkron; mengubahnya berarti ajuan di PDDIKTI admin dengan surat permohonan dan lampiran. Aplikasi harus **menandai kolom itu terkunci** setelah sinkron pertama, dan modul surat dapat mencetak surat permohonannya |

Juga dicatat, bukan pekerjaan: sinkronisasi dari UI Feeder memerlukan OTP
WhatsApp, jadi tidak pernah dapat diotomasi penuh. Monitor Feeder harus
menyatakan langkah itu, bukan berpura-pura "sinkron" selesai.

### P5 — Kelulusan elektronik: menutup risiko nomor ijazah

Risiko terbuka di [`MODUL.md`](MODUL.md) — nomor ijazah diberikan lokal —
sekarang punya rencana.

| # | Pekerjaan | Kenapa |
|---|---|---|
| ~~28~~ | ~~**Daftar periksa kesiapan PIN per calon lulusan**~~ | ✅ 22 Oktober 2026 — `KesiapanPinService`, delapan butir per lulusan (NIK, identitas PDM, registrasi, AKM tiap semester, nilai final, SKS, SK yudisium, Lulus/DO terkirim), ditampilkan di kolom "Siap PIN" layar Yudisium. SKS diakui pindahan menunggu #25/#33 |
| ~~29~~ | ~~**`nomor_ijazah` menjadi nilai yang diimpor, bukan dibangkitkan**~~ | ✅ 22 Oktober 2026 — `ACADEMIC_IJAZAH_SUMBER=pin` bawaan: pembangkit ditolak, nomor dicatat per baris dari hasil reservasi (NIM, nomor), tidak pernah ditimpa. `lokal` tetap ada untuk institusi di luar skema. Bila kampus memakai PIN (wajib sejak akhir 2020), kolom itu berhenti diisi sendiri; verifikasi publik ijazah adalah SIVIL, bukan QR lokal. QR lokal tetap untuk surat dan SKPI. Butuh satu setelan kampus, dan bawaannya harus yang aman: *tidak membangkitkan* |
| 30 | **Pendaftaran wisuda oleh mahasiswa** | Hari ini staf yang mendaftarkan. Syarat (yudisium, bebas tagihan, bebas pinjaman) sudah dapat dihitung |
| 31 | **Status "Menunggu Uji Kompetensi"** | Prodi kesehatan: lulus akademik tetapi belum lulus ukom dilaporkan sebagai status aktivitas kuliah tersendiri. `StudentStatus` belum punya nilainya; tanpa itu mahasiswa seperti ini tercatat *Lulus* atau *Aktif* — keduanya salah |

### P6 — Kesenjangan pembanding yang tersisa di dalam poros

| # | Pekerjaan | Catatan |
|---|---|---|
| 32 | **Pendaftaran PMB daring oleh calon** | Satu-satunya kesenjangan *besar* yang baru. `pmb_pendaftar` tidak punya akun; rute publik PMB hanya melayani berkas. Perlu: akun pendaftar, periode & syarat, jadwal seleksi, kartu ujian pendaftar, pengumuman lewat surel, penanda calon penerima KIP. Nilai seleksi dari CBT mana pun **diimpor**, bukan diselenggarakan di sini |
| 33 | **Mahasiswa pindahan** | Intake dengan PT dan prodi asal; SKS diakui lewat `konversi_kredit` yang ada. Pasangan #25 |
| 34 | **Jadwal ujian (UTS/UAS)** | Entitas sendiri: sesi, ruang, pengawas, bentrok. Kartu ujian sudah dicetak, tetapi dari jadwal kuliah |
| 35 | **Kalender akademik** | Tanggal kunci per semester sebagai data yang dibaca gerbang KRS, nilai, dan pengingat — bukan sebagai pengumuman |
| 36 | **Pengingat mahasiswa aktif yang belum KRS** | Notifikasi ada; penerimanya belum. Dipicu menjelang jendela KRS tutup |
| 37 | **KKN / PKL sebagai kegiatan terkelola** | Kelompok, lokasi, pembimbing lapangan, nilai. Mendarat sebagai `aktivitas_mahasiswa` agar tetap satu jalur ke Feeder (#22), bukan tabel baru |
| 38 | **Tahap proposal tugas akhir** | Proposal → seminar proposal → bimbingan. TA hari ini mulai dari judul |
| 39 | **Riwayat kepegawaian yang tersisa** | Diklat, pengalaman kerja, studi lanjut / tugas belajar, tugas dinas, jabatan struktural, inpassing, pensiun & kontrak — ditambah enam formulir yang sudah tercatat di MODUL.md (penghargaan & sanksi, bahasa, organisasi profesi, keluarga, pangkat, mutasi). Inilah isi "Profil Dosen" di SISTER yang masih diketik ulang dosen di sana |
| 40 | **Buku besar angka kredit (Kum A–E)** | Dihitung dari BKD dan portofolio (kinerja sebelum 2026 dikonversi dengan faktor 4 dari SKS BKD ke AK), per dosen, per jenjang. Keluarannya: rekap kumulatif, kesiapan usulan kenaikan jabatan, dan **ekspor lembar penyetaraan** (NUPTK, jabatan, TMT, Kum A–E, total DUPAK) dalam format yang diterima SISTER. Buku besar, bukan dasbor — SISTER yang menilai, bukan aplikasi ini |
| 41 | **Tarif per SKS / per mata kuliah** | Komponen tarif hari ini: UKT, SPP, praktikum, registrasi, wisuda. Kampus yang menagih per SKS tidak terlayani |
| 42 | Monitoring pemakaian ruang | Sudah tercatat di §Peta Cakupan |
| 43 | Denda, helpdesk | Sudah tercatat (#15, #18) |

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
apa yang sudah tidak ada di sini, dan **#23–#25 selesai** di PR terpisah: P4 tuntas
kecuali #27 dan #24b. Berikutnya **#27**, lalu **#32**. #32 adalah
pekerjaan terbesar dan satu-satunya yang membuka portal keempat (calon
mahasiswa); kerjakan setelah Feeder tuntas, supaya pendaftar yang diterima
langsung punya riwayat pendidikan yang benar (#25).

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
| Adaptor Midtrans | Kredensial merchant + endpoint notifikasi terverifikasi |
| ~~Klien SISTER~~ | **Dicabut 22 Oktober 2026.** SISTER kini terpusat dan menarik data pengajaran, pembimbingan, dan pengujian dari PDDIKTI; tidak ada endpoint per kampus untuk dituju. Yang tersisa untuk SISTER adalah ekspor berkas (sudah ada) dan pengiriman entitas yang kurang ke Feeder (#19–22) |
| Endpoint pembayaran Easy Accounting | Belum ada di API v1 mereka. Bila lahir, yang berubah satu metode di `PenjurnalanService` |
| Federasi IdP eksternal | Keputusan pemetaan guard |
| **Rilis publik v1.0** | Keputusan pemilik repo. Remote sudah ada: repo publik di GitHub sejak 22 Oktober 2026 |

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
| Perintah pembangkit data skala | ✅ | Perintah openacademic:beban-katalog & beban-kuota; fixture 5.000 mahasiswa untuk [`KAPASITAS.md`](KAPASITAS.md) dibuat sekali pakai |
| Katalog KRS pada ±1.000 kelas | ✅ | Diukur 19 Agu 2026: 1.000 baris = 1,92 s dan 2,2 MB HTML. **Sudah dipaginasi**: 105 KB, 0,79 s — lihat KAPASITAS.md |
| Uji beban bersamaan | ✅ | Perebutan kuota diuji 19 Agu 2026: 19 proses, 1 kursi, tepat 1 pemenang. Beban HTTP penuh masih terbuka |
| CSP masih `'unsafe-eval'` | 🟡 | Alpine mengevaluasi ekspresi `x-` lewat konstruktor `Function` |
| **CI merah sejak 22 Agustus 2026** | ✅ | Lunas 22 Okt 2026. Bukan 8 tes: **170**. CI menjalankan Pest *sebelum* `npm run build`, dan sejak tata letak dipisah (`build/` di root, di-ignore) setiap layar membalas 500 "Vite manifest not found". Di mesin pengembang `build/` selalu ada, jadi suite terlihat hijau dua bulan. Ditambah 14 tes SSO yang hanya lolos bila `.env` menyetel `SSO_ENABLED=true` — rute SSO didaftarkan saat boot, jadi `config()` di dalam tes terlambat. Keduanya diperbaiki di suite, bukan di CI: `TestCase::setUp()` memanggil `withoutVite()`, dan `phpunit.xml` menyetel `SSO_ENABLED=true` |
| 8 tes gagal di `main` sejak Oktober 2026 | ✅ | Lunas 22 Okt 2026. Bukan regresi kode: fixture `KurikulumLanjutanTest` dan `KonversiKreditTest` membuka jendela KRS dengan menggeser `krs_mulai`/`krs_selesai` ke hari ini, tetapi `TahunAkademik::krsDibuka()` menutup jendela pada `krs_perubahan_selesai` — yang tetap 15 Sep 2026 dari factory. Hijau sampai tanggal itu lewat. Kini lewat state factory `krsDibuka()` yang menjangkarkan ketiga tanggal ke hari ini |
| Font dari Google Fonts | ✅ | Di-host sendiri lewat `@fontsource` 19 Agu 2026; `style-src`/`font-src` pihak ketiga dicabut. Menyingkap bug terpendam: Vite menulis URL aset absolut dari akar domain, jadi setiap pemasangan subfolder kehilangan hurufnya — `base: './'` |

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
