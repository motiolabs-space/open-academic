# DEMO.md — Memasang dan Menghapus Kampus Demo

Dua perintah. Keduanya **menghapus seluruh isi basis data**, dan itu bukan efek
samping melainkan cara kerjanya — penjelasannya di bawah.

```bash
php artisan openacademic:demo-pasang
```

```bash
php artisan openacademic:demo-hapus
```

---

## Kenapa keduanya menghapus semuanya

Demo ini bukan sekumpulan baris yang ditambahkan ke kampus, melainkan **satu
kampus utuh**: dua prodi, tiga semester bernilai, keuangan, beasiswa, PMB, EDOM,
BKD, sampai keadaan integrasi. Semuanya hanya bermakna bersama-sama.

Memasangnya di atas data yang sudah ada akan bentrok di setiap kunci unik yang
dimilikinya. Dan **menghapusnya secara bedah tidak mungkin**: seeder-nya menulis
ke sekitar 90 tabel, jadi "hapus baris demonya saja" akan meninggalkan nilai
tanpa KRS, tagihan tanpa mahasiswa, dan jurnal yang menunjuk kelas yang sudah
tidak ada. Berpura-pura bisa memilah justru lebih buruk daripada tidak
menyediakannya.

Karena itu **penjaganya adalah fiturnya**, bukan proses seeding-nya.

---

## Tiga penjaga

### 1. Menolak pada produksi

Keduanya berhenti bila `APP_ENV=production`. Tidak ada bendera yang membukanya.
Semua akun demo memakai kata sandi `password`.

### 2. `demo-hapus` menolak basis data yang tidak ditandai

`DemoCampusSeeder` menulis penanda (`settings`, grup `demo`) setiap kali
berjalan. `demo-hapus` menolak bekerja tanpa penanda itu.

Artinya perintah ini **hanya dapat menghapus sesuatu yang dipasang oleh aplikasi
ini sendiri.** Diarahkan ke kampus sungguhan, ia berhenti.

Penandanya ditulis oleh seeder, bukan oleh perintah pemasangnya — supaya setiap
jalur pemasangan ikut tertandai, termasuk `php artisan migrate:fresh --seed`.

### 3. `demo-pasang` menolak menimpa data yang bukan demo

Bila basis data sudah berisi data akademik **dan** tidak bertanda demo,
perintahnya berhenti dan menyebutkan jumlah barisnya. Data tanpa penanda ditaruh
oleh seseorang; menghapusnya tanpa bertanya akan membuang pekerjaan orang itu.

Bisa dilanjutkan dengan `--paksa` bila memang disengaja.

---

## Sesudah dihapus

Basis data dikembalikan **kosong tapi siap pakai** — skema lengkap, peran dan
izin terpasang. Bukan sekadar kosong, karena hal pertama yang dilakukan orang
setelah membersihkan demo adalah membuat akun sungguhan, dan itu mustahil tanpa
daftar peran.

---

## Akun demo

Semua kata sandinya `password`.

| Peran | Akun |
|---|---|
| Super Admin | admin@demo.test |
| BAAK | baak@demo.test |
| Keuangan | keuangan@demo.test |
| Operator PDDIKTI | pddikti@demo.test |
| Dosen (wali) | dosen1@demo.test |
| Dosen praktisi | praktisi@demo.test |
| Mahasiswa | mahasiswa1@demo.test |

---

## Bendera

| Bendera | Pada | Artinya |
|---|---|---|
| `--paksa` | `demo-pasang` | Lanjutkan walau basis data berisi data yang bukan demo |
| `--paksa` | `demo-hapus` | Jangan tanya konfirmasi (untuk skrip/CI) |

---

## Berkas

```
app/Console/Commands/DemoPasangCommand.php
app/Console/Commands/DemoHapusCommand.php
app/Support/Demo.php                      — penanda, satu-satunya pembacanya demo-hapus
database/seeders/DemoCampusSeeder.php     — menulis penanda di akhir
tests/Feature/PerintahDemoTest.php        — yang diuji penolakannya, bukan seeding-nya
```
