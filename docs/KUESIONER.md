# KUESIONER.md — Kuesioner Umum

Mesin EDOM yang digeneralisasi, **tanpa kehilangan anonimitasnya**.

---

## Properti yang dipertahankan

Anonimitas EDOM bersifat **struktural**, bukan janji runtime:

- `edom_jawaban` tidak punya kolom yang bisa menunjuk mahasiswa
- `edom_partisipasi` hanya mencatat bahwa seseorang mengisi, tidak pernah apa

Tidak ada yang perlu diingat atau ditegakkan saat berjalan, karena **tidak ada
tempat untuk menaruh tautannya.**

Modul ini mempertahankannya dengan menolak penyederhanaan yang jelas: satu tabel
jawaban dengan kolom responden yang nullable.

Dengan bentuk itu, anonimitas menjadi properti **baris**, bukan properti skema.
Satu bug, satu migrasi, atau satu niat baik "ayo isi datanya siapa yang mengisi"
akan mengakhirinya diam-diam — untuk data yang sudah terkumpul di bawah sebuah
janji.

Maka ada **dua tabel jawaban**, dan tabel mana yang dipakai ditetapkan saat
kuesionernya dibuat.

| Tabel | Kolom responden |
|---|---|
| `kuesioner_jawaban_anonim` | **tidak ada** |
| `kuesioner_jawaban` | ada, sengaja |
| `kuesioner_partisipasi` | ada — hanya siapa, tidak pernah apa |

Ada tes yang memeriksa **daftar kolom** tabel anonim dan menolak `responden_id`,
`responden_type`, maupun `mahasiswa_id`. Ia menguji skemanya, bukan perilakunya.

---

## `anonim` tidak dapat diubah setelah dibuat

Membaliknya belakangan akan membuat jawaban yang sudah terkumpul jadi yatim —
atau, lebih buruk, secara surut menempelkan nama pada jawaban yang diberikan
dengan pengertian bahwa tidak ada nama yang disimpan.

---

## Satu tempat kedua bentuk itu berpisah

Seluruh alur `isi()` identik sampai baris terakhir:

```php
if ($kuesioner->anonim) {
    KuesionerJawabanAnonim::create($baris);
} else {
    KuesionerJawaban::create([...$baris, 'responden_type' => ..., 'responden_id' => ...]);
}
```

Sengaja: tidak ada jalur kode kedua yang bisa lupa.

Partisipasi dan jawaban ditulis dalam satu transaksi, ke tabel berbeda. Untuk
form anonim keduanya tidak pernah dapat di-join. Crash di antaranya
meninggalkan kuesioner yang dapat diisi dua kali — dapat dipulihkan; urutan
sebaliknya akan meninggalkan jawaban yang dapat diatribusikan selama belum ada
yang menyadarinya.

---

## Tiga tipe pertanyaan, dan tidak lebih

| Tipe | Diagregasi sebagai |
|---|---|
| Skala 1–5 | rerata |
| Pilihan | cacah per opsi |
| Isian bebas | **tidak diagregasi** — hanya didaftar |

Rerata dari prosa bukan apa-apa, dan cacah prosa tidak memberi tahu pembaca hal
yang dapat ditindaklanjuti.

Setiap tipe tambahan adalah satu cabang lagi di formulir, validator, dan
agregasi — dan tipe keempat yang diminta kampus selalu dapat dinyatakan dengan
salah satu dari tiga ini.

---

## Layar (Sesi 69)

| Portal | Rute | Isi |
|---|---|---|
| Staf | `/admin/kuesioner` | Daftar, buat draf (`kuesioner.manage`) |
| Staf | `/admin/kuesioner/{uuid}` | Data, pertanyaan, terbitkan/tutup |
| Staf | `/admin/kuesioner/{uuid}/hasil`, `/ekspor` | Hasil teragregasi, CSV (`kuesioner.view`) |
| Mahasiswa / Dosen | `/{portal}/kuesioner` | Kuesioner yang dibuka untuknya, formulir isi |
| Staf | `/admin/kuesioner-saya` | Staf sebagai responden |

**Sasaran** adalah satu peran, dipersempit ke satu program studi bila perlu
(`prodi_id`; kosong = seluruh kampus; staf tidak berprodi). Dosen dicocokkan
dengan prodi homebase-nya. Sasaran diperiksa dua kali: policy menolak membuka
formulir, dan `KuesionerService::isi()` menolak menyimpan — jalur tanpa
controller tidak bisa lupa.

**Terbit pertama membekukan pertanyaan.** Jawaban harus tetap menjawab
pertanyaan yang sama; kuesioner yang ditutup boleh dibuka kembali, tetapi
pertanyaannya tetap beku. Sasaran dan ambang ikut beku. Nama, pengantar, dan
periode tetap dapat diubah.

**Hasil kuesioner anonim tertahan di bawah ambang** (`min_responden`, bawaan
`KUESIONER_MIN_RESPONDEN=5`, disimpan per kuesioner saat dibuat) — aturan EDOM.
Selama tertahan, controller tidak menghitung hasilnya sama sekali, jadi tidak
ada jawaban yang dimuat lalu disembunyikan template. Kuesioner beridentitas
tidak memakai ambang.

**Layar hasil dan ekspor menjaga anonimitas yang sama dengan skemanya:**

| | Anonim | Beridentitas |
|---|---|---|
| Layar hasil | rerata, sebaran, isian bebas | sama — teragregasi |
| Isian bebas | diurutkan abjad, bukan urutan pengisian | sama |
| CSV | per pertanyaan: cacah per nilai/opsi, isian bebas terurut; tanpa id, waktu, atau nama | satu baris per responden: nama, nomor induk, waktu isi, jawaban |
| Daftar siapa yang mengisi | tidak ditampilkan, hanya jumlah | tersedia lewat CSV |

Urutan pengisian disingkirkan karena ia satu-satunya hal yang dapat menjajarkan
jawaban anonim dengan baris `kuesioner_partisipasi`. Ekspor CSV atau XLSX
(`?format=xlsx`) lewat `App\Support\EksporTabel`; sel CSV melewati
`App\Support\Csv` (penangkal formula).

**Sisa risiko yang diketahui.** Bagi yang memegang akses basis data langsung,
`kuesioner_jawaban_anonim.id` dan `created_at` masih berurutan sejalan dengan
`kuesioner_partisipasi`. Layar dan ekspor tidak pernah memperlihatkannya; menutup
celah di tingkat tabel tercatat sebagai butir ROADMAP tersendiri.

---

## Berkas

```
database/migrations/2026_08_18_100004_create_kuesioner_tables.php
app/Enums/{SasaranKuesioner,TipePertanyaan}.php
app/Models/Kuesioner/*.php
app/Services/Kuesioner/KuesionerService.php
app/Enums/StatusKuesioner.php                 — draf / dibuka / ditutup, dibaca dari kolom
app/DTOs/Kuesioner/{KuesionerData,PertanyaanData}.php
app/Policies/Kuesioner/KuesionerPolicy.php
app/Http/Controllers/Admin/KuesionerController.php
app/Http/Controllers/Kuesioner/IsiKuesionerController.php — tiga portal
database/migrations/2026_10_29_400000_add_pengelolaan_to_kuesioner.php
database/seeders/Demo/KuesionerSeeder.php
lang/id/kuesioner.php
tests/Feature/Kuesioner/KuesionerTest.php
tests/Feature/Kuesioner/PengelolaanKuesionerTest.php
```
