# RPS-ANALITIK.md — Rencana Pembelajaran & Analitik Perkuliahan

Tiga hal yang saling bergantung, dan urutannya bukan kebetulan:

1. **RPS** memetakan mata kuliah ke capaian pembelajaran (CPL).
2. **Jurnal perkuliahan** mencatat apa yang benar-benar diajarkan.
3. **Analitik** membaca keduanya bersama nilai dan presensi.

Tanpa yang pertama, "seberapa jauh mahasiswa menguasai materi" hanya dapat
dijawab dengan nilainya — yaitu pertanyaannya diulang, bukan dijawab.

---

## Yang Membuat Angka Penguasaan Bermakna

### Komponen nilai dipetakan ke CPL, dengan porsi

Pivot `komponen_nilai_cpl`, bukan kolom tunggal. Satu UTS lazim mengukur dua
atau tiga CPL; memaksa memilih satu berarti CPL yang dibuang justru tampak tidak
pernah diukur.

```
UTS   bobot 30%   →  CPL-01 porsi 60%   →  bobot efektif 18
                  →  CPL-02 porsi 40%   →  bobot efektif 12
UAS   bobot 40%   →  CPL-01 porsi 100%  →  bobot efektif 40
```

Penguasaan CPL-01 = rerata nilai, ditimbang **bobot komponen × porsi CPL**:

```
(18 × 80 + 40 × 60) / (18 + 40) = 66,21
```

Ada tesnya, dan sudah dibuktikan mengikat: mengabaikan `porsi` mengubah hasilnya
menjadi 68,57 dan tesnya gagal seketika.

### RPS yang sudah terbit dibekukan

Nilai yang dicatat pada pekan keempat terhadap CPL-01 harus tetap milik CPL-01
pada pekan kedua belas. Revisi berarti **versi baru**, bukan menyunting yang
sedang dipakai menilai orang.

Satu RPS berlaku per mata kuliah per semester, dijaga kolom `kunci_aktif` yang
unik-dan-nullable — pola "satu yang aktif" yang sama dengan
`tugas_akhir.mahasiswa_aktif_id`.

Revisi dimulai dari salinan versi berlaku, bukan dari kosong. Mengetik ulang
enam belas pekan untuk memperbaiki satu di antaranya adalah cara sebuah kampus
berhenti merevisi apa pun.

### RPS tidak boleh mengaku lebih dari yang diukurnya

Terbit ditolak bila: pertemuannya belum lengkap, bobotnya bukan 100%, atau tidak
ada satu pun CPL dibebankan. Dan layar analitik menyebut **CPL yang dibebankan
tetapi tidak diukur komponen mana pun** — celah yang persis dicari saat
visitasi, karena CPL itu tampak diajarkan dan tidak pernah dapat dilaporkan.

Mata kuliah yang mengaku menjawab seluruh CPL sama dengan tidak menjawab satu
pun: angka ketercapaiannya menjadi rerata atas segalanya dan berhenti menunjuk
ke mana-mana.

---

## CPMK dan Sub-CPMK — Kurikulum Berbasis Capaian (ADR-038)

Sejak 29 Oktober 2026 lapis di bawah CPL bukan teks bebas lagi:

```
CPL prodi (prodi_cpl)
  └─ CPMK per mata kuliah per kurikulum (cpmk_versi → cpmk, cpmk_cpl.bobot)
       └─ sub-CPMK (sub_cpmk.bobot, berjumlah 100 dalam satu CPMK)
            └─ diukur komponen nilai (komponen_nilai_cpmk.porsi, berjumlah 100 per komponen)
```

- **Berversi per kurikulum, beku setelah berlaku.** Disusun prodi di
  `/admin/cpmk` atau dosen pengampu dari RPS (`/dosen/rps/cpmk/{mk}`) — satu
  penyunting. Terbit hanya bila sub-CPMK satu CPMK berjumlah 100 dan, untuk
  setiap CPL, bobot CPMK yang mendukungnya berjumlah 100. Revisi = versi baru;
  versi lama diarsipkan, barisnya tidak disentuh.
- **RPS satu sumber.** RPS memasang versi berlaku; CPL-nya diturunkan dari CPMK
  (`rps_cpl` menjadi cerminan), dan tiap pertemuan dapat menautkan sub-CPMK.
- **Rencana evaluasi** menampilkan matriks komponen × sub-CPMK. Sub-CPMK yang
  tidak diukur komponen mana pun diperingatkan di layar nilai dan di rekap.
- **Disimpan saat final.** Capaian per CPMK (dengan `cakupan`) dan per CPL per
  mahasiswa ditulis di dalam transaksi finalisasi, lalu hanya berubah lewat
  koreksi nilai per komponen (`/admin/koreksi-nilai`, "Koreksi lewat
  komponen"). Koreksi nilai akhir saja tidak menggeser capaian — capaian
  diukur dari komponen.

Contoh satu mahasiswa, Tugas 80 / UTS 60 / UAS 90 (bobot 30/30/40):

```
Tugas → Sub 1.1 (100%)          Sub 1.1 = 80
UTS   → Sub 1.2 (50%), Sub 2.1 (50%)   Sub 1.2 = Sub 2.1 = 60
UAS   → Sub 2.2 (50%), CPMK-3 (50%)    Sub 2.2 = CPMK-3 = 90

CPMK-1 = 50%·80 + 50%·60 = 70        (Sub 1.1 50, Sub 1.2 50)
CPMK-2 = 40%·60 + 60%·90 = 78        (Sub 2.1 40, Sub 2.2 60)
CPL-A  = 60%·70 + 40%·78 = 73,2      (CPMK-1 60, CPMK-2 40)
CPL-B  = 50%·78 + 50%·90 = 84        (CPMK-2 50, CPMK-3 50)
```

Lintas mata kuliah, CPL mahasiswa dibobot SKS. Rekap: kelas
(`/dosen/capaian/{kelas}`), prodi per angkatan (`/admin/capaian`), mahasiswa
(`/mahasiswa/capaian`), masing-masing dengan CSV; tabel capaian pembelajaran di
borang LKPS membaca baris yang sama.

Pemetaan langsung komponen → CPL di atas tetap terbaca untuk kelas lama, sebagai
"pengukuran berjalan"; pemetaan baru memakai CPMK.

---

## Jurnal Perkuliahan

Presensi menjawab **siapa yang hadir**. Jurnal menjawab **apa yang diajarkan** —
separuh yang ditanya saat monitoring, dan separuh yang di banyak kampus masih
ditulis tangan.

Kolom ditambahkan ke `pertemuan_kelas`, bukan tabel paralel: satu-ke-satu
dengan pertemuannya, dan tabel kedua hanya menambah join di setiap layar
presensi.

**Jurnal sengaja boleh berbeda dari RPS.** Libur nasional menggeser pekan lima
ke pekan enam, dua rencana digabung, kuliah tamu menggantikan satu topik.
Memaksa jurnal cocok dengan rencana justru menghapus informasi yang dicari orang
yang bertanya kenapa hanya dua belas dari enam belas pertemuan tersampaikan.

**Cacah kehadiran dibekukan saat jurnal diisi.** Jurnal adalah pernyataan
tentang satu sore; menghitungnya ulang berbulan-bulan kemudian — setelah koreksi
presensi, atau setelah seorang mahasiswa mengundurkan diri — akan mengubah
catatan yang sudah ditandatangani.

### Dua angka, dan jaraknya adalah temuannya

| | |
|---|---|
| `terlaksana` | Pertemuan yang terjadi, dari daftar hadir |
| `berjurnal` | Pertemuan yang punya catatan |

Kelas dengan empat belas terlaksana dan empat berjurnal **bukan mengajar lebih
sedikit** — ia mendokumentasikan lebih sedikit. Melaporkan satu angka saja
menyembunyikan mana dari dua masalah itu yang dipunyai kampusnya.

---

## Analitik: Tiga Lensa

| Lensa | Isinya |
|---|---|
| **Kehadiran** | Rerata, sebaran, dan daftar di bawah ambang UAS |
| **Penilaian** | Rerata per komponen, rentang, dan **komponen terlemah yang disebut namanya** |
| **Penguasaan CPL** | Ketercapaian per CPL, untuk kelas dan untuk mahasiswa |

"Kelasnya jelek" tidak dapat ditindaklanjuti; "praktikumnya jelek" dapat. Karena
itu komponen terlemah dinamai, bukan diserahkan ke mata pembaca atas tabel lima
baris setiap kali.

Perhitungan kehadiran **mendelegasi ke `PresensiService`**, tidak mengulanginya.
Dua implementasi "persentase kehadiran" adalah cara sebuah dasbor dan sebuah
lembar nilai berselisih tentang mahasiswa yang sama — pelajaran yang sudah
dibayar saat tiga perhitungan IPK disatukan menjadi `PerolehanAkademik`.

### Tampilan mahasiswa

Nilai 68 di tiga mata kuliah tidak mengatakan apa-apa. "Lemah pada CPL-03 di
mana pun ia diukur" adalah kalimat yang dapat dibawa dosen wali ke pertemuan.
Karena itu daftarnya **diurutkan terlemah lebih dulu**, dan tiap baris menyebut
di mata kuliah mana saja CPL itu diukur.

---

## Yang Modul Ini Menolak Lakukan

**Tidak ada prediksi.** Tidak ada model yang memperkirakan apakah seorang
mahasiswa akan lulus, karena Open Academic tidak punya riwayat maupun validasi
untuk membuat klaim itu jujur — dan angka yang disajikan sebagai ramalan akan
dipercaya seperti ramalan.

Yang ada dua jenis:

- **Fakta** — persentase, rerata, ketercapaian. Dicacah, bukan diperkirakan.
- **Aturan** — peringatan yang menyala saat sebuah angka melewati ambang yang
  ditetapkan kampus.

Daftar "perlu perhatian" berisi **alasan tertulis, bukan skor risiko**. Indeks
risiko berperingkat mengundang pembacanya memperlakukan kombinasi aritmetik dua
angka tak sejenis sebagai ramalan; daftar beralasan mengundangnya memeriksa.

Kata "potensi" pada penguasaan materi berarti: ketercapaian yang **sudah**
terukur atas CPL yang **sudah** dinilai. Ia bacaan tentang hari ini, tidak
diekstrapolasi oleh apa pun.

### Nol bukan jawaban yang jujur

Ketika komponen belum dipetakan ke CPL, layarnya berkata **belum dipetakan** —
bukan menampilkan nol. Nol akan terbaca "mahasiswa tidak menguasai apa pun",
padahal artinya "belum ada yang menyatakan ujian ini mengukur apa".

---

## Ambang Adalah Kebijakan

`config('academic.cpl.ambang_penguasaan')`, bawaan 65.

Sikap yang sama dengan bobot SKS BKD dan bagan akun: Open Academic menjamin
perhitungannya benar dan menyerahkan ambangnya. Ambang **selalu ditampilkan
bersama angkanya**, supaya pembaca dapat berselisih dengan ambangnya — bukan
dengan mahasiswanya.

---

## Berkas

```
database/migrations/2026_08_16_100000_create_rps_tables.php
database/migrations/2026_08_16_100001_add_jurnal_to_pertemuan_kelas.php
config/academic.php                          — bagian 'cpl'

app/Enums/StatusRps.php
app/Models/Akademik/{Rps,RpsPertemuan}.php
app/Models/Akademik/KomponenNilai.php        — relasi cpl()

app/Services/Akademik/RpsService.php         — susun, terbitkan, kunci
app/Services/Akademik/JurnalService.php      — BAP & keterlaksanaan
app/Services/Akademik/AnalitikService.php    — tiga lensa

app/Http/Controllers/Dosen/{RpsController,AnalitikController}.php
app/Http/Controllers/Mahasiswa/PenguasaanController.php
resources/views/dosen/{rps,rps-susun,jurnal,analitik,analitik-kelas}.blade.php
resources/views/mahasiswa/penguasaan.blade.php

database/seeders/Demo/RpsSeeder.php
tests/Feature/Akademik/RpsAnalitikTest.php

# Kurikulum berbasis capaian (ADR-038)
database/migrations/2026_10_31_100000_create_capaian_mata_kuliah_tables.php
app/Enums/StatusCpmk.php
app/Models/Akademik/{CpmkVersi,Cpmk,SubCpmk,KomponenNilaiCpmk,CapaianCpmkMahasiswa,CapaianCplMahasiswa}.php
app/Services/Akademik/CpmkService.php        — hierarki, versi, sasaran ukur
app/Services/Akademik/CapaianService.php     — pemetaan, cakupan, perhitungan
app/Services/Akademik/RekapCapaianService.php — rekap kelas/prodi/mahasiswa, LKPS
app/Policies/Akademik/{CpmkVersiPolicy,CapaianCplMahasiswaPolicy}.php
app/Http/Controllers/{Admin,Dosen}/{CpmkController,CapaianController}.php
resources/views/capaian/_editor.blade.php    — penyunting bersama
lang/id/capaian.php
database/seeders/Demo/CapaianSeeder.php
tests/Feature/Akademik/{CpmkService,CapaianService,RekapCapaian,ObeDemo}Test.php
```
