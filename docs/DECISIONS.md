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
