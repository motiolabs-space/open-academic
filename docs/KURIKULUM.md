# KURIKULUM.md — Padanan, Konsentrasi & Paket Kuliah

Tiga hal yang dibutuhkan sebuah kurikulum begitu ia pernah diganti sekali saja.
Ketiganya ada karena alasan yang sama: **kurikulum bukan daftar yang diam.** Ia
digantikan, ia bercabang menjadi jalur, dan di vokasi ia diserahkan kepada
mahasiswa alih-alih dipilihnya.

---

## Padanan Mata Kuliah

Setiap pergantian kurikulum melahirkan persoalan yang sama: mahasiswa yang sudah
lulus "Algoritma & Pemrograman" (2018) tidak boleh disuruh mengambil "Dasar
Pemrograman" (2026). Tanpa modul ini, kampus menyelesaikannya dengan tangan —
petugas mencentang pembebasan satu per satu, yang lambat sekaligus tidak dapat
diaudit.

### Mendarat di satu tempat

```
PrasyaratChecker::mataKuliahLulus()
```

Itu **satu-satunya** metode yang menjawab "apa yang sudah dilulusi mahasiswa
ini". Prasyarat, aturan sudah-ambil di KRS, dan daftar periksa kelulusan
semuanya lewat sana.

Memperluas himpunannya di situ membuat ketiganya menghormati padanan tanpa satu
pun tahu padanan itu ada — dan, yang lebih penting, tanpa satu pun bisa
berselisih tentangnya. Pelajaran yang sama dengan `PerolehanAkademik`.

### Arahnya mengikat

Padanan **terarah**: lulus A dihitung sebagai lulus B, tidak sebaliknya.

Mata kuliah pengganti biasanya mencakup lebih banyak. Menerimanya mundur akan
meloloskan mahasiswa sekarang dari prasyarat yang silabus lama tidak pernah
ajarkan. Kampus yang benar-benar bermaksud dua arah mencatat **dua baris**,
sengaja — bukan mendapatkannya dari flag yang tidak dibaca siapa pun.

Dibuktikan mengikat: membuat resolvernya simetris membuat tes "tidak berlaku
terbalik" gagal seketika.

### Transitif, dan lingkaran ditolak

2018 → 2022 → 2026 adalah bentuk biasa; satu lompatan saja akan meninggalkan
angkatan tertua. Karena itu padanan diikuti sampai habis.

Lingkaran ditolak **saat ditulis**, bukan saat dibaca. Cincin padanan membuat
setiap mata kuliah di dalamnya setara dengan semua yang lain — hampir tidak
pernah yang dimaksud, dan mustahil terlihat begitu terbentuk: resolvernya hanya
akan mengembalikan makin banyak mata kuliah sebagai "sudah lulus".

Grafnya dimuat utuh, bukan dikueri rekursif. Kampus punya puluhan padanan, bukan
ribuan — dan CTE rekursif akan mengikat resolvernya ke satu mesin basis data.

---

## Konsentrasi

Satu prodi, beberapa jalur wajib. Menempel pada **kurikulum**, bukan prodi:
jalur ada di kurikulum yang mendefinisikannya, dan revisi kurikulum rutin
mengganti nama atau menggabungkan jalur. Menempelkannya ke prodi akan membuat
mahasiswa angkatan lalu menjadi anggota jalur tahun ini.

| `kurikulum_mata_kuliah.konsentrasi_id` | Artinya |
|---|---|
| `null` | Mata kuliah bersama — terbuka untuk semua |
| terisi | Hanya untuk mahasiswa jalur itu |

Null adalah bawaan, karena sebagian besar gelar memang bersama.

**Mahasiswa yang belum memilih jalur ditolak, bukan diloloskan.** Meloloskannya
berarti ia menempuh mata kuliah yang dihitung ke syarat jalur yang tidak berlaku
baginya — dan menemukan itu saat yudisium jauh lebih mahal daripada diberi tahu
sekarang.

---

## Kuliah Paket

Lazim di vokasi dan diploma: satu angkatan bergerak bersama melalui urutan yang
tetap, dan rencana studinya diterbitkan alih-alih disusun.

**Yang berubah adalah siapa yang memilih, bukan aturan mana yang berlaku.**

`PaketKuliahService` memanggil `KrsService::tambahKelas`, bukan menyisipkan
`krs_detail` sendiri. Itu seluruh desainnya: paket yang menulis barisnya sendiri
akan melewati kunci kuota, deteksi bentrok jadwal, aturan prasyarat, dan penjaga
hitung-ganda — diam-diam, untuk satu angkatan sekaligus.

Kegagalan dikumpulkan **per mata kuliah**, tidak menggugurkan penerapan. Satu
mahasiswa mengulang yang sudah memegang satu mata kuliah tidak boleh
menghentikan tujuh lainnya, dan petugas yang menjalankannya untuk satu angkatan
butuh daftar alasan — bukan exception pada yang pertama.

Paket jalur mendahului paket bersama. Prodi memilih modenya lewat
`prodi.mode_krs` (`pilih` | `paket`).

---

## Berkas

```
database/migrations/2026_08_17_100000_create_kurikulum_lanjutan_tables.php

app/Models/Akademik/{Konsentrasi,PaketKuliah}.php
app/Services/Akademik/PadananMataKuliah.php
app/Services/Akademik/PaketKuliahService.php
app/Services/Akademik/PrasyaratChecker.php   — tempat padanan mendarat
app/Services/Akademik/KrsService.php         — gerbang konsentrasi

app/Http/Controllers/Admin/KurikulumLanjutanController.php
resources/views/admin/kurikulum-lanjutan.blade.php

tests/Feature/Akademik/KurikulumLanjutanTest.php
```
