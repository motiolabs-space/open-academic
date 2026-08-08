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
```
