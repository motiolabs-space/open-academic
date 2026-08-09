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

## Berkas

```
database/migrations/2026_08_18_100004_create_kuesioner_tables.php
app/Enums/{SasaranKuesioner,TipePertanyaan}.php
app/Models/Kuesioner/*.php
app/Services/Kuesioner/KuesionerService.php
tests/Feature/Kuesioner/KuesionerTest.php
```
