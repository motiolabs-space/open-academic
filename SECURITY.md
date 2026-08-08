# Kebijakan Keamanan

Open Academic menyimpan catatan akademik resmi, NIK, dan data keuangan
mahasiswa. Kerentanan di sini berdampak pada orang sungguhan, bukan sekadar
pada sebuah instalasi.

## Melaporkan Kerentanan

**Jangan** membuka issue publik untuk kerentanan keamanan.

Kirim surel ke **security@motiolabs.space** dengan:

- deskripsi kerentanan dan dampaknya,
- langkah reproduksi (atau proof of concept),
- versi/commit yang diuji.

Kami menargetkan balasan pertama dalam **3 hari kerja** dan perbaikan untuk
temuan kritis dalam **14 hari**. Pelapor akan dicantumkan pada catatan rilis
kecuali meminta sebaliknya.

## Versi yang Didukung

| Versi | Dukungan keamanan |
|---|---|
| 1.x | ✅ |
| < 1.0 (pra-rilis) | ❌ |

---

## Wajib Dilakukan Sebelum Produksi

Daftar ini bukan saran. Melewatkan satu saja membuat instalasi tidak layak
memegang data mahasiswa.

```env
APP_ENV=production
APP_DEBUG=false          # APP_DEBUG=true membocorkan isi .env pada halaman galat
SESSION_SECURE_COOKIE=true
BRIDGE_WEBHOOK_SECRET=<64+ karakter acak>
BERKAS_DISK=local        # JANGAN "public" — lihat §Berkas Unggahan
```

1. **`php artisan key:generate`** dijalankan, dan `APP_KEY` tidak pernah
   dibagikan antar instalasi. Kunci yang sama berarti sesi satu kampus dapat
   dipalsukan dari kampus lain.
2. **`APP_DEBUG=false`.** Halaman galat Laravel menampilkan seluruh variabel
   lingkungan — termasuk kata sandi basis data dan kredensial Feeder.
3. **`SESSION_SECURE_COOKIE=true`** pada instalasi HTTPS. Tanpa ini cookie sesi
   ikut terkirim lewat HTTP polos, dan penyerang di wifi kampus yang sama cukup
   menunggu.
4. **Jangan pernah menjalankan `--seed` di produksi.** `DemoCampusSeeder`
   membuat akun dengan kata sandi `password` dan akan menolak berjalan bila
   `APP_ENV=production` — jangan mengakali penolakan itu.
5. **Hanya `public/` yang boleh menjadi document root.** Bila root diarahkan ke
   direktori proyek, `.env` dapat diunduh siapa pun.
6. **Worker antrean menyala.** Jejak audit ter-antre; tanpa worker, perubahan
   nilai tidak pernah tercatat — dan jejak audit yang diam adalah masalah
   keamanan, bukan sekadar fitur yang mati. Sejak Sesi 18 notifikasi juga
   melewatinya: tanpa worker, tidak ada pemberitahuan apa pun yang terkirim.
7. **Entri cron penjadwal terpasang** bila pengingat tenggat diharapkan jalan:
   `* * * * * cd /path && php artisan schedule:run`. Lihat
   [docs/NOTIFIKASI.md](docs/NOTIFIKASI.md).
8. **`BRIDGE_WEBHOOK_SECRET` terisi.** Sejak v1.0 pengiriman webhook dibatalkan
   bila kunci kosong, karena tanda tangan HMAC berkunci kosong dapat dipalsukan
   siapa pun — tampak aman padahal tidak.

---

## Model Keamanan

### Autentikasi

Tiga tabel identitas terpisah (`staff`, `dosen`, `mahasiswa`), masing-masing
dengan guard sendiri. Tidak ada tabel `users` generik, sehingga kompromi satu
populasi tidak otomatis menjangkau dua lainnya.

- Kata sandi di-hash bcrypt (`BCRYPT_ROUNDS=12`).
- Percobaan masuk dibatasi rate limiter per identitas + IP.
- Akun nonaktif (`is_active = false`) ditolak walau kata sandinya benar.
- Sesi diregenerasi setelah berhasil masuk, dan **guard lain di-logout** — satu
  peramban tidak boleh memegang dua identitas sekaligus.

### Otorisasi

Spatie Laravel Permission dengan peran & izin **per guard**. Kebijakan akses
ada di `app/Policies/`, bukan tersebar di controller.

Aturan yang mudah terlewat: **rute dengan dua parameter harus memeriksa bahwa
keduanya berhubungan.** Otorisasi atas objek A tidak mengatakan apa pun tentang
objek B, sekalipun keduanya muncul di URL yang sama. Lihat
`PresensiController::pastikanMilikKelas()` dan
`tests/Feature/Keamanan/LintasObjekTest.php`.

### Data yang Tidak Pernah Keluar Lewat Campus Bridge

`StudentResource` sengaja lebih sempit daripada modelnya. **NIK, alamat rumah,
nama orang tua, dan penghasilan keluarga tidak pernah diserialisasi** — bukan
karena konsumen sekarang tidak membutuhkannya, tetapi karena kontrak yang
memuatnya akan dipakai orang.

Setiap endpoint menyatakan scope-nya sendiri; scope melekat pada aplikasi
konsumen, bukan pada token, sehingga pencabutan berlaku seketika.

### Webhook

Ditandatangani HMAC-SHA256 atas `{timestamp}.{body}`. Verifikasi di sisi
konsumen **wajib** memakai perbandingan waktu-tetap:

```php
$sah = hash_equals(
    hash_hmac('sha256', $timestamp.'.'.$body, $secret),
    $request->header('X-OpenAcademic-Signature'),
);
```

`==` biasa membocorkan posisi karakter pertama yang berbeda lewat waktu
eksekusi, dan itu cukup untuk menebak tanda tangan yang sah.

### Berkas Unggahan

Berkas pendukung berisi KTP, kartu keluarga, ijazah, dan surat keterangan sakit
— dokumen identitas dan rekam medis milik orang sungguhan. Tiga aturan berlaku,
dan ketiganya mudah dilanggar tanpa ada yang mengeluh:

1. **Tidak pernah pada disk publik.** Berkas di bawah document root dapat dibaca
   siapa pun yang menebak alamatnya, masuk atau tidak. `BerkasService` menolak
   berjalan bila `BERKAS_DISK=public`, alih-alih diam-diam menurutinya.
2. **Tidak pernah memakai nama berkas dari pengunggah.** Nama itu datang dari
   klien, jadi bisa berisi path traversal (`../../.env`), null byte, atau
   ekstensi kedua (`ktp.pdf.php`). Nama simpan dibangkitkan; nama asli hanya
   disimpan sebagai label.
3. **Jenis diperiksa terhadap isi, bukan ekstensi.** Ekstensi hanyalah bagian
   dari nama yang diketik seseorang.

Setiap unduhan melewati `BerkasController`, dan tiap rute memutuskan siapa yang
boleh melihat berkas **itu** — bukan sekadar siapa yang sudah masuk. Perbedaannya
nyata: "staf mana pun yang sudah masuk" akan membiarkan bagian keuangan membaca
kartu keluarga para pendaftar, dan mahasiswa mana pun membaca surat sakit
temannya.

Dijaga `tests/Feature/Berkas/UnggahBerkasTest.php`, yang sudah dibuktikan gagal
ketika penjaganya dilonggarkan.

### Halaman Verifikasi Dokumen

`/verifikasi` terbuka tanpa autentikasi, dan memang harus: yang memeriksa
keaslian surat adalah petugas bank, staf kedutaan, atau calon pemberi kerja —
tak satu pun punya akun di sini.

Karena terbuka, seluruh pengamanannya pindah ke tiga tempat lain:

1. **Dikunci pada UUID, bukan nomor surat.** Nomor surat berurutan dan memang
   harus dapat ditebak — itu konvensi arsip. Menaruhnya di URL verifikasi berarti
   menyediakan direktori semua orang yang pernah dikirimi surat oleh kampus,
   dengan jawaban yang otoritatif.
2. **Pencarian manual menuntut nomor dan NIM sekaligus**, keduanya tercetak pada
   dokumen. Salah satunya saja dapat ditebak. Dijaga
   `tests/Feature/Surat/LayarSuratTest.php`, yang sudah dibuktikan gagal ketika
   syarat NIM dilepas.
3. **Hanya menampilkan yang sudah tercetak di kertasnya** — nama, NIM, prodi,
   nomor, tanggal. Bukan NIK, bukan alamat, bukan IPK, bukan status keuangan.
   Pembaca sedang mencocokkan, bukan menyelidiki.

Ditambah pembatasan laju per alamat IP (`SURAT_VERIFIKASI_BATAS`), karena
pencarian manual menerima tebakan. Seluruh halaman dapat dimatikan dengan
`SURAT_VERIFIKASI_AKTIF=false`.

Kode QR pada surat memuat **URL verifikasi saja**, bukan isinya. QR yang membawa
faktanya sendiri adalah salinan kedua dokumen yang tidak dapat dicabut siapa pun.

### Header Respons

`SecurityHeaders` memasang `X-Frame-Options`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy`, dan CSP pada seluruh respons.

---

## Keterbatasan yang Diketahui

Dicatat terbuka, bukan disembunyikan.

1. **CSP masih mengizinkan `'unsafe-eval'` dan `'unsafe-inline'` pada skrip.**
   Alpine mengevaluasi ekspresi `x-` lewat konstruktor `Function`. Menghapus
   izin ini menuntut migrasi ke Alpine CSP build lebih dulu.
2. **Font dilayani Google Fonts.** Setiap kunjungan halaman mengungkap alamat
   IP pengunjung ke pihak ketiga. Sebagian panduan keamanan instansi melarang
   ini; meng-hosting sendiri kedua famili font akan menghapus sekaligus
   pengecualian CSP dan pengungkapan tersebut.
3. **SSO OAuth2 belum ada.** Tombolnya ada di halaman masuk tetapi belum
   berfungsi. Disengaja: server OAuth setengah jadi lebih berbahaya daripada
   tidak ada sama sekali.
4. **Seeder menulis langsung ke tabel**, melewati service layer. Aman karena
   menolak berjalan di produksi, tetapi berarti data demo bisa melanggar aturan
   yang ditegakkan service.
5. **Belum ada 2FA.** Direncanakan pasca-1.0 untuk akun staf.
