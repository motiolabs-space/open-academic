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
