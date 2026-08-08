# SURAT.md — Surat Keterangan, Legalisir, dan SKPI

Antrean terpanjang di loket BAAK mana pun adalah surat keterangan aktif kuliah.
Datanya sudah ada di sistem sejak hari pertama; yang belum ada hanyalah kabel
dari kolom status ke printer.

Modul ini memasang kabel itu, lalu menambahkan satu hal yang lebih sulit: cara
bagi pihak ketiga untuk memeriksa bahwa kertas di tangannya benar-benar berasal
dari kampus ini.

---

## Lima Jenis, Satu Mekanisme

| Jenis | Terbit | Masa berlaku |
|---|---|---|
| **Surat Keterangan Aktif Kuliah** | Seketika, tanpa persetujuan | 90 hari |
| **Surat Keterangan Lulus** | Persetujuan BAAK | 180 hari |
| **Surat Pengantar** | Persetujuan BAAK | 90 hari |
| **Transkrip Legalisir** | Persetujuan BAAK | Tanpa batas |
| **SKPI** | Otomatis saat kelulusan ditetapkan | Tanpa batas |

Satu tabel, satu deret penomoran, satu halaman verifikasi. Membangun SKPI
sebagai sistem terpisah akan berarti dua skema penomoran dan dua endpoint
verifikasi — dan yang kedua selalu berakhir lebih lemah.

### Mengapa hanya satu yang swalayan

Surat keterangan aktif kuliah adalah kampus **membacakan kolom status**. Tidak
ada keputusan di dalamnya. Surat pengantar berbeda: ia meminjamkan nama
institusi pada proyek orang lain, dan itu keputusan.

`JenisSurat::swalayan()` adalah tempat pembedaan itu tinggal.

---

## Tiga Sifat yang Membuat Surat Dapat Dipercaya

### 1. Nomor yang tidak pernah bertabrakan maupun terpakai ulang

Indeks unik gabungan `(jenis, tahun, nomor_urut)`. `PenomoranSurat` mencoba
ulang terhadap indeks itu, bukan membaca `max()+1` lalu berharap — versi kedua
punya celah di antara baca dan tulis, tepat selebar satu permintaan lain.

Dua hal yang mengikutinya:

- **Permohonan yang ditolak tidak memakai nomor.** Lubang pada deret adalah
  pertanyaan yang harus dijawab seseorang saat audit, bertahun-tahun kemudian.
- **Baris yang dihapus tetap dihitung.** Nomor yang pernah tercetak sudah
  meninggalkan gedung; memberikannya pada surat lain berarti dua kertas asli
  mengaku identitas yang sama.

### 2. Fakta dibekukan saat terbit

Seluruh isi surat disimpan ke kolom `konten` pada saat penerbitan dan **tidak
pernah dirakit ulang**. Surat yang menyatakan seseorang mahasiswa aktif tetap
menjadi catatan jujur tentang bulan Maret, sekalipun yang bersangkutan berhenti
pada bulan April.

Dokumen resmi yang diam-diam menulis ulang dirinya mengikuti basis data adalah
dokumen yang berbeda setiap kali dibuka.

Satu pengecualian yang disengaja: daftar mata kuliah pada transkrip legalisir
dirakit ulang saat cetak. Itu laporan atas catatan, bukan pernyataan tentang satu
saat — mencetak ulang versi yang melewatkan koreksi nilai berarti mencetak ulang
kesalahan.

### 3. Pencabutan, bukan penghapusan

Surat yang dicabut **tetap dapat diverifikasi**, dan tampil sebagai dicabut.

Seseorang di luar sana memegang kertasnya. Jawaban "tidak ditemukan" akan
terbaca sebagai pemalsuan dan menempatkan pemegangnya pada posisi bersalah atas
sesuatu yang dilakukan kampus.

---

## Verifikasi Publik

`/verifikasi` — terbuka tanpa autentikasi, dan memang harus begitu. Yang
memeriksa adalah petugas bank, staf kedutaan, calon pemberi kerja. Tak satu pun
punya akun di sini, dan meminta mereka membuatnya berarti tidak akan ada yang
pernah memverifikasi apa pun.

Karena terbuka, seluruh pengamanannya ada pada tiga hal lain.

**Dikunci pada UUID, bukan nomor surat.** Nomor surat berurutan — memang harus,
itu konvensi arsip. Menaruhnya di URL verifikasi adalah undangan menyusuri
rentangnya dan memanen nama semua orang yang pernah dikirimi surat oleh kampus,
dengan jawaban yang otoritatif.

**Pencarian manual menuntut nomor *dan* NIM.** Keduanya tercetak pada dokumen;
salah satunya saja dapat ditebak.

**Hanya menampilkan yang tercetak pada kertasnya.** Nama, NIM, prodi, jenis,
nomor, tanggal. Bukan NIK, bukan alamat, bukan IPK, bukan status keuangan.
Pembaca sedang mencocokkan, bukan menyelidiki.

Ditambah pembatasan laju per alamat IP pada pencarian manual, karena ia menerima
tebakan.

### Tiga jawaban, bukan dua

| Jawaban | Artinya |
|---|---|
| Asli dan berlaku | Dokumen sah, isinya masih menggambarkan keadaan |
| Asli tetapi kedaluwarsa | Sah, tetapi menggambarkan keadaan pada tanggal terbit |
| Asli tetapi dicabut | Sah diterbitkan, lalu ditarik penerbitnya |
| Tidak ditemukan | Tidak ada dokumen dengan nomor itu |

Menggabungkan yang kedua ke salah satu ujung membuat verifikasi tak berguna:
surat keterangan aktif kuliah yang kedaluwarsa **bukan pemalsuan**, dan surat
yang dicabut **bukan dokumen berlaku**.

---

## Kode QR

Memuat **URL verifikasi saja** — bukan nama, bukan nomor, bukan muatan apa pun.

QR yang membawa faktanya sendiri adalah salinan kedua dokumen yang tidak dapat
dicabut siapa pun, dan memalsukannya cukup dengan menyunting teks. Menunjuk ke
kampus berarti yang menjawab adalah kampus.

Bila pembuatan QR gagal, surat tetap terbit dengan URL verifikasi tercetak
sebagai teks. Surat tanpa QR masih surat yang sah; surat yang gagal dicetak
adalah orang yang dikirim kembali ke loket.

---

## SKPI

Wajib menurut regulasi untuk **setiap** lulusan, dwibahasa.

Karena itu diterbitkan otomatis saat kelulusan ditetapkan, bukan atas permintaan.
Menjadikannya permintaan berarti ia diterbitkan kepada lulusan yang tahu harus
meminta — dan yang tidak tahu justru yang paling tidak mampu mengejarnya.

Isinya dirakit dari:

| Bagian | Sumber |
|---|---|
| Identitas & kualifikasi | Data mahasiswa dan yudisium |
| Jenjang KKNI | Diturunkan dari jenjang prodi — dipetakan regulasi, tidak disimpan |
| Capaian pembelajaran | `prodi_cpl`, dwibahasa |
| Aktivitas | `aktivitas_mahasiswa` yang **sudah terverifikasi** saja |

Dua keputusan di situ:

**Hanya aktivitas terverifikasi.** SKPI adalah pernyataan yang ditandatangani
institusi. Klaim magang yang belum diverifikasi adalah perkataan mahasiswa;
mencetaknya di atas kop kampus mengubahnya menjadi perkataan kampus.

**CPL yang kosong dinyatakan terbuka** pada dokumennya. Bagian yang hilang tanpa
keterangan terbaca sebagai kelalaian pemegang ijazahnya, bukan penerbitnya.

CPL diisi lewat **Master Akademik → Capaian Pembelajaran**, sekali per program
studi. Tanpa itu, bagian terpenting SKPI kosong — dan layar itu mengatakannya.

Ditulis per prodi, bukan per lulusan, karena itulah satu-satunya cara versi
Inggrisnya benar-benar terisi. Sebagai isian per lulusan, ia menjadi pekerjaan
penerjemahan pada pagi hari wisuda, dan berhenti dikerjakan.

Mengubah daftar CPL **tidak mengubah SKPI yang sudah terbit** — isi tiap surat
dibekukan saat penerbitan.

---

## Transkrip Gratis vs Transkrip Legalisir

Transkrip yang diunduh sendiri dari portal **tidak bernomor** dan kini menyatakan
dirinya sebagai salinan tidak resmi.

Sebelumnya lembar itu mencetak "kode verifikasi" — hash dari uuid mahasiswa —
beserta kalimat bahwa dokumen sah tanpa tanda tangan basah bila kodenya cocok
dengan pangkalan data. **Tidak ada tempat untuk mencocokkannya.** Kode di samping
kalimat semacam itu mengajak pembaca percaya bahwa seseorang bisa memeriksanya.

Versi resminya adalah Transkrip Legalisir: bernomor, dapat diverifikasi, dapat
dicabut.

---

## Konfigurasi

[`config/surat.php`](../config/surat.php).

```env
SURAT_POLA_NOMOR="{urut}/{kode}/{institusi}/{bulan}/{tahun}"
SURAT_PENANDATANGAN_NAMA="Dr. Nama Pejabat, M.Kom."
SURAT_PENANDATANGAN_JABATAN="Kepala Biro Administrasi Akademik"
SURAT_PENANDATANGAN_NIP="19700101..."
SURAT_TAHAN_BILA_MENUNGGAK=false
SURAT_VERIFIKASI_BATAS=10
```

**Tetapkan pola nomor sebelum surat pertama terbit.** Mengubahnya setelah itu
berarti dua konvensi hidup berdampingan di lemari arsip yang sama.

**`SURAT_TAHAN_BILA_MENUNGGAK` menghitung tunggakan yang sudah lewat jatuh tempo
saja**, bukan setiap tagihan yang belum lunas. Memblokir yang kedua akan menolak
surat kepada semua orang di pekan pertama semester — termasuk mahasiswa yang
membutuhkannya untuk mencairkan beasiswa yang akan membayar tagihan itu.

Nama penandatangan boleh dikosongkan; yang tercetak lalu hanya nama institusi.
Itu lebih baik daripada nama pejabat yang sudah tidak menjabat.
