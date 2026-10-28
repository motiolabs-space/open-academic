# DECISIONS.md — ADR-lite

> Satu entri per keputusan non-obvious. Format: Konteks → Keputusan → Konsekuensi.
> Append-only: entri lama tidak diedit, cukup ditambahkan entri baru yang menggantikan.

---

## ADR-001 — Greenfield, bukan fork Neco Siakad (2026-08-06)

- **Konteks.** Dokumen perencanaan menetapkan Neco Siakad v2 sebagai basis kode.
  Neco berjalan di Laravel 12 dengan template Argon/Mazer dan business logic tipis
  di controller; dokumen yang sama mensyaratkan ekstraksi penuh ke Service + DTO.
- **Keputusan.** Membangun dari `laravel new` dan memakai Neco hanya sebagai
  referensi domain (cakupan PMB, matriks tarif, integrasi Midtrans).
- **Konsekuensi.** Kehilangan modul PMB dan keuangan yang sudah jadi — keduanya
  ditulis ulang. Ditukar dengan skema yang konsisten sejak awal (UUID, softDeletes,
  FK `restrictOnDelete`, enum PDDIKTI) dan tanpa utang teknis frontend. Atribusi MIT
  Neco tidak lagi wajib karena tidak ada kode yang disalin.

## ADR-002 — Laravel 12, bukan 13 (2026-08-06)

- **Konteks.** Permintaan awal menyebut Laravel 13, tetapi Laravel 13 mensyaratkan
  PHP ≥ 8.3 sedangkan lingkungan pengembangan berjalan di PHP 8.2.12 (XAMPP).
- **Keputusan.** Menggunakan Laravel 12 (`^12.0`, PHP `^8.2`).
- **Konsekuensi.** Jalur upgrade tetap terbuka: tidak ada API khusus Laravel 12 yang
  dipakai, dan tidak ada sintaks PHP 8.3+. Menaikkan ke Laravel 13 nantinya cukup
  mengubah `composer.json` setelah runtime PHP 8.3+ tersedia.

## ADR-003 — Tiga tabel autentikasi, bukan satu tabel `users` (2026-08-06)

- **Konteks.** Staf, dosen, dan mahasiswa adalah entitas domain yang berbeda dengan
  atribut yang hampir tidak beririsan (NIP vs NIDN vs NIM, homebase prodi, dosen wali).
- **Keputusan.** `staff`, `dosen`, dan `mahasiswa` masing-masing authenticatable pada
  guard-nya sendiri. Tabel `users` bawaan Laravel dihapus; migrasi bawaan disisakan
  hanya untuk `sessions` dan `password_reset_tokens`.
- **Konsekuensi.** Kolom autentikasi terduplikasi di tiga tabel. Ditukar dengan isolasi
  otorisasi: sesi dosen tidak mungkin tertukar dengan sesi mahasiswa, dan bug otorisasi
  di satu portal tidak bocor ke portal lain. Spatie Permission memakai guard yang sama
  sehingga peran ikut terpisah per portal.

## ADR-004 — Satu formulir masuk untuk tiga portal (2026-08-06)

- **Konteks.** Tiga guard berarti tiga kemungkinan halaman masuk, atau satu halaman
  dengan pilihan peran.
- **Keputusan.** Satu kolom identitas. `LoginController` mencoba guard secara berurutan
  (mahasiswa → dosen → staf) terhadap kolom NIM/NIDN/NIP/email.
- **Konsekuensi.** Sampai tiga percobaan basis data untuk sekali masuk; mahasiswa
  didahulukan karena populasinya terbesar sehingga mayoritas selesai di percobaan
  pertama. Pengguna tidak perlu tahu apa itu auth guard. Throttling per identitas + IP
  agar satu akun yang diserang tidak mengunci seluruh kampus di balik NAT bersama.

## ADR-005 — Tidak ada tabel `khs` tersendiri (2026-08-06)

- **Konteks.** Dokumen menyebut KRS/KHS sebagai satu modul. KHS berisi ips/ipk/sks per
  semester — data yang persis sama dengan payload `AktivitasKuliahMahasiswa` PDDIKTI.
- **Keputusan.** `status_mahasiswa` menjadi catatan per semester sekaligus header KHS
  (`is_final`, `finalized_at`). KHS di layar diturunkan dari baris ini + nilai final.
- **Konsekuensi.** Tidak ada duplikasi ips/ipk yang bisa berbeda antar tabel, dan mapper
  Feeder membaca satu sumber. Konsekuensinya nama tabel tidak langsung menyiratkan
  fungsinya sebagai KHS — didokumentasikan di docblock model.

## ADR-006 — Satu tabel `feeder_refs`, bukan `feeder_ref_*` per entitas (2026-08-06)

- **Konteks.** Dokumen menyarankan tabel terpisah per jenis referensi Feeder
  (agama, wilayah, jenjang, kode status).
- **Keputusan.** Satu tabel `feeder_refs` dengan diskriminator `ref_type`.
- **Konsekuensi.** Menambah jenis referensi baru tidak butuh migrasi — cukup entri di
  `config('feeder.references')`. Ditukar dengan hilangnya tipe kolom spesifik per
  referensi; detail tak seragam disimpan di kolom `payload` JSON.

## ADR-007 — `$guarded`, bukan `$fillable`, pada model (2026-08-06)

- **Konteks.** Empat puluh model dengan daftar `$fillable` panjang sulit dirawat dan
  mudah tertinggal saat kolom bertambah.
- **Keputusan.** Semua model memakai `protected $guarded = ['id', 'uuid', 'created_at',
  'updated_at']`.
- **Konsekuensi.** Perlindungan mass-assignment bergeser dari model ke lapisan tulis:
  controller tidak pernah meneruskan `request()->all()`, validasi selalu lewat
  FormRequest, dan mutasi domain lewat Service. Aturan ini wajib ditegakkan pada review
  PR — melanggarnya membuka celah mass assignment.

## ADR-008 — Jejak audit di-antre, bukan sinkron (2026-08-06)

- **Konteks.** Nilai, persetujuan KRS, dan perubahan status wajib punya jejak audit,
  tetapi menulis jejak di dalam request menambah biaya pada setiap penyimpanan.
- **Keputusan.** `HasLogAktivitas` mengirim `RecordActivityLogJob` ke antrean `audit`
  dengan payload skalar (tanpa serialisasi model).
- **Konsekuensi.** Baris audit tetap bisa ditulis meski subjeknya sudah dihapus.
  Namun jejak baru muncul setelah worker antrean berjalan — instalasi produksi **wajib**
  menjalankan `queue:work`, jika tidak jejak audit tidak pernah tertulis. Seeder demo
  mengalihkan antrean ke driver `null` agar data fixture tidak menghasilkan puluhan ribu
  baris audit.

## ADR-009 — Zona waktu aplikasi Asia/Jakarta (2026-08-06)

- **Konteks.** Kalender akademik bersifat lokal: jendela KRS, jam kuliah, dan batas
  presensi semuanya berarti waktu kampus. Default Laravel adalah UTC.
- **Keputusan.** `config/app.php` memakai `env('APP_TIMEZONE', 'Asia/Jakarta')`.
- **Konsekuensi.** Timestamp tersimpan mengikuti zona ini. Kampus di WITA/WIT cukup
  mengubah `APP_TIMEZONE`; instalasi multi-zona bukan cakupan v1.

## ADR-010 — Kalender semester aktif dijangkarkan ke hari ini pada data demo (2026-08-06)

- **Konteks.** Kalender akademik nyata dimulai September; instalasi demo di bulan lain
  membuka aplikasi dengan grid presensi kosong dan jendela KRS yang belum dibuka.
- **Keputusan.** `TahunAkademikFactory::berjalan()` menjangkarkan tanggal semester aktif
  ke tanggal hari ini (mulai 8 pekan lalu), hanya dipakai `MasterAkademikSeeder`.
- **Konsekuensi.** Kode semester tetap benar (`20261`), tetapi tanggal kalender demo
  tidak sesuai kalender akademik nyata. Instalasi produksi tidak pernah memakai factory ini.

## ADR-011 — Kajian pembanding disimpan lokal; dokumen publik hanya memuat kesimpulan (2026-10-22)

- **Konteks.** Repo kini publik. Peta jalan lanjutan disusun dari daftar fitur
  produk komersial dan bahan sosialisasi platform kementerian. Menyebut nama
  produk pembanding di dokumen publik tidak menambah nilai bagi pembaca dan
  mengikat dokumen ini pada pihak yang tidak punya suara di dalamnya.
- **Keputusan.** Catatan sumber (URL, tabel fitur mentah, teks slide) hidup di
  `docs/lokal/`, dikecualikan lewat `.git/info/exclude` — bukan `.gitignore`,
  supaya pengecualiannya sendiri tidak ikut ter-commit. `ROADMAP.md` memuat
  kesimpulannya dengan istilah netral: "pembanding komersial", "aplikasi
  pelaporan PDDIKTI", "SISTER".
- **Konsekuensi.** Kontributor di mesin lain tidak melihat folder itu dan tidak
  tahu ia ada kecuali dari ADR ini. Nama produk yang pernah tertulis sebelum
  tanggal ini tetap ada di riwayat Git; riwayat tidak ditulis ulang.

## ADR-012 — Klien SISTER dicabut; jalur ke SISTER adalah Feeder (2026-10-22)

- **Konteks.** Sejak 14 Agustus roadmap menyimpan "Klien SISTER — menunggu
  kredensial". SISTER kini terpusat; data pengajaran, pembimbingan, dan
  pengujian dosen masuk ke sana dari PDDIKTI, bukan dari kampus. Tidak ada
  endpoint per kampus untuk dituju.
- **Keputusan.** Baris itu dicabut. Yang dikerjakan untuk SISTER adalah ekspor
  berkas (sudah ada) dan pengiriman entitas Feeder yang belum pernah dikirim:
  dosen pengajar kelas, komponen evaluasi, Lulus/DO, aktivitas MBKM beserta
  pembimbing dan pengujinya (ROADMAP #19–22).
- **Konsekuensi.** BKD unsur pendidikan yang dihitung di sini baru "sampai" ke
  SISTER setelah #19 dan #22 dikirim lewat Feeder. Portofolio yang SISTER ambil
  lewat klaim manual tetap diekspor sebagai berkas; tidak ada janji sinkron dua
  arah dengan SISTER.

## ADR-013 — Nomor ijazah dicatat dari PIN; bawaan tidak membangkitkan (2026-10-22)

- **Konteks.** `wisuda_peserta.nomor_ijazah` dibangkitkan dari pola kampus sejak
  Fase 1. Penomoran ijazah nasional (PIN) wajib sejak 21 Desember 2020 dan
  bekerja terbalik: kampus mereservasi nomor di portal kementerian, nomor itu
  yang dicetak, dan hanya nomor itu yang terverifikasi lewat SIVIL. Ijazah
  bernomor lokal tercetak rapi dan gagal saat diperiksa pemberi kerja
  bertahun-tahun kemudian — kegagalan yang sunyi di kampus dan mahal di lulusan.
- **Keputusan.** `config('academic.ijazah.sumber')` dengan bawaan `pin`. Di mode
  itu `WisudaService::terbitkanNomorIjazah()` melempar `AturanAkademikException`
  yang menyebut jalurnya, dan nomor dicatat lewat `catatNomorIjazah()` dari
  tempelan hasil reservasi (satu lulusan per baris; tiap baris dinilai sendiri;
  nomor yang sudah tercatat tidak pernah ditimpa; satu nomor tidak boleh milik
  dua lulusan). Mode `lokal` mempertahankan pembangkit untuk institusi di luar
  skema nasional.
- **Konsekuensi.** Instalasi yang sudah berjalan dengan nomor lokal **tidak
  berubah** — nomor yang ada tetap tercatat — tetapi pembangkitnya berhenti
  sampai `ACADEMIC_IJAZAH_SUMBER=lokal` disetel dengan sadar. Bawaan dipilih
  yang tidak dapat menghasilkan dokumen tak terverifikasi karena kelalaian.
  Verifikasi publik ijazah adalah SIVIL; QR lokal tetap untuk surat dan SKPI.

## ADR-014 — Payment gateway opsional; transfer manual adalah jalur utama (2026-10-24)

- **Konteks.** Sejak perbandingan dengan pembanding komersial (16 Agustus 2026)
  ROADMAP menempatkan adaptor payment gateway sebagai P0, "satu-satunya
  kekalahan nyata", dan mengulanginya di empat tempat. Adaptornya tertahan dua
  bulan pada kredensial merchant. Sementara itu jalur yang ada — tagihan
  diterbitkan, mahasiswa transfer, kasir mencatat (termasuk cicilan), gerbang
  KRS membaca status tagihan — sudah lengkap dan teruji, dan itulah cara kerja
  sebagian besar kampus sasaran.
- **Keputusan.** Pemilik repo menyatakan payment gateway **opsional**.
  Pencatatan manual oleh kasir adalah jalur utama dan cukup untuk rilis v1.0.
  Kontrak `PaymentGatewayInterface` tetap; adaptornya dibangun hanya bila ada
  kampus yang membawa kredensial merchant dan endpoint notifikasi yang dapat
  diverifikasi tanda tangannya. P0 dikosongkan; butirnya turun ke P3 dengan
  tanda opsional.
- **Konsekuensi.** `config/payment.php` tetap tidak menjanjikan gateway sampai
  kodenya ada. Tabel perbandingan menyebutnya "model berbeda", bukan
  "kekalahan". Yang mungkin layak dikerjakan sebagai gantinya, dan lebih murah:
  konfirmasi atau unggah bukti transfer oleh mahasiswa, agar kasir tidak
  mencocokkan mutasi rekening tanpa petunjuk. Belum diputuskan.

## ADR-015 — Denda keterlambatan diakrualkan harian dan tidak memperketat gerbang KRS (2026-10-24)

- **Konteks.** Roadmap #15. Payment gateway diputuskan opsional (24 Okt 2026),
  jadi denda harus bekerja penuh pada pembayaran manual yang dicatat kasir.
  Dua jalan yang tampak setara: menghitung ulang denda dari nol tiap malam
  (rumus murni atas tanggal dan sisa), atau mengakrualkan periode baru di atas
  yang sudah tercatat. Dan satu pertanyaan yang jawabannya tidak boleh
  tersirat: apakah denda ikut dihitung pada ambang pembayaran KRS.
- **Keputusan.** Denda adalah satu baris `tagihan_item` berjenis `denda`,
  **diakrualkan**: tiap jalan `openacademic:hitung-denda` hanya menagih periode
  yang dimulai sesudah `tagihan.denda_dihitung_sampai`, pada sisa pokok hari
  itu, dan watermark hanya bergerak maju. Persen dihitung `intdiv`, tanpa
  float. `Tagihan::memenuhiSyaratKrs()` mengukur ambang terhadap `pokok()` =
  `total − denda`. Pembebasan adalah baris potongan negatif beralasan yang
  menghentikan akrual, bukan penghapusan baris denda. Bawaan **nonaktif**.
- **Konsekuensi.** Pembayaran sebagian tidak menyusutkan denda yang sudah
  berjalan; jalan ganda pada hari yang sama tidak menggandakan; jalan susulan
  sesudah server mati menagih tepat periode yang terlewat. Kampus yang
  menyalakan kebijakan di tengah semester akan menagih tunggakan lama sejak
  jatuh temponya — `--kering` ada untuk melihatnya dulu. Denda belum
  dijurnalkan ke integrasi akuntansi.


## ADR-016 — Skala nilai dan periode perkuliahan per prodi diturunkan ke tabel, tidak dipetakan dari konfigurasi (2026-10-24)

- **Konteks.** `InsertSkalaNilaiProdi` dan `InsertPeriodePerkuliahan` adalah
  baris per prodi (dan per huruf / per semester) di PDDIKTI, sedangkan kampus
  menyimpan satu skala di `config/academic.php` dan satu kalender di
  `tahun_akademik`. Mesin sinkron bekerja per baris model Eloquent: buku besar
  menyimpan `local_type`/`local_id`, ulang-yang-gagal memuatnya kembali, dan
  hapus-yang-tiada mencocokkannya — tidak ada tempat untuk "baris" yang hanya
  ada di konfigurasi.
- **Keputusan.** Dua tabel, `skala_nilai_prodi` dan `periode_perkuliahan`,
  **diturunkan** oleh `SkalaPeriodeProdiService` untuk prodi yang belum punya
  baris: dari skala kampus (batas atas huruf = batas bawah huruf di atasnya;
  berlaku sejak semester pertama kampus) dan dari kalender semester plus angka
  PMB semester itu. Penurunan berjalan saat mapper membaca barisnya (validasi
  atau sinkron) dan di `IntegrasiSeeder`; ia **aditif dan idempoten** — baris
  yang ada tidak pernah disentuh.
- **Konsekuensi.** Skala kampus tetap satu-satunya tempat kebijakan ditulis,
  tetapi prodi yang harus berbeda (mis. skala kesehatan) menyunting barisnya
  sendiri, dan perubahan konfigurasi sesudah penurunan **tidak** merambat —
  itu disengaja, karena skala yang sudah dilaporkan punya tanggal berlaku.
  Hitungan "antre" di monitor membaca tabel, jadi nol sampai penurunan pertama;
  seeder demo menutup celah itu untuk instalasi baru.

## ADR-017 — Kolom yang dikunci PDDIKTI dicatat saat berubah, bukan dikunci di layar (2026-10-24)

- **Konteks.** PDDIKTI membekukan enam kolom identitas setelah biodata
  diterima dan tujuh kolom keluar setelah Lulus/DO diterima. Koreksinya adalah
  PDM/PJK: sebagian lewat web service, sisanya lewat PDDIKTI admin dengan surat.
  Mengunci kolomnya di layar hanya menghentikan satu jalur perubahan — impor,
  perintah artisan, dan service lain tetap bisa mengubahnya — dan sinkron
  berikutnya akan mengirim ulang biodata yang pasti ditolak, atau lebih buruk,
  diterima build Feeder lama dan diam-diam berbeda dari keputusan PDDIKTI admin.
- **Keputusan.** `KunciFeederObserver` pada setiap model pemegang kolom itu
  mencatat `feeder_change_requests` (satu per kolom; permintaan terbuka
  disunting, bukan digandakan; nilai yang dikembalikan mencabutnya). Katalog
  kolom dan jalurnya di `KolomTerkunci`. Validator pra-kirim **menolak**
  biodata dan Lulus/DO selama ada permintaan jalur PDDIKTI admin yang terbuka.
  Hanya kolom riwayat pendidikan (NIM, periode masuk) dikirim lewat
  `UpdateRiwayatPendidikanMahasiswa`; penolakannya disimpan pada permintaan.
  "Selesai" adalah pernyataan operator sesudah memeriksa PDDIKTI admin —
  web service tidak menyediakan cara membacanya.
- **Konsekuensi.** Perubahan kolom terkunci tidak pernah diam-diam: ia tampak
  di konsol dengan jalur dan statusnya, dan biodata tidak dikirim ulang sampai
  operator menyatakan PDDIKTI sudah memegang nilai barunya. Sesudah itu
  sinkron berikutnya mengirim pembaruan yang isinya sama dengan yang sudah
  dipegang PDDIKTI — dicatat, bukan disembunyikan. Surat permohonannya belum
  dicetak dari modul surat (#27b).

## ADR-018 — Helpdesk adalah tiket administratif, status bergerak lewat tabel transisi, SLA hanya menandai (2026-10-24)

- **Konteks.** Roadmap P3 #18 meminta helpdesk. Dua godaan rancangan: menjadikannya
  utas percakapan umum (yang bergeser ke wilayah Open Campus), dan membiarkan
  penjadwal menutup atau mengeskalasi tiket yang lewat tenggat (yang mengambil
  keputusan atas nama unit). Keduanya terasa "lengkap" dan keduanya salah.
- **Keputusan.** Tiket selalu dari satu pemohon (mahasiswa/dosen, polimorfik) ke
  satu unit kerja; tidak ada visibilitas antarpemohon dan tidak ada lingkaran
  "dosen wali" di policy. Status hanya bergerak sepanjang `StatusTiket::tujuanSah()`,
  dijaga di service, bukan di layar; tiket tertutup tidak menerima apa pun.
  `openacademic:helpdesk-sla` hanya menstempel `lewat_sla_at` pada tiket yang
  menunggu kampus — tidak menutup, tidak mengalihkan, tidak memberi tahu pemohon.
  Notifikasi helpdesk kategori opsional. Seluruh teks UI modul lewat `lang/id/`.
- **Konsekuensi.** Eskalasi otomatis, pengaduan etik, dan percakapan komunitas
  tidak akan dibangun di sini; kampus yang memerlukannya menyambungkannya lewat
  Bridge. Tenggat dibekukan saat tiket dibuat, jadi mengubah SLA kategori tidak
  mengubah antrean yang sudah berjalan. Lampiran mengikuti aturan modul berkas
  (disk privat, policy tiket) dan lampiran catatan internal hanya untuk staf.

## ADR-019 — Federasi IdP: klien OIDC minimal, staf saja, TOTP tetap ditantang (2026-10-24)

- **Konteks.** Roadmap #16 menyisakan "federasi IdP eksternal" dan `SSO.md`
  menahannya pada satu keputusan: identitas dari Google/Entra/Keycloak dipetakan
  ke tabel yang mana. Tiga tabel identitas (ADR-003) berarti salah petakan bukan
  salah tampil — dosen bisa mendarat di portal mahasiswa. Di sisi teknis, repo
  belum punya klien OIDC; pustaka klien yang lazim membawa katalog penyedia,
  abstraksi sesi, dan pohon dependensi yang tidak ada hubungannya dengan dua
  panggilan HTTP yang dibutuhkan. 2FA staf (22 Agustus 2026) sudah ada dan harus
  punya sikap terhadap sesi yang datang dari IdP.
- **Keputusan.** (1) Klien OIDC ditulis sendiri di `OidcIdentityProvider`:
  discovery, authorization-code, baca klaim `id_token`; `iss`/`aud`/`azp`/`exp`/
  `nonce` divalidasi di `ValidatorKlaim` yang **sama** dipakai
  `FakeIdentityProvider`. Tanda tangan JWS tidak diverifikasi terhadap JWKS —
  token diterima langsung dari token endpoint lewat TLS dengan client secret
  (OIDC Core §3.1.3.7.6), maka issuer non-HTTPS ditolak sebelum jaringan
  disentuh. (2) Pemetaan: hanya guard di `IDP_GUARDS` (bawaan `staff`; `dosen`
  dapat ditambah; mahasiswa tidak), `idp_subject` yang sudah tertaut menang,
  surel hanya untuk penautan pertama dan hanya bila `email_verified`, cocok di
  dua tabel ditolak sebagai ambigu, tidak ada provisioning bawaan
  (`IDP_PROVISION=true` membuat staf **tanpa peran**). (3) `IDP_SATISFIES_2FA`
  bawaan `false`: staf ber-TOTP tetap masuk ke layar tantangan yang sama dengan
  jalur kata sandi, dengan bentuk sesi `dua_faktor` yang sama.
- **Konsekuensi.** Tidak ada dependensi Composer baru; yang harus diaudit satu
  kelas ±150 baris. Kampus yang menginginkan verifikasi JWKS mengikat
  `IdentityProviderInterface`-nya sendiri — titik sambungnya sudah ada. Akun yang
  surelnya berganti tetap milik orang yang sama karena subject-nya yang dipegang;
  akun yang alamat bekasnya diberikan ke orang lain ditolak sebagai "tertaut ke
  identitas lain", bukan diserahkan. Bawaan 2FA memilih gesekan ganda daripada
  mempercayai kebijakan MFA IdP yang tidak dapat diperiksa dari sini; kampus
  yang MFA-nya memang dipaksakan di IdP menyalakannya dengan satu variabel.
  Entra ID memerlukan `IDP_REQUIRE_VERIFIED_EMAIL=false` yang disetel sadar,
  karena ia tidak mengirim `email_verified` untuk anggota tenant.

## ADR-020 — Adopsi mahasiswa PDDIKTI: NIM menemukan, NIK menegaskan, keraguan diputuskan orang (2026-10-26)

- **Konteks.** Roadmap #44. Kampus yang pindah dari SIAKAD lain sudah punya
  seluruh mahasiswanya di PDDIKTI, sedangkan mapper biodata hanya mengenal
  `InsertBiodataMahasiswa` dan rekonsiliasi sengaja tidak membaca biodata
  (satu-satunya kunci uniknya NIK, yang tidak boleh tersalin ke tabel selisih).
  Sinkronisasi pertama akan mendaftarkan ulang semua orang itu: ditolak karena
  NIK, atau lebih buruk, menjadi orang kedua di PDDIKTI. Penautan yang salah
  sama berbahayanya — nilai, SKS, dan kelak nomor ijazah seorang mahasiswa
  terlapor atas nama orang lain, dan tidak ada yang di hilir yang akan
  menyadarinya.
- **Keputusan.** `FeederAdopsi` membaca `GetListRiwayatPendidikanMahasiswa`
  (NIM, `id_registrasi_mahasiswa`) dan `GetListMahasiswa` (NIK) per prodi +
  periode masuk, digabung pada `id_mahasiswa`; nama aksi dan filternya di
  `config/feeder.php` → `adopt`, dan penolakan Feeder menghentikan proses
  seperti pada rekonsiliasi. Pencocokan: (1) kandidat = baris yang NIM-nya
  sama ∪ baris yang NIK-nya sama; (2) satu kandidat, NIM dan NIK sama →
  **diadopsi**, apa pun namanya (NIK mengalahkan nama); (3) NIK kosong di salah
  satu sisi → nama yang dinormalkan menggantikannya dan harus sama; (4) dua
  kandidat, NIM sama tetapi NIK beda, NIK sama tetapi NIM beda, atau mahasiswa
  yang sudah memegang id Feeder lain → **ambigu**, tidak pernah diadopsi
  otomatis. NIK-sama-NIM-beda sengaja ambigu meski orangnya hampir pasti sama:
  mengadopsinya akan meninggalkan NIM lama di PDDIKTI di balik buku besar yang
  menyatakan tidak ada yang perlu dikirim. Yang diadopsi mendapat `feeder_id`
  dan `feeder_registrasi_id` serta satu baris `feeder_sync_logs` per entitas
  (`direction = adopt`, status `success`, hash muatan lokal saat ini), **hanya
  bila** buku besar belum pernah mencatat sukses untuknya — hash lama dari
  dorongan sungguhan tidak ditimpa. Laporan di `feeder_adoptions` (empat
  keadaan otomatis, diganti setiap kali dijalankan) tanpa NIK, hanya
  `nik_cocok`. Yang ambigu diputuskan orang di `/admin/feeder/adopsi`:
  *tautkan* ke salah satu kandidat atau *pisahkan* ("bukan orang yang sama"),
  keduanya wajib alasan, tercatat di Log Aktivitas, dan bertahan saat adopsi
  diulang. Validator pra-kirim menolak biodata mahasiswa yang masih ambigu
  (`kandidat_feeder_belum_diadopsi`). Klaim mahasiswa lampau
  (`GetListDaftarLampau` / `KlaimMahasiswaLampau`) dipisah ke #44b.
- **Konsekuensi.** Tidak ada satu pun `Insert`/`Update` yang terkirim saat
  adopsi; dorongan berikutnya mencatat `Skipped` bagi yang teradopsi. Harganya
  di buku besar: selisih isi biodata antara laporan SIAKAD lama
  dan data lokal hari ini (alamat, telepon) tidak terkirim sampai data lokalnya
  berubah, karena hash yang dicatat adalah hash muatan lokal — nama yang
  berbeda ditampilkan di layar adopsi, kolom lain tidak dibandingkan. Baris
  adopsi dihitung sebagai "Terkirim" di kartu entitas dan ikut dipertimbangkan
  "Hapus yang tiada": catatan PDDIKTI milik kampus, bukan milik aplikasi yang
  dulu mengirimnya. Validator hanya tahu kandidat yang sudah pernah dibaca;
  kampus yang lupa menjalankan adopsi tidak diperingatkan. Nama aksi dan field
  Feeder mengikuti pemakaian komunitas dan perlu diverifikasi terhadap
  dokumentasi build Feeder kampus.

## ADR-021 — SKS diakui pindahan diturunkan dari konversi; semester masuk dan batas KRS pertama membacanya (2026-10-26)

- **Konteks.** Sejak #25 `mahasiswa` punya `jenis_daftar`, `pt_asal`,
  `prodi_asal`, dan `sks_diakui`, dan Feeder menolak riwayat pendidikan pindahan
  tanpa keduanya. Kolom itu hanya terisi lewat seeder. Pengakuan kredit sendiri
  sudah ada di `konversi_kredit` (G5) dengan batas persentase. Bila `sks_diakui`
  diketik di layar intake, dua angka yang harus sama — total di riwayat
  pendidikan dan jumlah baris transfer kredit yang dikirim di sampingnya —
  dipelihara oleh dua orang di dua layar. Mahasiswa pindahan juga masuk dengan
  semester tempuh 1 dan batas SKS bawaan, seolah tidak membawa apa pun, dan
  sebagian kampus menghitung masa studi sejak masuk di PT asal.
- **Keputusan.** (1) `sks_diakui` **bukan input**: `KonversiService` menulisnya
  sebagai jumlah konversi yang disetujui pada setiap persetujuan dan pencabutan,
  hanya untuk jenis daftar yang `butuhAsal()`, dan `null` selama jumlahnya nol —
  supaya validator tetap berkata "belum ada SKS diakui", bukan meloloskan
  pindahan nol SKS. (2) Semester masuk = 1 + floor(`sks_diakui` /
  `academic.pindahan.sks_per_semester`, bawaan 20), dihitung saat dibaca di
  `BatasSksCalculator::semesterKe()` hanya bila belum ada baris status sama
  sekali; tidak disimpan. (3) Batas SKS KRS selama belum ada semester final di
  sini diambil dari rata-rata berbobot nilai konversi yang disetujui dan bernilai,
  lewat matriks IPS yang sama, **terlepas dari** `konversi.hitung_ipk` — setelan
  itu tentang apa yang diukur ijazah, bukan beban semester pertama. Konversi tanpa
  nilai (RPL) tidak berkata apa pun, jadi bawaan tetap berlaku. Angkanya tercatat
  sebagai `ips_acuan` KRS seperti acuan lainnya. (4) Kolom baru
  `term_masuk_asal`; masa studi menghitung semester di asal hanya bila
  `academic.pindahan.masa_studi_dari_asal` dinyalakan (bawaan mati), dan hanya
  untuk batas masa studi — titik evaluasi bertingkat tetap pada studi di sini.
  (5) Koreksi asal ditolak setelah `feeder_registrasi_id` terisi; perubahan sejak
  itu adalah pembaruan riwayat di Feeder. (6) Saran nama PT dari referensi
  `GetAllPT` (tipe `perguruan_tinggi` di `feeder_refs`) ditambah nama yang sudah
  pernah dimasukkan; prodi asal hanya dari intake sebelumnya, karena PDDIKTI
  tidak punya pengenal prodi PT lain dan payload membawanya sebagai teks.
- **Konsekuensi.** Satu sumber angka untuk layar, KRS, semester tempuh, dan
  payload Feeder. Riwayat yang sudah terkirim lalu konversinya berubah akan
  berbeda dari PDDIKTI sampai diperbarui — hal yang sama berlaku untuk setiap
  baris transfer kredit, dan layar menampilkan isu validator yang sama dengan
  sinkronisasi. Pendaftar dari gelombang transfer/RPL diarahkan ke intake, bukan
  daftar ulang biasa; `PmbService::daftarUlang()` kini juga mengisi `term_masuk`,
  yang selama ini kosong sehingga setiap mahasiswa hasil PMB gagal pemeriksaan
  periode masuk.

## ADR-022 — Syarat wisuda satu sumber; "Menunggu Uji Kompetensi" berkode lokal dan meluluskan pada tanggal ujian (2026-10-26)

- **Konteks.** Roadmap #30 meminta mahasiswa mendaftar wisuda sendiri. Satu-satunya
  syarat hari itu — yudisium ditetapkan — tertanam di `WisudaService::daftarkan()`;
  layar mahasiswa yang menyalakan tombol "Daftar" butuh salinan kedua, dan staf
  dapat mendaftarkan lulusan yang menunggak. Roadmap #31: prodi kesehatan tidak
  meluluskan siapa pun sebelum lulus uji kompetensi, dan PDDIKTI mengenal status
  aktivitas kuliah tersendiri untuk masa tunggu itu. `StudentStatus` memakai kode
  yang meniru PDDIKTI dan `AktivitasKuliahMapper` jatuh balik ke kode lokal bila
  pemetaan kosong; kode PDDIKTI untuk status baru ini tidak dapat diverifikasi
  dari sini.
- **Keputusan.** (1) `SyaratWisudaService` satu-satunya daftar periksa wisuda,
  dibaca layar mahasiswa, antrean staf, dan `WisudaService` sebelum menulis — staf
  kini terikat syarat yang sama. Wajib: yudisium ditetapkan, bebas tunggakan
  (`Tagihan::tunggakanPerMahasiswa()`, definisi yang juga dibaca yudisium),
  lulus ukom bila prodinya wajib. **Denda yang belum dibebaskan dihitung
  tunggakan**; yang dibebaskan tidak, karena baris pembebasannya sudah
  mengurangi `total`. Kesiapan PIN tampil sebagai informasi, tidak menghalangi:
  seluruh butirnya diperbaiki kampus di Feeder, bukan oleh mahasiswa. Tidak ada
  baris bebas pinjaman — modul perpustakaan tidak ada, dan baris yang selalu
  lolos berbohong. Pembatalan sendiri hanya selama periode dibuka. (2)
  `StudentStatus::MenungguUkom = 'U'` adalah **kode lokal** (`kodeLokal()`):
  mapper tidak menjatuhkannya balik ke dirinya sendiri, validator AKM menolak
  baris berstatus itu selama grup `status_mahasiswa` belum memetakannya. (3)
  `prodi.wajib_ukom` (bawaan `false`). `YudisiumService::tetapkan()` pada prodi
  itu menetapkan SK/IPK/predikat tetapi menaruh mahasiswa di MenungguUkom,
  `tanggal_lulus` kosong, tanpa alumni, tanpa `student.graduated`, tanpa SKPI.
  (4) Hasil ukom adalah peristiwa per sitting di tabel `uji_kompetensi`, yang
  gagal ikut tercatat. Yang lulus meluluskan lewat
  `YudisiumService::lulusSetelahUjiKompetensi()` pada **tanggal ujian, atau
  tanggal yudisium bila ujian lebih dulu**; pengumumannya (webhook, SKPI,
  notifikasi) menunggu commit terluar. Semester hasil itu menjadi periode keluar
  Lulus/DO. (5) Semester tunggu ditulis `UjiKompetensiService::catatSemesterMenunggu()`
  saat penutupan semester: catatan beku berstatus `U`, SKS dan IPS nol, IPK dan
  SKS kumulatif dibawa dari semester beku terakhir. Evaluasi studi melewati
  mahasiswa yang menunggu dan tidak menghitung semester `U` sebagai semester
  tempuh (`StudentStatus::tidakDihitungMasaStudi()`). (6) Validator Lulus/DO
  menolak **Lulus** pada prodi wajib ukom tanpa hasil yang lulus; DO dan
  mengundurkan diri tidak disyaratkan ukom.
- **Konsekuensi.** Kampus yang memakai status ini harus mengisi pemetaannya dari
  `GetStatusMahasiswa` sebelum sinkron AKM pertama; seeder demo memetakannya ke
  `U` hanya untuk kampus demo. Lulusan prodi wajib ukom tidak dapat mendaftar
  wisuda dan tidak siap PIN sebelum lulus ukom — sesuai aturan ijazah, tetapi
  kampus yang mewisuda sebelum pengumuman ukom harus mengubah syaratnya di satu
  tempat itu. Mahasiswa yang menunggu tidak pernah memicu peringatan masa studi;
  batas waktu ulang ujian, bila kampus punya, belum dimodelkan. Layar Yudisium
  dan Wisuda menampilkan tanggal lulus mereka "—" sampai hasil yang lulus dicatat.

## ADR-023 — CI menguji PHP 8.2, 8.4, dan 8.5; produksi berjalan di 8.4/8.5 (2026-10-26)

- **Konteks.** `composer.json` mengizinkan `^8.2` dan CI hanya menguji 8.2,
  padahal instalasi produksi berjalan di PHP 8.4 atau 8.5. Tes yang hijau di
  CI tidak membuktikan apa pun tentang versi tempat kode benar-benar dijalankan;
  perilaku yang berubah atau usang di 8.4/8.5 baru akan tampak di server. Celah
  inti PHP 21 Oktober 2026 sekaligus memaksa XAMPP pengembang naik dari 8.2.12.
- **Keputusan.** Job CI menjadi matriks `['8.2', '8.4', '8.5']` dengan
  `fail-fast: false`. Pest berjalan di ketiganya; Pint dan `npm run build` tidak
  bergantung pada versi PHP dan hanya berjalan di 8.4. Batas bawah `^8.2` tetap,
  supaya kampus di shared hosting yang masih di 8.2 tidak terkunci keluar.
  Lingkungan pengembang disarankan mengikuti produksi (8.4).
- **Konsekuensi.** Waktu CI kira-kira tiga kali lipat untuk langkah tes, berjalan
  paralel. Begitu dukungan keamanan PHP 8.2 berakhir (31 Desember 2026), batas
  bawah layak dinaikkan ke `^8.4` dan 8.2 dikeluarkan dari matriks — keputusan
  terpisah, karena memutus instalasi yang masih di 8.2.

## ADR-024 — PMB daring: akun pendaftar di guard sendiri, biaya formulir di buku besar PMB, hasil terlihat hanya sesudah diumumkan (2026-10-26)

- **Konteks.** Roadmap #32: calon mendaftar sendiri. `pmb_pendaftar` tidak punya
  akun, rute publik PMB hanya melayani berkas, dan seluruh pemberitahuan sistem
  ditujukan kepada mahasiswa, dosen, atau staf. Tiga hal harus diputuskan:
  identitas calon, tempat biaya formulir, dan kapan calon boleh tahu hasilnya.
- **Keputusan.**
  (1) **Guard kelima `pendaftar`** (session) dengan provider tabel baru
  `pmb_akun`, bukan kolom kata sandi di `pmb_pendaftar`: satu akun dapat
  mendaftar di beberapa gelombang, pendaftar yang diinput panitia tidak punya
  akun, dan guard terpisah adalah satu-satunya cara agar tidak ada jalur yang
  me-resolve calon menjadi baris yang dikenal portal mahasiswa. `UserRole` tetap
  tiga; akun pendaftar tanpa peran Spatie, tidak dikenal SSO/API, punya halaman
  masuk sendiri (`/pmb/masuk`), broker lupa sandi dengan tabel token sendiri
  (surel calon bisa juga surel mahasiswa), verifikasi surel wajib, dan batas laju
  pada daftar/masuk/kirim surel. Masuk di satu sisi mengeluarkan sesi sisi lain.
  (2) **Biaya formulir tidak ditagih lewat `tagihan`.** Rancangan awal meminta
  "tagihan lewat modul keuangan yang ada", tetapi setiap invarian `tagihan`
  menganggap pemiliknya mahasiswa: `mahasiswa_id` wajib dan unik per semester,
  denda diakrualkan padanya (ADR-015), pengingat jatuh tempo dikirim ke
  mahasiswanya, gerbang KRS dan daftar periksa wisuda membacanya, dan kontak
  outbox akuntansi adalah UUID mahasiswa. Menjadikan `mahasiswa_id` nullable
  akan menuntut penyaring di belasan pembaca dan membuat biaya formulir terkena
  denda. Biaya formulir dibekukan di `pmb_pendaftar.biaya_formulir` dan dibayar ke
  buku besar `pmb_pembayaran`, dicatat kasir (`keuangan.manage` atau
  `pmb.manage`) di layar Biaya Formulir PMB pada menu KEUANGAN, dengan aturan
  `PembayaranService`: tanpa kelebihan bayar, lunas diturunkan dari baris
  berhasil, pembatalan menyimpan barisnya. Bagian keuangan hanya dapat membuka
  bukti bayar, bukan dokumen identitas calon.
  (3) **Status `draf` baru** sebelum `mendaftar`, di luar seluruh hitungan
  pendaftar (corong, LKPS, skala PDDIKTI). Perpindahan status lewat satu metode
  yang menolak sisi di luar `ApplicantStatus::bolehMenujuKe()`.
  (4) **Keputusan dan pengumuman terpisah.** Calon melihat "Seleksi" sampai
  gelombangnya diumumkan (`diumumkan_at`); pengumuman memberi tahu setiap yang
  sudah diputuskan, dan keputusan sesudahnya dikirim saat diambil — setiap hasil
  sekali per calon lewat `Notifier::kirimSekali`. Pemberitahuan PMB memakai
  kategori Kemahasiswaan, bukan kategori baru, karena daftar kategori juga
  layar preferensi mahasiswa dan staf.
  (5) **Nilai seleksi diimpor**, CSV dua kolom wajib, semua-atau-tidak-sama-sekali
  dengan seluruh galat dilaporkan sekaligus. Kelulusan otomatis per ambang prodi
  adalah konfigurasi bawaan mati; ia memutuskan lewat `PmbService` yang sama
  dengan staf dan hanya di prodi yang punya ambang.
  (6) **Daya tampung** per prodi per gelombang ditegakkan pada setiap keputusan
  lulus; **kuota** gelombang menghitung pendaftaran yang diajukan, bukan draf.
- **Konsekuensi.** Pendapatan formulir tidak tampak di layar Tagihan &
  Rekonsiliasi dan belum dijurnalkan ke integrasi akuntansi; bila kampus
  membutuhkannya, outbox akuntansi perlu kontak non-mahasiswa lebih dulu.
  Gelombang lama tetap tertutup bagi pendaftaran daring sampai staf
  menyalakannya. Surel verifikasi menuntut worker antrean berjalan — tanpa itu
  calon berhenti di langkah pertama. Akun pendaftar dan akun mahasiswa adalah dua
  akun; calon yang diterima memakai NIM dan kata sandi dari bagian akademik.

## ADR-025 — Jadwal ujian: draf boleh bentrok, terbit tidak; bentrok mahasiswa dari KRS disetujui (2026-10-26)

- **Konteks.** Roadmap #34. Kartu ujian dicetak dari jadwal kuliah mingguan,
  padahal ujian terjadi sekali, pada tanggal tertentu, sering di beberapa ruang
  sekaligus dengan pengawas, bukan pengampu. Jadwal kuliah menjadwalkan
  *penawaran*, jadi paling buruk ia menawarkan dua kelas bersamaan dan mahasiswa
  memilih; jadwal ujian menjadwalkan *orang yang sudah terdaftar*, jadi dua ujian
  bersamaan berarti satu terlewat. `BentrokJadwal::mahasiswa()` sudah ada untuk
  ujian tugas akhir, tetapi tidak ada entitas UTS/UAS.
- **Keputusan.** (1) Tabel `jadwal_ujian` per kelas per `JenisUjianKelas`
  (UTS/UAS; enum terpisah dari `JenisUjian` tugas akhir), dengan `tahun_akademik_id`
  disalin dari kelas supaya pembaca per semester — kartu, portal, dan kelak
  kalender #35 — tidak masing-masing menulis join-nya sendiri. Ruang di
  `jadwal_ujian_ruang` dengan **kursi per ruang** (bawaan kapasitas ruang, boleh
  diturunkan untuk jarak duduk); pengawas di `jadwal_ujian_pengawas` dengan dua
  FK nullable (`dosen_id`/`staff_id`), bukan morph, supaya basis data tetap
  menolak orang yang tidak ada. (2) **Draf boleh disimpan dengan bentrok; terbit
  tidak.** BAAK yang sedang menyusun perlu melihat seluruh gambaran, bukan ditolak
  satu baris demi satu baris. `terbitkan()` per semester × jenis × (prodi) bersifat
  **semua-atau-tidak**: setiap draf diperiksa terhadap yang sudah terbit dan
  sesama draf dalam cakupan, dan satu penghalang menahan seluruhnya. Jadwal yang
  sudah terbit diperlakukan hidup: perubahannya langsung melewati gerbang yang
  sama, diumumkan kepada peserta, pengampu, pengawas baru, dan pengawas yang
  dilepas; menghapusnya ditolak. (3) Bentrok penghalang: ruang ganda, pengawas
  ganda, mahasiswa dengan dua ujian beririsan (dari **KRS disetujui** yang belum
  dihapus), dan peserta melebihi kursi (termasuk sesi tanpa ruang). Kursi di atas
  kapasitas ruang hanya peringatan. Bentrok mahasiswa dilaporkan per pasangan sesi
  dengan jumlah dan contoh NIM, bukan satu baris per orang. Jadwal kuliah mingguan
  **tidak** diperiksa: kuliah lazimnya berhenti selama pekan ujian, dan memeriksanya
  akan menandai hampir setiap sesi. (4) Peserta dibagi ke ruang menurut urutan
  NIM dan kursi, dihitung saat dibaca — bukan disimpan — sehingga mahasiswa yang
  ditambahkan belakangan menempati urutannya tanpa menggeser ruang orang lain
  secara acak. (5) Kelayakan memakai kebijakan kartu ujian yang sudah ada di
  `config/dokumen.php`: tahan karena kehadiran hanya untuk jenis yang
  `butuhKehadiran()` (UAS), ambangnya `PresensiService::memenuhiAmbangUas()`;
  tahan karena tunggakan dari `Tagihan::tunggakanPerMahasiswa()`. Satu kebijakan,
  dibaca daftar peserta dan kartu. (6) Kartu ujian dicetak dari jadwal ujian bila
  ada sesi terbit pada semester itu — jenis terakhir yang terbit (UAS mengalahkan
  UTS); bila tidak ada, perilaku lama. (7) Hak kelola memakai `kelas.manage` yang
  sudah ada: sesi ujian adalah atribut penawaran kelas, dan izin terpisah akan
  membiarkan orang menempatkan ujian untuk kelas yang jadwal kuliahnya tidak dapat
  ia lihat. (8) Notifikasi terbit satu per orang per penerbitan, membawa jumlah
  sesinya — bukan satu per sesi.
- **Konsekuensi.** Penyusunan massal rakus (kelas terbesar lebih dulu, sesi
  paling awal yang bebas bentrok dan muat), bukan optimal; kelas yang tidak dapat
  ditempatkan dilaporkan dengan alasannya, tidak dibuang diam-diam. Kelas tanpa
  peserta KRS disetujui tidak disusun. Bila KRS berubah sesudah terbit, pembagian
  ruang bergeser mengikuti NIM dan sesi bisa kekurangan kursi tanpa peringatan
  sampai layar BAAK dibuka; gerbang terbit tidak berjalan ulang dengan sendirinya.
  Kalender #35 membaca `JadwalUjian::terbit()`, `scopeDalamRentang()`, `mulai()`
  dan `selesai()` — bukan menyusun ulang tanggal dan jam sendiri.

## ADR-026 — Surat permohonan PDM/PJK: satu surat per mahasiswa per prosedur, dirakit dari permintaannya (2026-10-26)

- **Konteks.** #27 (ADR-017) mencatat setiap perubahan kolom terkunci sebagai
  `feeder_change_requests` dan menyatakan sebagian "harus diajukan lewat
  PDDIKTI admin", tetapi suratnya diketik di luar aplikasi dan nomornya diisi
  tangan. PDDIKTI admin memproses PDM dan PJK sebagai formulir berbeda; beberapa
  koreksi identitas satu mahasiswa adalah baris pada formulir yang sama. Modul
  surat sudah punya penomoran tanpa celah, isi yang dibekukan saat terbit, dan
  verifikasi publik — tetapi setiap surat di sana milik satu mahasiswa dan
  diajukan olehnya.
- **Keputusan.** (1) Dua `JenisSurat` baru, satu per prosedur, masing-masing
  dengan urutan nomornya sendiri; tanpa masa berlaku (surat ini meminta, tidak
  menyatakan keadaan); tidak dapat diajukan mahasiswa. (2) Satu surat = satu
  mahasiswa × satu prosedur, memuat seluruh permintaan yang menunggu surat —
  jalur PDDIKTI admin, atau jalur web service yang ditolak (penolakannya menjadi
  alasan). Campuran mahasiswa atau prosedur ditolak. (3) Permintaan ditautkan
  ke surat lewat `surat_id` sebelum surat diterbitkan, dan `PerakitKonten`
  membaca tautan itu — isi tetap fungsi dari catatan, bukan argumen. (4)
  Lampiran diturunkan dari kolom PDDIKTI lewat katalog di kode (SPTJM selalu)
  dan ikut dibekukan. (5) Nomor yang terbit ditulis balik lewat
  `FeederPerubahanService::ajukan()`, jalur yang sama dengan nomor yang diketik
  tangan, sehingga status *diajukan* punya satu arti. (6) Surat diterbitkan
  lewat `SuratService::terbitkan()` biasa: mahasiswa diberi tahu dan melihatnya
  di portal, karena isinya data dirinya sendiri.
- **Konsekuensi.** Surat yang dicabut tidak mengembalikan permintaannya ke
  *belum diajukan*; operator menerbitkan ulang lewat jalur tangan atau
  menandainya selesai. Katalog lampiran adalah yang lazim diminta, bukan aturan
  resmi yang dapat diverifikasi dari sini; perubahan cukup di satu konstanta.
  Penerima surat dapat diatur per kampus (`SURAT_TUJUAN_PDDIKTI`) karena
  sebagian mengajukan lewat LLDIKTI wilayahnya.

## ADR-027 — Kalender akademik: lapisan baca atas pemilik tanggal, tabel hanya untuk yang yatim, feed iCalendar bertoken (2026-10-27)

- **Konteks.** Roadmap #35. Tanggal-tanggal akademik sudah ada, tersebar di
  pemiliknya: jendela KRS, perubahan KRS, dan nilai di `tahun_akademik` (dan
  kolom itulah yang menggerbang `krsDibuka()`/`penilaianDibuka()`), sesi UTS/UAS
  di `jadwal_ujian` (#34), periode EDOM, wisuda, yudisium, dan gelombang PMB di
  tabelnya masing-masing. Yang tidak ada: satu tempat untuk melihat semuanya,
  dan tempat untuk tanggal yang memang tidak dimiliki siapa pun — libur, kegiatan
  kampus, batas administrasi lain.
- **Keputusan.** (1) **Tidak menyalin.** `KalenderService` membaca setiap sumber
  pada saat diminta dan menghasilkan `PeristiwaKalender` (DTO baca); kalender
  yang menyimpan salinan benar pada hari diisi dan salah pada hari ujian digeser.
  Turunannya: perkuliahan (awal dan akhir sebagai dua penanda, bukan bilah lima
  bulan), KRS, perubahan KRS (sehari sesudah pengisian ditutup sampai
  `krs_perubahan_selesai`, seperti `krsDibuka()`), nilai; masa UTS/UAS per
  semester × jenis × prodi dari sesi **terbit** saja, satu kueri ber-`GROUP BY`,
  rentangnya dari sesi pertama sampai terakhir kelompok — bukan dipotong batas
  bulan; EDOM yang `is_active`; yudisium sebagai **jumlah** lulusan ditetapkan per
  hari per prodi, tanpa nama; wisuda; gelombang PMB aktif (staf saja). (2) Tabel
  `kalender_peristiwa` hanya untuk tiga jenis yang tidak punya pemilik: libur,
  kegiatan, batas. Service dan FormRequest menolak jenis turunan — jendela KRS
  yang diketik di sini akan menjadi jendela KRS kedua yang tidak menggerbang
  apa pun. Sasaran tunggal (`semua`/`mahasiswa`/`dosen`/`staf`), prodi opsional,
  draf → terbit, `HasLogAktivitas`, hapus lunak. Izin tulis baru `kalender.manage`
  (BAAK), bukan `kelas.manage`: libur bukan atribut penawaran kelas. (3) **Satu
  aturan pembaca** untuk ketiga portal dan feed: `SasaranPeristiwa::terlihatOleh()`
  — staf semua; dosen semua/dosen/mahasiswa (wali paling sering ditanya batas
  KRS); mahasiswa semua/mahasiswa, dan prodinya **dipaku** apa pun filternya.
  Draf hanya untuk staf ber-`kalender.manage`. Mahasiswa dan dosen juga melihat
  sesi ujian **miliknya** (berjam, berruang) lewat `untukMahasiswa()`/`untukDosen()`
  milik `JadwalUjianService`, bukan aturan "sesi siapa ini" yang kedua.
  (4) **Feed iCalendar per orang**, URL bertoken 48 karakter: hanya SHA-256-nya
  yang disimpan (`calendar_feed_tokens`, tabel infrastruktur), URL tampil sekali
  dari flash; satu tautan hidup per orang — membuat ulang mencabut yang lama,
  mencabut tanpa mengganti tersedia; token mati bersama akun yang dinonaktifkan;
  token tak dikenal dan yang dicabut sama-sama 404. Isinya tanpa nama, NIM, atau
  surel: tautan yang bocor memberi tahu kapan suatu kelas UTS, bukan kalender
  siapa itu. Draf tidak pernah ikut — kalender langganan ada di ponsel orang dan
  tidak dapat ditarik kembali. Rentang 3 bulan ke belakang sampai 12 bulan ke
  depan; tanggal seharian sebagai `VALUE=DATE` dengan akhir eksklusif, sesi berjam
  dalam UTC sehingga tidak perlu `VTIMEZONE`; baris dilipat per 75 oktet tanpa
  memotong karakter multibita. Rute publik dengan batas laju; `last_used_at`
  ditulis paling sering sejam sekali, bukan per tarikan. (5) **Libur di tempat
  yang murah.** Sesi ujian pada libur terbit (untuk semua prodi atau prodi
  kelasnya, sasaran bukan staf) mendapat `BentrokJadwal::libur()` yang **tidak
  menghalangi** — kampus kadang memang menguji di hari libur, yang tidak boleh
  terjadi adalah tanpa disadari; penyusun massal melewati hari libur. Pencarian
  libur adalah `KalenderPeristiwa::tanggalLibur()` di model, bukan di service,
  karena kalender membaca jadwal ujian dan dependensi service dua arah akan
  melingkar.
- **Konsekuensi.** Gerbang yang sudah ada **sengaja tidak disentuh**: libur tidak
  memperpanjang jendela KRS atau nilai, tidak menggeser 16 pertemuan, dan tidak
  menyaring pengingat KRS. Tanggal-tanggal itu sudah ditetapkan BAAK dengan
  liburnya dalam pikiran; memperpanjang otomatis diam-diam mengubah keputusan yang
  sudah diumumkan. Yudisium tampil sebagai hari keputusan yang sudah lewat, karena
  tidak ada tabel sidang yudisium terjadwal — bila kelak ada, sumber itu
  ditambahkan di `KalenderService` tanpa menyentuh tabel kalender. Kunci
  `PeristiwaKalender` stabil per baris sumber, jadi klien kalender melihat tanggal
  yang diubah sebagai acara yang dipindah. Feed membaca ulang setiap sumber pada
  setiap tarikan; bila kelak mahal, cache per pemilik dengan masa hidup pendek
  cukup karena sumbernya tidak disalin.

## ADR-028 — Riwayat kepegawaian: satu alur validasi di atas tabel bertipe; buku besar angka kredit, bukan penilaian (2026-10-27)

- **Konteks.** #39: SISTER meminta "Profil Dosen" per kelompok, dan sebagian
  besar kelompok itu tidak punya tempat di sini — delapan riwayat tanpa tabel,
  enam dengan tabel tanpa layar. Tidak satu pun riwayat punya status: apa pun
  yang masuk dianggap fakta, sehingga ekspor SISTER akan memuat klaim yang
  belum pernah diperiksa begitu dosen dapat mengisinya sendiri.
- **Keputusan (#39).** (1) Delapan tabel baru, tetap bertipe (alasan
  KEPEGAWAIAN.md), dan **lima kolom validasi yang sama** pada keempat belasnya:
  `status` (bawaan *diajukan*), `alasan_tolak`, `divalidasi_oleh`,
  `divalidasi_at`, `dokumen_path`. Enam tabel lama di-backfill *disetujui*
  karena seluruh barisnya ditulis staf atau seeder. (2) Dua jalur, satu keadaan:
  dosen **mengajukan** (selalu *diajukan*), staf **mencatat** (langsung
  *disetujui* atas namanya — staf adalah peninjaunya, dan langkah setuju
  terpisah adalah orang yang menyetujui dirinya sendiri). (3) Kosakata tunggal
  `JenisRiwayat` + satu definisi bidang `FormulirRiwayat` + satu policy
  `RiwayatKepegawaianPolicy` yang didaftarkan untuk keempat belas model, dan
  parameter rute `{riwayat}` yang diikat dari `{jenis}` di satu tempat —
  alih-alih empat belas controller yang akan menyimpang. (4) Efek persetujuan
  hanya di tempat yang memang beraturan: **pangkat berlaku** = disetujui
  ber-TMT terakhir (bukan terakhir disetujui, supaya SK lama yang diajukan
  belakangan tidak menurunkan siapa pun); **mutasi** tetap menulis penunjuk dan
  riwayat bersama, dan tidak disunting/dihapus — koreksinya mutasi baru;
  **tugas belajar** disetujui membebaskan BKD selama beririsan dengan semester,
  dibaca di `wajibMelapor()`/`wajibBkd()`, bukan disalin; **pensiun** disetujui
  menutup akun pada TMT-nya lewat `halanganNonaktifDosen()` yang sama dengan
  tombol nonaktif, harian lewat `openacademic:tutup-akun-pensiun`. (5) Mutasi,
  kontrak, dan pensiun **diisi staf saja**. (6) Ekspor SISTER hanya memuat baris
  disetujui; kelompok yang kini dapat diisi dibuka di katalog, keluarga tetap
  sengaja tidak diekspor.
- **Yang dipilih untuk TIDAK ditegakkan (#39).** Jabatan struktural tidak
  mengurangi beban BKD (sudah keputusan asesor di `config/bkd.php`); kontrak
  yang berakhir tidak menutup akun (perpanjangan lazim terlambat diproses);
  akun pensiunan yang masih memegang kelas/perwalian dibiarkan aktif dan
  dilaporkan, bukan dipaksa tutup; tiga riwayat G7 (pendidikan, jabatan
  fungsional, sertifikasi) belum dipindah ke alur validasi; riwayat tenaga
  kependidikan (staf) tidak dicakup.
- **Konsekuensi (#39).** Bawaan kolom *diajukan* berarti kode lama yang
  menulis baris tanpa status menghasilkan ajuan, bukan fakta —
  `naikPangkat()`/`catatMutasi()` kini menulis *disetujui* secara eksplisit.
  Bahasa yang dihapus lalu ditambah ulang memulihkan baris lamanya, karena
  indeks unik `(dosen_id, bahasa)` ikut menghitung baris terhapus. Riwayat
  tidak membawa notifikasi; dosen melihat status dan alasannya di portal.
- **Konteks (#40).** Angka kredit hanya ada sebagai satu angka pada jabatan
  fungsional; tidak ada buku besar Kum A–E, sehingga kesiapan usulan dan lembar
  AK penyetaraan dihitung di luar aplikasi dari BKD yang sudah tersimpan di
  sini. Layanan PAK 2026 mengonversi kinerja sebelum 2026 dengan SKS BKD × 4 dan
  membatasi Kum D dan E masing-masing 10 %; penilaiannya terjadi di platform
  kementerian.
- **Keputusan (#40).** (1) Buku besar `angka_kredit_dosen`, satu baris per
  butir per unsur, dalam perseratus; **tidak ada total tersimpan** — rekap
  selalu dihitung. (2) Lima unsur `UnsurAngkaKredit` terpisah dari `UnsurBkd`;
  "pendidikan" BKD adalah Kum B. (3) Empat sumber: dua turunan (BKD **disahkan**,
  ijazah S2/S3) yang disusun ulang di tempat dan dijaga indeks unik per sumber
  per unsur, dua isian staf (konversi semester lama, penyesuaian beralasan).
  (4) Konversi × 4 untuk semester yang **mulai** sebelum 1 Januari 2026; sesudahnya
  faktor kampus yang ditandai *perkiraan*, karena klaimnya terjadi di platform.
  Konversi manual ditolak untuk semester sejak 2026 dan untuk semester × unsur
  yang sudah dikreditkan dari BKD. (5) DUPAK = baris sesudah TMT jabatan berlaku;
  AK kumulatif = AK jabatan itu + DUPAK. Ijazah dibandingkan per tahun dan tahun
  TMT tidak dihitung. (6) Batas D/E ditegakkan pada rekap sebagai **titik tetap**
  terhadap total sesudah pembatasan — angka yang tertulis di lembar dan diperiksa
  saat diunggah — bukan terhadap total mentah. (7) Syarat jenjang berikutnya di
  `config/angka_kredit.php`: AK kumulatif (bawaan enum) dan bagian minimum Kum C
  dari AK yang masih dibutuhkan; "siap" = keduanya terpenuhi. (8) Lembar
  penyetaraan .xlsx ditulis `LembarKerjaXlsx` di atas ext-zip, bukan pustaka
  spreadsheet; NUPTK dan TMT sebagai sel teks, AK sebagai angka; dosen tanpa
  NUPTK dikeluarkan dan disebut di layar. `dosen.nuptk` ditambahkan. (9) Ekspor
  dan isian manual memakai `dosen.manage`; membaca memakai `dosen.view`; dosen
  membaca buku besarnya sendiri.
- **Yang dipilih untuk TIDAK dibangun (#40).** Tim penilai, plotting, pejabat
  pengesah, TTE, DUPAK PDF, nomor surat — semuanya di platform kementerian.
  Pengali predikat kinerja tahunan pasca-2026 tidak dimodelkan; faktor
  "sesudah" adalah perkiraan kampus. Syarat PTNBH/PTS untuk penyetaraan tidak
  diperiksa per baris. Riwayat pendidikan (sumber Kum A) belum melewati alur
  validasi #39 — ijazah yang diketik dosen langsung terhitung, seperti ia sudah
  langsung terbaca di ekspor SISTER.
- **Konsekuensi (#40).** BKD yang disahkan langsung mengkredit buku besar;
  koreksi sesudahnya cukup "Susun ulang". Persentase penelitian bawaan adalah
  tafsir, bukan kutipan regulasi, dan dapat diubah lewat env. Kolom lembar
  penyetaraan mengikuti panduan operator; templat yang berubah cukup diubah di
  `AngkaKreditService::KOLOM_PENYETARAAN`.

## ADR-029 — KKN/PKL mendarat sebagai `aktivitas_mahasiswa` per peserta; tahap proposal tugas akhir dikunci saat pengajuan (2026-10-27)

### Bagian 1 — KKN / PKL sebagai kegiatan terkelola (#37)

- **Konteks.** PDDIKTI mencatat KKN dan PKL sebagai *aktivitas mahasiswa* —
  rantai yang sama dengan magang MBKM: aktivitas, anggota, pembimbing, lalu
  konversi nilai ke mata kuliah. Rantai itu sudah ada sejak #22 dan #23, tetapi
  tidak ada yang menulis baris konversinya, dan tidak ada tempat untuk periode,
  lokasi, kelompok, pembimbing mitra, atau logbook. Tabel kegiatan tersendiri
  akan membuka jalur Feeder kedua untuk data yang sama.
- **Keputusan.** (1) **Satu peserta = satu baris `aktivitas_mahasiswa`**, sama
  persis dengan peserta MBKM, sehingga KKN/PKL dikirim oleh mapper aktivitas,
  anggota, bimbing, dan konversi yang sudah ada tanpa entitas Feeder baru. PDDIKTI
  mengizinkan satu aktivitas beranggota banyak; di sini tetap satu aktivitas per
  mahasiswa (peran *personal*) karena begitulah baris lokalnya, dan memecah
  baris itu menjadi aktivitas-berkelompok berarti menulis ulang empat mapper
  untuk keuntungan yang tidak dibaca siapa pun. (2) Tabel tambahan hanya untuk
  yang tidak punya tempat di baris aktivitas: `kegiatan_lapangan` (periode,
  dengan aturan kelompok dan bobot nilai yang disalin dari
  `config/kegiatan_lapangan.php`), `kegiatan_lapangan_lokasi`,
  `kegiatan_lapangan_kelompok` (DPL dan pembimbing mitra), dan
  `kegiatan_lapangan_logbook` (FK ke aktivitas). Baris aktivitas mendapat FK ke
  periode dan kelompok, penanda ketua, dan dua nilai. (3) Setiap perubahan
  periode, lokasi, atau kelompok **ditulis ulang ke baris aktivitas** (judul,
  mitra, lokasi wilayah, tanggal, DPL) — yang dibaca PDDIKTI selalu yang tampak
  di layar. (4) Dua jenis baru `StudentActivityType::Kkn` dan `::Pkl`; hanya
  jenis MBKM yang dikirim dengan `untuk_kampus_merdeka = 1`, dan nomor/tanggal
  SK periode ikut sebagai `sk_tugas`/`tanggal_sk_tugas` (payload MBKM lama tidak
  berubah karena keduanya kosong). (5) Pembentukan kelompok otomatis: peserta
  diurutkan prodi → jenis kelamin → NIM lalu dibagikan bergiliran, ukuran
  diratakan (23 dengan target 10 menjadi 8/8/7); aturan campur yang dimatikan
  memartisi peserta lebih dulu. Hasilnya dapat diulang. (6) Nilai akhir =
  bobot DPL × nilai DPL + bobot mitra × nilai mitra, terbentuk hanya bila semua
  nilai berbobot ada. (7) **Konversi per kelas**: kelas mata kuliah KKN/PKL
  memilih peserta dari prodinya (kurikulum tiap prodi punya kode sendiri),
  menulis atau menempelkan baris KRS yang menandai aktivitas, dengan nilai
  final, memverifikasi aktivitas, dan mengisi `sks_konversi`. Sesudahnya nilai
  kegiatan terkunci; koreksi lewat jalur koreksi nilai. (8) Baris KRS bertanda
  aktivitas **dikeluarkan dari lembar nilai dosen** (`PenilaianService`), supaya
  finalisasi kelas tidak menghitung ulangnya dari komponen yang tidak pernah
  diisi.
- **Konsekuensi.** Izin memakai `mahasiswa.view`/`mahasiswa.manage` (yang sama
  dengan verifikasi aktivitas MBKM di layar IKU) — tanpa izin baru, tanpa
  migrasi peran. Jangkauan dosen ditentukan per kelompok oleh
  `dosen_pembimbing_id`, bukan oleh peran. Aktivitas KKN baru terverifikasi
  saat dikonversi, jadi baru terkirim ke Feeder sesudah dinilai; kampus yang
  ingin melaporkannya lebih awal tetap dapat memverifikasinya di layar IKU.
  Kelas yang hanya berisi baris konversi tetap berstatus nilai *belum*.

### Bagian 2 — Tahap dokumen proposal tugas akhir (#38)

- **Konteks.** Tugas akhir dimulai dari pengajuan judul; seminar proposal
  (`JenisUjian::Proposal`) sudah ada tetapi baru dapat dijadwalkan saat karya
  dibimbing. Sebagian prodi menelaah dokumen proposal *sebelum* judul disetujui,
  sebagian tidak sama sekali. Menambah tahap wajib untuk semua kampus mengubah
  alur yang sudah berjalan; menyalakannya per prodi tanpa batas waktu akan
  menahan tugas akhir yang diajukan sebelum saklar dinyalakan.
- **Keputusan.** (1) Saklar per prodi `prodi.wajib_proposal_ta`, bawaan mati,
  di layar master prodi. (2) **Dikunci saat pengajuan**: `TugasAkhirService::
  ajukan()` menyalin saklar ke `tugas_akhir.wajib_proposal`; menyalakan atau
  mematikannya kemudian tidak menjangkau tugas akhir yang sudah diajukan.
  (3) Versi proposal (`tugas_akhir_proposal`) dan telaah
  (`tugas_akhir_proposal_telaah`) hanya bertambah: revisi adalah versi baru,
  keputusan adalah baris telaah baru, dan status versi berpindah sekali dari
  *menunggu*. Berkas lewat `BerkasService` di disk privat, judul per versi
  tersimpan di versinya (judul tugas akhir mengikuti versi terakhir).
  (4) Penelaah: dosen yang disebut sebagai calon pembimbing pada versi itu
  (dengan `tugas_akhir.bimbing`), atau staf dengan `tugas_akhir.manage` —
  koordinator prodi bertindak sebagai staf; tidak ada peran baru. Revisi dan
  penolakan wajib bercatatan. (5) **Diterima** membuka gerbang yang sudah ada:
  `setujuiJudul()` menolak judul ber-`wajib_proposal` tanpa versi diterima;
  sesudah judul disetujui, pembimbing dan seminar proposal berjalan seperti
  dulu. **Ditolak** menutup tugas akhir seperti judul yang ditolak dan
  membebaskan slot mahasiswa.
- **Konsekuensi.** Kampus yang tidak menyalakan saklar tidak melihat apa pun
  berubah — tidak ada kartu, tidak ada gerbang. Tahap ini tidak menggantikan
  seminar proposal; yang menyeminarkan proposal tetap menjadwalkannya sesudah
  pembimbing ditetapkan. Dokumen proposal bukan entitas Feeder.

## ADR-030 — Tarif per SKS dan per mata kuliah ditagih dari KRS yang disetujui, ke tagihan semester yang sama; gerbang KRS hanya mengukur komponen tetap (2026-10-27)

- **Konteks.** Matriks tarif hanya mengenal komponen tetap (UKT, SPP, …) yang
  diterbitkan massal di awal semester. Kampus yang menagih per SKS tidak
  terlayani: jumlahnya baru diketahui setelah KRS ada. Tiga kendala membentuk
  rancangannya. (1) Satu tagihan per mahasiswa per semester adalah invarian
  (indeks unik) yang dibaca gerbang KRS, daftar tunggakan, bebas tanggungan,
  pengingat, dan jurnal. (2) Gerbang KRS mensyaratkan pembayaran minimum
  *sebelum* KRS diajukan — bila tagihan per SKS ikut diukur, KRS butuh bayar
  dan tagihan butuh KRS. (3) KRS yang disetujui ternyata **tidak dapat
  direvisi** sama sekali (`Disetujui → []`); `krs_perubahan_selesai` hanya
  memperpanjang masa isi draf.
- **Keputusan.**
  1. Komponen menjadi enum `KomponenTarif`; `per_sks` dan `mata_kuliah`
     ditandai `dariKrs()`. `TarifResolver::untuk()` (penerbitan massal) hanya
     komponen tetap. Baris `mata_kuliah` membawa `mata_kuliah_id` dan bersaing
     hanya dengan baris untuk mata kuliah yang sama; komponen per MK
     **menambah** biaya per SKS, tidak menggantikannya.
  2. **Ditagih saat persetujuan, ke tagihan yang sama.** `TagihanKrsService::
     sinkronkan()` dipanggil `KrsService::putuskan()` setiap kali KRS
     disetujui. Bila tagihan semester belum ada, dibuat beserta komponen
     tetapnya (lewat `PenerbitanTagihanService::buatTagihan()` yang sama
     dengan penerbitan massal). Tidak ada tagihan kedua per semester.
  3. **Selisih, bukan timpa.** Baris KRS dicocokkan per (komponen, mata
     kuliah): yang belum ditagih ditambah; yang tertagih tetapi tidak lagi di
     KRS dijawab **baris koreksi** yang menunjuk baris aslinya
     (`koreksi_atas_item_id`), berjenis tagihan, bernama sama (supaya beasiswa
     yang mencakupnya lewat nama ikut menyusut). Koreksi bernilai negatif bila
     kelas dikeluarkan (`krs_detail.deleted_at`) sampai batas
     `payment.krs.batas_pengurangan` (`krs_selesai` bawaan,
     `krs_perubahan_selesai`, `tidak_pernah`), Rp0 beralasan bila sesudahnya.
     Baris yang dikoreksi Rp0 tetap dianggap terbayar: mengambil mata kuliahnya
     lagi tidak menagih dua kali. Tarif yang berubah sesudah ditagih tidak
     menagih ulang.
  4. **Revisi**: transisi baru `Disetujui → Draft` lewat `KrsService::
     bukaRevisi()`, hanya selama `krsDibuka()`. Diajukan dan disetujui ulang
     seperti biasa; tagihan tetap seperti persetujuan terakhir sampai itu.
  5. **Gerbang**: `memenuhiSyaratKrs()` mengukur pembayaran terhadap
     `pokokGerbangKrs()` = pokok × (kotor − `nominal_krs`) / kotor, bulat ke
     bawah. Potongan dibagi proporsional karena baris potongan tidak menyebut
     baris mana yang dibayarnya. `tagihan.nominal_krs` (neto baris KRS) disimpan
     supaya tagihan tanpa baris KRS dijawab tanpa kueri, persis seperti dulu.
  6. **Jatuh tempo**: hari persetujuan + `payment.krs.jatuh_tempo_hari`, hanya
     bila lebih lambat dari jatuh tempo tagihan yang ada.
  7. **Akuntansi**: tagihan yang dibuat saat persetujuan → invoice biasa;
     baris pada tagihan yang sudah terbit → jurnal per baris (`oa-krs-{id}`,
     Dr Piutang/Cr Pendapatan, sebaliknya untuk koreksi).
- **Konsekuensi.** Tidak ada lingkaran: kampus tetap+per SKS mengunci KRS pada
  komponen tetap; kampus per SKS murni tidak pernah terkunci oleh tagihannya
  sendiri (tidak ada tagihan sebelum persetujuan; sesudahnya porsi tetapnya
  nol). Memundurkan jatuh tempo untuk baris baru juga memundurkan jam denda
  porsi tetap yang sudah lewat — diterima, karena mahasiswa itu sudah lolos
  gerbang dan denda adalah dorongan, bukan pendapatan. Selama revisi KRS
  berstatus draf dan mahasiswa tidak tampil di daftar kelas/presensi; itu
  harga revisi dan alasan jendelanya ditutup bersama masa perubahan.
  Perubahan potongan beasiswa akibat penyesuaian tidak dijurnalkan (celah yang
  sama dengan penetapan beasiswa sesudah terbit). Baris KRS yang ditulis
  konversi KKN/MBKM tidak memicu penagihan; ikut tertagih pada persetujuan
  berikutnya.

## ADR-031 — Pemakaian ruang: laporan baca dari dua jadwal, ditelusuri per tanggal, penyebutnya jam operasional kampus (2026-10-27)

- **Konteks.** Roadmap #42. Bentrok ruang sudah dicegah oleh `JadwalService`
  (kuliah) dan `JadwalUjianService` (ujian, #34), dan libur akademik sudah
  terbit dari kalender (#35). Yang tidak terlihat: seberapa terpakai tiap ruang,
  ruang mana yang selalu sesak, dan ruang mana yang kosong ketika staf mencari
  tempat untuk satu kelas atau satu sesi ujian. Pemanfaatan adalah rasio, dan
  penyebutnya — kapan ruang *bisa* dipakai — tidak tercatat di mana pun.
- **Keputusan.** (1) **Laporan, bukan pemesanan.** `PemanfaatanRuangService`
  tidak menulis apa pun; ruang "dipegang" oleh jadwal yang memakainya, dan
  gerbang bentrok kedua jadwal itulah yang menahan. Pencarian ruang kosong juga
  hanya membaca. (2) **Penyebut dari config kampus**: `config/ruang.php` —
  hari operasional (ISO), jam buka–tutup, ambang "jarang dipakai" (bawaan 25 %),
  lebar kolom peta panas. Pemakaian di luar jendela itu (kelas malam, Sabtu
  yang tutup, ujian pada hari libur) dilaporkan sebagai kolom tersendiri, tidak
  ditambahkan ke persentase — angka di atas 100 % tidak menjawab pertanyaan
  apa pun. (3) **Ditelusuri per tanggal** dari `tanggal_mulai` sampai
  `tanggal_selesai` semester, tidak dikalikan dari satu pekan: libur kampus
  (`KalenderPeristiwa::tanggalLibur()`, terbit, bukan sasaran staf) mengeluarkan
  hari itu dari tersedia *dan* terpakai; libur satu prodi hanya meniadakan kuliah
  prodi itu; ujian hanya ada pada tanggalnya. Hari yang tidak berbeda dihitung
  sekali per hari-pekan dan dipakai ulang. Angka ditampilkan sebagai jam per
  pekan rata-rata semester. (4) **Terpakai = gabungan rentang**: kuliah dan ujian
  yang menumpuk (atau dua kelas yang lolos ganda) dihitung sekali; jam
  bersentuhan bukan tumpang tindih; waktu dibaca sebagai menit, bukan dibanding
  sebagai string (`10:00` vs `10:00:00`). (5) **Okupansi kursi** = Σ(peserta ×
  menit) / Σ(kapasitas × menit) atas setiap pemakaian, tanpa digabung. Peserta
  kuliah = mahasiswa ber-KRS **disetujui** (tafsiran yang sama dengan jadwal
  ujian); peserta ujian per ruang = kursi diisi berurutan per `urutan`, seperti
  kartu ujian — sesi 50 orang di 40 + 40 kursi menaruh 40 dan 10, bukan 25 dan
  25. Kapasitas 0 berarti belum diisi: tanpa okupansi, tidak pernah "kelebihan".
  (6) **Ujian hanya yang terbit**, kuliah dari semua slot kelas semester itu.
  (7) Izin baca baru `ruang.view` untuk BAAK, pimpinan, dan peran staf baru
  `sarpras` (bersama `master.view` dan helpdesk) — bukan `kelas.view`, supaya
  sarana-prasarana melihat ruang tanpa diberi layar penyusunan jadwal. Policy di
  model `Ruang` (`viewUtilization`).
- **Konsekuensi.** Enam kueri per laporan berapa pun jumlah ruang, slot, dan
  sesi (dipaku tes). Ruang nonaktif tetap tampil selama masih dipakai jadwal —
  itulah baris yang perlu dilihat — tetapi tidak pernah "jarang dipakai". Ujian
  di luar rentang tanggal semester tidak terhitung, dan kuliah diasumsikan
  berjalan setiap pekan semester termasuk pekan ujian (model jadwal tidak
  mengenal pekan tanpa kuliah); tumpukan ujian di atas kuliah tidak menggelembung
  karena (4). Instalasi yang sudah berjalan perlu menjalankan ulang
  `RolePermissionSeeder` agar izin dan peran baru ada.

## ADR-032 — Klaim mahasiswa lampau: klaim bukan penautan; yang diklaim diadopsi lewat pencocok #44 (2026-10-27)

- **Konteks.** Roadmap #44b. Mahasiswa yang dilaporkan bertahun-tahun lalu —
  sebelum Neo Feeder, atau dari instalasi yang sudah diganti — dapat tercatat
  di PDDIKTI untuk kampus ini tanpa muncul di dua daftar yang dibaca adopsi
  (ADR-020). Adopsi melaporkan mereka *hanya di SIAKAD*, validator tidak
  menahan apa pun, dan sinkronisasi pertama mendaftarkan mereka sebagai orang
  baru. Feeder menyediakan daftar mahasiswa lampau dan aksi klaimnya; klaim
  yang keliru membawa catatan orang lain ke Feeder kampus, dan penautan yang
  keliru sama berbahayanya seperti pada adopsi.
- **Keputusan.** (1) **Klaim hanya membawa catatan, tidak menautkan.**
  `FeederKlaimLampau` membaca `GetListDaftarLampau` per prodi + periode masuk,
  mengirim `KlaimMahasiswaLampau` untuk baris pilihan, lalu **langsung
  menjalankan adopsi** untuk prodi dan periode itu. Siapa pemilik catatannya
  diputuskan adopsi, dengan keputusan manual yang sama — tidak ada jalur kedua
  yang mengisi `feeder_id`. (2) **Satu pencocok.** Aturan pencocokan ADR-020
  dipindah tanpa perubahan ke `PencocokMahasiswaFeeder`, dipakai adopsi dan
  klaim; di layar klaim hasilnya dibaca dari sisi baris Feeder: *cocok* (satu
  mahasiswa lokal), *ambigu* (alasan yang sama dengan adopsi), *tidak ada di
  SIAKAD*. Cocok-dengan-satu mengalahkan menjadi-salah-satu-kandidat bagi
  mahasiswa lain. (3) **Yang boleh diklaim**: cocok; ambigu hanya dengan
  alasan tertulis — klaimnya tidak memutuskan apa pun, adopsi sesudahnya
  melaporkan baris itu ambigu dan validator menahan biodatanya sampai orang
  menautkan atau memisahkan di layar adopsi; *tidak ada di SIAKAD* tidak
  pernah — tidak ada yang akan melapor di bawah catatan itu. (4) **Validasi
  pra-kirim membaca ulang PDDIKTI**, bukan laporan yang mungkin basi, dan
  semua-atau-tidak: satu baris bermasalah (tanpa mahasiswa lokal, ambigu tanpa
  alasan, kunci klaim tidak lengkap, tidak lagi terdaftar, registrasi dipegang
  mahasiswa lain) menahan seluruh klaim dan semua masalah dilaporkan sekaligus.
  Penolakan Feeder sesudahnya diperlakukan per baris. (5) **Buku besar**:
  setiap panggilan satu baris `feeder_sync_logs` (`entity = mahasiswa_lampau`,
  `direction = claim`, `feeder_id` = id registrasi, mahasiswa lokal sebagai
  `local`). Registrasi dengan baris sukses tidak pernah dikirim lagi; yang
  ditolak dicatat dengan pesan Feeder dan jumlah percobaannya, dan dapat
  diklaim lagi. (6) **Laporan** `feeder_past_students` tanpa NIK, diganti
  setiap pembacaan kecuali baris yang sudah diklaim — yang menyimpan pelaku,
  waktu, dan alasan, dan tercatat di Log Aktivitas. (7) **Validator** menolak
  biodata mahasiswa yang sudah diklaim tetapi belum ber-`feeder_id` dan tidak
  dipisahkan (`klaim_lampau_belum_diadopsi`).
- **Konsekuensi.** Klaim menjalankan adopsi penuh untuk prodi dan periode itu,
  jadi kasus jelas lain di angkatan yang sama ikut teradopsi — hasil yang sama
  dengan menjalankan adopsi sendiri. Rancangan bergantung pada catatan yang
  diklaim muncul di daftar riwayat pendidikan dengan periode masuk yang sama;
  bila tidak, hasil klaim menyebut NIM yang belum teradopsi dan validator
  menahan biodatanya, bukan diam. Baris lampau tanpa `id_registrasi_mahasiswa`
  dihitung tetapi tidak ditawarkan. Mahasiswa lama yang belum dimigrasi ke
  SIAKAD tidak dapat diklaim dari sini; migrasikan dulu. Tidak ada perintah
  artisan: klaim selalu butuh pilihan orang. Nama aksi, filter, dan kunci klaim
  (`id_registrasi_mahasiswa`) ada di `config/feeder.php` → `adopt.lampau`,
  mengikuti pemakaian komunitas, dan perlu diverifikasi terhadap dokumentasi
  build Feeder kampus.

## ADR-033 — Pengerasan keamanan menjelang v1.0: konfirmasi sebagai data, sesi seumur akun dan kata sandinya, batas masuk tiga lapis (2026-10-27)

- **Konteks.** Audit keamanan Fase 5 (`docs/KEAMANAN-AUDIT-2026-10.md`)
  menemukan dua celah tinggi dan delapan sedang. Beberapa perbaikannya
  memilih satu jalan di antara beberapa yang masuk akal, dan pilihan itu
  menentukan bagaimana kode baru harus ditulis.
- **Keputusan.** (1) **Konfirmasi adalah data, bukan skrip.** Tidak ada lagi
  penangan `on*=` di view; konfirmasi memakai `data-confirm` (dan
  `data-confirm-if="medan=nilai"` untuk yang bersyarat) yang ditangani satu
  pendengar di `app.js`. Karena itu `script-src` tidak lagi memuat
  `'unsafe-inline'`, dan tes memindai seluruh view supaya tetap begitu.
  `'unsafe-eval'` tetap — itu utang Alpine yang terpisah. (2) **Sesi seumur
  akun dan kata sandinya, diperiksa per guard.** `PastikanAkunAktif` di grup
  web memeriksa keempat guard portal setiap permintaan, bukan memakai
  `AuthenticateSession` bawaan Laravel: middleware itu hanya membaca guard
  bawaan dan mengandaikan guard itu `SessionGuard`, sedangkan guard `sso`
  bukan. Sidik kata sandi di sesi adalah HMAC atas hash-nya, terikat ke id
  akun, dan diperbarui sesudah respons — perangkat yang mengganti kata
  sandinya tetap masuk, perangkat lain tidak. Cookie "ingat saya" ditangani
  terpisah: `PutarTokenIngat` memutar `remember_token` di model setiap kali
  kata sandi berubah, sehingga layar kata sandi baru tidak dapat lupa
  melakukannya. (3) **Batas masuk tiga lapis** (`BatasMasuk`): per identitas
  + alamat (seperti sebelumnya), per akun dari alamat mana pun, per alamat ke
  akun mana pun. Hanya kegagalan yang dihitung; masuk yang berhasil tidak
  membersihkan penghitung per alamat. (4) **Peran tertinggi hanya diberikan
  pemegangnya** (`StaffPolicy`), dan tidak seorang pun mengubah perannya
  sendiri — tanpa memecah `pengaturan.manage` menjadi izin baru, supaya
  instalasi berjalan tidak perlu menjalankan ulang seeder peran.
  (5) **Provisioning IdP gagal tertutup**: tanpa `IDP_PROVISION_DOMAINS` tidak
  ada akun yang dibuat, dan surel wajib terverifikasi meski penautan
  dilonggarkan. (6) **Satu penulis CSV** (`App\Support\Csv`): apostrof di
  depan sel teks pemicu rumus, angka tidak disentuh, kutipan RFC 4180 tanpa
  karakter escape.
- **Konsekuensi.** Kode baru menulis konfirmasi sebagai `data-confirm`;
  penangan sebaris membuat tes gagal. Setiap permintaan web membaca hingga
  empat guard — tanpa kueri tambahan bagi guard yang tidak ada di sesi.
  Mengganti kata sandi atau menaikkan `BCRYPT_ROUNDS` (hash diperbarui saat
  masuk berikutnya) mengeluarkan sesi lain akun itu dan mematikan cookie
  "ingat saya" lamanya. Pengguna di balik satu NAT berbagi penghitung per
  alamat; naikkan `AUTH_BATAS_PER_ALAMAT` bila salah ketik wajar satu kampus
  melampauinya. Penyerang dapat menahan satu akun selama 15 menit dengan
  sengaja gagal — harga yang lazim, jauh lebih murah daripada akunnya jatuh.
  Instalasi yang sudah menyalakan `IDP_PROVISION=true` berhenti membuat akun
  sampai `IDP_PROVISION_DOMAINS` diisi. Sel CSV teks yang memang diawali
  tanda hubung (jarang) kini tampil dengan apostrof tersembunyi di lembar
  kerja dan apostrof terlihat bila berkasnya dibaca mesin.

## ADR-034 — Anggaran kueri diukur pada kampus yang ditumbuhkan; indeks hanya bila rencana kuerinya menuntut (2026-10-27)

- **Konteks.** Audit N+1 Fase 5 menjelang v1.0. `SmokeLayarTest` memaku
  anggaran kueri setiap layar pada kampus demo 50 mahasiswa, dan justru
  karena itu tidak melihat N+1 yang paling mahal: pratinjau penutupan
  semester menjalankan 61 kueri di demo — di dalam anggaran mana pun yang
  ditulis untuknya — dan 212 kueri pada tiga kali jumlah mahasiswa. Anggaran
  yang diukur pada data kecil tidak dapat membedakan "dua puluh kueri" dari
  "dua puluh kueri karena ada dua puluh baris". Menumbuhkan data per layar
  dengan tangan tidak terjangkau untuk ratusan layar.
- **Keputusan.** (1) **Pertumbuhan sebagai alat ukur.** `Tests\Support\
  PenggandaBaris` menggandakan baris satu tabel beserta semua baris yang
  menggantung padanya lewat kunci asing (mahasiswa → KRS → baris KRS → nilai,
  presensi, tagihan …), memetakan ulang setiap FK ke salinannya, dan membuat
  kolom unik di luar FK berbeda. `AnggaranKueriSkalaTest` menghitung tiap
  layar ramai di demo, menumbuhkan kampus (kelas ×2, mahasiswa ×3, antrean
  lain ×3–4), menghitung lagi, dan menuntut angkanya tetap di bawah anggaran
  **dan** tidak melebihi hitungan demo lebih dari satu langkah eager load.
  Dijalankan pada kode sebelum perbaikan, tes ini gagal di tujuh layar —
  persis layar yang kuerinya tumbuh bersama data. (2) **Perbaikan dengan satu kueri per daftar**, bukan cache:
  agregat atau kueri berkelompok yang mengembalikan peta per baris, dengan
  metode per-baris lama tetap ada dan tes yang memaku keduanya memberi jawaban
  yang sama. Aturan akademik tidak berubah. (3) **Paginasi tampilan, bukan
  data**, bila layar butuh seluruh baris untuk laporan: jadwal ujian BAAK tetap
  menyusun seluruh sesi semester untuk laporan bentrok, hanya tabelnya yang
  dipaginasi. (4) **Indeks hanya bila rencana kueri menuntut.** Setiap SELECT
  selama audit dijalankan ulang dengan `EXPLAIN QUERY PLAN` pada kampus yang
  ditumbuhkan. Pemindaian penuh pada tabel besar hanya terjadi pada kolom FK
  — yang di SQLite tidak berindeks, tetapi di InnoDB selalu berindeks karena
  kendala FK mewajibkannya — sehingga tidak ada indeks baru. Satu-satunya
  predikat tak-terindeks pada tabel yang tumbuh tanpa batas (`whereDate` pada
  `log_aktivitas.created_at`) ditulis ulang menjadi rentang setengah terbuka.
  `whereDate` pada sumber kalender sengaja dibiarkan: tabelnya kecil, dan
  `UjianService` mencatat kenapa perbandingan tanggal polos tidak portabel.
- **Konsekuensi.** Tiga tes yang masing-masing menanam demo lalu
  menumbuhkannya — beberapa detik lebih lama per proses paralel. Data hasil
  penggandaan tidak realistis dan tidak dimaksudkan realistis: penghitung
  terdenormalisasi (`kelas_kuliah.terisi`) tidak ikut, salinan berbagi jadwal
  dengan aslinya sehingga laporan bentrok ujian membengkak, dan baris yang
  kunci uniknya hanya berisi FK yang tidak disalin (satu tagihan per mahasiswa
  per semester) dilewati. Untuk menghitung kueri itu cukup; untuk mengukur
  waktu tidak. Anggaran harus disesuaikan ketika sebuah layar sah menambah
  kueri — penyesuaiannya ditulis di komentar baris anggaran, seperti di
  `SmokeLayarTest`.
