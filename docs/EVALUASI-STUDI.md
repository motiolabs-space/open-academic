# EVALUASI-STUDI.md — Peringatan & Putus Studi

Titik-titik tempat kampus memutuskan siapa yang boleh melanjutkan.

---

## Satu kalimat yang menentukan seluruh rancangan

**Sistem menghitung. Manusia memutuskan.**

Setiap sapuan menghasilkan baris dengan `keputusan = menunggu`, dan tidak ada
satu pun jalur di sini yang mengubah status mahasiswa. Mengakhiri studi
seseorang bukan hasil yang boleh dicapai pekerjaan terjadwal tanpa pengawasan —
dan cara tercepat membangun sistem yang justru melakukannya adalah membiarkan
sapuannya menulis vonis "karena aturannya sudah jelas".

Aturannya memang jelas. Keadaannya tidak.

Karena itu `HasilEvaluasi` **tidak punya** case bernama "putus studi". Yang ada
`TidakMemenuhi` — sistem dapat mengamati bahwa seorang mahasiswa di bawah semua
ambang, dan pengamatan itu tetap bukan keputusan.

| | Milik |
|---|---|
| `temuan` (`HasilEvaluasi`) | angka |
| `keputusan` (`KeputusanEvaluasi`) | orang |

---

## Aturan sebagai kebijakan

`config/academic.php` → `evaluasi`.

| Aturan | Bawaan |
|---|---|
| Evaluasi Tahap I | akhir semester tempuh ke-2 · 24 SKS · IPK 2,00 |
| Evaluasi Tahap II | akhir semester tempuh ke-4 · 48 SKS · IPK 2,00 |
| Evaluasi Tahap III | akhir semester tempuh ke-8 · 96 SKS · IPK 2,00 |
| Batas masa studi | 14 semester tempuh |
| Peringatan IPS | di bawah 2,00 |

Semuanya berbeda antar kampus, bahkan antar prodi. Karena itu ada di config, dan
karena itu pula **setiap temuan selalu melaporkan ambang yang dipakainya**:

> `SKS kumulatif 18 dari syarat 24 · IPK 1,85 dari syarat 2,00`

Pembaca harus dapat berselisih dengan **aturannya**, bukan dengan mahasiswanya.
"IPK 1,85" saja tidak memungkinkan itu.

---

## Semester cuti tidak dihitung

Itu justru gunanya cuti.

Menghitungnya menggeser titik evaluasi seorang mahasiswa satu semester lebih
awal daripada yang dimaksud aturan — dan menghukumnya karena sakit atau
kesulitan biaya yang menjadi alasan cuti diberikan sejak awal.

`status_mahasiswa.semester_ke` menghitung semester **kalender** (konvensi
PDDIKTI), jadi evaluasi menghitung sendiri **semester tempuh**: baris yang
statusnya bukan `Cuti`, sampai dengan semester yang dievaluasi. Satu kueri
teragregasi untuk seluruh angkatan, bukan satu per mahasiswa.

Dibuktikan mengikat: melepas filter cuti membuat dua tes gagal seketika.

---

## Yang dibekukan, dan kenapa

Baris evaluasi menyalin — bukan merujuk — empat hal:

- `sks_kumulatif`, `ipk`, `ips` — angkanya saat itu
- `syarat_sks`, `syarat_ipk` — **ambang yang berlaku saat itu**

Yang kedua sering terlupa. Tanpanya, catatan itu tidak dapat dibaca ulang:
"24 SKS, gagal" kehilangan makna begitu kampus menurunkan syaratnya jadi 20, dan
tidak ada yang bisa menilai apakah keputusan lama itu benar.

Angkanya dibaca dari catatan semester yang **sudah dibekukan** penutupan
semester, bukan dihitung ulang — supaya hasil evaluasi sama persis dengan KHS
yang diterima mahasiswa.

Dan sapuan yang dijalankan ulang **tidak menyentuh baris yang sudah
diputuskan**. Keputusan dibuat terhadap angka sebagaimana adanya saat itu;
koreksi nilai bulan depan tidak boleh diam-diam mengubah dasarnya.

---

## Keputusan wajib beralasan

`putuskan()` menolak tanpa alasan tertulis, apa pun keputusannya.

Bukan formalitas. Kampus **rutin** memutuskan melawan aturannya sendiri, dan
memang seharusnya begitu: mahasiswa yang melewatkan ambang SKS karena satu
semester di rumah sakit adalah kasus untuk manusia, bukan untuk operator
perbandingan. Alasan itulah satu-satunya bagian catatan yang menjelaskan
keputusan semacam itu.

| Keputusan | Efek |
|---|---|
| Diizinkan lanjut | tidak ada perubahan status |
| Peringatan akademik | tidak ada perubahan status |
| Mengundurkan diri | status → Keluar |
| Putus studi | status → Drop Out |

Pembatalan keputusan tersedia, dan **tidak** memulihkan status secara otomatis.
Mahasiswa yang terlanjur di-DO dipulihkan lewat layar status, tempat tindakan
itu sendiri terekam audit. Catatan lama tidak dihapus — alasan pembatalan
ditambahkan di bawahnya, karena alternatifnya adalah menyunting baris langsung
tanpa jejak bahwa ia pernah berbunyi lain.

---

## Pemberitahuan saat temuan, bukan saat keputusan

Mahasiswa diberi tahu begitu temuannya dicatat.

Seluruh nilai sebuah peringatan dini adalah bahwa ia datang **selagi mahasiswa
masih bisa berbuat sesuatu**. Menunggu rapat berarti memberi tahu setelah
semester yang perlu diperbaikinya lewat.

Dedupe pada temuannya, bukan pada jalannya sapuan: menjalankan ulang untuk
semester yang sama tidak memberi tahu ulang; titik evaluasi baru memberi tahu.

---

## Yang belum ada

**Surat peringatan formal.** `SuratService` beralur permohonan — mahasiswa
mengajukan, staf menerbitkan. Surat peringatan diterbitkan kampus tanpa diminta,
dan itu bentuk yang berbeda. Memaksakannya ke alur `ajukan()` akan menghasilkan
permohonan palsu atas nama mahasiswa yang justru diperingatkan. Pemberitahuan
sudah menjadi kanal yang dapat ditindaklanjuti; suratnya pekerjaan tersendiri.

---

## Berkas

```
config/academic.php                                  → evaluasi
database/migrations/2026_08_18_100000_create_evaluasi_studi_table.php
app/Enums/{HasilEvaluasi,KeputusanEvaluasi}.php
app/Models/Kemahasiswaan/EvaluasiStudi.php
app/Services/Kemahasiswaan/EvaluasiStudiService.php
app/Notifications/Kemahasiswaan/PeringatanAkademik.php
app/Http/Controllers/Admin/EvaluasiStudiController.php
resources/views/admin/evaluasi-studi.blade.php
tests/Feature/Kemahasiswaan/EvaluasiStudiTest.php
```
