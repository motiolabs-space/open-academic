# UNIT-KERJA.md — Struktur Organisasi

Menggantikan `staff.unit`, sebuah kolom teks bebas.

---

## Apa yang rusak sebelumnya

Kolom itu berisi apa pun yang diketik orang: `BAAK`, `Baak`, `Bag. Akademik`.
Sejauh laporan mana pun tahu, itu tiga kantor berbeda.

Tiga hal yang tidak mungkin dilakukan dengannya, dan sekarang bisa:

- **menggulung ke atas** — "berapa staf di bawah Biro Akademik" tidak punya jawaban
- **mendisposisi** — tidak ada yang bisa jadi tujuan disposisi
- **melaporkan** — rekap per unit menghitung tiga unit di tempat yang seharusnya satu

---

## Kolom lamanya tetap ada

`staff.unit` **tidak dihapus.** Ia adalah bukti bagaimana seseorang dulu
diarsipkan, dan membuangnya berarti membuang satu-satunya cara memeriksa apakah
backfill menebak dengan benar. Semua pembacaan sekarang lewat `unit_kerja_id`.

Backfill-nya sengaja datar dan sengaja bodoh: ia **tidak** mencoba menebak bahwa
"Bag. Akademik" dan "BAAK" adalah kantor yang sama. Tebakan yang salah di sana
tidak terlihat dan permanen; baris duplikat terlihat di layar dan hilang dengan
satu klik.

Staf yang tidak berhasil dipetakan **ditampilkan sebagai peringatan** di layar,
bukan disembunyikan — staf tanpa unit tidak terhitung di rekap mana pun, dan itu
persis kegagalan tak terlihat yang dulu disebabkan kolom teks bebas.

---

## Satu-satunya cara pohon ini rusak

Pohon yang disimpan sebagai penunjuk induk punya tepat satu mode kegagalan
struktural: **lingkaran**. Dan ia senyap — tidak ada yang gagal saat menyimpan,
lalu setiap penelusuran sesudahnya berjalan selamanya.

Karena itu ditolak saat ditulis, di tempat alasannya bisa dijelaskan:

> Unit "C" berada di bawah "A", jadi menjadikannya induk akan membentuk lingkaran.

Penelusurannya naik dari calon induk, bukan turun dari unitnya: rantai leluhur
paling dalam sedalam pohonnya, sedangkan himpunan turunan bisa sebesar seluruh
kampus.

Dibuktikan mengikat: melepas penjaganya membuat dua tes gagal seketika. Dan
`turunan()` tetap dibatasi jumlah simpul — loop yang memercayai datanya asiklik
hanya berjarak satu baris buruk dari menggantung request yang membacanya.

---

## Kepala unit: dua kolom, satu terisi

Dekan itu **dosen**. Kepala biro itu **staf**. Memaksakan keduanya ke satu tabel
berarti mengarang baris palsu di tabel yang lain untuk separuh bagan organisasi.

Jadi ada `kepala_staff_id` dan `kepala_dosen_id`, dan servicenya menolak bila
keduanya terisi. Dua-duanya terisi bukan jawaban yang lebih kaya — itu **dua
jawaban**, dan setiap layar yang menampilkan "kepala unit" harus memilih salah
satu, berbeda-beda.

---

## Menonaktifkan ditolak selagi masih dipakai

| Kondisi | Hasil |
|---|---|
| Masih ada staf di dalamnya | ditolak, sebutkan jumlahnya |
| Masih membawahi unit aktif | ditolak, sebutkan jumlahnya |
| Penempatan baru ke unit nonaktif | ditolak |

Menonaktifkan diam-diam meninggalkan staf yang menunjuk unit yang tidak muncul
di daftar mana pun — terbaca sebagai "tanpa unit" di setiap layar dan sebagai
baris hilang di setiap laporan.

---

## Rekap bertingkat

Kolom **+ Bawahan** menghitung staf termasuk seluruh unit di bawahnya. Itu angka
yang sebenarnya ditanyakan seorang kepala biro — bukan "berapa yang tercatat
persis di level saya".

Dihitung sekali di controller, bukan per baris di view, di mana ia akan menjadi
penelusuran subpohon di dalam perulangan.

---

## Berkas

```
database/migrations/2026_08_18_100002_create_unit_kerja_table.php   — termasuk backfill
app/Enums/JenisUnitKerja.php
app/Models/Sdm/UnitKerja.php
app/Models/Sdm/Staff.php                       — relasi unitKerja()
app/Services/Sdm/UnitKerjaService.php
app/Http/Controllers/Admin/UnitKerjaController.php
resources/views/admin/unit-kerja.blade.php
tests/Feature/Sdm/UnitKerjaTest.php
```
