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
- **Diperbarui oleh** ADR-036 §2 (PISN menggantikan istilah dan alur PIN).

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

## ADR-035 — Lima celah P0: jalur tulis untuk data yang sudah dibaca kode (2026-10-28)

Inventaris modul 28 Oktober menemukan lima alur yang dibaca kode atau
dijanjikan dokumen tetapi tidak punya jalur tulis atau tampilan. Satu ADR,
satu bagian per celah.

### 1. Dosen wali: dosen aktif ber-homebase prodi yang sama; KRS mengikuti kolom

- **Konteks.** `mahasiswa.dosen_wali_id` memutuskan siapa yang boleh
  menyetujui KRS (`KrsService::putuskan()`, `KrsPolicy::approve`), tetapi
  hanya seeder demo yang menulisnya. Kampus nyata tidak dapat menjalankan
  persetujuan KRS sama sekali.
- **Keputusan.** `DosenWaliService` satu-satunya penulis kolom itu: per
  mahasiswa, per baris (massal), dan bagi merata per prodi/angkatan. (1)
  **Aturan ketat**: dosen wali harus dosen aktif yang homebase-nya prodi
  mahasiswa; mahasiswa lulus/DO/keluar/ganti prodi tidak diberi wali baru.
  Kampus yang mewalikan lintas prodi harus mengubah aturan ini di kode, bukan
  menembusnya dari layar. (2) **KRS mengikuti kolom, tidak ditulis ulang.**
  KRS tidak menyimpan dosen tujuannya; keputusan diperiksa terhadap wali
  *saat keputusan dibuat*. Maka KRS yang sudah diajukan otomatis pindah ke
  antrean wali baru begitu kolom berubah; jumlahnya dicatat di riwayat dan
  wali baru diberi tahu. (3) **Riwayat sebagai peristiwa**: tabel
  `riwayat_dosen_wali` (lama, baru, cara, alasan, KRS dialihkan, penetap),
  ditambah Log Aktivitas pada baris mahasiswa. (4) **Peran mengikuti tugas**:
  dosen yang diberi mahasiswa wali mendapat peran `dosen-wali` (pemegang
  `krs.approve`) bila belum. Tanpa itu antreannya tampil tetapi setiap
  keputusan ditolak. (5) **Bagi merata stabil**: kuota tiap dosen berselisih
  paling banyak satu; mahasiswa yang sudah dibimbing salah satu dosen terpilih
  dipertahankan selama kuotanya cukup, jadi menjalankan ulang tidak mengubah
  apa pun. (6) **Tanpa cakupan prodi untuk staf**: kode belum punya konsep
  staf per prodi (staf hanya ber-unit kerja), jadi izinnya `mahasiswa.manage`
  seperti layar mahasiswa lain. `MahasiswaPolicy::assignAdvisor` adalah
  tempat cakupan itu kelak.
- **Konsekuensi.** Penetapan massal semua-atau-tidak: satu baris tidak sah
  menolak seluruh kiriman dengan semua alasannya sekaligus. Mencabut wali
  tanpa pengganti tidak tersedia — mahasiswa terdaftar tanpa wali tidak dapat
  diputus KRS-nya, dan itu bukan keadaan yang perlu dibuat dengan sengaja.

### 2. Dispensasi pembayaran: berbatas tanggal, berakhir sendiri, dicabut per kemarin

- **Konteks.** `Tagihan::memenuhiSyaratKrs()` dan `DendaService` membaca
  `tagihan.dispensasi_sampai`, ROADMAP menjanjikan "ambang + dispensasi
  berbatas waktu", tetapi tidak ada kode yang menulis kolom itu.
- **Keputusan.** (1) `DispensasiService` satu-satunya penulis kolom itu;
  setiap pemberian, permohonan, penolakan, dan pencabutan adalah baris
  `dispensasi_tagihan` dengan pelaku dan alasan, ditambah Log Aktivitas pada
  tagihan. Kolom pada `tagihan` tetap satu-satunya yang dibaca gerbang dan
  denda, jadi keduanya tidak berubah. (2) **Tanggal wajib di masa depan,
  alasan wajib.** Dispensasi tanpa tanggal adalah pembebasan gerbang
  selamanya. (3) **Berakhir sendiri**: tidak ada tugas tengah malam; gerbang
  membandingkan tanggal dengan hari ini. (4) **Dicabut = berakhir kemarin**,
  bukan dikosongkan. Gerbang tertutup hari itu juga, dan jam denda mulai dari
  pencabutan. Mengosongkan kolom akan membuat `jatuhTempoEfektif()` kembali
  ke jatuh tempo asli dan menagih hari-hari yang tadinya dijanjikan bebas.
  (5) **Perpanjangan menggantikan**: memberi dispensasi saat masih ada yang
  berlaku menutup yang lama sebagai dicabut ("digantikan"), jadi paling
  banyak satu yang berlaku per tagihan. (6) Mahasiswa dapat **memohon** dari
  layar tagihan; keuangan menyetujui (boleh dengan tanggal lain) atau menolak
  dengan catatan wajib. Satu permohonan menunggu per tagihan; tagihan lunas
  tidak menerima dispensasi.
- **Konsekuensi.** Izin memakai `keuangan.manage` (policy
  `grantDispensation` yang sudah ada sejak Fase 1). Pengingat jatuh tempo
  belum memperhitungkan dispensasi: mahasiswa berdispensasi masih menerima
  pengingat berdasar jatuh tempo asli.

### 3. Pengumuman: satu scope untuk tiga portal, plus waktu berakhir

- **Konteks.** Pengumuman punya `target_roles`, tetapi hanya dasbor
  mahasiswa yang merendernya — dengan kuerinya sendiri. Pengumuman untuk
  dosen atau staf tidak pernah terlihat siapa pun, dan tidak ada halaman
  daftar di portal mana pun.
- **Keputusan.** (1) `Pengumuman::scopeTampilUntuk(UserRole)` satu-satunya
  definisi "yang tampil": terbit, belum berakhir, sesuai sasaran, sematan
  di atas lalu terbaru. Dasbor ketiga portal dan halaman daftar/detail
  membaca scope yang sama, lewat satu `PengumumanPortalController` (pola
  kalender) dan satu partial kartu. (2) **Kolom `berakhir_at`** (nullable):
  "belum kedaluwarsa" tidak dapat dinyatakan tanpa kolom itu, dan null
  mempertahankan perilaku lama (tampil sampai ditarik), jadi pengumuman lama
  tidak berubah. (3) **Papan staf terpisah dari layar kelola**: membaca
  untuk semua staf (`/admin/pengumuman/papan`), mengelola tetap menuntut
  `pengaturan.*`. (4) Pengumuman yang bukan sasaran portal pembaca, belum
  terbit, atau sudah berakhir dibalas 404, bukan 403 — bagi pembaca itu,
  pengumumannya tidak ada.
- **Konsekuensi.** Satu kueri tambahan di dasbor dosen dan staf; anggaran
  kueri `AnggaranKueriSkalaTest` tidak berubah. Isi pengumuman tetap teks
  polos (baris baru dipertahankan), bukan HTML — tidak ada CMS di sini.

### 4. `student.enrolled`: terbit saat mahasiswa lahir, sekali, sesudah commit

- **Konteks.** Event terdaftar di `config/bridge.php` dan `bridge.yaml`
  tetapi tidak pernah diterbitkan; enam event lain sudah. Mahasiswa baru
  terbentuk di dua tempat: daftar ulang PMB (`PmbService::daftarUlang`,
  yang juga dilalui pindahan lewat PMB) dan intake pindahan/RPL langsung
  (`PindahanService::daftarkanLangsung`). Adopsi dan klaim PDDIKTI (#44,
  #44b) hanya menautkan mahasiswa yang sudah ada.
- **Keputusan.** (1) `PenerbitPendaftaran::umumkan()` dipanggil di kedua
  titik lahir itu dan menerbitkan lewat `BridgeEventPublisher` yang sama
  dengan event lain (antre, HMAC). (2) **Sesudah commit**
  (`DB::afterCommit`), muatan dibangun dari baris saat itu. Pindahan lewat
  PMB mengoreksi `jenis_daftar` sesudah daftar ulang di dalam satu
  transaksi; event yang dibangun lebih awal akan mengumumkan pindahan
  sebagai mahasiswa baru, dan event untuk pendaftaran yang dibatalkan akan
  mengumumkan orang yang tidak ada. (3) **Sekali per mahasiswa**: kolom
  `mahasiswa.bridge_enrolled_at` diklaim dengan update bersyarat sebelum
  apa pun diantre, jadi panggilan kedua, percobaan ulang, atau dua
  permintaan yang berpacu tidak menerbitkan lagi. Kolom tetap diisi saat
  Bridge dimatikan — keputusan "sudah diumumkan" tidak menunggu Bridge
  dinyalakan. (4) **Adopsi dan klaim data lama tidak menerbitkan**: tidak
  ada yang mendaftar di sana. Mahasiswa yang sudah ada sebelum rilis ini
  tidak pernah menerima event; konsumen membacanya lewat `GET /students`.
  (5) Muatan sempit seperti `StudentResource` (identitas, prodi, angkatan,
  semester masuk, jenis daftar, status) dan kini didokumentasikan di
  `bridge.yaml` → `x-webhooks.payloads`; tes memaku kuncinya sama dengan
  spesifikasi.
- **Konsekuensi.** Konsumen yang ingin populasi lengkap tetap harus
  menarik `GET /students` sekali di awal; event hanya untuk mahasiswa baru
  sejak sekarang. Seeder demo memanggil intake pindahan sebelum konsumen
  demo dibuat, jadi demo segar tidak menghasilkan kiriman.

### 5. Detail mahasiswa staf: baca saja, bagian per izin, PDF dari service yang sama

- **Konteks.** `Admin\MahasiswaController` hanya daftar. Tidak ada halaman
  per mahasiswa di sisi staf, dan KHS/transkrip hanya dapat dibuka mahasiswa
  sendiri.
- **Keputusan.** (1) `/admin/mahasiswa/{uuid}` **hanya baca**: identitas
  (NIK tersamar), akademik, riwayat status dan cuti, dosen wali (dengan
  form ganti dari §1 dan riwayatnya), KRS dan KHS per semester, tagihan
  ringkas, kesiapan PDDIKTI (aturan `FeederValidator` yang sama dengan
  sinkronisasi) dan perubahan terkunci yang terbuka. Identitas yang dikunci
  PDDIKTI diubah lewat jalur PDM/PJK (ADR-017) — halaman ini menautkan ke
  konsol Feeder, tidak memberi tombol ubah. (2) **Bagian per izin**: nilai
  (KHS, transkrip, cetak) menuntut `nilai.view`, tagihan `keuangan.view`;
  staf keuangan melihat tagihan tanpa nilai, BAAK sebaliknya. (3)
  **`KhsService`** baru untuk layar dan PDF KHS satu semester, dipakai portal
  mahasiswa (yang kini juga dapat mencetak KHS) dan staf; transkrip memakai
  `TranskripService` yang sudah ada. KHS hanya dicetak untuk semester yang
  sudah ditutup. (4) Tanpa cakupan prodi: kode belum punya staf per prodi
  (lihat §1).
- **Konsekuensi.** Penyuntingan data mahasiswa oleh staf tetap tersebar di
  layar asalnya (pindahan, cuti, dosen wali, Feeder). Halaman detail
  mengumpulkan, tidak menggantikan.

## ADR-036 — Tiga perubahan regulasi: IKU 2026, PISN, Neo Feeder 3.0.1 (2026-10-29)

Komparasi modul 28 Oktober menemukan tiga perubahan regulasi/teknis yang belum
tercermin di kode. Satu ADR, satu bagian per perubahan; setiap bagian menyebut
sumber resminya dengan nomor saja — tidak ada dokumen kementerian yang masuk
repo (CLAUDE.md §10, ADR-011).

### 1. IKU 2026: blok `iku_2026` di samping kunci lama yang dibekukan

- **Konteks.** Kepmendiktisaintek 358/M/KEP/2025 (menggantikan Kepmendikbudristek
  210/M/2023; periode penilaian Januari–Desember) menetapkan dua belas IKU
  perguruan tinggi, sebagian wajib dan sebagian pilihan, dengan penomoran baru:
  1 Angka Efisiensi Edukasi, 2 lulusan bekerja/berwirausaha/studi lanjut,
  3 mahasiswa berkegiatan atau berprestasi di luar prodi, 4 rekognisi
  internasional dosen, 5 luaran kerja sama dan hilirisasi, 6 publikasi
  bereputasi, 7 SDGs, 8 SDM dalam penyusunan kebijakan, 9 pendapatan non-UKT,
  10 Zona Integritas, 11 tata kelola (opini audit, SAKIP, integritas akademik,
  anti-kekerasan/narkoba/korupsi — pilihan), 12 perencanaan kesejahteraan dosen.
  Diverifikasi dari salinan resmi keputusan itu. Kode, `CLAUDE.md` §6, dan
  `GET /iku-data` masih memakai penomoran delapan IKU: lulusan sebagai IKU 1,
  MBKM sebagai IKU 2, dosen di luar kampus 3, praktisi mengajar 4, kelas
  kolaboratif 7, ditambah "IKU 11 efisiensi" yang tidak pernah ada di daftar
  delapan itu. Open Campus mengonsumsi kunci-kunci itu.
- **Keputusan.** (1) **Aditif, bukan versi baru.** `iku-data` mendapat satu
  kunci baru, `iku_2026` (`App\Services\Bridge\DataIku2026`), berisi cacahan
  per IKU 2026 plus peta `ketersediaan` kedua belas IKU: `tersedia`
  (1, 3), `sebagian` (2 — status kerja dari tracer study; 4–6 — indeks,
  kuartil, bukti pemanfaatan, dan sitasi tidak dicatat di sini),
  `belum_tersedia` (8 — tidak ada jenis catatan keterlibatan dosen dalam
  penyusunan kebijakan), `di_luar_cakupan` (7, 9–12: program SDGs, keuangan
  institusi, ZI, audit, renstra). Konsumen yang mengabaikan medan tak dikenal
  tidak terganggu; `/api/bridge/v2` tidak dibuka untuk satu endpoint. (2)
  **Kunci lama dibekukan dan usang.** `iku_1_lulusan` … `iku_11_efisiensi`
  tetap ada dengan isi persis seperti sebelumnya, ditandai `deprecated` di
  `bridge.yaml` dengan padanannya, dan tidak menerima fakta baru. Dihapus
  hanya pada versi mayor Bridge, diumumkan di CHANGELOG paling sedikit satu
  periode penilaian penuh sebelumnya. Medan `iku: [3, 4]` pada penugasan
  dosen dan `berpotensi_iku5` di `lecturer-workload` diperlakukan sama. (3)
  **Tetap cacahan.** Bobot Kepmen 358 (pita SKS ≤5/6–10/>10 untuk IKU 3,
  peringkat prestasi, kuartil jurnal, bobot tracer study) tidak diterapkan;
  yang dikembalikan adalah pitanya. IKU 1 dibaca per **tahun akademik** tempat
  semester yang diminta berada — menjumlahkan dua respons semester akan
  menghitung mahasiswa lanjutan dua kali — dan lulusan dikelompokkan menurut
  lama studi dengan masa studi standar di sampingnya; pemotongan "tepat
  waktu" dan pengecualian (pindahan, DO, cuti berlebih) milik konsumen. (4)
  **Label tanpa nomor yang sudah tidak berarti.** Kelas kolaboratif (case
  method / team-based project) dan praktisi mengajar tidak punya padanan IKU
  2026, jadi layar menyebutnya apa adanya (`lang/id/iku.php`), bukan "IKU 7"
  atau "IKU 4". Ambang 50% bobot partisipatif + proyek tetap sebagai definisi
  kampus atas penanda itu (`JenisEvaluasi::AMBANG_KOLABORATIF_PERSEN`); kunci
  aturan validator `iku7_bobot_kurang` tidak diganti karena ia identifier
  yang tersimpan, bukan label.
- **Konsekuensi.** `CLAUDE.md` §6 kini memetakan IKU 2026 (perubahan instruksi
  proyek). Open Campus dapat pindah ke `iku_2026` kapan saja dalam masa
  transisi. Data IKU 8 menjadi butir ROADMAP. Anggaran kueri `iku-data`
  bertambah sejumlah kueri tetap (tidak tumbuh bersama baris).

### 2. PISN: istilah dan validator diperbarui; nama `pin` tetap dibaca sebagai alias usang

Memperbarui ADR-013.

- **Konteks.** ADR-013 ditulis untuk Penomoran Ijazah Nasional (PIN) dan
  menyebut alurnya "reservasi". Layanan yang berlaku kini PISN — Penomoran
  Ijazah dan Sertifikat Profesi Nasional — dengan dasar Permendikbudristek
  50/2024 (yang mencabut Permendikbudristek 6/2022; nomor ijazah dan nomor
  sertifikat profesi nasional diterbitkan sistem kementerian atas permohonan
  berlampiran bukti kelulusan dan SPTJM; ijazah yang dikoreksi mendapat nomor
  baru, yang diterbitkan ulang memakai nomor lama). Diverifikasi dari materi
  resmi Direktorat Pembelajaran dan Kemahasiswaan Ditjen Dikti (paparan
  penerapan PISN 2024, disebarkan LLDIKTI), surat Ditjen Dikti
  4889/B2/DT.00.08/2025 dan 1037/B.B2/DT.00.08/2025, dan materi sosialisasi
  LLDIKTI. Yang berbeda dari asumsi ADR-013: (a) nomor **terbit sesudah**
  mahasiswa berstatus Lulus di PDDIKTI dengan tanggal lulus terisi, nomor
  ijazah di PDDIKTI masih kosong, dan seluruh validator PISN lolos
  ("eligible"); yang tidak lolos diperbaiki di PDDIKTI atau diajukan eksepsi
  — kecuali akreditasi, yang tanpa eksepsi; (b) nomor 21 digit: kode PT (6),
  kode prodi (5), tahun terbit (4), tipe (1 = ijazah, 2 = sertifikat profesi),
  nomor urut (5); (c) nomor yang terbit dikirim PISN ke PDDIKTI dan dapat
  diverifikasi di laman PISN; (d) "pemutihan" adalah klaim nomor sertifikat
  profesi yang sudah dilaporkan antara Februari 2022 dan akhir 2024, bukan
  pembatalan; (e) masa transisi modul sertifikat sampai akhir Desember 2024,
  semua prodi memakai PISN sejak semester genap 2024/2025; (f) sejak
  1 November 2025 validatornya mengikuti Permendiktisaintek 39/2025: IPK
  minimum, SKS tempuh minimum, beban SKS per semester dan semester antara,
  dan masa studi per jenjang. Ringkasan sekunder yang menyebut alur
  "pembatalan" tidak menemukan padanan resmi; yang ada koreksi (nomor baru)
  dan eksepsi.
- **Keputusan.** (1) **Istilah.** Semua yang terlihat pengguna menyebut PISN
  (`lang/id/wisuda.php`, layar Yudisium dan Wisuda, pesan validator Feeder),
  dan kelas yang namanya terbaca di kode diganti: `KesiapanPisnService`,
  `KesiapanPisn`, kode syarat wisuda `pisn`. Tanpa migrasi — tidak ada kolom
  bernama PIN; `wisuda_peserta.nomor_ijazah` tetap. (2) **Config.**
  `ACADEMIC_IJAZAH_SUMBER` bawaannya `pisn`; `pin` tetap dibaca sebagai
  `pisn` (`App\Enums\SumberNomorIjazah`), ditandai usang di config,
  `.env.example`, dan peringatan di layar Wisuda; berhenti diterima pada rilis
  mayor berikutnya. Nilai tak dikenal juga dibaca `pisn` — bawaan yang aman
  ADR-013 tidak berubah. (3) **Daftar periksa mengikuti validator PISN.**
  Ditambah: prodi terakreditasi, IPK minimum per jenjang, beban SKS per
  semester (D1–S1: 20 pada semester 1–2, 24 sesudahnya) dan semester antara
  (9), masa studi (semester berjalan sejak semester masuk sampai semester
  lulus, cuti ikut terhitung — jalur eksepsi masa studi meminta bukti cuti),
  dan catatan semester pertama pada semester masuk. SKS minimum kini per
  jenjang (D3 108, bukan 144 kampus-luas). Angka-angka itu di
  `config('academic.ijazah.pisn')`, bukan di kode: sudah berubah sekali pada
  November 2025. (4) **Struktur nomor diperiksa saat dicatat** di mode PISN:
  21 digit, kode PT sama dengan `BRAND_INSTITUTION_CODE` dan kode prodi sama
  dengan `prodi.kode` (masing-masing hanya bila berbentuk kode PDDIKTI), tahun
  masuk akal, digit tipe sesuai jenjang (profesi/spesialis = 2). Lulusan
  dengan tanggal lulus sebelum 2025 tidak diperiksa: nomornya dari skema
  lama. (5) **Lulus/DO tidak mengirim nomor ijazah** (sudah begitu sejak
  ADR-013); kini dinyatakan sebagai alur yang benar, bukan kebetulan: PISN
  yang menulis nomor ke PDDIKTI.
- **Konsekuensi.** Instalasi dengan `.env` berisi `pin` tidak berubah
  perilakunya, hanya melihat peringatan. Lulusan yang sebelumnya tampil
  "Siap" dapat berubah menjadi belum siap karena butir baru (akreditasi
  belum dicatat, semester masuk tidak tercatat) — itu temuan, bukan regresi.
  Akreditasi yang kedaluwarsa tidak terdeteksi karena tanggal berlakunya
  tidak dicatat. Validator untuk profesi/spesialis non-medis (masa studi
  ditetapkan kampus) tidak diperiksa di sini.

### 3. Neo Feeder 3.0.1 dan 3.1: hanya yang menyentuh data yang kita kirim, dengan tingkat keyakinan yang dinyatakan

- **Konteks.** Neo Feeder 3.0.1 dirilis Pusdatin pada Juli 2025 dan 3.1 pada
  Mei 2026. **Tidak ada catatan rilis resmi yang dapat diakses publik** untuk
  keduanya: halaman LLDIKTI hanya mengumumkan bimtek dan pemeliharaan, dan
  daftar perubahan yang beredar berasal dari penyedia SIAKAD, konsultan, dan
  blog operator. Yang terverifikasi resmi hanyalah alur kelulusan: materi
  sosialisasi LLDIKTI menyuruh meluluskan mahasiswa di Neo Feeder **tanpa**
  nomor ijazah, lalu PISN mengisinya (lihat §2). Sisanya — nomor HP wajib,
  NUPTK pada data dosen, tanggal ijazah pada Lulus/DO, jenis daftar baru dan
  re-NIM, AKM menghitung SKS transfer dan konversi aktivitas, komponen
  evaluasi dapat dihapus, sinkron per kelompok tabel, OTP lewat surel, akun
  WS khusus, validasi zona waktu WIB (3.0.1); AKM wajib sebelum Lulus/DO, HP
  dan surel wajib serta unik (3.1) — bersumber sekunder, walau beberapa
  sumber independen sepakat.
- **Keputusan.** Diaudit terhadap mapper, `FeederValidator`,
  `config/feeder.php`, dan `FakeFeederClient`; hanya yang menyentuh data
  kiriman kita yang diubah, dan keparahannya mengikuti keyakinan. (1) **Nomor
  HP kosong menjadi galat** pada biodata: disebut oleh setiap sumber dan oleh
  kedua versi, dan biodata tanpa HP akan ditolak. Nomor yang tidak berbentuk
  telepon (9–15 digit) atau dipakai dua mahasiswa hanya **peringatan**. Surel
  di sini adalah login — tidak pernah kosong dan unik oleh skema — jadi hanya
  bentuknya yang diperiksa. (2) **Lulus/DO tanpa satu pun catatan AKM menjadi
  galat**: dilaporkan untuk 3.1, dan tidak ada alasan sah untuk meluluskan
  mahasiswa tanpa riwayat semester. Yang dihitung catatan lokal, bukan yang
  sudah terkirim, karena AKM semester itu boleh terkirim dalam putaran yang
  sama. (3) **AKM sudah benar**: `sks_semester` menjumlahkan baris KRS
  semester itu termasuk yang dikonversi dari aktivitas, `sks_total` diambil
  dari `PerolehanAkademik` yang memuat SKS transfer/RPL; kini dipaku tes,
  tanpa perubahan kode. (4) **Tidak diubah**: NUPTK (dosen tidak dikirim
  lewat WS — dibaca dari `GetListDosen`), tanggal dan nomor ijazah pada
  Lulus/DO (nama medan tanggal belum terverifikasi; nomor memang tidak
  dikirim), jenis daftar (dipetakan dari referensi yang ditarik, enum lokal
  sudah mencakup daftar resmi LLDIKTI), penghapusan komponen evaluasi (aksi
  hapus WS belum terverifikasi), tanggal (sudah `Y-m-d` dengan zona
  `Asia/Jakarta`). `FakeFeederClient` tidak mensimulasikan aturan per medan;
  gerbangnya validator. (5) **Teks operator** yang menyebut OTP WhatsApp
  diganti: otorisasi sinkron ke pusat kini lewat surel.
- **Konsekuensi.** Mahasiswa tanpa nomor HP kini tertahan di validasi biodata
  — temuan yang memang akan ditolak Feeder. Tindak lanjut tercatat di
  ROADMAP #52–#55: nama medan HP (`telepon_seluler`) perlu dicocokkan dengan
  kamus web service sebuah instalasi Neo Feeder; tanggal ijazah; changelog
  resmi 3.1; alur re-NIM. Snapshot payload tidak berubah karena payload tidak
  berubah.

## ADR-037 — Ekspor SIMKATMAWA dan data lulusan untuk tracer study nasional (2026-10-29)

Pemeriksaan kompatibilitas 29 Oktober 2026 dengan enam belas sistem
Kemdiktisaintek menemukan dua yang belum didukung sama sekali, dan keduanya
kini sumber IKU 2026 (ADR-036 §1): SIMKATMAWA (IKU 3, prestasi) dan tracer
study nasional (IKU 2, lulusan). Kolom dan aturan di bawah ditulis ulang dari
panduan publiknya dengan kata-kata sendiri — Panduan SIMKATMAWA 2026 (Belmawa,
Februari 2026) dan sosialisasi integrasi tracer study (Belmawa, Agustus 2026).
Tidak ada panduan, templat, atau contoh berkas kementerian yang masuk repo
(CLAUDE.md §10, ADR-011); catatan riset di `docs/lokal/`.

### 1. SIMKATMAWA: ekspor empat tabel dari buku besar prestasi, tanpa pengiriman

- **Konteks.** SIMKATMAWA menilai kemahasiswaan kampus dari prestasi
  kompetisi (tingkat kabupaten/kota sampai internasional, peringkat,
  individu/kelompok, daring/luring, bidang, jumlah PT atau negara peserta),
  rekognisi nonkompetisi (karya, juri/pelatih, pembicara utama, buku, paten,
  publikasi, duta), dan sertifikat kompetensi, masing-masing dengan tautan
  bukti yang dapat dibuka penilai. Data sumbernya sudah ada di
  `poin_mahasiswa` (prestasi terverifikasi, juga dikirim ke Feeder lewat
  `PrestasiMapper`), tetapi buku besar poin dibangun untuk syarat kelulusan:
  judul, tanggal, kategori katalog, dan poin. Tingkat katalog
  (internal/lokal/nasional/internasional) tidak dapat membedakan lomba
  kabupaten dari provinsi.
- **Keputusan.** (1) **Kolom baru di baris prestasi, bukan tabel baru**
  (migrasi append-only `2026_10_29_100000`): tingkat SIMKATMAWA, peringkat,
  bentuk kepesertaan, moda, bidang lomba, jenis rekognisi, penyelenggara,
  tanggal selesai, jumlah PT dan negara peserta, nomor sertifikat, tiga
  tautan (sertifikat, bukti, surat tugas), dan dosen pembimbing (FK
  `restrictOnDelete`). Semuanya nullable: baris lama tetap sah, dan layar
  laporan yang menyebut apa yang kurang. Prestasi yang sama tidak dicatat dua
  kali untuk dua tujuan. (2) **Tabel SIMKATMAWA ditetapkan per kategori
  katalog** (`poin_kategori.simkatmawa_kategori`: kompetisi, rekognisi,
  sertifikasi, atau kosong = tidak dilaporkan), bukan per baris — staf yang
  mencatat empat puluh juara lomba nasional tidak memilih tabelnya empat
  puluh kali. Pelanggaran tidak pernah dilaporkan. Tabel keempat,
  pembimbing, diturunkan dari prestasi yang punya dosen pembimbing, bukan
  dicatat. (3) **Validasi pra-ekspor seperti Feeder** (`EksporSimkatmawa`):
  setiap baris diperiksa sekaligus; *galat* (tingkat tak dikenal atau tak
  diterima kategorinya, penyelenggara kosong, tanpa tautan sertifikat, isian
  wajib kompetisi kosong, jumlah PT/negara kosong, tautan bukan http(s),
  nomor sertifikat kosong) membuat baris **tidak ikut terunduh**, karena
  kementerian akan menolaknya; *peringatan* (tanpa tautan bukti, tanpa surat
  tugas atau pembimbing, mahasiswa belum di PDDikti, judul berpenanda pameran
  di tingkat internasional) tetap terunduh dengan skor yang mungkin lebih
  rendah. Tingkat nasional/internasional katalog dipakai bila baris kosong;
  `lokal` tidak ditebak, `internal` tidak dilaporkan. (4) **Ekspor saja.**
  CSV lewat `App\Support\Csv` (perlindungan injeksi rumus, PR #30) dan XLSX
  lewat `LembarKerjaXlsx` (sel teks inline yang tidak pernah dievaluasi) —
  keduanya lewat `App\Support\EksporTabel`. Tidak ada klien, kredensial, atau
  pengiriman otomatis ke SIMKATMAWA. (5) **Bobot dan skor tidak dihitung.**
  Seperti `iku-data`, yang disiapkan fakta; skor SIMKATMAWA milik kementerian.
  Indeks kompleksitas dan dampak rekognisi (penilaian penilai) tidak dicatat.
- **Konsekuensi.** Kampus mengisi tabel SIMKATMAWA katalognya sekali, lalu
  melengkapi prestasi dari layar Lengkapi. Kegiatan MBKM tetap mengalir ke
  IKU 3 lewat Feeder dan `iku-data`, bukan lewat SIMKATMAWA. Kelompok lomba
  tercatat per mahasiswa (satu baris per anggota); SIMKATMAWA menerima
  anggota satu per satu. `PrestasiMapper` kini mengirim `penyelenggara` dari
  kolomnya sendiri, dengan `keterangan` sebagai cadangan untuk baris lama.
  Izin: `mahasiswa.manage` (`PoinMahasiswaPolicy`).

### 2. Tracer study nasional: Open Academic menyiapkan populasinya dan menyimpan hasil status pekerjaan; Open Campus tetap pemilik kuesioner

- **Konteks.** Tracer study kini terpusat di laman Kemdiktisaintek: alumni
  masuk dengan NIM dan data yang diverifikasi kampus. Di sini `alumni`
  dibuat `YudisiumService` dan hanya terlihat lewat Bridge `graduates` dan
  `iku-data`; `alumni.status_pekerjaan` hanya pernah diisi `belum` (artinya
  *belum ditelusuri*, bukan *belum bekerja*). Surel alumni disalin dari surel
  akun kampus yang dapat mati setelah lulus, dan formulir profil mahasiswa
  sudah mengirim `email_pribadi` ke kolom yang tidak pernah ada. Kuesioner
  tracer dan perhitungan IKU milik Open Campus (CLAUDE.md §1, §6).
- **Keputusan.** (1) **Pemilik data status pekerjaan: sumber jawabannya,
  bukan sistem ini.** Jawaban dikumpulkan di luar Open Academic — oleh tracer
  study nasional atau oleh kuesioner Open Campus. Open Academic menyimpan
  **hasilnya** di baris alumni sebagai catatan resmi lulusan, beserta
  sumbernya (`status_pekerjaan_sumber`: tracer nasional, Open Campus, survei
  kampus) dan tanggal surveinya (`status_pekerjaan_per`), karena hanya di
  sini status itu dapat bersanding dengan yudisium dan diumpankan ke
  `iku-data`. Penulisnya satu jalur: **impor CSV** (`ImporStatusPekerjaan`);
  tidak ada formulir untuk mengetik status satu per satu, supaya tidak ada
  jawaban tanpa sumber. Open Campus mengirim hasilnya dalam bentuk berkas
  yang sama — tidak ada endpoint tulis Bridge baru. (2) **Validasi pra-impor
  dan idempoten.** Berkas diperiksa seluruhnya dulu (kolom wajib
  `nim`/`status`, NIM dikenal dan sudah lulusan, NIM ganda, status dikenali
  dari kode atau label kuesioner, tanggal, masa tunggu 0–120 bulan, tanggal
  survei tidak di masa depan); setiap baris ditolak dengan nomor baris dan
  alasannya, tanpa menahan baris yang sah. Baris yang sama dengan data
  tersimpan dihitung *tidak berubah*; baris dengan tanggal survei lebih lama
  dari yang tersimpan ditolak, supaya tracer tahun lalu tidak menimpa tahun
  ini. Penerapan memeriksa ulang dan berjalan dalam satu transaksi; setiap
  perubahan tercatat lewat `HasLogAktivitas` (kini dipasang di `Alumni`). (3)
  **Kontak dan ekspor.** Layar Lulusan menandai kontak: surel pribadi kosong
  atau hanya surel kampus, telepon kosong atau tidak berbentuk nomor
  Indonesia, belum atau lebih dari 12 bulan sejak diverifikasi
  (`KEMAHASISWAAN_LULUSAN_VERIFIKASI_BULAN`). Kontak yang dikonfirmasi staf
  ditulis ke baris alumni, bukan ke data mahasiswa yang sudah diterima
  PDDIKTI. Ekspor tracer memuat NIM, nama, prodi, jenjang, tahun masuk dan
  lulus, tanggal lulus, surel, telepon, dan tanggal verifikasi — **tanpa**
  NIK, alamat, dan tanggal lahir; kontak alumni yang menolak dibagikan
  (`kontak_tidak_dibagikan`) dikosongkan tetapi lulusannya tetap tercantum,
  karena populasi tracer harus utuh. (4) **Akses bertingkat.** Tiga izin
  baru: `alumni.view` (daftar dan kelengkapan, kontak tersamar — pimpinan),
  `alumni.manage` (melihat dan memverifikasi kontak, impor — BAAK),
  `alumni.ekspor` (unduhan — BAAK). Setiap unduhan wajib menyebut keperluan
  dan dicatat di Log Aktivitas (subjek `EksporTracer`: jumlah, filter,
  format, keperluan). (5) **Bridge menyajikan, tidak menghitung.**
  `iku_2026.iku_2_lulusan` kini memuat `per_status_pekerjaan` (null dan
  `belum` digabung sebagai `belum_ditelusuri`), `ditelusuri`,
  `per_sumber_status`, dan pita `masa_tunggu_bulan` (0–6, 7–12, 13+, tidak
  diketahui) untuk yang bekerja atau berwirausaha. `GET /graduates` menambah
  `status_pekerjaan_sumber` dan `status_pekerjaan_per`. Kunci lama
  `iku_1_lulusan` tetap membaca nilai tersimpan apa adanya (dibekukan,
  ADR-036). Bobot, ambang gaji, dan persentase IKU 2 tetap milik Open Campus.
- **Konsekuensi.** `mahasiswa.email_pribadi` dibuat (memperbaiki formulir
  profil yang selama ini gagal menyimpan), dan `YudisiumService` memakai surel
  pribadi lebih dulu, baru surel akun. Bila kelak Open Campus perlu mengirim
  status lewat API alih-alih berkas, endpoint itu harus menulis lewat
  `ImporStatusPekerjaan` yang sama. Kategori status mengikuti instrumen tracer
  lama (bekerja, wiraswasta, studi lanjut, mencari kerja, belum memungkinkan
  bekerja); bila instrumen 2026 berbeda, `StatusPekerjaanAlumni::dariTeks()`
  yang diperbarui. Instalasi berjalan: `php artisan migrate` dan
  `db:seed --class=RolePermissionSeeder`.

## ADR-038 — Kurikulum berbasis capaian: CPL → CPMK → sub-CPMK, penilaian per CPMK, capaian tersimpan (2026-10-29)

Komparasi modul 28 Oktober menemukan kurikulum OBE sampai CPMK sebagai
kesenjangan yang diiklankan pembanding komersial dan diminta akreditasi. Open
Academic baru punya CPL prodi (`prodi_cpl`), RPS dengan CPL dibebankan
(`rps_cpl.rumusan` sebagai teks CPMK bebas, `rps_pertemuan.kemampuan_akhir`
sebagai teks sub-CPMK bebas), dan pemetaan komponen nilai langsung ke CPL
(`komponen_nilai_cpl`) yang hanya ditulis seeder — tanpa layar. Angka
penguasaan dihitung ulang setiap kali layar dibuka.

### 1. Hierarki berversi per kurikulum, beku setelah berlaku

- **Konteks.** CPMK adalah janji sebuah mata kuliah; mata kuliah yang sama di
  kurikulum 2022 dan 2026 dapat menjanjikan hal berbeda. Di dalam satu
  kurikulum, rumusannya direvisi — dan nilai yang sudah diukur terhadap
  rumusan lama tidak boleh ikut berubah artinya.
- **Keputusan.** `cpmk_versi` per (kurikulum, mata kuliah) dengan siklus
  draf → berlaku → diarsipkan (`StatusCpmk`), satu yang berlaku lewat
  `kunci_aktif` nullable-unique (pola `rps`). `cpmk` → `sub_cpmk` (bobot
  per seratus terhadap induk) dan `cpmk_cpl` (bobot per seratus kontribusi
  CPMK ke CPL). Versi berlaku tidak dapat disunting siapa pun; revisi adalah
  draf baru yang menyalin baris berlaku menjadi baris **baru**, lalu
  diterbitkan dan mengarsipkan yang lama tanpa menyentuh barisnya. Syarat
  terbit, semuanya disebut sekaligus: sedikitnya satu CPMK, setiap CPMK
  terkait ke CPL, sub-CPMK satu CPMK berjumlah 100, dan untuk setiap CPL
  bobot CPMK yang mendukungnya berjumlah 100. Kurikulum sebuah mata kuliah
  dibaca dari pivot `kurikulum_mata_kuliah`: kurikulum aktif prodinya, lalu
  yang terbaru. Bobot bilangan bulat, tidak ada float.
- **Konsekuensi.** Draf boleh setengah jadi; tidak ada yang merujuk baris
  draf (pemetaan dan RPS hanya ke versi berlaku), jadi menyimpan draf
  mengganti seluruh barisnya tanpa kehilangan apa pun.

### 2. Dua penyusun, satu penyunting

- **Keputusan.** Prodi (staf `master.manage`, layar `/admin/cpmk`) dan dosen
  yang mengampu mata kuliah itu pada semester aktif (dari RPS,
  `/dosen/rps/cpmk/{mk}`) — `CpmkVersiPolicy`. Keduanya memakai satu Blade
  penyunting dan satu service. Dosen lain dan staf tanpa izin ditolak; staf
  `master.view` membaca saja. Yang menerbitkan dicatat namanya.
- **Konsekuensi.** Tidak ada alur persetujuan kaprodi terpisah; bila kampus
  membutuhkannya, itu langkah di antara draf dan terbit, bukan perubahan
  skema.

### 3. Penilaian berbasis CPMK dan aritmetikanya

- **Keputusan.** `komponen_nilai_cpmk`: setiap komponen memetakan ke satu
  atau lebih **sasaran** dengan porsi per seratus yang berjumlah 100 (atau
  tidak dipetakan sama sekali). Sasaran adalah sub-CPMK bila CPMK-nya punya
  sub, selain itu CPMK itu sendiri — tidak pernah dua tingkat untuk satu
  CPMK, supaya satu skor tidak terhitung dua kali. Hanya versi yang berlaku
  untuk kelas yang dapat dipetakan: versi RPS kelas itu, atau versi berlaku
  mata kuliahnya; kelas final dibekukan pada versi pemetaannya. Layar rencana
  evaluasi menampilkan matriks komponen × sasaran dan **memperingatkan
  sub-CPMK yang tidak diukur komponen mana pun**, juga komponen tanpa
  pemetaan dan pemetaan ke versi yang sudah diarsipkan. Untuk satu mahasiswa
  satu kelas: bobot baris = bobot komponen × porsi; sasaran = rerata terbobot
  skornya; CPMK = rerata sub-CPMK terbobot bobot sub, **atas sub yang
  diukur saja**, dengan `cakupan` = jumlah bobot sub yang diukur (sub yang
  tidak dinilai tidak dihitung nol terhadap mahasiswa); CPL dalam mata kuliah
  itu = rerata CPMK terbobot bobot CPMK→CPL atas CPMK yang terukur. Lintas
  mata kuliah, CPL mahasiswa = SUM(nilai × sks) / SUM(sks).
- **Konsekuensi.** Pemetaan langsung ke CPL (`komponen_nilai_cpl`, ADR RPS
  P1) tidak dihapus: kelas lama tetap terbaca di lensa penguasaan berjalan
  dan di `cplTanpaPengukur`, yang kini juga menghitung CPL yang tercapai
  lewat CPMK. Seeder demo tidak lagi memetakan langsung kelas yang sudah
  dipetakan ke CPMK, supaya skor yang sama tidak terbaca dua kali.

### 4. Disimpan saat final, berubah hanya lewat koreksi ter-audit

- **Keputusan.** `capaian_cpmk_mahasiswa` (nilai, cakupan) dan
  `capaian_cpl_mahasiswa` (nilai, sks) per baris KRS, ditulis
  `CapaianService::hitungKelas()` **di dalam transaksi finalisasi**. Perhitungan
  pertama disisipkan massal dan diaudit sebagai satu peristiwa
  `capaian_dihitung` pada kelas — ratusan baris yang belum pernah berubah
  tidak perlu ratusan job audit. Jalur koreksi nilai mendapat cabang baru:
  `PenilaianService::koreksiKomponen()` mengoreksi skor komponen, menghitung
  ulang nilai akhir dengan bobot kelas, lalu menghitung ulang capaian baris
  itu lewat model — setiap angka yang bergeser tercatat dengan nilai lama dan
  barunya, dan nilai mendapat satu jejak `corrected` yang menyebut komponen
  dan alasannya. Koreksi **nilai akhir saja** (jalur lama) tetap ada dan
  **tidak** menggeser capaian: capaian diukur dari komponen, dan total yang
  dikoreksi tanpa komponen tidak berkata apa-apa tentang capaian mana yang
  keliru. Layar koreksi menyatakan perbedaan itu.
- **Konsekuensi.** Layar dan ekspor membaca baris tersimpan; tidak ada yang
  menghitung ulang dari skor. Pemetaan dibekukan bersama kelasnya.

### 5. RPS satu sumber

- **Keputusan.** `rps.cpmk_versi_id` dan `rps_pertemuan.sub_cpmk_id`. RPS
  baru memasang versi berlaku mata kuliahnya; draf RPS yang masih terbuka
  pindah ke versi yang baru terbit (tautan sub-CPMK minggu yang tidak lagi
  ada dilepas, teksnya tetap). CPL RPS berversi diturunkan dari CPMK —
  `rps_cpl` ditulis sebagai cerminannya, karena layar RPS, analitik kelas,
  dan RPS lama membacanya — dan `simpanCpl()` menolak isian terpisah. Layar susun RPS
  menampilkan CPMK/sub-CPMK versi itu (baca saja; disunting di layarnya
  sendiri) dan pilihan sub-CPMK per pertemuan. RPS tanpa CPMK berlaku tetap
  memilih CPL langsung seperti sebelumnya.

### 6. Tampilan, ekspor, dan LKPS — bukan dasbor

- **Keputusan.** Rekap per kelas untuk dosen pengampu (`/dosen/capaian/{kelas}`),
  per prodi per angkatan untuk staf `nilai.view` (`/admin/capaian`), dan per
  mahasiswa di `/mahasiswa/capaian` (CPL lintas mata kuliah dan CPMK per mata
  kuliah dari nilai final, di samping lensa penguasaan berjalan untuk kelas
  yang dipetakan langsung). CSV lewat `App\Support\Csv`. Policy
  `CapaianCplMahasiswaPolicy` per portal. Perakit LKPS mendapat tabel
  `capaian_pembelajaran` (per CPL: terukur, rerata, mencapai ambang) dari
  baris tersimpan dalam deret tahun borang, dengan alasan — bukan nol — bila
  belum ada. Tanpa target, tanpa perbandingan antarprodi, tanpa skor
  akreditasi (CLAUDE.md §1, §6).
- **Konsekuensi.** Mapper Feeder komponen evaluasi tidak berubah: PDDIKTI
  tidak menerima CPMK, dan payload yang tidak berubah tidak mengubah snapshot.

**Diperbarui juga:** `CLAUDE.md` §11 baris Fase 4 kini memakai penomoran IKU
2026 (§6, ADR-036, ADR-041), bukan "IKU 1/2/3/4/7".

## ADR-039 — Empat layar pengelolaan: kuesioner, konsumen Bridge, klien SSO, tiket staf (2026-10-30)

Inventaris kode 28 Oktober 2026 menemukan empat alur yang mesinnya ada tetapi
layarnya tidak: kuesioner umum (ROADMAP menandainya ✅ tanpa satu rute pun),
konsumen Bridge dan klien SSO (hanya lewat artisan), dan staf yang tidak dapat
membuka tiket helpdesk. Satu ADR, satu bagian per layar.

### 1. Kuesioner: terbit pertama membekukan; hasil anonim tertahan di bawah ambang

- **Konteks.** `KuesionerService` hanya punya `isi()` dan `hasil()`. Layar kelola
  harus memutuskan kapan instrumen boleh disunting, dan layar hasil harus
  mempertahankan janji skema dua tabel — padahal agregat dari dua responden atau
  urutan isian bebas sudah cukup untuk mengenali orang.
- **Keputusan.** (1) Terbit pertama (`diterbitkan_at`) membekukan pertanyaan,
  sasaran, prodi, dan ambang; nama, pengantar, dan periode tetap dapat diubah;
  `anonim` tidak pernah dibaca saat memperbarui. (2) Sasaran = satu peran,
  dipersempit ke satu prodi (`prodi_id`, staf tidak berprodi), diperiksa policy
  *dan* `isi()`. (3) Ambang responden EDOM dibawa ke sini (`min_responden`,
  bawaan `KUESIONER_MIN_RESPONDEN=5`, disimpan per kuesioner); di bawahnya hasil
  dan ekspor kuesioner anonim tidak dihitung sama sekali. (4) Isian bebas
  diurutkan abjad; ekspor anonim per pertanyaan (cacah per nilai/opsi, teks
  terurut) tanpa id, waktu, atau nama; ekspor beridentitas satu baris bernama
  per responden. (5) `isi()` kini memvalidasi rentang skala dan keanggotaan
  opsi, dan tidak menyimpan isian opsional yang kosong sebagai jawaban.
- **Konsekuensi.** Kampus yang ingin mengubah pertanyaan sesudah terbit membuat
  kuesioner baru — hasil lama tetap menjawab pertanyaan lama. Daftar siapa yang
  mengisi kuesioner anonim tidak ditampilkan; hanya jumlahnya. Sisa risiko di
  tingkat tabel (id dan `created_at` jawaban anonim berurutan sejalan dengan
  partisipasi, terbaca oleh pemegang akses basis data) tidak diperlihatkan layar
  mana pun dan dicatat sebagai butir ROADMAP tersendiri.

### 2. Kredensial integrasi: izin sendiri, tampil sekali, rahasia webhook terenkripsi

- **Konteks.** `bridge.manage` (kirim ulang webhook) hanya dipegang super-admin,
  tetapi menerbitkan token adalah hibah membaca data mahasiswa secara massal —
  kepercayaan yang berbeda dari mengulang pengiriman. Rahasia webhook tersimpan
  polos di `bridge_consumers` (sudah keluar dari log sejak ADR-033, tetapi tidak
  dari dump basis data).
- **Keputusan.** (1) Izin baru `integrasi.kredensial` untuk konsumen Bridge dan
  klien SSO sekaligus; dipegang super-admin dan peran baru `tik` (Bridge,
  kredensial, log, antrean helpdesk sendiri — tanpa data akademik/keuangan).
  (2) Token dan rahasia muncul sekali: nilai polos hanya di nilai kembali
  service, di-flash ke satu halaman yang dikirim `Cache-Control: no-store`.
  Token Sanctum tersimpan sebagai SHA-256; rahasia webhook tidak dapat di-hash
  (dipakai menandatangani), jadi di-cast `encrypted` dan baris lama dienkripsi
  migrasi `2026_10_29_400100` (kolom melebar ke `text`). (3) Log mencatat
  peristiwa (`token_issued`, `token_revoked`, `webhook_secret_rotated`) dengan
  nama token, tidak pernah nilainya. (4) Token dicabut lewat konsumennya
  (`$konsumen->tokens()->whereKey()`), bukan dari id saja. (5)
  `openacademic:bridge-token` memakai service yang sama. (6) URL webhook harus
  HTTPS kecuali loopback (`UriCallbackAman`, aturan yang sama dengan redirect
  SSO).
- **Konsekuensi.** Instalasi berjalan perlu menjalankan ulang
  `RolePermissionSeeder` dan memberi peran `tik` kepada operator TI. Rotasi
  rahasia webhook langsung mematahkan verifikasi di sisi konsumen sampai ia
  diperbarui — layar mengatakannya sebelum tombol ditekan. Mengganti `APP_KEY`
  kini juga mengharuskan rahasia webhook dirotasi.

### 3. Klien SSO: catatan kampus di samping tabel Passport; pihak pertama akhirnya dihormati

- **Konteks.** Layar SSO butuh tiga hal yang tidak dimiliki `oauth_clients`:
  penanda pihak pertama, siapa yang mendaftarkan, dan jejak audit —
  `HasLogAktivitas` menuntut kunci integer, sedangkan id klien UUID. Yang
  tersingkap: `SSO_FIRST_PARTY` didokumentasikan sebagai "melewati layar
  persetujuan", tetapi tidak ada kode yang membacanya saat otorisasi.
  `Client::skipsAuthorization()` milik Passport selalu `false`.
- **Keputusan.** (1) Tabel `sso_klien` (satu baris per klien, dibuat saat
  pertama disentuh bagi klien lama) memegang `is_first_party`, pendaftar,
  `rahasia_dirotasi_at`, dan `dicabut_at`, diaudit lewat trait biasa. (2)
  `KlienOAuth extends Passport\Client`, didaftarkan `Passport::useClientModel()`,
  melewati persetujuan bila id-nya ada di `SSO_FIRST_PARTY` *atau* penandanya
  menyala, dan tidak pernah untuk klien yang dicabut. (3) Mencabut klien final:
  `revoked`, lalu `PencabutanTokenSso` mencabut token akses beserta refresh
  token-nya; klien yang tercabut keliru didaftarkan ulang. (4) Rotasi memakai
  `ClientRepository::regenerateSecret()`; rahasia ter-hash Passport, tampil
  sekali. (5) "Pengguna mengizinkan" = orang berbeda dengan izin yang masih dapat
  dipakai (token akses belum kedaluwarsa, atau refresh token-nya masih hidup),
  satu kueri berkelompok. (6) Rute layar hanya terdaftar bila `SSO_ENABLED`, dan
  item navigasinya ikut hilang.
- **Konsekuensi.** Kampus yang sudah mengisi `SSO_FIRST_PARTY` akan melihat layar
  persetujuan berhenti muncul bagi aplikasi itu — perilaku yang selama ini
  dijanjikan dokumen. Tabel Passport tidak diubah, jadi pembaruan Passport tidak
  bertabrakan dengan skema kampus.

### 4. Staf sebagai pemohon helpdesk, dan pencatat atas nama orang lain

- **Konteks.** ADR-018 menetapkan tiket dari mahasiswa/dosen ke unit. Staf
  keuangan yang laptopnya rusak tidak punya jalan resmi ke UPT TIK, dan petugas
  loket yang menerima mahasiswa datang langsung tidak dapat menuliskan tiketnya.
- **Keputusan.** (1) Staf menjadi pemohon keempat (`helpdesk.ajukan` di guard
  staf, semua peran staf) lewat controller pemohon yang sama, di
  `/admin/tiket-saya`. Kategori yang ditangani unitnya sendiri ditolak — itu
  pekerjaan antreannya, bukan tiket. (2) Lingkaran pemohon berlaku untuk staf
  persis seperti untuk mahasiswa: ia melihat tiketnya sendiri; tidak melihat
  tiket lain di luar unitnya; dan **pemohon tidak pernah menangani tiketnya
  sendiri** (`handle` menolak, balasannya dihitung sebagai balasan pemohon, ia
  tidak dapat menulis catatan internal), sekalipun tiketnya kemudian
  didisposisi ke unitnya. (3) Pemegang `helpdesk.tangani` dapat mencatat tiket
  atas nama mahasiswa/dosen (dicari dari NIM/NIDN). Tiket itu milik pemohonnya
  — ia yang diberi tahu dan melihatnya di portalnya — dan pencatatnya disimpan
  di `dicatat_oleh_staff_id`, tampil di kedua sisi. Pencatat tidak mendapat
  akses lanjutan karena mencatat; ia melihat tiket itu hanya bila unit atau
  namanya memang menanganinya.
- **Konsekuensi.** Tiket staf ikut antrean dan SLA yang sama; notifikasinya
  menuju `/admin/tiket-saya/{uuid}`. Tidak ada lingkaran "atasan pemohon".

---

## ADR-040 — Layanan swalayan mahasiswa: cuti, beasiswa, prestasi, konversi (2026-10-30)

Inventaris kode 28 Oktober 2026 menemukan empat urusan yang hanya bisa
dijalankan staf: mengajukan cuti, mengajukan beasiswa/keringanan, melaporkan
prestasi, dan melihat poin serta status konversi kredit. Keempatnya kini punya
jalur masuk mahasiswa. Aturan yang mengikat tetap satu: **mahasiswa mengajukan,
staf memutuskan dengan alasan, mahasiswa diberi tahu** — tidak ada keputusan
otomatis, dan setiap ajuan memanggil service yang sama dengan jalur staf.

### 1. Cuti: jendela semester, aturan lama, event Bridge yang ternyata belum ada

- **Konteks.** `CutiService::ajukan()` sudah menolak mahasiswa tidak aktif,
  ajuan ganda per semester, dan KRS yang masih hidup. Brief menyebut juga
  batas jumlah cuti dan larangan bertunggakan "bila ada" — **keduanya tidak
  ada** di kode maupun konfigurasi. Brief juga menyebut persetujuan cuti
  "tetap menerbitkan `student.status_changed` seperti hari ini"; ternyata
  hanya yudisium yang menerbitkannya.
- **Keputusan.** `ajukanMandiri()` membungkus `ajukan()` tanpa mengubah
  aturannya, ditambah dua hal: semester yang boleh dipilih hanya semester
  aktif dan semester reguler berikutnya (bukan antara, bukan setahun ke
  depan), dan dokumen disimpan lewat `BerkasService` di dalam transaksi yang
  sama. Batas jumlah cuti dan syarat tunggakan **tidak** dibuat: itu aturan
  bisnis baru, dan kampus belum memintanya. `setujui()` dan `aktifkanKembali()`
  kini menerbitkan `student.status_changed` (muatan sama dengan yudisium,
  dipaku di `bridge.yaml`), sesudah transaksi selesai.
- **Konsekuensi.** Persetujuan cuti mengubah status mahasiswa **saat itu
  juga**, apa pun semester ajuannya — perilaku lama yang tidak diubah. Untuk
  ajuan semester yang belum berjalan, layar staf memberi tanda supaya BAAK
  memutusnya ketika semester itu aktif. Tombol "Aktifkan Kembali" di layar staf
  selama ini memanggil service tanpa staf pemutus dan selalu gagal; diperbaiki.

### 2. Beasiswa: periode pendaftaran di depan jalur penetapan yang sudah ada

- **Konteks.** `BeasiswaService::tetapkan()` menjaga kuota, satu penerimaan
  aktif per skema, dan penerapan potongan ke tagihan. Tidak ada tempat
  mahasiswa meminta.
- **Keputusan.** Dua tabel: `beasiswa_periode` (skema, semester mulai berlaku,
  tanggal buka–tutup, persyaratan tertulis, IPK minimum opsional, daftar
  berkas yang diminta) dan `beasiswa_ajuan` (motivasi, IPK saat mengajukan,
  berkas berlabel, keputusan, tautan ke `beasiswa_penerima`). Sistem hanya
  memeriksa yang pasti: periode dibuka, status aktif, belum mengajukan, belum
  menerima skema yang sama, IPK minimum. Persyaratan tertulis dibaca dan
  dinilai orang. Menetapkan memanggil `BeasiswaService::tetapkan()` dalam satu
  transaksi dengan pembaruan ajuan; kuota penuh membiarkan ajuan menunggu.
- **Konsekuensi.** Tidak ada uang yang bergerak karena ajuan. Berkas yang sudah
  tersimpan dihapus bila ajuannya gagal ditulis. Satu periode per skema per
  waktu tidak dipaksakan — kampus dapat membuka gelombang yang berdampingan.

### 3. Prestasi: laporan mahasiswa masuk antrean verifikasi yang sama

- **Keputusan.** `PoinKemahasiswaanService::laporkan()` lewat `catat()` —
  kategori aktif, poin dibekukan, bukti wajib bila katalog memintanya, mulai
  belum terverifikasi — hanya untuk kategori prestasi (pelanggaran tidak
  dilaporkan atas diri sendiri). Rincian SIMKATMAWA divalidasi dengan aturan
  yang sama dengan layar Lengkapi (`DataSimkatmawaRequest::aturanRincian()`);
  dosen pembimbing tetap diisi staf. Kolom baru
  `poin_mahasiswa.dilaporkan_by_mahasiswa_id` menandai asalnya; verifikasi dan
  penolakan memberi tahu pelapor. Mahasiswa melihat kedua buku besarnya, tetap
  berdampingan dan tidak pernah dijumlahkan.
- **Konsekuensi.** Ekspor SIMKATMAWA dan `PrestasiMapper` sudah menyaring
  `is_verified`, jadi laporan baru tidak pernah sampai ke kementerian sebelum
  diverifikasi — dipaku tes.

### 4. Konversi kredit: baca saja, dan satu dokumen bila diminta

- **Keputusan.** Mahasiswa melihat usulan konversinya, statusnya, dan SKS
  yang diakui (hanya yang disetujui terhitung) beserta plafonnya. Selama usulan
  menunggu, penilai dapat **meminta dokumen**; mahasiswa mengunggahnya ke kolom
  sendiri (`berkas_mahasiswa_path`) yang tidak pernah menggantikan berkas
  usulan staf. Unggahan berikutnya mengganti yang sebelumnya (berkas lama
  dihapus, kejadiannya tercatat di log). Diakui, ditolak, dan permintaan
  dokumen memberi tahu mahasiswa.

### 5. Izin dan batas otorisasi

- Tiga izin mahasiswa baru — `cuti.ajukan`, `beasiswa.ajukan`,
  `prestasi.lapor` — satu per alur, supaya kampus dapat menahan satu alur tanpa
  mematikan yang lain. Membaca poin dan konversi cukup kepemilikan.
- Tidak ada rute mahasiswa yang menerima mahasiswa sebagai parameter. Policy
  baru (`CutiMahasiswaPolicy`, `BeasiswaAjuanPolicy`, `KonversiKreditPolicy`,
  tambahan `PoinMahasiswaPolicy::lapor/view`) membatasi baca dan unduh berkas
  pada pemiliknya dan staf berizin; memutus tetap `mahasiswa.manage` (cuti,
  prestasi, konversi) dan `keuangan.manage` (beasiswa). Instalasi berjalan:
  `php artisan migrate` lalu `db:seed --class=RolePermissionSeeder`.

---

## ADR-041 — Data IKU 8 dan IKU 6, dan masa berlaku akreditasi prodi: fakta yang dinyatakan, diverifikasi, lalu dihitung di tempat lain (2026-10-30)

Tiga butir ROADMAP P7 (#47, #48, #50) yang tertunda di ADR-036 karena
butuh jenis data baru. Satu ADR, satu bagian per butir. Definisi IKU
diverifikasi dari salinan resmi Kepmendiktisaintek 358/M/KEP/2025 dan
ditulis ulang dengan kata-kata sendiri; dokumennya tidak masuk repo
(CLAUDE.md §10).

### 1. IKU 8 — keterlibatan dosen dalam penyusunan kebijakan sebagai jenis penugasan

- **Konteks.** IKU 8 (pilihan) mengukur proporsi SDM perguruan tinggi —
  dosen tetap, atau peneliti/perekayasa yang berafiliasi — yang secara resmi
  ditugaskan atau diakui terlibat dalam penyusunan kebijakan publik tingkat
  nasional, daerah, atau sektor industri dalam periode pengukuran. Bentuk
  yang diakui ada tiga: anggota tim penyusun; narasumber atau ahli yang
  diminta memberi masukan tertulis; kontributor yang kajiannya dimuat dalam
  dokumen kebijakan resmi. Buktinya harus dokumen resmi yang mencantumkan
  nama orangnya (SK penugasan, undangan resmi, laporan FGD, notulen, atau
  dokumen kebijakannya). ADR-036 mencatat IKU 8 `belum_tersedia` karena tidak
  ada jenis catatannya.
- **Keputusan.** (1) **Jenis `penugasan_dosen` baru,
  `penyusunan_kebijakan`**, bukan tabel baru: judul, lembaga pemberi
  (`mitra_nama`), tanggal, unsur BKD, dan berkas bukti sudah ada di baris itu,
  dan satu catatan dengan banyak pembaca adalah alasan tabel itu diperlebar
  sejak BKD. Empat kolom baru: `kebijakan_bentuk`
  (`BentukKeterlibatanKebijakan`: tim penyusun, narasumber, kontributor
  kajian), `kebijakan_tingkat` (`TingkatKebijakan`: internasional, nasional,
  daerah, industri — bukan `TingkatKegiatan`, karena "industri" adalah jenis
  pembuat aturan, bukan jangkauan), `kebijakan_bukti_jenis`
  (`JenisBuktiKebijakan`, lima jenis dokumen di atas), dan
  `kebijakan_nomor_bukti`. Kepmen menyebut tiga tingkat; `internasional`
  tetap dicatat agar faktanya tidak hilang, dan konsumen yang memutuskan
  apakah dihitung. (2) **Wajib lengkap saat dicatat.** Untuk jenis ini
  `CatatKegiatanRequest` mewajibkan bentuk, tingkat, lembaga pemberi, jenis
  bukti, dan **berkas bukti** (lewat `BerkasService`); jenis lain tetap boleh
  menyusulkan bukti. `KegiatanDosenData` membuang medan kebijakan dari jenis
  lain sehingga baris penelitian tidak pernah membawa tingkat kebijakan.
  (3) **Diverifikasi seperti penugasan lain** di `/admin/data-iku`, kini lewat
  `PenugasanDosenService::verifikasi()` dan `PenugasanDosenPolicy`
  (`dosen.manage`); layar menampilkan bentuk, tingkat, jenis dan nomor bukti,
  serta tautan bukti (`/berkas/kegiatan-dosen/{uuid}`, hanya pemilik dan staf
  ber-`dosen.view`). Penyimpanan kegiatan dosen yang sebelumnya ditulis di
  controller dipindah ke service yang sama, FormRequest, dan DTO.
  (4) **Bridge.** `iku_2026.iku_8_penyusunan_kebijakan` (baru): keterlibatan
  terverifikasi, dosen terlibat (sekali per orang), dosen **tetap** terlibat,
  dosen tetap aktif sebagai penyebut, per bentuk, per tingkat — per semester
  seperti IKU 4–6. `ketersediaan` IKU 8 menjadi `tersedia` dengan kunci itu.
  Butirnya dibaca dari kueri luaran yang sama (satu kueri untuk IKU 4–6 dan
  8) dan kedua penyebut dosen digabung dalam satu kueri, sehingga anggaran
  kueri `iku-data` tidak bertambah. Kunci penomoran lama dan medan `iku` pada
  event `lecturer.assignment_recorded` tidak berubah (dibekukan, ADR-036):
  jenis baru tidak dihitung ke "IKU 3" atau "IKU 4" lama.
- **Konsekuensi.** Peneliti/perekayasa yang bukan dosen tidak tercatat di
  sini; bila kampus memilikinya, Open Campus melengkapinya. Persentase dan
  keputusan apakah dosen tidak tetap atau tingkat internasional dihitung
  milik konsumen. Ekspor portofolio (SISTER/BKD, `lecturer-workload`) ikut
  membawa empat medan baru.

### 2. IKU 6 — indeks dan kuartil publikasi dicatat sebagai fakta yang dinyatakan dan diverifikasi

- **Konteks.** IKU 6 (wajib bagi PTN-BH, pilihan bagi yang lain) mengukur
  proporsi publikasi hasil riset yang terindeks basis data internasional
  bereputasi — Scopus dan/atau Web of Science — terhadap seluruh publikasi
  perguruan tinggi. Artikel jurnal diberi bobot menurut kuartil (dengan kelas
  "top tier" di atas Q1), prosiding bereputasi berbobot tetap, publikasi
  dengan penulis dari luar negeri mendapat tambahan bobot, beberapa penerbit
  dikecualikan, dan karya seni/budaya berpengakuan internasional ikut
  dihitung. `penugasan_dosen` hanya mencatat jenis luaran dan tingkatnya;
  ROADMAP #48 menanyakan apakah indeks dan kuartil dicatat di sini atau tetap
  di Open Campus.
- **Keputusan.** (1) **Dicatat di sini, sebagai fakta.** Indeks dan kuartil
  adalah sifat artikel, sama seperti DOI-nya — bukan penilaian. Open Academic
  mencatat apa yang **dinyatakan** dosen dan **diverifikasi** staf; menilai
  bukti, menimbang, dan menghitung persentase tetap di Open Campus. Enam
  kolom baru pada luaran publikasi (jurnal/prosiding): `luaran_indeks`
  (`IndeksPublikasi`: Scopus, WoS, SINTA 1–6, lainnya — satu indeks tempat
  artikel dilaporkan), `luaran_kuartil` (`KuartilPublikasi`: Q1–Q4, hanya
  untuk artikel jurnal ber-Scopus/WoS; selain itu ditolak validasi),
  `luaran_doi` (dinormalkan: tanpa awalan `https://doi.org/`, huruf kecil),
  `luaran_tautan`, `luaran_penerbit`, dan `luaran_kolaborasi_internasional`.
  `KegiatanDosenData` membuang semuanya dari luaran yang bukan publikasi.
  (2) **Tanpa I/O ke pengindeks.** Tidak ada panggilan ke API Scopus, WoS,
  SINTA, atau Crossref: itu I/O eksternal baru (CLAUDE.md §3 aturan 6) dan
  di luar cakupan. Bila kelak dibutuhkan — misalnya mengisi kuartil dari
  DOI — ia masuk di balik interface dengan implementasi palsu untuk uji,
  dan hasilnya tetap usulan yang diverifikasi staf, bukan penimpa data.
  (3) **Top tier dan penerbit tidak diputuskan di sini.** "Top tier" adalah
  penggolongan di atas kuartil yang daftarnya ditetapkan di luar repo ini,
  dan pengecualian penerbit adalah aturan penghitungan; keduanya milik
  konsumen. Repo ini menyimpan nama penerbit agar aturan itu dapat diterapkan.
  (4) **Bridge menghitung artikel, bukan baris.** Dua dosen kampus yang sama
  menulis satu artikel menghasilkan dua baris; `iku_2026.iku_6_publikasi`
  mendapat medan baru yang menggabungkan baris ber-DOI sama:
  `publikasi_unik`, `ber_doi`, `per_indeks`, `bereputasi_internasional`,
  `jurnal_bereputasi_per_kuartil`, `prosiding_bereputasi`,
  `bereputasi_kolaborasi_internasional`. Tiga medan lama (`total_publikasi`,
  `per_jenis`, `per_tingkat`) tidak diubah dan tetap menghitung baris.
  `ketersediaan` IKU 6 tetap `sebagian`: pengakuan karya seni/budaya dan
  penetapan top tier tidak dicatat. Tanpa kueri tambahan — kolomnya ikut
  dibaca kueri luaran yang sama.
- **Konsekuensi.** Kualitas penggabungan bergantung pada DOI; `ber_doi`
  memberi tahu konsumen seberapa jauh angka unik dapat dipercaya. Kuartil
  dicatat sebagaimana tertera saat terbit; perubahan kuartil jurnal di tahun
  berikutnya tidak mengubah catatan lama. Dosen yang sudah mencatat publikasi
  sebelum perubahan ini perlu melengkapi indeksnya lewat koreksi oleh staf
  (belum ada layar sunting kegiatan oleh dosen).

### 3. Akreditasi prodi sebagai riwayat SK bertanggal; PISN membaca yang berlaku pada tanggal lulus

- **Konteks.** Validator PISN menolak, tanpa jalur eksepsi, lulusan dari prodi
  yang akreditasinya tidak berlaku (ADR-036 §2). `prodi.akreditasi` hanya
  menyimpan peringkat yang diketik sekali, jadi SK yang sudah kedaluwarsa
  tetap terbaca "terakreditasi" dan daftar periksa hanya bisa berkata
  "pastikan masih berlaku". Pertanyaan yang sebenarnya — SK mana yang berlaku
  pada tanggal lulus seseorang — hanya bisa dijawab riwayat.
- **Keputusan.** (1) **Tabel `akreditasi_prodi`, satu baris per SK:**
  lembaga (`LembagaAkreditasi`: BAN-PT, tujuh LAM, lembaga internasional),
  peringkat (`PeringkatAkreditasi`: Unggul/Baik Sekali/Baik, A/B/C,
  Terakreditasi, Terakreditasi Sementara, Tidak Terakreditasi), nomor dan
  tanggal SK, tanggal mulai berlaku (bawaan = tanggal SK), tanggal
  kedaluwarsa, berkas SK lewat `BerkasService`, pencatat. SK baru selalu
  baris baru; yang salah ketik **ditarik** (hapus lunak, tercatat di Log
  Aktivitas), tidak disunting. SK yang sama dua kali ditolak. Bila dua SK
  bertumpang (akreditasi ulang terbit sebelum yang lama habis), yang mulai
  berlaku belakangan yang dipakai. (2) **`prodi.akreditasi` menjadi cache**
  peringkat SK terbaru, ditulis hanya oleh `AkreditasiProdiService`;
  formulir master prodi tidak lagi menyuntingnya, dan kolomnya dilebarkan ke
  32 karakter. Peringkat lama yang tercatat tanpa riwayat tetap tampil,
  tetapi **tidak** dianggap berlaku. (3) **PISN.** `KesiapanPisnService`
  memeriksa SK yang berlaku pada `yudisium.tanggal_lulus` (hari ini bila
  belum ditetapkan) dan menyebut alasannya: belum dicatat, peringkat tanpa
  SK, kedaluwarsa sebelum tanggal lulus, belum berlaku, atau SK tidak
  terakreditasi. Riwayat dimuat sekali untuk seluruh daftar lulusan.
  (4) **Peringatan.** Keadaan (`KeadaanAkreditasi`, diturunkan, tidak
  disimpan): berlaku, segera kedaluwarsa (dalam
  `ACADEMIC_AKREDITASI_PERINGATAN_BULAN`, bawaan 6, kecuali SK penerusnya
  sudah dicatat), kedaluwarsa, tidak terakreditasi, belum dicatat. Tampil di
  kolom baru layar master prodi, layar riwayat
  `/admin/master/prodi/{uuid}/akreditasi` (izin `master.view` / `master.manage`
  lewat `AkreditasiProdiPolicy`), dan sebagai peringatan di dasbor staf untuk
  prodi aktif. (5) **LKPS** menuliskan SK yang berlaku hari ini (peringkat,
  lembaga, nomor, masa berlaku) di layar dan sebagai baris di CSV; bila tidak
  ada, kalimat yang menyuruh mencatatnya, bukan sel kosong.
- **Konsekuensi.** Instalasi berjalan: setelah `php artisan migrate`, setiap
  prodi tampil "Belum dicatat" dan lulusannya tidak siap PISN sampai SK
  akreditasinya dicatat — itu temuan, bukan regresi, sama seperti butir PISN
  baru di ADR-036. Dasbor staf menambah satu kueri (riwayat akreditasi dimuat
  bersama daftar prodi); layar master prodi dan daftar periksa PISN juga satu
  kueri tetap. Tanggal berlaku tidak diambil dari layanan BAN-PT/LAM mana pun:
  disalin staf dari SK.
