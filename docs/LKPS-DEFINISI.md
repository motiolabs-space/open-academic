# LKPS-DEFINISI.md — Delapan Keputusan Sebelum Angkanya Dihitung

> Disusun 21 Agustus 2026. Setiap pilihan di bawah diambil dari nilai yang
> **benar-benar ada** di basis data ini — status, kolom, dan tahapan yang
> tercantum bukan karangan, melainkan hasil membaca enum dan migrasinya.
>
> Nomor dan susunan tabel LKPS berbeda antar-LAM dan berubah antar-revisi
> instrumen; yang di sini adalah **besarannya**, bukan letaknya di borang.

Angka LKPS jarang meleset karena rumusnya. Ia meleset karena dua orang
memakai definisi yang berbeda untuk kata yang sama, dan tidak ada yang
menyadarinya sampai asesor bertanya.

Delapan pertanyaan berikut adalah tempat itu terjadi. Jawabannya keputusan
kampus — bukan tebakan yang boleh diambil aplikasi. Setelah dijawab, keputusan
itu dikunci sebagai config dan diuji, sehingga angka tahun ini dan angka tahun
depan dihitung dengan aturan yang sama.

**Yang tidak menunggu jawaban:** rumus dasarnya sendiri. Bagian itu dikerjakan
lebih dulu, dan tiap keputusan di bawah masuk sebagai parameter.

---

## 1. Keketatan — siapa yang disebut "pendaftar"?

Alur PMB di aplikasi ini punya delapan tahap:

```
mendaftar → verifikasi → seleksi → lulus / tidak_lulus → daftar_ulang → mahasiswa
                                                                    batal
```

Rasio keketatan = pendaftar : diterima. Yang perlu diputuskan: **garisnya di
mana.**

| Pilihan | "Pendaftar" | Akibatnya |
|---|---|---|
| **A** | Semua yang berstatus `mendaftar` ke atas | Angka terbesar; termasuk yang tak pernah menyerahkan berkas |
| **B** | Mulai `verifikasi` ke atas | Hanya yang berkasnya lengkap |
| **C** | Mulai `seleksi` ke atas | Hanya yang benar-benar diseleksi |

Dan "diterima": status `lulus` saja, atau `lulus` + `daftar_ulang` +
`mahasiswa`?

> **Kenapa penting.** Pilihan A membuat keketatan terlihat lebih baik, dan
> asesor yang mengecek silang ke PDDIKTI akan menemukan angka yang berbeda.
> Yang dilaporkan sebaiknya sama dengan yang dilaporkan ke Feeder.

**Jawaban: ____**

---

## 2. Dosen tetap prodi (DTPS) — siapa yang dihitung?

`dosen.status_kepegawaian` berisi tiga nilai: `tetap`, `tidak_tetap`,
`luar_biasa`. Terpisah dari itu ada penanda `is_praktisi`.

| Pertanyaan | Pilihan |
|---|---|
| Yang masuk DTPS | `tetap` saja / `tetap` + `tidak_tetap` |
| Dosen praktisi | Ikut dihitung / tidak |
| Dosen yang mengajar di dua prodi | Dihitung penuh di keduanya / dibagi |

> **Kenapa penting.** DTPS adalah penyebut rasio dosen–mahasiswa dan pembagi
> beban penelitian. Salah satu dosen saja menggeser beberapa tabel sekaligus.
>
> Baris terakhir sudah punya preseden di sini: BKD **membagi** SKS kelas antar
> pengampu, karena tanpa itu satu kelas 4 SKS yang diampu berdua terhitung 8
> SKS di tingkat kampus. Konsistensi dengan aturan itu disarankan.

**Jawaban: ____**

---

## 3. Masa studi — dihitung dari kapan sampai kapan?

Tersedia di basis data: `mahasiswa.angkatan` (tahun), `mahasiswa.term_masuk`
(kode semester PDDIKTI), dan `yudisium.tanggal_lulus`.

| Pilihan | Awal | Akhir |
|---|---|---|
| **A** | Semester pertama aktif (`term_masuk`) | Tanggal yudisium |
| **B** | Semester pertama aktif | Tanggal ijazah / wisuda |
| **C** | Tahun angkatan | Tahun lulus |

Pilihan C paling kasar tetapi paling sering dipakai borang.

**Jawaban: ____**

---

## 4. Cuti — menambah masa studi atau tidak?

`cuti_mahasiswa` mencatat cuti per semester. Mahasiswa yang cuti dua semester
lulus satu tahun lebih lambat menurut kalender.

| Pilihan | Masa studi |
|---|---|
| **A** | Kalender apa adanya — cuti ikut terhitung |
| **B** | Semester cuti dikurangkan |

> **Kenapa penting.** Pilihan B membuat masa studi rata-rata lebih pendek dan
> lebih adil bagi prodi, tetapi harus konsisten dengan cara PDDIKTI
> menghitungnya — dan dengan cara evaluasi studi di aplikasi ini menentukan
> semester ke berapa seorang mahasiswa berada.

**Jawaban: ____**

---

## 5. Lulus tepat waktu — batasnya berapa, dan untuk siapa?

Batas lazimnya 4 tahun untuk S1. Yang perlu diputuskan bukan angkanya
melainkan **kepada siapa ia dikenakan**:

- Mahasiswa **alih jenjang** dan **pindahan** — dihitung dari angkatan
  masuknya di sini, atau dikeluarkan dari populasi?
- Mahasiswa dengan **konversi kredit** (`KonversiKredit`, modul MBKM/RPL) —
  batasnya dikurangi sebanding SKS yang diakui, atau tetap?

> **Kenapa penting.** Memasukkan mahasiswa alih jenjang dengan batas penuh
> menekan angka kelulusan tepat waktu tanpa sebab yang nyata. Mengeluarkan
> mereka tanpa menyebutkannya membuat angkanya tidak dapat dicek ulang.
> Apa pun pilihannya, jumlah yang dikeluarkan sebaiknya ikut dilaporkan.

**Jawaban: ____**

---

## 6. Mahasiswa aktif — dihitung pada saat kapan?

`status_mahasiswa` menyimpan status per semester: `A` aktif, `C` cuti, `N`
non-aktif, `L` lulus, `D` drop out, `K` keluar, `G` ganti prodi.

| Pertanyaan | Pilihan |
|---|---|
| Waktu pencacahan | Awal semester / akhir semester / tanggal potong tertentu |
| Yang dihitung "aktif" | `A` saja / `A` + `C` |

> **Kenapa penting.** Ini penyebut rasio dosen–mahasiswa. Mencacah di awal
> semester dan mencacah di akhir dapat berbeda beberapa persen di prodi dengan
> banyak mahasiswa non-aktif.

**Jawaban: ____**

---

## 7. Mahasiswa putus studi (DO) — mana yang termasuk?

Tiga status berbeda dapat berarti "berhenti": `D` drop out, `K` keluar
(mengundurkan diri), `N` non-aktif berkepanjangan.

| Pertanyaan | Pilihan |
|---|---|
| Yang dilaporkan sebagai putus studi | `D` saja / `D` + `K` |
| Non-aktif berturut-turut | Berapa semester sebelum dianggap putus studi? |

> **Kenapa penting.** Mahasiswa yang hilang tanpa pernah diproses akan terus
> berstatus `N` selamanya. Kalau tidak ada ambangnya, mereka tidak muncul di
> tabel mana pun — tidak sebagai aktif, tidak pula sebagai putus studi — dan
> jumlah mahasiswa prodi tidak akan pernah berimbang.

**Jawaban: ____**

---

## 8. Kepuasan mahasiswa — dari instrumen yang mana?

LKPS menanyakan kepuasan mahasiswa atas **layanan** (akademik, sarana,
kemahasiswaan). Yang ada di aplikasi ini adalah **EDOM** — kepuasan atas
pengajaran seorang dosen, per kelas.

Keduanya tidak sama.

| Pilihan | Akibatnya |
|---|---|
| **A** | Pakai EDOM sebagai proksi, dan **sebutkan** bahwa itu proksi |
| **B** | Kosongkan, tunggu instrumen kepuasan layanan tersendiri |
| **C** | Buat instrumennya — modul kuesioner sudah ada dan dapat dipakai |

> Pilihan C bukan pekerjaan besar: `Kuesioner` sudah mendukung periode,
> pertanyaan berskala, dan pengisian anonim. Yang belum ada hanya kuesioner
> layanan beserta populasinya.

**Jawaban: ____**

---

## Dua tabel yang tidak akan terisi dari sini

Bukan pertanyaan — pemberitahuan, supaya tidak ditemukan pada minggu tenggat.

**Tracer study.** Tabel `alumni` menyimpan `status_pekerjaan`, `pekerjaan`,
`instansi`, dan `mulai_bekerja` — kerangka, bukan instrumen. Tracer study
nasional menanyakan jauh lebih banyak, dan instrumennya milik Open Campus.

**Penelitian, PkM, dan publikasi.** `penugasan_dosen` adalah catatan yang
**dilaporkan sendiri oleh dosen** untuk keperluan BKD dan IKU. Ia bukan basis
data penelitian: tidak ada usulan, review, kontrak, luaran, maupun kekayaan
intelektual di dalamnya.

Perakit LKPS akan **menyatakan kedua kelompok tabel itu tidak diisinya**, bukan
mengeluarkan sel kosong. Sel kosong di borang akreditasi terbaca sebagai nol,
dan nol di tabel penelitian adalah pernyataan yang sangat berbeda dari "sistem
ini tidak menyimpannya".
