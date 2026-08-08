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
