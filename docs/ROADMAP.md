# ROADMAP.md — Rencana Kerja Modul & Status

> Diverifikasi terhadap kode dan basis data pada **6 Agustus 2026**.
> Status di sini adalah hasil pemeriksaan, bukan rencana: kalau tertulis ✅,
> berarti ada kode yang mengeksekusinya dan sudah dijalankan.

**Legenda** — ✅ Selesai · 🟡 Sebagian · ⬜ Belum

---

## Ringkasan Cepat

| | Jumlah |
|---|---|
| Tabel domain | 47 (+14 tabel infrastruktur Laravel/Spatie) |
| Foreign key | 78 |
| Enum | 16 |
| Model Eloquent | 40 |
| Policy | 6 |
| Service | 15 |
| Layar terpakai | 21 dari 32 di bundel desain |
| Endpoint Bridge API | 12 |
| Perintah artisan | 4 (`openacademic:feeder-*`, `bridge-token`) |
| Tes Pest | 606 hijau (1.336 asersi) |

**Basis data: sudah ada dan terisi.** `open_academic` di MariaDB, seluruh
47 tabel domain ter-migrasi, dan `php artisan migrate --seed` mengisi kampus
demo utuh. Rincian isinya ada di §"Isi Basis Data" paling bawah.

**Fase 0–5 + SSO selesai, ditambah enam modul administrasi** (Master Akademik,
SDM, PMB, Cuti, Keuangan, dan layar koreksi nilai / pindai QR / log aktivitas /
pengaturan).

**Gap fungsional sudah ditutup** (§1 di bawah): catatan semester kini dibekukan,
sehingga tangga batas SKS berbasis IPS akhirnya menyala di instalasi sungguhan.
Begitu pula layar Jadwal & Kelas (§2).

**Dibandingkan cakupan SIAKAD yang lazim dipakai kampus Indonesia**, tercatat
tujuh kesenjangan pada 8 Agustus 2026. **Enam sudah ditutup:** G1 Tugas Akhir,
G2 Notifikasi, G3 Surat & SKPI, G4 Keringanan & Beasiswa, G5 Konversi Kredit,
dan G6 EDOM.

Sisanya satu, di [§Perbandingan dengan SIAKAD Lain](#perbandingan-dengan-siakad-lain):
**G7 SISTER/BKD**, yang menunggu akses ke sistem kementerian sisi dosen.

**Repo sudah di bawah Git** sejak 11 Agustus 2026, satu commit awal atas
sembilan belas sesi kerja. Belum ada remote — itu keputusan pemilik repo.

**Portabilitas basis data:** mesin basis data adalah pilihan konfigurasi.
Seluruh akses lewat Eloquent, pencarian teks memakai satu scope yang menghasilkan
`ilike` pada PostgreSQL dan `like` pada MySQL, dan uang disimpan sebagai integer.
Rinciannya beserta pernyataan terbuka soal Oracle ada di
[`BASIS-DATA.md`](BASIS-DATA.md).

---

## Fase 0 — Fondasi ✅

| Item | Status | Catatan |
|---|---|---|
| Skema basis data 47 tabel | ✅ | `uuid` + `softDeletes` + FK `restrictOnDelete` di seluruh tabel akademik |
| 16 enum sejajar kode PDDIKTI | ✅ | Encoding semester, status mahasiswa, MBKM, jenjang, dsb. |
| 40 model + relasi + cast | ✅ | |
| Trait `HasUuid` | ✅ | |
| Trait `HasLogAktivitas` + job antrean | ✅ | Terverifikasi end-to-end: 7 tes + satu jalankan sungguhan `queue:work` pada driver database |
| Autentikasi 3 guard | ✅ | Satu formulir, guard dikenali dari NIM/NIDN/NIP/surel |
| RBAC Spatie (8 peran, 38 izin) | ✅ | 6 Policy + `authorize()` di controller; 18 tes batas otorisasi |
| Middleware `EnsureTermIsActive` | ✅ | Portal membalas 503 dengan pesan berbeda per peran saat tak ada semester aktif |
| Design system + app shell | ✅ | Token "Midnight Executive", 8 komponen Blade |
| Responsif 375/768/1280 | ✅ | Bottom nav + sheet navigasi, nol geser horizontal, target sentuh ≥44px |
| `DemoCampusSeeder` | ✅ | Kampus utuh 3 semester |
| Pint + Pest + GitHub Actions | ✅ | |
| Dokumen repo | ✅ | README, CLAUDE.md, STATUS.md, DECISIONS.md (ADR-001..010) |

**Sisa pekerjaan Fase 0 — selesai 6 Agustus 2026:**

- ✅ Audit trail terverifikasi end-to-end. Selain 7 feature test, satu putaran
  `queue:work --queue=audit` sungguhan pada driver database membuktikan barisnya
  benar-benar tertulis. Ditemukan risiko operasional: tanpa worker, jejak audit
  **gagal diam-diam** — kini diperingatkan tegas di README.
- ✅ 6 Policy (`Mahasiswa`, `Krs`, `Nilai`, `Tagihan`, `KelasKuliah`, `Dosen`)
  dengan trait bersama `ResolvesActor`, plus `authorize()` di seluruh controller
  yang ada. 18 tes batas otorisasi.
- ✅ Middleware `EnsureTermIsActive` terpasang di ketiga grup rute portal.

**Batas otorisasi yang sekarang dijamin uji:**

| Aturan | Kenapa penting |
|---|---|
| Mahasiswa hanya melihat tagihan & nilainya sendiri | Kebocoran lintas mahasiswa |
| Dosen **tidak** boleh melihat tagihan mahasiswa bimbingannya | Golongan UKT mencerminkan penghasilan keluarga |
| Hanya dosen wali **yang ditugaskan** boleh menyetujui KRS | Kalau tidak, tahap persetujuan kehilangan makna |
| Nilai final terkunci bahkan dari dosen pengampunya | Perbaikan hanya lewat jalur koreksi ter-audit |
| Dosen tidak boleh membuka kembali nilai final lewat `correct` | Mencegah dosen membatalkan kuncinya sendiri |
| Staf tanpa peran ditolak meski berhasil masuk | Akun baru tidak otomatis berwenang |

---

## Fase 1 — Inti Akademik ✅

Aturan bisnis sudah pindah dari konfigurasi ke kode. Satu siklus semester penuh
— isi KRS, disetujui wali, kuliah, presensi, nilai, finalisasi, IPK, transkrip —
berjalan end-to-end di data demo.

Sisa kecil yang sengaja ditunda ke fase berikutnya: layar Profil Akademik,
halaman pemindaian QR sisi mahasiswa (service-nya sudah ada dan teruji, tinggal
layarnya), serta layar koreksi nilai untuk staf (service-nya sudah ada).

### 1.1 Modul KRS/KHS — ✅ selesai 6 Agustus 2026

| Item | Status |
|---|---|
| Skema `krs`, `krs_detail` | ✅ |
| Aturan batas SKS di `config/academic.php` | ✅ |
| Enum `KrsStatus` + transisi sah | ✅ |
| Layar KHS & Transkrip (baca) | ✅ |
| `BatasSksCalculator` — batas dari IPS semester **final** terakhir | ✅ |
| `PrasyaratChecker` — hanya nilai final yang membuka prasyarat | ✅ |
| `KrsService::tambahKelas()` — kurikulum, duplikat, sudah lulus, prasyarat, batas SKS, bentrok jadwal | ✅ |
| Penguncian kuota atomik (`lockForUpdate` dalam transaksi) | ✅ |
| Gerbang pembayaran minimum, dipanggil alur KRS | ✅ |
| `ajukan()` / `putuskan()` dengan transisi tervalidasi & ter-audit | ✅ |
| Layar KRS mahasiswa (katalog, bar kuota, tray SKS, stempel emas) | ✅ |
| Layar persetujuan KRS dosen wali (peringatan + tolak wajib catatan) | ✅ |
| Feature test | ✅ 33 tes (26 aturan service + 7 alur HTTP) |

**Aturan yang kini ditegakkan kode, bukan sekadar konfigurasi:**
batas SKS turun dari IPS semester final terakhir (semester yang nilainya belum
final sengaja diabaikan agar mahasiswa tidak dihukum oleh keterlambatan
penilaian) · prasyarat hanya terbuka oleh nilai final · kursi terakhir
diperebutkan secara atomik · rencana studi terkunci setelah diajukan ·
hanya dosen wali yang ditugaskan boleh memutuskan · penolakan wajib bercatatan
dan mengembalikan rencana ke keadaan dapat disunting.

### 1.2 Modul Penilaian & Transkrip — ✅ selesai 6 Agustus 2026

| Item | Status |
|---|---|
| Skema `komponen_nilai`, `nilai_komponen`, `nilai` | ✅ |
| Skala huruf terkonfigurasi + `GradeLetter::fromScore()` | ✅ |
| `PenilaianService` — validasi total bobot 100, nilai akhir terbobot | ✅ |
| Kunci & finalisasi + jalur koreksi ter-audit (staf saja) | ✅ |
| `IndeksPrestasiCalculator` — IPS/IPK, mengulang dihitung sekali dengan nilai terbaik | ✅ |
| Layar input nilai ala spreadsheet + hitung huruf saat mengetik | ✅ |
| `TranskripService` → PDF resmi bingkai ganda + kode verifikasi | ✅ |
| Feature test | ✅ 17 tes |

### 1.3 Modul Presensi — ✅ selesai 6 Agustus 2026

| Item | Status |
|---|---|
| Skema `pertemuan_kelas`, `presensi` + data demo | ✅ |
| Aturan kehadiran minimum di konfigurasi | ✅ |
| `PresensiService` — input massal per pertemuan, rekap per mahasiswa | ✅ |
| Sesi QR (token berputar, kedaluwarsa, absen mandiri) | ✅ |
| Kelayakan UAS dari persentase kehadiran, dikonsumsi layar nilai | ✅ |
| Layar grid 16 pekan + panel isi per pertemuan | ✅ |
| Feature test | ✅ 13 tes |

**Keputusan yang perlu diketahui:** persentase kehadiran dihitung terhadap
pertemuan yang **benar-benar terlaksana**, bukan terhadap 16 nominal — kelas
yang baru berjalan 8 pertemuan tidak boleh menampilkan seluruh mahasiswanya di
angka 50%. Izin dan sakit dihitung hadir; hanya alpa yang tidak.

Sistem **tidak** otomatis menggugurkan nilai mahasiswa yang di bawah ambang
kehadiran — layar nilai menandainya dan penetapan kelayakan UAS tetap keputusan
dosen pengampu dan program studi.

### 1.4 Portal — layar yang belum dibuat

| Layar | Peran | Status |
|---|---|---|
| Dasbor | 3 peran | ✅ |
| KHS & Transkrip | Mahasiswa | ✅ |
| Tagihan & Pembayaran | Mahasiswa | ✅ (baca) |
| Data Mahasiswa | Admin | ✅ (baca + filter) |
| KRS | Mahasiswa | ✅ |
| Jadwal Kuliah | Mahasiswa | ✅ |
| Transkrip PDF | Mahasiswa | ✅ |
| Persetujuan KRS | Dosen | ✅ |
| Kelas Diampu | Dosen | ✅ |
| Input Nilai | Dosen | ✅ |
| Presensi | Dosen | ✅ |
| Mahasiswa Bimbingan | Dosen | ✅ |
| Profil Akademik | Mahasiswa | ⬜ |
| Master Akademik | Admin | ⬜ |
| Jadwal & Ruang | Admin | ⬜ |
| Kepegawaian Dosen | Admin | ⬜ |

---

## Fase 2 — Neo Feeder PDDIKTI ✅ selesai 6 Agustus 2026

Diferensiator utama proyek ini.

| Item | Status |
|---|---|
| Skema `feeder_sync_logs`, `feeder_mappings`, `feeder_refs`, `feeder_validation_issues` | ✅ |
| `config/feeder.php` — urutan entitas & dependensinya | ✅ |
| `FeederClientInterface` + `NeoFeederClient` (cache token, retry sekali saat token kedaluwarsa) | ✅ |
| `FakeFeederClient` — dapat diprogram menolak/membalas, dipakai tes & demo | ✅ |
| Tarik tabel referensi → `feeder_refs` | ✅ |
| Enam mapper: Mahasiswa, RiwayatPendidikan, AktivitasKuliah, KelasKuliah, KRS, Nilai | ✅ |
| `FeederValidator` pra-kirim → `feeder_validation_issues` | ✅ |
| `FeederSyncService` idempotent + urutan dependensi + ulangi-yang-gagal | ✅ |
| `SyncFeederEntityJob` (antrean `feeder`) | ✅ |
| Artisan `feeder-sync`, `feeder-validate`, `feeder-refs` | ✅ |
| Layar monitor: heartbeat, kartu entitas, laporan validasi, buku besar | ✅ |
| Feature test | ✅ 16 tes |

**Tiga properti yang membuat modul ini layak ada, dan terbukti di jalankan nyata:**

1. **Idempotent.** Payload tiap baris di-hash dan dibandingkan dengan yang
   terakhir diterima Feeder. Jalankan ulang pada data demo: **0 terkirim, 197
   dilewati** — bukan 197 duplikat. Sinkronisasi yang putus di tengah selalu
   aman diulang.
2. **Berurutan.** Entitas menolak jalan sebelum dependensinya sukses pada
   semester yang sama, dengan pesan yang menyebut dependensi mana.
3. **Dapat dipertanggungjawabkan.** Tiap kirim, lewat, dan gagal menulis satu
   baris ledger beserta payload dan alasannya — inilah jawaban ketika PDDIKTI
   dan kampus berbeda pendapat soal apa yang dilaporkan.

**Validasi pra-kirim terbukti pada data demo:** menemukan 1 mahasiswa tanpa NIK
dan 2 kelas berpengampu praktisi tanpa NIDN, lalu **membatalkan** sinkronisasi
sebelum satu baris pun dikirim.

---

## Fase 3 — Campus Bridge 🟡 sebagian selesai 6 Agustus 2026

| Item | Status |
|---|---|
| Skema `bridge_consumers`, `bridge_webhook_deliveries`, `bridge_api_requests` | ✅ |
| `config/bridge.php` — scope, event, backoff | ✅ |
| Token Sanctum per konsumen + perintah `openacademic:bridge-token` | ✅ |
| Read API `/api/bridge/v1/*` — 11 endpoint, 6 resource | ✅ |
| Penegakan scope + pencatat lalu lintas API | ✅ |
| `BridgeEventPublisher` + `PublishBridgeEventJob` bertanda tangan HMAC | ✅ |
| Backoff bertingkat 60s/5m/15m/1j/6j + kirim ulang manual | ✅ |
| Event terpasang pada alur nyata (`krs.approved`, `grade.finalized`) | ✅ |
| `docs/openapi/bridge.yaml` (spec-first, termasuk kontrak webhook) | ✅ |
| Layar Bridge console | ✅ |
| Feature test | ✅ 27 tes |
| **SSO OAuth2** | ✅ Laravel Passport 13 — lihat catatan di bawah |

**Batas privasi yang ditegakkan uji:** payload Bridge sengaja lebih sempit dari
model internalnya. NIK, alamat rumah, nama orang tua, dan penghasilan keluarga
dikumpulkan untuk pelaporan PDDIKTI, bukan untuk platform engagement, dan tidak
pernah keluar lewat Bridge. Ada tes yang gagal bila salah satunya bocor.

**Keputusan pembagian tanggung jawab:** ambang 20 SKS IKU 2 **tidak** diterapkan
di sisi Open Academic. Nilainya diatur peraturan menteri dan berubah; Bridge
melaporkan `sks_konversi` dan status verifikasinya, keputusan indikatornya milik
Open Campus. Membekukan ambang itu di payload akan menanam kebijakan yang
berumur pendek ke dalam kontrak yang seharusnya stabil.

**SSO — selesai 7 Agustus 2026 dengan Laravel Passport 13.** Open Academic
menjadi *server* OAuth2; aplikasi lain mengarahkan penggunanya ke sini.

Tiga kendala yang harus dijembatani, karena Passport mengasumsikan satu tabel
pengguna sedangkan kita punya tiga:

| Kendala | Jembatan |
|---|---|
| `AuthorizationController` terikat **satu** guard | `App\Auth\SsoGuard` — tanpa sesi sendiri, membaca guard mana pun yang sedang masuk, sehingga tidak ada permintaan login kedua |
| Subject = `getAuthIdentifier()` di kolom **bigint**; id 1 ada di ketiga tabel | Subject jadi **UUID** (`AuthenticatesWithUuid`), kolom `oauth_*.user_id` diubah ke `char(36)` |
| `Token::user()` mengasumsikan satu model | `App\Auth\SsoUserProvider` — menelusuri ketiga tabel, sekaligus menolak akun nonaktif |

Layar persetujuan memakai design system kampus, dan menu **Aplikasi Terhubung**
membuat janji "dapat dicabut kapan saja" di layar itu benar-benar berlaku.
Prasyarat lingkungan: ekstensi PHP **sodium** wajib aktif.

Belum ada: federasi ke IdP eksternal. Penahannya bukan pekerjaan teknis
melainkan keputusan kebijakan — identitas dari Google dipetakan ke tabel yang
mana. Salah memilih berarti dosen bisa masuk ke portal mahasiswa. Lihat
[`SSO.md`](SSO.md).

---

## Fase 4 — Data IKU ✅ selesai 6 Agustus 2026

| IKU | Sumber data | Endpoint | Layar verifikasi |
|---|---|---|---|
| 1 — Kesiapan lulusan | ✅ `yudisium`, `alumni` (9 lulusan di demo) | ✅ | ✅ Yudisium & Wisuda |
| 2 — Pengalaman luar kampus | ✅ `aktivitas_mahasiswa` | ✅ | ✅ Verifikasi Data IKU |
| 3 — Dosen di luar kampus | ✅ `penugasan_dosen` | ✅ | ✅ Verifikasi Data IKU |
| 4 — Praktisi mengajar | ✅ `kelas_dosen.peran` | ✅ | ✅ |
| 7 — Kelas kolaboratif | ✅ flag di `kelas_kuliah` | ✅ | — |
| 11 — Efisiensi edukasi | ✅ rasio & sebaran status | ✅ | — |

**`YudisiumService`** — checklist kelulusan (SKS lulus dengan nilai terbaik per
mata kuliah, IPK, bebas tanggungan, seluruh nilai final, status aktif),
penetapan dalam satu transaksi, pembuatan record alumni, perubahan status ke
Lulus, event `student.graduated`. Syarat **diperiksa ulang saat penetapan**,
bukan dipercaya dari pengajuan: berminggu-minggu dapat berlalu di antaranya.

**`GET /api/bridge/v1/iku-data`** — cacahan fakta transaksional untuk keenam
indikator. **Bukan kalkulator IKU:** tidak ada skor, ambang, maupun target.
Endpoint ini menuntut seluruh scope baca yang menyusunnya, sehingga ringkasan
tidak dapat dijadikan jalan pintas membaca data yang scope-nya tidak diberikan.

Angka yang bergantung pada kebijakan dikembalikan per bucket, bukan diambang.
`per_sks_konversi` melaporkan `0-5`, `6-19`, `20+` — ambang 20 SKS diatur
peraturan menteri dan berubah, jadi keputusannya milik konsumen.

**Layar Verifikasi Data IKU** — titik ketika laporan mandiri menjadi bukti.
Verifikasi tercatat atas nama staf beserta waktunya, dan menerbitkan
`activity.recorded` / `lecturer.assignment_recorded`.

Modul pendukung yang belum digarap sama sekali:

| Modul | Skema | UI & Service |
|---|---|---|
| Neo Feeder PDDIKTI | ✅ | ✅ |
| Wisuda, Yudisium & Alumni | ✅ | ✅ |
| Verifikasi data IKU | ✅ | ✅ |
| PMB (NIM generator, auto-provision akun) | ✅ | ✅ |
| Cuti Mahasiswa | ✅ | ✅ |
| Matriks tarif & generate tagihan otomatis | ✅ | ✅ |
| Rekonsiliasi pembayaran | ✅ | ✅ |
| Pengaturan & branding (UI) | ✅ | ✅ |
| Log Aktivitas (UI penampil) | ✅ | ✅ |
| Master Akademik (6 tab CRUD) | ✅ | ✅ |
| SDM: dosen & staf | ✅ | ✅ |
| Profil akademik mahasiswa | ✅ | ✅ |
| Koreksi nilai (layar staf) | ✅ | ✅ |
| Pindai QR sisi mahasiswa | ✅ | ✅ |
| Midtrans di balik `PaymentGatewayInterface` | ✅ kontrak | ⬜ implementasi |

---

## Belum Dikerjakan — diverifikasi 7 Agustus 2026

Diperiksa terhadap kode, bukan ingatan: item menu ber-`route` null, dan tabel
domain yang tidak pernah ditulis kode produksi (hanya oleh seeder).

### 1. Finalisasi status semester ✅ selesai 7 Agustus 2026

**Koreksi atas laporan awal.** Pemeriksaan pertama menyimpulkan IPS/IPK tidak
pernah dihitung. Itu keliru: `PenilaianService::finalisasi()` memanggil
`IndeksPrestasiCalculator::hitungUlangSeluruhTerm()` setiap kali dosen
memfinalisasi kelas, jadi angkanya selalu mutakhir.

Yang benar-benar tidak pernah terjadi hanyalah **pembekuannya**.
`status_mahasiswa.is_final` tidak pernah disetel siapa pun, dan
`BatasSksCalculator::semesterAcuan()` hanya mau membaca catatan yang **sudah
beku**. Akibatnya tetap sama persis: setiap mahasiswa di instalasi sungguhan
selamanya jatuh ke `default_credits`, dan tangga batas SKS berbasis IPS tidak
pernah menyala. Tanpa galat apa pun.

Kampus demo menutupinya karena `RiwayatAkademikSeeder` menulis baris itu sudah
beku — contoh paling mahal dari utang teknis "seeder menulis langsung ke tabel".

**Perbaikan:** `PenutupanSemesterService` + layar `admin.tutup-semester` +
perintah `openacademic:tutup-semester`.

Pembekuan sengaja dijadikan tindakan administratif tersendiri, bukan efek
samping dari dosen terakhir menekan "finalisasi": koreksi nilai pada pekan
setelah finalisasi adalah hal biasa, dan itu harus tetap mungkin tanpa
pembukaan-kembali yang teraudit.

| Sifat | Alasan |
|---|---|
| **Parsial** | Kampus besar selalu punya satu dosen telat. Menolak menutup apa pun sampai kelas terakhir masuk berarti tidak ada plafon SKS yang pernah diperbarui — justru kegagalan yang mau diperbaiki |
| **Idempotent** | Catatan yang sudah beku dilewati, bukan dihitung ulang. Menulis ulang KHS yang sudah terbit bukan wewenang proses batch |
| **Dihitung ulang tepat sebelum beku** | Perhitungan terakhir terjadi saat dosen lain memfinalisasi; koreksi bisa mendarat sesudahnya |
| **Pembukaan kembali teraudit** | Mengubah KHS yang sudah terbit dan IPK yang mungkin sudah dikutip untuk beasiswa — alasannya wajib |

Terverifikasi pada kampus demo: semester berjalan melaporkan 2 siap · 48
terhalang · 20 kelas belum final; semester yang sudah selesai melaporkan 34
sudah beku dan tidak menyentuh apa pun.

### 2. Jadwal & Kelas ✅ selesai 7 Agustus 2026

`KelasKuliahService` + `JadwalService` + layar `admin.kelas`. Membuka kelas
(termasuk paralel A–Z sekaligus), menugaskan dosen, dan menjadwalkan slot
mingguan.

Nilai sebenarnya ada pada deteksi bentrok, yang memisahkan **mustahil secara
fisik** dari **pertimbangan kebijakan**:

| Bentrok | Perlakuan | Alasan |
|---|---|---|
| Ruang dipakai dua kelas | **ditolak** | Satu ruang tidak bisa menampung dua kelas sekaligus |
| Dosen mengajar dua kelas | **ditolak** | Satu orang tidak bisa berada di dua ruang |
| Kuota > kapasitas ruang | diperingatkan | Keputusan registrar, bukan urusan perangkat lunak |
| Kelas sekohor beririsan | diperingatkan | Mata kuliah pilihan memang lazim bertabrakan |

Perangkat lunak yang memblokir jenis kedua akan diakali — biasanya dengan
menjadwalkan di luar sistem sama sekali.

Pemeriksaan dosen berjalan **dua arah**: saat menjadwalkan slot, dan saat
menugaskan dosen ke kelas yang sudah punya slot. Hanya salah satunya berarti
dosen tetap bisa berakhir di dua ruang sekaligus.

Layar memimpin dengan apa yang **belum siap** — kelas tanpa dosen dan kelas
tanpa jadwal — karena keduanya tidak menimbulkan galat apa pun sampai masa KRS
dibuka.

### 3. Tarif / matriks UKT ✅ selesai 7 Agustus 2026

Layar `admin.tarif` + `TarifResolver`. Tetapi pekerjaan sebenarnya bukan
layarnya — melainkan **tiga cacat yang ditemukan saat membangunnya**, semuanya
pada kode yang saya tulis sendiri pada sesi sebelumnya.

#### Cacat 1 — tarif dijumlahkan, bukan ditimpa

`Tarif` mendokumentasikan aturannya sejak awal: *"baris yang paling spesifik
menang, sehingga tarif umum dan penimpa per prodi dapat hidup berdampingan"*,
lengkap dengan `spesifisitas()` untuk memutuskannya.

`PenerbitanTagihanService` dan `PmbService` **menjumlahkan seluruh baris yang
cocok**. Tarif umum 5 juta ditambah penimpa prodi 7 juta menjadi tagihan
**12 juta**, dan mahasiswa diminta membayarnya. Tidak ada yang menandai.

#### Cacat 2 — masa berlaku diabaikan

`Tarif::scopeBerlakuPada()` sudah ada sejak rilis pertama. Kedua pemanggil tidak
pernah memakainya, sehingga jadwal biaya yang kedaluwarsa dua tahun lalu tetap
ditagihkan.

#### Cacat 3 — golongan UKT tidak mungkin cocok

Tabel `tarif` punya dimensi `golongan_ukt` sejak awal, tetapi **`mahasiswa`
tidak punya kolomnya**. Dimensi itu hanya bisa mencocoki wildcard null, jadi
seluruh sistem UKT berjenjang mati total — setiap mahasiswa ditagih sama rata,
yang membatalkan seluruh kebijakan UKT.

Ditambahkan lewat migrasi append-only, disimpan (bukan dihitung ulang dari
`penghasilan_ortu`): golongan ditetapkan berdasarkan keadaan keluarga saat
diterima, dan tidak boleh diam-diam bergeser ketika seseorang menyunting kolom
penghasilan bertahun-tahun kemudian.

#### Perbaikan

Satu `TarifResolver` sebagai satu-satunya definisi aturan pencocokan — kedua
pemanggil mendelegasi ke sana. Layarnya membawa **simulator**: masukkan NIM
sungguhan, dan ia menunjukkan komponen apa saja yang ditagih, dari baris mana
angkanya berasal, dan baris mana yang kalah. Petugas keuangan yang hendak
menagih lima ribu orang tidak semestinya menghitung itu di kepala.

### 4. Wisuda ✅ selesai 7 Agustus 2026

`WisudaService` + layar `admin.wisuda`. Dipisahkan dari yudisium dengan sengaja:
yudisium adalah keputusan akademik bahwa seseorang lulus, wisuda adalah acara
yang ia daftari, boleh ia lewatkan, dan boleh ia ikuti pada periode berikutnya.
Menggabungkan keduanya berarti lulusan yang tidak hadir wisuda tercatat belum
lulus.

Yang harus benar adalah **nomor ijazah**: tercetak pada dokumen yang dipegang
seumur hidup dan dikutip di setiap lamaran kerja. Karena itu diterbitkan sekali,
tidak pernah dipakai ulang, dan **tidak pernah diterbitkan ulang** — menjalankan
penerbitan lagi hanya mengisi peserta yang belum bernomor. Peserta yang sudah
bernomor pun tidak dapat dikeluarkan dari daftar.

Kuota dijaga di dalam transaksi ber-`lockForUpdate`: dua pendaftaran yang tiba
bersamaan akan membaca hitungan yang sama dan sama-sama lolos melewati kuota
penuh, atau mengambil nomor urut yang sama.

### 5. Pengumuman ✅ selesai 7 Agustus 2026

Layar `admin.pengumuman`. Sengaja tetap kecil sesuai catatan pada migrasinya —
judul, isi, siapa yang melihat, kapan tayang. Komentar, reaksi, dan feed adalah
wilayah Open Campus; menumbuhkan ini menjadi CMS berarti menduplikasi lapisan
engagement di dalam *system of record*.

Yang perlu perhatian hanya penjadwalan: `published_at` di masa depan membuatnya
tak terlihat sampai saat itu tiba — itulah yang memungkinkan pengumuman KRS
ditulis Jumat dan muncul Senin. Slug dijaga unik supaya "Jadwal KRS" semester
berikutnya tidak mengambil alih alamat milik yang sebelumnya.

### 6. Unggah berkas ✅ selesai 7 Agustus 2026

`BerkasService` + `BerkasController` + `config/berkas.php`, terpasang di PMB
(`pmb_berkas`) dan cuti (`dokumen_path`).

Berkasnya berisi KTP, kartu keluarga, ijazah, dan surat keterangan sakit —
dokumen identitas dan rekam medis milik orang sungguhan. Tiga aturan berlaku,
dan ketiganya mudah dilanggar tanpa ada yang mengeluh:

| Aturan | Kegagalan bila dilanggar |
|---|---|
| **Tidak pernah disk publik** | Berkas di bawah document root dapat diunduh siapa pun yang menebak alamatnya, masuk atau tidak. `BerkasService` **menolak berjalan** bila `BERKAS_DISK=public` |
| **Tidak pernah nama dari pengunggah** | Nama datang dari klien: bisa berisi `../../.env`, null byte, atau ekstensi kedua `ktp.pdf.php`. Nama simpan dibangkitkan; nama asli hanya label |
| **Jenis diperiksa dari isi** | Ekstensi hanyalah bagian dari nama yang diketik seseorang |

Otorisasi ditegakkan **per berkas**, bukan per sesi. "Staf mana pun yang sudah
masuk" akan membiarkan bagian keuangan membaca kartu keluarga para pendaftar,
dan mahasiswa mana pun membaca surat sakit temannya. Penjaga itu sudah
dibuktikan gagal ketika sengaja dilonggarkan — tanpa dia, permintaan mahasiswa
lain menjawab 200, bukan 403.

### Sudah tercatat sebelumnya

| Item | Status |
|---|---|
| Adaptor Midtrans di balik `PaymentGatewayInterface` | ⬜ butuh kredensial merchant + endpoint notifikasi terverifikasi |
| Federasi ke IdP eksternal (Google/Entra/Keycloak) | ⬜ opsional per kampus; tertahan keputusan pemetaan guard |
| 2FA akun staf | ⬜ |
| Rilis publik v1.0 di `motiolabs-space` | ⬜ repo lokal belum di bawah Git |

---

**Catatan `PaymentGatewayInterface`.** Kontraknya sudah ada beserta pencatatan
pembayaran manual (tunai/transfer) yang menghitung ulang `tagihan.terbayar` dari
baris pembayaran. Adaptor Midtrans-nya belum ditulis — itu memerlukan kredensial
merchant sungguhan dan endpoint notifikasi yang terverifikasi tanda tangannya;
menerima notifikasi tanpa verifikasi berarti siapa pun yang dapat menjangkau
endpoint itu bisa melunasi tagihan mana pun di kampus.

---

## Perbandingan dengan SIAKAD Lain

Diperiksa 8 Agustus 2026 terhadap cakupan yang lazim ada pada SIAKAD kampus
Indonesia (produk komersial maupun buatan internal PTN/PTS).

Semua pernyataan di bawah diverifikasi terhadap migrasi, model, dan service yang
benar-benar ada — bukan terhadap daftar tugas.

### Yang sudah setara atau lebih baik

| Modul | Di sini |
|---|---|
| Master akademik | Fakultas, Prodi, Kurikulum, Mata Kuliah, Gedung, Ruang, Tahun Akademik |
| PMB | Gelombang, pendaftar, seleksi, berkas, daftar ulang, generator NIM |
| Registrasi & status | `StatusMahasiswa` per semester, cuti berjenjang |
| KRS/KHS | Batas SKS berbasis IPS, prasyarat, persetujuan dosen wali |
| Jadwal & kelas | Deteksi bentrok ruang/dosen/kohort, kelas paralel |
| Presensi | Grid 16 pekan, sesi QR, rekap agregat |
| Penilaian | Komponen nilai, koreksi teraudit, pembekuan semester |
| Transkrip & yudisium | PDF, syarat kelulusan per prodi |
| Wisuda | Periode, peserta, nomor ijazah sekali terbit |
| Keuangan | Tarif berlapis, UKT, tagihan, pembayaran, dispensasi |
| **PDDIKTI Neo Feeder** | Sinkronisasi idempotent + validator pra-kirim |
| **Campus Bridge** | REST ber-scope + webhook bertanda tangan |
| **SSO OAuth2** | Passport sebagai IdP, kampus jadi penyedia identitas |
| Audit | `LogAktivitas` pada setiap mutasi |

Tiga yang ditebalkan biasanya justru **tidak** ada, atau ada sebagai ekspor CSV
manual, pada SIAKAD yang lebih tua.

### Kesenjangan Tingkat 1 — kampus masih memakai kertas tanpa ini

#### G1. Tugas Akhir / Skripsi ✅ selesai 8 Agustus 2026

Sebelumnya: **akhir ceritanya dibangun tanpa ceritanya.** Yudisium ada, wisuda
ada, nomor ijazah terbit — tetapi `judul_tugas_akhir` hanyalah teks bebas yang
diketik operator pada saat yudisium.

Lima tabel (`tugas_akhir`, `_pembimbing`, `_bimbingan`, `_ujian`, `_penguji`),
tiga service, tiga portal.

**Perubahan yang paling berarti:** `YudisiumService` kini membaca judul dari
catatan yang sudah diuji. Argumen teks lama masih ada, tetapi hanya sebagai
cadangan untuk data pindahan — bila ada catatan tugas akhir, catatan itulah yang
menang. Ditambah satu baris daftar periksa kelulusan yang dihormati per prodi
lewat `prodi.wajib_tugas_akhir`.

##### Aturan yang membuatnya perangkat lunak, bukan formulir

| Aturan | Kegagalan yang dicegah |
|---|---|
| **Satu TA aktif per mahasiswa** — dijaga indeks unik `mahasiswa_aktif_id`, bukan hanya service | Dua judul bersaing muncul di yudisium |
| **Kuota pembimbing** dihitung atas bimbingan berjalan saja | Satu dosen dengan 40 mahasiswa dan tak satu pun benar-benar terbimbing |
| **Panel sidang wajib punya penguji bukan pembimbing** | Karya diuji oleh pihak yang ikut menghasilkannya |
| **Minimum bimbingan yang *disetujui* sebelum sidang** | Syarat disertifikasi sendiri oleh orang yang dibatasi olehnya |
| **Bentrok ruang terhadap jadwal kuliah mingguan** | Panel datang ke ruang yang sudah ada kuliahnya |
| **Selesai wajib lewat sidang yang lulus** | Judul sampai ke ijazah tanpa pernah diuji |

Dua pembedaan yang disengaja: pembimbing **boleh** duduk di panel (praktik lazim
di sini) selama ada satu penguji luar; dan seminar proposal **boleh** dijalankan
tim pembimbing saja — menolaknya hanya akan memindahkan seminar itu ke luar
sistem. Begitu pula status `disetujui` yang terpisah dari `dibimbing`: judul yang
disetujui tanpa pembimbing adalah keadaan nyata yang perlu terlihat, bukan
disembunyikan dengan memaksa keduanya jadi satu langkah.

##### Bug yang ditemukan saat membangunnya

`where('tanggal', $tanggal)` pada pemeriksaan bentrok tidak cocok di SQLite —
mesin itu tak punya tipe DATE dan menyimpan apa pun yang diberikan, sehingga cast
Laravel menuliskan `2026-08-15 00:00:00`. Diperbaiki dengan `whereDate()`.

Yang membuatnya berbahaya bukan kesalahannya, melainkan arah kegagalannya:
pemeriksaan itu **gagal terbuka**. Nol baris terbaca sebagai "tidak ada bentrok",
dan sidang dijadwalkan ke ruang yang terisi. Penjaga yang menjawab "aman" saat
dirinya rusak lebih buruk daripada tidak ada penjaga.

#### G2. Notifikasi ✅ selesai 9 Agustus 2026

Sebelumnya: **tidak ada satu pun notifikasi terkirim di seluruh aplikasi.**
Trait `Notifiable` di-import pada ketiga model identitas dan tidak pernah
dipakai. Seluruh sistem bersifat *pull* — seseorang harus membuka halaman untuk
tahu.

Tiga tabel, enam kategori, tiga belas kelas notifikasi, satu perintah
terjadwal, dan satu layar untuk ketiga portal.

##### Pembedaan yang menentukan rancangannya

**Catatan dalam aplikasi adalah catatan resminya** — itulah yang dapat ditunjuk
seseorang ketika kelak dipersoalkan apakah ia pernah diberi tahu. Surel dan
WhatsApp hanyalah pengantaran, dan selalu boleh dimatikan.

Dari situ mengalir aturan kategori wajib: penolakan KRS dan jatuh tempo tagihan
**tidak dapat dibungkam pada kanal aplikasi**. Menawarkan sakelar mati untuk
keduanya berarti membiarkan seseorang mematikan satu-satunya peringatan yang ia
terima, lalu dikatakan seharusnya ia tahu. Pembedanya bukan tingkat kepentingan,
melainkan akibat administratif.

##### Tiga aturan yang ditegakkan

| Aturan | Kegagalan yang dicegah |
|---|---|
| **Mengumumkan tidak boleh membatalkan** — `Notifier` menelan setiap kegagalan | Server surel mati membatalkan persetujuan cuti yang sudah terjadi |
| **Tidak ada yang keluar sebelum transaksi commit** — `queue.after_commit` untuk *seluruh* job | Mahasiswa diberi tahu KRS-nya disetujui padahal basis data tak menyimpan apa pun |
| **Pengingat yang sama tidak terkirim dua kali** — tabel `notifikasi_kunci` | Kanal yang bicara tiap malam melatih orang mengabaikannya |

##### Bug yang ditemukan saat membangunnya

Penerjemahan pelanggaran indeks unik saya tulis dengan mencocokkan kode SQLSTATE
sendiri — dan **tidak pernah cocok**. Laravel punya
`UniqueConstraintViolationException` yang sudah menangani perbedaan antar-mesin.

Kesalahan yang sama ternyata ada di `TugasAkhirService` dari sesi sebelumnya,
dan tesnya melewatkannya karena menulis langsung ke tabel alih-alih lewat
service. Keduanya diperbaiki, dan tes yang melalui service ditambahkan.

##### WhatsApp

Seam-nya ada; adaptor penyedia tidak, dengan alasan yang sama seperti Midtrans.
Menyalakannya perlu **dua persetujuan terpisah** — driver, dan daftar kategori
yang boleh lewat — karena memasang penyedia bukan berarti memutuskan setiap
nilai yang terbit harus sampai ke ponsel pukul 23.00.

#### G3. Surat keterangan & SKPI ✅ selesai 10 Agustus 2026

Lima jenis surat, satu tabel, satu deret penomoran, satu halaman verifikasi.
Rinciannya di [SURAT.md](SURAT.md).

**Surat keterangan aktif kuliah terbit seketika tanpa persetujuan** — kampus
hanya membacakan kolom status, dan antrean loket untuk itu tidak pernah
mengerjakan apa pun. Jenis lain tetap lewat manusia karena meminjamkan nama
institusi pada proyek orang lain memang sebuah keputusan.

**SKPI terbit otomatis saat kelulusan ditetapkan.** Regulasi mewajibkannya untuk
*setiap* lulusan; menjadikannya permintaan berarti ia sampai kepada yang tahu
harus meminta, dan yang tidak tahu justru yang paling tidak mampu mengejarnya.

##### Tiga sifat yang membuat surat dapat dipercaya

| Sifat | Kegagalan yang dicegah |
|---|---|
| Nomor dijaga indeks unik `(jenis, tahun, urut)`, dengan retry | Dua kertas asli mengaku identitas yang sama |
| Permohonan ditolak tidak memakai nomor; baris terhapus tetap dihitung | Lubang deret yang harus dijawab saat audit; nomor terpakai ulang |
| Fakta dibekukan saat terbit | Dokumen resmi yang berbeda isinya setiap kali dibuka |
| Pencabutan, bukan penghapusan | "Tidak ditemukan" terbaca sebagai pemalsuan oleh pemegangnya |

##### Verifikasi yang benar-benar memverifikasi

Halaman publik, dikunci **UUID bukan nomor berurut** — nomor surat memang harus
dapat ditebak, itu konvensi arsip, dan menaruhnya di URL adalah undangan memanen
nama semua orang yang pernah dikirimi surat. Pencarian manual menuntut nomor
*dan* NIM, dibatasi lajunya, dan hanya menampilkan yang sudah tercetak di kertas.

Tiga jawaban, bukan dua: asli-dan-berlaku, asli-tetapi-kedaluwarsa,
asli-tetapi-dicabut. Menggabungkan yang tengah ke salah satu ujung membuat
verifikasi tak berguna.

##### Kebohongan lama yang ikut diperbaiki

Transkrip unduhan mandiri selama ini mencetak "kode verifikasi" — hash uuid —
beserta kalimat bahwa dokumen sah tanpa tanda tangan basah bila kodenya cocok
dengan pangkalan data. **Tidak pernah ada tempat untuk mencocokkannya.** Kini
lembar itu menyatakan dirinya salinan tidak resmi, dan versi bernomornya adalah
Transkrip Legalisir.

##### Capaian pembelajaran

Tab baru pada Master Akademik. Ditulis sekali per program studi, dwibahasa —
sebagai isian per lulusan, versi Inggrisnya akan menjadi pekerjaan penerjemahan
pada pagi hari wisuda dan berhenti dikerjakan. Layarnya menyatakan akibatnya bila
kosong, bukan sekadar menampilkan tabel hampa.

##### Dependensi baru

`endroid/qr-code` — QR pada surat memuat URL verifikasi saja, bukan faktanya.
Dependensi kedelapan proyek ini.

### Kesenjangan Tingkat 2 — akreditasi & keuangan

#### G4. Keringanan, beasiswa, potongan UKT ✅ selesai 12 Agustus 2026

Sebelumnya bukan sekadar layar yang belum dibuat: `tagihan_item.nominal` bertipe
`unsignedBigInteger`, jadi **baris potongan tidak dapat ada**.

##### Bentuk yang dipilih, dan alasannya

Potongan adalah **baris bernilai negatif pada tagihan**, bukan tabel terpisah
yang dikurangkan saat dibaca. Sepuluh tempat membaca `tagihan.total` dan
`tagihan.terbayar` — gerbang pembayaran KRS, daftar periksa kelulusan, pengingat
tunggakan, syarat surat keterangan aktif kuliah, dasbor. Mempertahankan
`total = jumlah baris` berarti **tak satu pun dari kesepuluhnya berubah**, dan
tak satu pun dapat menyimpang darinya.

Diverifikasi pada kampus demo: nol ketidakcocokan antara `total` dan jumlah
barisnya, atas seluruh tagihan.

##### Tiga cara kehilangan uang tanpa ada yang tahu

| Aturan | Kegagalan yang dicegah |
|---|---|
| Total tidak pernah di bawah nol | `total` unsigned — nilai negatif terbaca sebagai angka positif raksasa |
| Kelebihan bayar **ditampilkan**, bukan ditelan | Mahasiswa bayar Agustus, beasiswa disetujui September; uangnya nyata dan harus dapat dipertanggungjawabkan |
| Penerapan bersifat idempoten | Penerbitan ulang menjadi beasiswa kedua — dan totalnya tetap seimbang, jadi tak ada yang tampak salah |

Ditambah: alasan wajib pada setiap keringanan, kuota per skema dihitung atas
penerima aktif, dan dua beasiswa 60% tidak menjadi 120%.

##### Bug lama yang tersingkap

Aturan status pembayaran menuntut `total > 0` untuk berstatus lunas. Pembebasan
penuh karenanya menghasilkan tagihan **"belum bayar" senilai Rp0** — yang akan
menahan surat keterangan aktif kuliah dan memicu pengingat jatuh tempo. Dibuktikan
dengan mengembalikan aturan lamanya.

##### Batas yang dinyatakan

Pencabutan beasiswa bersifat **ke depan**. Membalik semester yang lalu akan
memunculkan kembali utang atas tagihan yang sudah dianggap selesai berbulan-bulan
sebelumnya; pembalikan satu baris tetap tersedia untuk kasus yang memang harus.

Menagih penyandang dana beasiswa eksternal adalah **piutang di sistem keuangan**,
bukan di sini. Yang dijamin modul ini: setiap potongan dapat ditelusuri ke pihak
yang menanggungnya.

#### G5. Konversi nilai & transfer kredit (RPL / pindahan) ✅ selesai 11 Agustus 2026

`pmb_gelombang.jalur` sudah menerima `rpl` dan `transfer` sejak modul PMB
dibangun, tanpa tempat mencatat apa yang diakui. Pintunya terbuka tanpa lantai di
baliknya.

##### Yang lebih dulu harus dibereskan

Logika **"ambil percobaan terbaik per mata kuliah"** ternyata ditulis **tiga
kali** — di `YudisiumService`, `IndeksPrestasiCalculator`, dan `TranskripService`
— nyaris identik. Menambahkan konversi ke masing-masing akan menjadikannya empat
salinan yang perlahan berbeda: transkrip menampilkan kredit yang tidak dihitung
layar kelulusan.

Disatukan lebih dulu menjadi `PerolehanAkademik`, baru konversinya ditambahkan
sekali.

**Penyatuan itu menyingkap bug yang sudah ada.** IPK tersimpan dihitung atas
seluruh mata kuliah final; IPK pada daftar periksa kelulusan hanya atas yang
lulus. Dua angka berbeda, nama sama — seorang mahasiswa bisa melihat 2,0 pada
catatannya dan 4,0 pada layar kelulusan. Sekarang satu.

##### Aturan yang membuatnya bukan sekadar formulir

| Aturan | Kegagalan yang dicegah |
|---|---|
| **Kredit ganda dicegah dari dua sisi** — konversi menolak MK yang sudah ditempuh; KRS menolak MK yang sudah dikonversi | Mahasiswa pindahan mengambil MK yang sudah diakui, kreditnya terhitung dua kali, dan totalnya hanya keluar lebih besar |
| Satu konversi disetujui per MK, dijaga indeks unik portabel | Pengakuan kedua atas MK yang sama |
| SKS diakui ≤ bobot MK di kurikulum ini | Enam SKS dari luar menjadi enam SKS di sini padahal MK-nya tiga |
| **Batas persentase terhadap syarat kelulusan** | Seseorang diakui masuk ke dalam gelar |
| Pencabutan ditolak setelah lulus | Transkrip terbit tidak lagi cocok dengan catatannya |

##### Keputusan yang perlu diambil kampus

Nilai konversi **tidak masuk IPK secara bawaan**, dan itu posisi, bukan
ketidakpedulian: IPK adalah penilaian institusi ini, sedangkan nilai konversi
diberikan pihak lain dengan standar lain. Dapat dinyalakan. Kreditnya selalu
masuk ke total SKS — itulah arti pengakuan.

Transkrip menandai barisnya (**T** transfer, **R** rekognisi) dan mencantumkan
totalnya pada catatan kaki. Pembaca luar perlu tahu mana yang dinilai kampus ini.

#### G6. EDOM / kuesioner evaluasi dosen ✅ selesai 13 Agustus 2026

**Batasnya diputuskan:** pertanyaan, jawaban, ambang, dan gerbang tinggal di Open
Academic, karena semuanya melekat pada `kelas_kuliah`, daftar peserta, dan KRS.
Open Campus membaca **agregatnya** lewat `GET /teaching-evaluations`. Tidak ada
lapisan kuesioner yang terduplikasi: yang berpindah hanyalah angka jadi.

Inti modulnya bukan kuesionernya, melainkan bentuk tabelnya. `edom_partisipasi`
(siapa sudah mengisi) dan `edom_jawaban` (apa yang dijawab) **tidak berbagi kunci
apa pun** — tidak ada `mahasiswa_id`, tidak ada kaitan ke partisipasi, tidak ada
pengenal respons. Anonimitasnya bukan kebijakan yang harus diingat orang; kolom
yang memungkinkan pemasangannya memang tidak ada. Harganya nyata dan diterima
sadar: jawaban tak dapat diubah maupun dicabut.

Ditambah: ambang responden per periode (di bawahnya tidak ditampilkan apa pun,
termasuk cacahnya), komentar bebas dengan aturan lebih ketat daripada skor
(bawaan: ke prodi, bukan ke dosen yang dinilai), instrumen terkunci begitu satu
jawaban masuk, dan gerbang yang bawaannya menahan KRS — bukan KHS, karena menahan
KHS memakai catatan yang sudah diperoleh mahasiswa sebagai alat tukar.

Rincian di [`EDOM.md`](EDOM.md).

### Kesenjangan Tingkat 3 — pelaporan sisi dosen

#### G7. SISTER & BKD ⬜

Neo Feeder melaporkan mahasiswa. Sisi dosen — BKD per semester (pendidikan,
penelitian, pengabdian, penunjang) dan sertifikasi — tidak punya padanan di sini.
`PenugasanDosen` mencatat mengajar saja, yaitu satu dari empat unsur.

### Sengaja **bukan** di sini

Bukan kesenjangan. Ini batas ekosistem, dan mengaburkannya akan menduplikasi
data yang harus punya satu pemilik.

| Modul | Pemiliknya |
|---|---|
| LMS / e-learning, forum, feed | Open Campus (atau Moodle) |
| Tracer study, jejaring alumni | Open Campus |
| Dasbor 12 IKU, borang akreditasi | Open Campus — di sini hanya faktanya |
| Perpustakaan | SLiMS atau sejenisnya |
| Kepegawaian penuh, payroll, presensi pegawai | HRIS |
| Inventaris, sarpras, aset | Sistem aset |
| Buku besar, jurnal akuntansi | Sistem keuangan; di sini hanya tagihan mahasiswa |

### Usulan urutan

| # | Modul | Alasan didahulukan |
|---|---|---|
| ~~1~~ | ~~**G1 Tugas Akhir**~~ | ✅ selesai 8 Agustus 2026 |
| ~~2~~ | ~~**G2 Notifikasi**~~ | ✅ selesai 9 Agustus 2026 |
| ~~3~~ | ~~**G3 Surat & SKPI**~~ | ✅ selesai 10 Agustus 2026 |
| ~~4~~ | ~~**G4 Keringanan/beasiswa**~~ | ✅ selesai 12 Agustus 2026 |
| ~~5~~ | ~~**G5 Konversi kredit**~~ | ✅ selesai 11 Agustus 2026 |
| ~~6~~ | ~~**G6 EDOM**~~ | ✅ selesai 13 Agustus 2026 |
| 7 | **G7 SISTER/BKD** | Terbesar cakupannya, paling sedikit ketergantungannya |

---

## Fase 5 — Polish & Rilis ✅ selesai 6 Agustus 2026

| Item | Status | Catatan |
|---|---|---|
| `Model::preventLazyLoading()` di luar produksi | ✅ | Berlaku pada kueri yang menghidrasi >1 model — batas bawaan Laravel, karena satu baris tidak bisa menyebabkan N+1 |
| Anggaran kueri per layar | ✅ | `tests/Feature/SmokeLayarTest.php` menelusuri 21 layar + 9 endpoint Bridge terhadap kampus demo penuh |
| N+1 yudisium | ✅ | **171 → ≤30 kueri.** `periksaSyaratBanyak()` menghitung daftar periksa untuk seluruh kohor sekaligus; versi satu-mahasiswa mendelegasi ke sana agar aturannya tidak bercabang dua |
| N+1 konsol Feeder | ✅ | 50 → ≤30 kueri lewat satu agregat berkelompok, bukan empat hitungan per entitas |
| Lazy load `PresensiService` | ✅ | Service tidak lagi menganggap pemanggilnya sudah meng-eager-load |
| **Otorisasi lintas objek** | ✅ | **Kerentanan nyata:** dosen dapat menulis presensi dan membuka sesi QR pada kelas rekan. Diperbaiki + dijaga `tests/Feature/Keamanan/LintasObjekTest.php`, yang sudah dibuktikan gagal tanpa perbaikannya |
| Webhook berkunci kosong | ✅ | Pengiriman dibatalkan bila `BRIDGE_WEBHOOK_SECRET` kosong — tanda tangan berkunci kosong tampak sah padahal dapat dipalsukan |
| Satu peramban satu identitas | ✅ | Masuk ke satu portal me-logout guard lain |
| Header keamanan | ✅ | `SecurityHeaders`: CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` |
| Sapuan mass assignment & SQL mentah | ✅ | Tidak ada `request()->all()`; seluruh raw SQL memakai string statis tanpa interpolasi |
| Aksesibilitas | ✅ | Tautan lompat ke konten, `aria-expanded`/`aria-haspopup` pada dropdown, `role="dialog"` pada lembar menu, nama aksesibel untuk pencarian & menu akun |
| `SECURITY.md` | ✅ | Daftar wajib pra-produksi + keterbatasan yang diketahui, ditulis terbuka |
| `CONTRIBUTING.md` · `CHANGELOG.md` · `docs/DEPLOYMENT.md` | ✅ | |
| Rilis publik v1.0 di `motiolabs-space` | ⬜ | Menunggu keputusan pemilik repo — repo lokal belum berada di bawah Git |

---

## Utang Teknis Lintas Fase

| Item | Status | Kenapa penting |
|---|---|---|
| DTO | 🟡 | `KeputusanWaliData`, `RingkasanKrs` ada; modul lain menyusul |
| FormRequest | 🟡 | `LoginRequest`, `KeputusanKrsRequest`; form Fase 1 sisanya menyusul |
| Seeder menulis lewat service | ⬜ | Seeder masih menulis langsung ke tabel, sehingga aturan akademik tidak ikut ditegakkan pada data demo. Sudah disiasati dengan penyaringan prasyarat, tapi akar masalahnya tetap ada |
| Policy | 🟡 | 6 model inti tercakup; PMB, Presensi, Feeder, Bridge menyusul bersama modulnya |
| Artisan command | ⬜ | Backfill, feeder-sync, prune log |
| Feature test per service | ⬜ | Mengikuti servicenya |
| `lang/id` | 🟡 | auth, validation, pagination sudah; string modul menyusul |
| Perintah pembangkit data skala | ⬜ | Fixture 5.000 mahasiswa untuk [`KAPASITAS.md`](KAPASITAS.md) dibuat sekali pakai; jadikan perintah artisan agar angkanya dapat diverifikasi ulang |
| Katalog KRS pada 1.000 kelas | ⬜ | Diukur pada 21 kelas; kampus 5.000 mahasiswa butuh ±1.000 kelas. Kemungkinan perlu paginasi — lihat §"Batas Uji" di KAPASITAS.md |
| Uji beban bersamaan | ⬜ | Puncak beban SIAKAD adalah jam pembukaan KRS; belum diuji sama sekali |
| CSP masih `'unsafe-eval'` | 🟡 | Alpine mengevaluasi ekspresi `x-` lewat konstruktor `Function`; menghapusnya menuntut Alpine CSP build lebih dulu |
| Font dari Google Fonts | ⬜ | Setiap kunjungan mengungkap IP pengunjung ke pihak ketiga; meng-hosting sendiri menghapus sekaligus pengecualian CSP dan pengungkapannya |

---

## Isi Basis Data Saat Ini

`open_academic` · MariaDB 10.4 · utf8mb4 · 47 tabel domain, 78 foreign key.

| Tabel | Baris | | Tabel | Baris |
|---|---|---|---|---|
| `mahasiswa` | 50 | | `krs` | 116 |
| `dosen` | 7 | | `krs_detail` | 605 |
| `staff` | 4 | | `nilai` | 357 |
| `prodi` | 2 | | `nilai_komponen` | 1.071 |
| `mata_kuliah` | 42 | | `presensi` | 1.374 |
| `mata_kuliah_prasyarat` | 21 | | `pertemuan_kelas` | 336 |
| `kelas_kuliah` | 63 | | `tagihan` | 49 |
| `kelas_dosen` | 78 | | `pembayaran` | 43 |
| `jadwal_kuliah` | 63 | | `pmb_pendaftar` | 110 |
| `komponen_nilai` | 189 | | `aktivitas_mahasiswa` | 8 |
| `tahun_akademik` | 3 | | `penugasan_dosen` | 5 |

Tabel yang sengaja masih kosong karena modulnya belum digarap:
`alumni`, `yudisium`, `wisuda_*`, `cuti_mahasiswa`, `feeder_sync_logs`,
`feeder_refs`, `bridge_webhook_deliveries`, `log_aktivitas`.

Muat ulang kapan saja dengan `php artisan migrate:fresh --seed` (±35 detik).

---

## Yang Bisa Diperiksa Sekarang

Jalankan `php artisan serve`, buka `http://127.0.0.1:8000`.
Kata sandi seluruh akun demo: `password`.

| Alur | Akun | Yang layak dicermati |
|---|---|---|
| Landing publik | — | Positioning, seksi ekosistem, responsif |
| Masuk | ketiga akun | Satu formulir, guard dikenali dari identitas |
| Dasbor Mahasiswa | `mahasiswa1@demo.test` | Jadwal hari ini, status KRS, peringatan tagihan |
| KHS & Transkrip | idem | **Cek silang perhitungan IPS/IPK terhadap tabel nilai** |
| Tagihan | idem | Rincian dari matriks tarif, riwayat pembayaran |
| Dasbor Dosen | `dosen1@demo.test` | Kelas diampu, badge IKU 7, antrean persetujuan |
| Dasbor Institusi | `admin@demo.test` | Sebaran prodi, funnel PMB, status integrasi |
| Data Mahasiswa | idem | Filter, dan **kolom "Siap PDDIKTI"** — satu baris sengaja dibuat gagal |
| Mobile | ketiganya | Bottom nav + sheet "Lainnya" pada 375px |

**Tiga hal yang paling perlu dikonfirmasi sebelum Fase 1 dimulai:**

1. **Skala nilai huruf** di `config/academic.php` — A/AB/B/BC/C/D/E dengan
   ambang 80/75/70/65/55/45. Tiap kampus berbeda; kalau salah, seluruh
   transkrip salah.
2. **Matriks batas SKS** — IPS ≥3,00 → 24 SKS, dan seterusnya.
3. **Ambang pembayaran KRS** — saat ini 50% dari total tagihan.

Ketiganya konfigurasi, bukan kode, jadi murah diubah sekarang dan mahal
diubah setelah ada data produksi.
