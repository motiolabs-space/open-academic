# EDOM.md — Evaluasi Dosen oleh Mahasiswa

Setiap kampus punya EDOM. Sebagian besar tidak dipercaya siapa pun: mahasiswa
tidak yakin jawabannya benar-benar anonim, dosen tidak yakin angkanya berarti
apa-apa, dan tingkat pengisiannya belasan persen.

Ketiganya adalah satu masalah. Mahasiswa yang tidak percaya anonimitas akan
menulis apa yang aman, bukan apa yang ia pikirkan — dan instrumennya lalu
mengukur kehati-hatian.

Modul ini berangkat dari sana.

---

## Anonimitas Ditegakkan Skema, Bukan Kedisiplinan

Janji anonimitas yang bergantung pada *tidak ada orang menjalankan kueri yang
salah* bukanlah anonimitas. Ia bertahan tepat sampai seseorang punya alasan
bagus untuk melanggarnya.

Karena itu jaminannya diletakkan pada bentuk tabelnya:

```
edom_partisipasi  — SIAPA sudah mengisi APA.   Tidak memuat jawaban.
edom_jawaban      — APA yang dijawab.          Tidak memuat mahasiswa.
```

**Tidak ada kunci apa pun yang menghubungkan keduanya.** Bukan yang nullable,
bukan yang tak langsung. Tidak ada `mahasiswa_id`, tidak ada
`edom_partisipasi_id`, dan tidak ada pengenal respons — pengenal respons pun akan
mengorelasikan pendapat satu orang lintas pertanyaan, cukup untuk merekonstruksi
individu pada kelas kecil.

Konsekuensinya nyata dan sengaja diterima:

- Mahasiswa **tidak dapat** mengubah jawabannya. Untuk itu sistem harus tahu
  jawaban mana miliknya.
- Satu pengisian yang menyimpang **tidak dapat** dicabut belakangan.
- Tidak ada laporan "siapa menjawab apa" yang dapat dibuat, sekarang maupun
  bertahun-tahun lagi, oleh siapa pun yang punya akses SQL langsung.

Yang ketiga itulah harganya, dan itulah yang dibeli.

Satu tes menjaga sifat ini dan akan gagal seketika begitu ada yang menambahkan
kolom penghubung demi permintaan yang terdengar wajar:

```php
$kolom = Schema::getColumnListing('edom_jawaban');

expect($kolom)->not->toContain('mahasiswa_id')
    ->and($kolom)->not->toContain('edom_partisipasi_id')
    ->and($kolom)->not->toContain('respons_id');
```

Kedua baris ditulis dalam satu transaksi, jadi mahasiswa tidak pernah tercatat
sudah mengisi tanpa jawabannya mendarat, atau sebaliknya.

---

## Ambang Responden

Rerata bukan bagian yang sulit. Yang sulit adalah ambang.

Tiga jawaban pada kelas berisi empat orang memberi tahu dosennya hampir persis
siapa berpendapat apa. Karena itu di bawah ambang, `HasilEdom::kelas()`
mengembalikan **tidak ada apa-apa** — bukan baris tersembunyi dengan cacahnya
tetap terlihat, bukan pula "data tidak cukup (n=3)". Cacahnya sendiri adalah
petunjuk ketika daftar hadirnya empat orang.

Ambangnya per periode (`min_responden`, bawaan 5), karena kampus dapat
menaikkannya setelah suatu insiden.

Satu pengecualian yang disengaja: `HasilEdom::partisipasiKelas()` — yang dibaca
layar admin — **tetap** menampilkan cacah pengisi di bawah ambang. Siapa yang
sudah mengisi memang sudah terlihat oleh administrator; itu dasar kerja
gerbangnya. Yang dilindungi ambang adalah kaitan antara orang dan pendapat, dan
cacah tidak mengungkapkannya. Menyembunyikannya hanya akan menghalangi orang
menyadari ada kelas yang belum diisi siapa pun.

---

## Komentar Bebas Punya Aturan Sendiri

Angka dapat dirata-ratakan sampai tidak seorang pun dapat dikenali. Kalimat
tidak: satu komentar tajam pada kelas berisi tujuh orang menunjuk penulisnya
lewat isinya sendiri, sekalipun namanya tidak pernah disimpan.

`config('edom.komentar')`:

| Nilai | Siapa yang membaca |
|---|---|
| `prodi` | Pengelola program studi, bukan dosen yang dinilai. **Bawaan.** |
| `dosen` | Dosen membacanya sendiri, di atas ambang responden. |
| `tutup` | Tidak ditampilkan di mana pun; hanya skor. |

Urutannya selalu diacak sebelum ditampilkan. Urutan penyisipan adalah urutan
pengiriman, dan itu pengurutan parsial atas siapa menjawab kapan.

---

## Gerbang

EDOM tanpa gerbang diisi belasan persen mahasiswa, dan hasilnya condong ke yang
paling puas dan yang paling marah — dua kelompok yang paling termotivasi
mengisi survei sukarela. Gerbang adalah satu-satunya cara memperoleh tingkat
pengisian yang berarti.

`config('edom.gerbang')`:

| Nilai | Yang ditahan |
|---|---|
| `nonaktif` | Tidak ada. |
| `krs` | Pengajuan KRS semester berikutnya. **Bawaan.** |
| `khs` | Tampilan kartu hasil studi. |

Bawaannya `krs`, dan itu posisi. Keduanya sama-sama memaksa, tetapi menahan KHS
berarti memakai catatan akademik yang **sudah diperoleh** mahasiswa sebagai alat
tukar untuk sebuah survei. Menahan pengajuan KRS menahan tindakan yang belum
terjadi.

Praktik `khs` lazim di Indonesia, jadi tetap disediakan. Pilihlah dengan sadar.

Gerbangnya dipasang paling akhir di `KrsService::penghalangPengajuan()` dan
berbentuk kalimat, bukan penolakan — ia satu-satunya penghalang di sana yang
dapat diselesaikan sendiri oleh mahasiswa dalam lima menit.

---

## Instrumen Terkunci Setelah Jawaban Pertama

Pertanyaan dimiliki periode, bukan bank soal global. Mengubah rumusan bersama
akan diam-diam menulis ulang arti jawaban tahun lalu — rerata 4,2 atas pertanyaan
yang kalimatnya sudah berganti adalah angka tentang tidak ada apa-apa.

`KelolaEdom::pastikanBelumDijawab()` menolak penambahan, penghapusan, maupun
penyalinan pertanyaan begitu satu jawaban masuk. Revisi instrumen dilakukan
dengan membuat periode baru; `salinPertanyaan()` menyalin barisnya, bukan
memakainya bersama, supaya hasil periode lama tetap terikat pada rumusan lamanya.

`aktifkan()` menolak periode tanpa pertanyaan. Periode terbuka yang kosong
memperlihatkan formulir yang tidak bisa dikirim — dan bila gerbang menyala, ia
menahan KRS di balik sesuatu yang mustahil diselesaikan mahasiswa.

---

## Izin

| Izin | Guard | Untuk |
|---|---|---|
| `edom.isi` | mahasiswa | Mengisi evaluasi |
| `edom.hasil` | dosen | Membaca hasil **sendiri** |
| `edom.view` | staff | Membaca rekap & hasil kelas |
| `edom.manage` | staff | Membuka periode, menulis pertanyaan |

`pimpinan` memperoleh `edom.view` tanpa `edom.manage`: instrumen yang
pertanyaannya dapat disunting oleh orang yang dinilai olehnya tidak mengukur
apa-apa.

Layar dosen tidak memuat pengenal dosen di URL-nya sama sekali. Tidak ada bentuk
permintaan yang mengembalikan nilai kolega, bukan karena ada pemeriksaan yang
menolaknya, melainkan karena tidak ada parameter yang dapat diubah.

---

## Campus Bridge

`GET /api/bridge/v1/teaching-evaluations` — butuh scope `evaluations.read` **dan**
`lecturers.read`.

Yang **tidak** dapat dikembalikannya adalah rancangannya: tidak ada parameter,
scope, maupun jalur kode yang menghasilkan satu jawaban, satu komentar, atau
identitas pengisi. Kelas di bawah ambang tidak menyumbang apa pun, termasuk cacah
respondennya.

Agregat inilah yang dikonsumsi Open Campus untuk dasbor mutu pengajaran. Batas
modulnya: pertanyaan, jawaban, dan gerbang tinggal di Open Academic karena
keduanya melekat pada KRS dan daftar peserta kelas; Open Campus membaca hasilnya.

---

## Berkas

```
database/migrations/2026_08_13_100000_create_edom_tables.php
config/edom.php

app/Enums/KategoriEdom.php
app/Enums/TipeJawabanEdom.php
app/Models/Edom/{EdomPeriode,EdomPertanyaan,EdomPartisipasi,EdomJawaban}.php

app/Services/Edom/EdomService.php     — pengisian, tertunda, penghalang
app/Services/Edom/HasilEdom.php       — agregasi + ambang
app/Services/Edom/KelolaEdom.php      — periode & instrumen

app/Http/Controllers/Mahasiswa/EdomController.php
app/Http/Controllers/Dosen/EdomController.php
app/Http/Controllers/Admin/EdomController.php
app/Http/Controllers/Api/Bridge/TeachingEvaluationController.php

resources/views/{mahasiswa,dosen}/edom.blade.php
resources/views/admin/{edom,edom-kelas}.blade.php

database/seeders/Demo/EdomSeeder.php
tests/Feature/Edom/EdomTest.php
```
