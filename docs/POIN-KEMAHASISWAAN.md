# POIN-KEMAHASISWAAN.md — Prestasi & Pelanggaran

Dua buku besar. **Tidak pernah dijumlahkan satu sama lain.**

---

## Kenapa tidak ada angka "poin bersih"

Menjumlahkan prestasi dan pelanggaran berarti membiarkan mahasiswa menebus
sanksi dengan kemenangan lomba. Tidak ada bagian kemahasiswaan yang bermaksud
begitu dengan angka mana pun — sanksi karena memalsukan tanda tangan tidak
hilang karena yang bersangkutan juara tahun berikutnya.

Karena itu:

- `rekap()` mengembalikan **dua angka berdampingan**, dan tidak ada metode yang
  mengembalikan selisihnya
- layar menampilkannya dalam dua kotak terpisah
- syarat kelulusan membaca **hanya** buku besar prestasi

Ada tes yang memeriksa daftar metode service dan menolak nama seperti `bersih`,
`total`, atau `saldo`. Begitu metode semacam itu ada, seseorang akan
memanggilnya dan mencetak hasilnya di suatu tempat.

---

## Katalog di basis data, ambang di config

Pembagian ini disengaja dan berbeda dari modul lain.

| | Di mana | Kenapa |
|---|---|---|
| Katalog jenis & nilai poin | basis data, ada layarnya | Daftarnya panjang, beda tiap kampus, direvisi tiap tahun oleh yang mengelolanya |
| Minimum lulus & ambang pelanggaran | `config/kemahasiswaan.php` | Angka tunggal, jarang berubah, dan mengubahnya adalah keputusan kebijakan |

Nilai poin **disalin** ke tiap baris catatan saat dibuat. Kampus yang menaikkan
harga "juara 1 nasional" dari 40 ke 60 tahun depan tidak menulis ulang apa yang
sudah dikreditkan ke lulusan tahun ini.

---

## Hanya yang terverifikasi terhitung

Dua arah, dan keduanya penting:

- klaim prestasi yang belum diperiksa **tidak boleh** mendorong seseorang
  melewati garis kelulusan
- tuduhan yang belum diperiksa **tidak boleh** tercatat atas nama seseorang
  seolah sudah terbukti

Catatan yang ditolak **disimpan**, lengkap dengan alasannya. Klaim prestasi yang
lenyap membuat mahasiswa tidak tahu apakah ia ditolak atau hilang; tuduhan yang
lenyap tidak meninggalkan catatan bahwa kampus pernah memeriksanya dan tidak
menemukan apa-apa.

---

## Temuan, bukan sanksi

Akumulasi pelanggaran yang melewati ambang menghasilkan **temuan** yang tercetak
beserta ambangnya:

> `Perlu pembinaan (60 poin, ambang 50)`

Sanksi adalah keputusan orang, dengan alasan tertulis — sikap yang sama dengan
[`EVALUASI-STUDI.md`](EVALUASI-STUDI.md). Sistem boleh mengamati bahwa seseorang
melewati 100 poin; apa yang menyusul bukan miliknya.

---

## Syarat kelulusan

Bila `kemahasiswaan.prestasi.minimum_lulus` bernilai nol, barisnya
**dihilangkan sama sekali** dari daftar syarat — bukan ditampilkan sebagai
syarat yang otomatis terpenuhi. Pola yang sama dengan tugas akhir pada prodi
yang tidak mewajibkannya: `persenSelesai()` tetap jujur, dan tidak ada yang
membaca baris hijau untuk syarat yang tidak pernah ada.

### Satu tes yang sempat tidak memaku apa pun

Versi pertama tes pemisahan buku besar memberi 60 prestasi + 100 pelanggaran
lalu memastikan syarat 50 tetap terpenuhi. Itu lolos **juga tanpa pemisahan**,
karena 160 pun melewati 50.

Arah yang berbahaya justru sebaliknya: pelanggaran ikut **menggenapi** syarat.
Tesnya sekarang memberi nol prestasi dan seratus pelanggaran, dan menuntut
syaratnya tidak terpenuhi. Lolos dengan pemisahan, gagal tanpanya.

---

## Satu jebakan model yang ditemukan di sini

`PoinKategori::create()` mengembalikan instance yang `is_active`-nya masih
`null` sampai ada yang membacanya ulang — nilai defaultnya milik kolom basis
data, bukan modelnya. Baris berikutnya di pemanggil melihat kategori yang baru
saja dibuat sebagai **tidak aktif**.

Diperbaiki dengan `$attributes` pada modelnya. Mengandalkan default kolom lalu
membaca atributnya kembali dari model yang belum di-refresh adalah jebakan untuk
setiap pemanggil, bukan hanya yang kebetulan menemukannya.

---

## Berkas

```
config/kemahasiswaan.php
database/migrations/2026_08_18_100001_create_poin_kemahasiswaan_tables.php
app/Enums/JenisPoin.php
app/Models/Kemahasiswaan/{PoinKategori,PoinMahasiswa}.php
app/Services/Kemahasiswaan/PoinKemahasiswaanService.php
app/Services/Kemahasiswaan/YudisiumService.php     — syarat poin kelulusan
app/Http/Controllers/Admin/PoinKemahasiswaanController.php
resources/views/admin/poin-kemahasiswaan.blade.php
tests/Feature/Kemahasiswaan/PoinKemahasiswaanTest.php
```
