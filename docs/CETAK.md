# CETAK.md — Dokumen Cetak Rutin & Pengaturannya

Empat dokumen yang dicetak kampus setiap semester, dan pengaturan kop yang
mereka pakai bersama.

| Dokumen | Untuk | Ditandatangani |
|---|---|---|
| KTM | mahasiswa | pejabat, tercetak |
| Kartu ujian | mahasiswa | pejabat, tercetak |
| Daftar hadir | kelas | di ruangan, kolom kosong |
| Jurnal perkuliahan | kelas | di ruangan, kolom kosong |

---

## Bukan templat yang dapat disunting

Item roadmap-nya semula berbunyi **"kustomisasi templat dokumen"**. Itu tidak
dibangun, dan alasannya bukan usaha.

Templat Blade yang tersimpan di basis data berarti **mengeksekusi kode yang
tersimpan di basis data**. Satu form admin yang bocor, satu peran yang salah
dipetakan, dan kampus kehilangan servernya — ditukar dengan keluwesan yang
hampir tidak pernah dipakai.

Yang sebenarnya berbeda antar kampus adalah **isinya**: baris alamat, siapa yang
bertanda tangan, catatan di kaki halaman. Itu setelan. Tata letaknya tetap di
berkas Blade, ikut kendali versi, dan dapat ditinjau.

```
config/dokumen.php                       — bawaan
settings (grup "dokumen")                — timpaan per kampus
resources/views/pdf/cetak/layout.blade   — tata letaknya, di kode
```

Nama institusi, singkatan, dan logo **tidak** diulang di sini — semuanya milik
`BrandingService`, dan salinan kedua cepat atau lambat berselisih dengannya.

---

## Penandatangan yang sengaja tidak ada

Daftar hadir dan jurnal ditandatangani **di kertas oleh yang hadir**. Nama
pejabat tercetak di situ bukan sekadar mubazir — ia keliru, karena orang itu
tidak ada di ruangan. Yang dibutuhkan justru ruang kosong.

Karena itu `penandatangan` adalah properti per jenis dokumen, dan layar
Pengaturan tidak menawarkan kolomnya untuk kedua dokumen ini.

Hal yang sama berlaku pada kolom kehadiran di daftar hadir: **kolomnya dibiarkan
kosong**, tidak diisi dari presensi digital. Tanda tangan basah adalah buktinya;
mencetak apa yang sistem sudah percayai ke dalam lembar itu berarti mengganti
bukti dengan salinan dari dugaan.

---

## Yang tidak tercetak di KTM

NIK, alamat rumah, dan nama orang tua **tidak pernah** masuk ke kartu.

Kartu adalah dokumen yang paling sering hilang, difoto, dan diunggah. Segala
yang tercetak padanya ikut tersebar bersamanya. Yang ada di sana hanya NIM,
nama, program studi, fakultas, dan angkatan.

QR-nya mengkodekan **NIM**, yang sudah tercetak di sebelahnya, jadi memindainya
tidak mengungkap apa pun yang kartunya tidak sudah ungkap. Ia sengaja **bukan**
URL verifikasi: itu berarti endpoint publik baru yang mengonfirmasi apakah
seseorang terdaftar di kampus ini.

Ada tesnya, dan tesnya memeriksa HTML sebelum dirender — bukan PDF-nya. dompdf
memampatkan aliran teksnya, jadi mencari string di dalam PDF akan selalu "lolos"
dan tidak membuktikan apa pun.

KTM dicetak **tanpa pas foto**; sistem ini tidak menyimpan foto mahasiswa aktif
(hanya pelamar PMB). Kotaknya disediakan untuk ditempel.

---

## Kartu ujian: penahanan adalah kebijakan

| Syarat | Bawaan | Config |
|---|---|---|
| KRS disetujui | selalu | — |
| Tidak menunggak | menahan | `dokumen.kartu_ujian.tahan_bila_menunggak` |
| Kehadiran cukup | tidak menahan | `dokumen.kartu_ujian.tahan_bila_kehadiran_kurang` |

Dua yang terakhir **kebijakan, bukan fakta** — sebagian kampus menahan, sebagian
tidak, sebagian hanya untuk UAS. Karena itu keduanya dapat dimatikan.

Ambang kehadirannya sendiri milik `config/academic.php` dan ditanyakan lewat
`PresensiService::layakUas`, bukan dihitung ulang: angka yang sama memutuskan
kelayakan ujian di tempat lain, dan dua salinan akan berselisih pada mahasiswa
yang persis di batas.

**Alasan penahanan selalu disebutkan.** Kartu yang sekadar tidak muncul mengirim
mahasiswa bertanya ke loket; "masih ada tagihan Rp 400.000 belum lunas" dapat
ditindaklanjuti sendiri.

Mata kuliah yang tidak memenuhi syarat kehadiran tetap **tercetak dan ditandai**,
bukan dihilangkan. Kartu yang lebih pendek daripada KRS-nya mengirim mahasiswa
bertanya kepada pengawas di depan ruang ujian.

### Tidak ada penembusan untuk loket

Rancangan awalnya punya `?paksa=1` agar loket dapat mencetak melewati tahanan
keuangan — untuk mahasiswa yang membayar tunai lima menit lalu dan kuitansinya
belum masuk sistem.

Itu dibuang. **Unduhan tidak meninggalkan baris audit**, jadi penembusan itu akan
membuat tahanan keuangan dapat dilewati tanpa jejak sama sekali. Dua perbaikan
yang sebenarnya sama-sama berjejak: catat pembayarannya, atau setujui KRS-nya.

---

## Otorisasi

| Rute | Siapa |
|---|---|
| `/mahasiswa/ktm`, `/mahasiswa/kartu-ujian` | **tanpa parameter** — pemiliknya yang sedang masuk |
| `/dosen/kelas/{kelas}/absensi`, `/jurnal` | hanya dosen pengampu kelas itu |
| `/admin/cetak/*` | staf, per izin (`mahasiswa.view`, `krs.view`, `kelas.view`) |

Rute mahasiswa **tidak menerima identitas** sama sekali. Endpoint yang menerima
NIM butuh pemeriksaan kepemilikan, dan pemeriksaan bisa salah ditulis — tidak
menerima parameternya tidak bisa.

Daftar hadir memuat nama seluruh peserta kelas, persis jenis daftar yang tidak
boleh dicetak siapa pun yang menebak id kelas. Karena itu jalur dosen memeriksa
pengampu, bukan sekadar peran.

---

## Berkas

```
config/dokumen.php
app/Services/Dokumen/PengaturanDokumen.php    — kop, penandatangan, kaki
app/Services/Dokumen/CetakService.php         — keempat dokumen + gerbang kartu ujian

app/Http/Controllers/Mahasiswa/CetakController.php
app/Http/Controllers/Dosen/CetakController.php
app/Http/Controllers/Admin/CetakController.php
app/Http/Controllers/Admin/PengaturanController.php  — simpanDokumen

resources/views/pdf/cetak/layout.blade.php
resources/views/pdf/cetak/{ktm,kartu-ujian,absensi,jurnal}.blade.php

tests/Feature/Dokumen/CetakTest.php           — gerbang & otorisasi, bukan tipografi
```
