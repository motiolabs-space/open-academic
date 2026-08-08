# Berkontribusi ke Open Academic

Terima kasih sudah mempertimbangkan untuk ikut membangun. Dokumen ini
menjelaskan cara kerja yang diharapkan; aturan arsitektur selengkapnya ada di
[`CLAUDE.md`](CLAUDE.md), yang berlaku untuk kontributor manusia maupun agen AI.

## Menyiapkan Lingkungan

```bash
git clone https://github.com/motiolabs-space/open-academic.git
cd open-academic
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build
```

Rincian prasyarat ada di [README](README.md#instalasi).

## Sebelum Membuka Pull Request

Ketiganya harus hijau. CI menjalankan hal yang sama, jadi menjalankannya lokal
menghemat satu putaran.

```bash
composer lint
composer test
npm run build
```

## Aturan yang Sering Terlewat

1. **Controller tipis.** Logika bisnis tinggal di `app/Services/`. Kalau sebuah
   controller mulai menghitung sesuatu, hitungannya salah tempat.
2. **Migrasi bersifat *append-only* setelah masuk `main`.** Kampus yang sudah
   memakai tidak bisa `migrate:fresh`. Ubah skema lewat migrasi baru.
3. **Model memakai `$guarded`, bukan `$fillable`.** Karena itu controller tidak
   boleh mengoper `request()->all()` ke model — validasi dulu, oper yang sudah
   tervalidasi.
4. **Kode, komentar, dan pesan commit dalam bahasa Inggris.** String yang
   dilihat pengguna dalam Bahasa Indonesia.
5. **Setiap PR fitur membawa minimal satu feature test.** Untuk perbaikan bug,
   tulis dulu tes yang gagal tanpa perbaikan itu — dan pastikan ia benar-benar
   gagal sebelum Anda memperbaikinya.
6. **Rute dengan dua parameter wajib memeriksa keduanya berhubungan.** Lihat
   [`SECURITY.md`](SECURITY.md#otorisasi).
7. **Jangan menambahkan lazy load.** `Model::preventLazyLoading()` menyala di
   luar produksi; `tests/Feature/SmokeLayarTest.php` menjaga anggaran kueri tiap
   layar. Kalau anggaran terlampaui, perbaiki kuerinya — jangan naikkan
   anggarannya tanpa alasan yang ditulis.

## Kontrak Campus Bridge Bersifat *Spec-First*

Ubah [`docs/openapi/bridge.yaml`](docs/openapi/bridge.yaml) lebih dulu, baru
kodenya. Ada sistem lain yang membaca kontrak itu; mengubah kode diam-diam akan
merusak mereka tanpa peringatan.

## Melaporkan Kerentanan Keamanan

Jangan lewat issue publik — lihat [`SECURITY.md`](SECURITY.md).

## Lisensi

Kontribusi Anda dirilis di bawah lisensi MIT yang sama dengan proyek ini.
