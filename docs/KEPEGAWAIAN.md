# KEPEGAWAIAN.md — Riwayat Kepegawaian Mendalam

Melanjutkan tiga tabel riwayat dari G7 (pendidikan, jabatan fungsional,
sertifikasi) dengan enam lagi: keluarga, pangkat, mutasi, penghargaan & sanksi,
bahasa, organisasi.

---

## Tabel bertipe, bukan satu "riwayat" generik

Tabel generik dengan kolom JSON tidak dapat divalidasi, tidak dapat diindeks
secara berguna, dan tidak dapat dipetakan ke sebuah field SISTER tanpa tabel
lookup yang tidak ada yang merawatnya.

---

## Hanya dua yang punya aturan

Keluarga, bahasa, organisasi, penghargaan dan sanksi adalah riwayat datar:
tambah baris, sunting baris. Membungkusnya dalam service akan jadi seremoni.

**Pangkat** dan **mutasi** berbeda — masing-masing punya nilai *berlaku* yang
dibaca hal lain, dan menjaga penunjuk itu tetap jujur adalah seluruh pekerjaannya.

### Pangkat: satu yang berlaku

Pola portabel yang sama dengan `jabatan_fungsional_dosen` dan
`tugas_akhir.mahasiswa_aktif_id`: kolom nullable-unique berisi id dosen selagi
berlaku, NULL selebihnya. NULL tidak bertabrakan di MySQL maupun PostgreSQL;
partial index tidak portabel.

Penjaganya ada di **kolom**, bukan hanya di service — ada tesnya yang menyisipkan
langsung dan menuntut `UniqueConstraintViolationException`. Seeder, perbaikan
manual, dan migrasi data ikut tertahan.

Kenaikan pangkat menulis dua baris dalam satu transaksi. Kalau gagal separuh,
yang tertinggal adalah kenaikan yang belum terjadi — dapat diulang dan terlihat
— bukan dosen tanpa pangkat sama sekali.

### Mutasi: penunjuk dan riwayat ditulis bersama

`dosen.unit_kerja_id` adalah jawaban *sekarang*; `mutasi_dosen` adalah
riwayatnya. Keduanya ditulis bersama, sengaja.

Menurunkan "unit sekarang" dari baris mutasi terakhir memang menghemat satu
kolom, dan menambah satu cara untuk salah: koreksi bertanggal mundur akan
diam-diam memindahkan orang yang tidak pernah pindah, dan setiap layar membayar
subquery untuk menanyakan di mana seseorang bekerja.

Dua hal yang dijaga:

- **Unit asal diambil dari penunjuk, bukan dari formulir.** Formulir bisa
  dikirim setelah orang lain memindahkannya lebih dulu.
- **Keluar mengosongkan unitnya.** Yang sudah keluar tidak lagi tercatat di biro
  lamanya — itulah cara sebuah rekap terus menghitung orang yang sudah
  mengundurkan diri.

---

## Penghargaan dan sanksi: satu tabel, tidak pernah satu angka

Keduanya berbagi setiap kolom dan tidak berbagi aritmetika. Tidak ada apa pun di
aplikasi yang menjumlah, mengurangi, atau menyajikan saldonya — penghargaan
tidak menghapus teguran, dan layar yang menyiratkan sebaliknya sedang membuat
penilaian yang tidak pernah dibuat kampus.

`berkas()` mengembalikannya sebagai **dua daftar**.

Sikap yang sama dengan [`POIN-KEMAHASISWAAN.md`](POIN-KEMAHASISWAAN.md), dengan
alasan yang sama.

---

## `unit_kerja_id` pada dosen

Berbeda dari `prodi_id`. `prodi_id` menyatakan untuk siapa ia mengajar;
`unit_kerja_id` menyatakan siapa yang mempekerjakannya. Dosen yang
diperbantukan ke perpustakaan tetap mengajar, dan rekap per pemberi kerja tidak
boleh mencampur keduanya.

---

## Berkas

```
database/migrations/2026_08_18_100003_create_kepegawaian_dosen_tables.php
app/Models/Sdm/{KeluargaDosen,PangkatDosen,MutasiDosen,PenghargaanSanksiDosen,BahasaDosen,OrganisasiDosen}.php
app/Models/Sdm/Dosen.php                     — relasi + unitKerja()
app/Services/Sdm/RiwayatKepegawaianService.php  — pangkat & mutasi saja
tests/Feature/Sdm/KepegawaianDosenTest.php
```
