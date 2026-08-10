# KINERJA.md — Kajian: Rencana Kinerja, IKU, dan SPMI

> **Status: pilihan C diambil, 11 Agustus 2026.**
>
> Lapisan perencanaan (sasaran unit + cascade + target/realisasi) dibangun di
> Open Academic. Dasbor 12 IKU, borang akreditasi, dan SPMI penuh **tetap milik
> Open Campus** — §Sengaja Bukan di Sini tidak dibatalkan, hanya dipertegas.
>
> Bagian kajian di bawah dipertahankan apa adanya, termasuk pilihan yang tidak
> diambil. Alasan sebuah keputusan hanya dapat dinilai bila alternatifnya masih
> terbaca.

---

## Konflik yang harus diselesaikan lebih dulu

Sebelum apa pun dirancang, ada keputusan lama yang menghalangi, dan ia tercatat:

> **`ROADMAP.md` §Sengaja Bukan di Sini**
> | Dasbor 12 IKU, borang akreditasi, **SPMI** | Open Campus — di sini hanya faktanya |

Dan kode menegakkannya. `IkuDataController` membuka dengan kalimat yang tidak
ambigu:

> *"**This is not an IKU calculator.** No score is produced, no threshold is
> applied, no target is compared against."*

Jadi membangun dasbor IKU atau SPMI di Open Academic **membatalkan keputusan
yang sudah diambil sadar**, bukan mengisi kekosongan. Itu boleh dilakukan —
keputusan lama bukan hukum — tapi harus dilakukan dengan mata terbuka dan
dicatat, bukan diselundupkan lewat modul yang kebetulan tumbuh ke sana.

Sisa dokumen ini menyiapkan bahan untuk keputusan itu.

---

## Kenapa batas itu dulu ditarik

Bukan soal kesulitan. Alasannya sama dengan CBT dan LMS: **bentuk datanya
berbeda, dan pemiliknya berbeda.**

Angka yang dibutuhkan sebuah dasbor IKU tidak semuanya ada di SIAKAD:

| IKU | Datanya di mana |
|---|---|
| 1 — lulusan mendapat pekerjaan | **tracer study** → Open Campus |
| 2 — mahasiswa berkegiatan di luar kampus | Open Academic ✅ |
| 3 — dosen berkegiatan di luar kampus | Open Academic ✅ |
| 4 — praktisi mengajar | Open Academic ✅ |
| 5 — hasil kerja dosen dipakai masyarakat | **penelitian & PkM** → sistem lain |
| 6 — kemitraan program studi | kerja sama → sistem lain |
| 7 — kelas kolaboratif & partisipatif | Open Academic ✅ |
| 8 — program studi berstandar internasional | akreditasi → sistem lain |

Empat dari delapan bukan milik Open Academic. Sebuah dasbor yang **menghitung**
IKU di sini akan memaksa empat sisanya diketik manual — dan angka yang diketik
manual di sebelah angka yang dihitung otomatis adalah dasbor yang tidak bisa
dipercaya sebagian, yang dalam praktiknya berarti tidak dipercaya seluruhnya.

---

## Tiga bentuk yang sering dikira satu

Kesalahan paling umum dalam modul semacam ini adalah menyatukan ketiganya ke
satu tabel "indikator" karena semuanya "punya target dan realisasi".

| | IKU & Standar Mutu | OKR / Renop | AMI (SPMI) |
|---|---|---|---|
| Menjawab | "berapa angkanya?" | "apa yang mau kita ubah?" | "apakah standar dipatuhi?" |
| Asal realisasi | **dihitung dari data** | dilaporkan pemilik | temuan auditor |
| Siklus | semester / tahun | kuartal | tahunan |
| Definisi boleh diubah? | **tidak** — rumusnya nasional | ya, itu gunanya | tidak setelah ditutup |
| Gagal berarti | angka di bawah target | pembelajaran | **ketidaksesuaian** |

Yang membuat penyatuan berbahaya bukan kerapiannya, melainkan kolom
**"definisi boleh diubah"**. Satu tabel berarti satu layar penyuntingan, dan
begitu seseorang dapat menyunting rumus IKU dari layar yang sama dengan tempat
ia menyunting OKR timnya, angka yang dilaporkan ke kementerian berhenti cocok
dengan rumus kementerian. Itu tidak terlihat sampai audit.

### SPMI bukan OKR

Bentuk SPMI adalah **audit**, dengan siklus PPEPP:

```
Penetapan standar → Pelaksanaan → Evaluasi → Pengendalian → Peningkatan
```

Yang menjadikannya SPMI justru bagian yang tidak dimiliki OKR: **temuan Audit
Mutu Internal yang tidak boleh disunting setelah ditutup**, dan tindak lanjut
yang diverifikasi ulang. Memaksanya ke pohon objective menghapus persis itu.

---

## Sikap yang disarankan: target diketik, realisasi dihitung

Konsisten dengan seluruh basis kode ini — BKD membekukan bobotnya, evaluasi
studi membekukan ambangnya, poin kemahasiswaan menyalin nilai poinnya.

**Yang boleh diketik manusia hanyalah targetnya.** Realisasi punya sumber, dan
sumbernya dicatat pada barisnya:

| `sumber_realisasi` | Artinya |
|---|---|
| `dihitung` | ditarik dari data; tidak dapat disunting siapa pun |
| `dilaporkan` | diketik pemilik unit, wajib disertai bukti |
| `eksternal` | datang dari sistem lain lewat Bridge |

Itu satu kolom, dan ia yang membedakan modul ini berguna atau sekadar formulir.
Sasaran yang realisasinya dihitung otomatis **tidak dapat dipoles menjelang
laporan** — dan setiap orang yang pernah menyiapkan borang tahu itu masalah
nyata, bukan hipotesis.

---

## Pemilik sasaran: unit, bukan orang

Ini keputusan struktural yang paling menentukan, dan ia berbeda dari yang
diambil di proyek KSP Humanix (`objective owner` manusia + `parent_objective_id`).

**Untuk kampus, pemiliknya sebaiknya unit kerja**, dengan penanggung jawab
diturunkan dari kepala unit saat itu.

Alasannya konkret: dekan berganti tiap empat tahun. Bila sasaran dimiliki orang,
sasaran fakultas ikut pindah ke mantan dekan dan penggantinya mulai dari nol;
bila dimiliki unit, pergantian pimpinan tidak memutus akuntabilitas, dan riwayat
tetap menunjukkan siapa yang memegangnya saat itu.

Struktur yang dibutuhkan **sudah ada**:

```
unit_kerja
├── parent_id            → cascade mengikuti pohon yang sama
├── kepala_staff_id      → penanggung jawab, diturunkan
└── kepala_dosen_id         (dekan itu dosen, kepala biro itu staf)
```

Cascade `parent_objective_id` dari KSP Humanix dapat dipakai ulang apa adanya;
yang berubah hanya jangkar kepemilikannya.

---

## Tiga pilihan, dan rekomendasinya

### A — Batas dipertahankan

Rencana kinerja, dasbor IKU, dan SPMI seluruhnya di Open Campus. Open Academic
menambah **satu hal saja**: mengekspos pohon unit kerja lewat Campus Bridge,
karena ia memang pemiliknya.

*Untung:* tidak ada keputusan yang dibatalkan; tidak ada data yang punya dua
pemilik.
*Rugi:* kampus yang hanya memakai Open Academic tidak mendapat apa pun.

### B — Batas dipindahkan

Open Academic menjadi system of record untuk organisasi **dan** perencanaan
kinerja, termasuk SPMI.

*Untung:* satu aplikasi, satu login, dan modul ini duduk persis di atas struktur
organisasi yang baru dibangun.
*Rugi:* empat dari delapan IKU harus diketik manual di sini; §Sengaja Bukan di
Sini harus ditulis ulang; dan Open Campus kehilangan alasan keberadaannya
sebagian.

### C — Dipecah menurut bentuk *(rekomendasi)*

| Di Open Academic | Di Open Campus |
|---|---|
| Pohon unit & penanggung jawab | Dasbor 12 IKU |
| **Sasaran unit + cascade** (OKR/Renop) | Borang akreditasi |
| Target & realisasi untuk indikator yang **datanya ada di sini** | SPMI penuh + AMI |
| Ekspor semuanya lewat Bridge | Menarik & menggabung dari banyak sumber |

Yang menentukan pembagiannya bukan selera, melainkan satu pertanyaan:
**apakah realisasinya dapat dihitung dari data yang aplikasi ini miliki?**
Bila ya, ia boleh tinggal di sini. Bila tidak, ia jadi formulir ketikan — dan
formulir ketikan lebih baik berada di tempat data pendukungnya berkumpul.

Dengan pembagian ini §Sengaja Bukan di Sini **tetap berlaku**: dasbor IKU dan
SPMI tetap milik Open Campus. Yang bertambah hanya lapisan perencanaan di atas
struktur organisasi — sesuatu yang memang belum dimiliki siapa pun.

---

## Bila C dipilih: bentuk datanya

```
periode_kinerja      tahun/semester, status: draf → berjalan → dikunci
  └── sasaran        milik unit_kerja, parent_id untuk cascade
        └── ukuran   target (diketik) + realisasi (per sumber_realisasi)
              └── capaian   snapshot berkala, dibekukan saat periode dikunci
```

Empat hal yang harus dijaga sejak awal, semuanya pelajaran dari modul lain di
repo ini:

1. **Target dan definisinya dibekukan** saat periode dikunci — aturan yang
   berubah tahun depan tidak boleh menulis ulang capaian tahun ini.
2. **Cascade menolak lingkaran** saat ditulis, seperti `UnitKerjaService`.
   Pohon berbasis penunjuk induk hanya punya satu mode kegagalan, dan ia senyap.
3. **Realisasi `dihitung` tidak dapat disunting** dari layar mana pun. Bukan
   dilarang lewat izin — tidak disediakan kolomnya.
4. **Capaian bukan penilaian orang.** Sistem melaporkan angka; apakah seorang
   kepala unit dinilai baik atau buruk adalah keputusan manusia dengan alasan
   tertulis — sikap yang sama dengan evaluasi studi dan poin kemahasiswaan.

---

## Yang belum dijawab kajian ini

- Apakah kampus target memakai Open Campus, atau Open Academic saja? Jawabannya
  menentukan A/B/C lebih kuat daripada argumen mana pun di atas.
- Apakah SPMI yang dibutuhkan setara BAN-PT (berat) atau sekadar pencatatan AMI
  (ringan)?
- Apakah penelitian & PkM akan punya sistemnya sendiri? Bila ya, IKU 5 dan 6
  jelas bukan di sini. Bila tidak, itu modul tersendiri yang lebih besar
  daripada seluruh kajian ini.

Tidak satu pun dari ketiganya dapat dijawab dari dalam kode.

---

# Bagian II — Implementasi

> Dibangun 11 Agustus 2026, mengikuti pilihan C. 18 tes.

## Bentuk data

```
periode_kinerja        draf → berjalan → dikunci   (searah)
 └── sasaran_kinerja   milik UNIT, parent_id untuk cascade
      └── ukuran_kinerja   target diketik + sumber_realisasi
           └── capaian_kinerja   check-in berkala
```

Ditambah satu kolom pada `unit_kerja`: **`prodi_id`**, nullable. Unit akademik
yang *adalah* sebuah prodi menunjuk ke sana alih-alih menyalin namanya. Tanpa
itu indikator yang dihitung tidak dapat dipersempit — data akademik
dikelompokkan per prodi, sedangkan pohon unit bersifat administratif.

## Kolom yang menentukan segalanya

`ukuran_kinerja.sumber_realisasi`:

| Nilai | Realisasinya |
|---|---|
| `dihitung` | ditarik dari data · **tidak dapat diketik dari layar mana pun** |
| `dilaporkan` | diketik pemilik unit |
| `eksternal` | datang lewat Bridge |

Larangannya bukan soal izin. `catatCapaian()` menolak ukuran non-`dilaporkan`
apa pun perannya — angka yang ditimpa manual tidak dapat lagi dibedakan dari
yang asli, dan seluruh nilai kolom ini justru pada ketidakmungkinan itu.

`PengukurKinerja` menghitung delapan indikator: mahasiswa aktif, lulusan,
peserta MBKM, kelas diampu praktisi, kelas kolaboratif, dosen berkegiatan di
luar kampus, rerata IPK, dan keterlaksanaan jurnal. Semua dipersempit ke prodi
unitnya, dan unit induk **menjumlahkan turunannya** — angka yang sebenarnya
dimaksud seorang dekan dengan "fakultas saya".

Ukuran `dihitung` yang menyebut indikator tak terdaftar **ditolak saat
ditulis**. Tanpa itu ia menjadi target yang tidak pernah dapat terealisasi, dan
tidak ada yang menyadarinya sampai tinjauan yang justru menjadi alasan ia
dibuat.

## Empat penjaga

1. **Realisasi `dihitung` tidak dapat diketik** — dibuktikan mengikat.
2. **Cascade menolak lingkaran**, termasuk induk lintas periode. Pohon berbasis
   penunjuk induk hanya punya satu mode kegagalan struktural dan ia senyap.
3. **Penguncian membekukan target dan realisasi, searah.** Dibuktikan mengikat:
   melumpuhkannya membuat angka terbaca 99 alih-alih 2 setelah baris capaian
   disunting.
4. **Tidak ada yang menilai orang.** Ambang capaian menghasilkan sebutan
   ("Tercapai", "Mendekati"), bukan penilaian atas kepala unit. Sikap yang sama
   dengan evaluasi studi dan poin kemahasiswaan.

Dua hal kecil yang ikut dijaga: persentase **dibalik** untuk ukuran yang makin
kecil makin baik — angka putus studi separuh dari batasnya adalah 200%, bukan
50% — dan **"belum terukur" dibedakan dari nol**, karena layar yang
menyamakannya mengundang percakapan yang salah.

## Penanggung jawab diturunkan, tidak disimpan

`SasaranKinerja::penanggungJawab()` membaca kepala unit saat ini. Tidak ada
kolom pemilik-orang di mana pun, dan itu disengaja: dekan berganti tiap empat
tahun, dan sasaran fakultas tidak boleh ikut pindah ke mantan dekan.

## Yang masih milik Open Campus

Tidak berubah: dasbor 12 IKU, borang akreditasi, SPMI penuh dengan AMI.
Batas itu dinyatakan di layar Rencana Kinerja sendiri, bukan hanya di dokumen
ini — supaya orang yang membukanya tahu apa yang sedang dan tidak sedang ia
lihat.

## Berkas

```
config/kinerja.php                          — katalog indikator + ambang
database/migrations/2026_08_19_100000_create_kinerja_tables.php
app/Enums/{SumberRealisasi,StatusPeriodeKinerja}.php
app/Models/Kinerja/{PeriodeKinerja,SasaranKinerja,UkuranKinerja,CapaianKinerja}.php
app/Services/Kinerja/PengukurKinerja.php    — yang membuat "dihitung" berarti
app/Services/Kinerja/KinerjaService.php     — cascade, penguncian, capaian
app/Http/Controllers/Admin/KinerjaController.php
resources/views/admin/kinerja.blade.php
tests/Feature/Kinerja/RencanaKinerjaTest.php
```
