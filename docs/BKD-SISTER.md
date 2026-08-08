# BKD-SISTER.md — Beban Kerja Dosen & Portofolio Kepegawaian

Neo Feeder melaporkan mahasiswa. **SISTER** melaporkan orang yang mengajar
mereka, dan **BKD** adalah laporan beban kerja per semester yang menentukan
dibayar atau tidaknya tunjangan sertifikasi.

Modul ini menyiapkan seluruh bahannya. Yang belum ada hanyalah kliennya —
kredensial SISTER belum tersedia — dan itu dinyatakan terus terang di layar
alih-alih disamarkan sebagai "terintegrasi".

---

## Mengapa Dibangun Sebelum Kredensialnya Ada

Bagian mahal dari sebuah integrasi tidak pernah panggilan HTTP-nya. Yang mahal
adalah menemukan, dua minggu setelah mulai, bahwa kampus tidak pernah mencatat
ijazah seorang dosen berasal dari negara mana, atau perannya di sebuah penelitian
apa.

Menyiapkan datanya lebih dulu memindahkan penemuan itu ke sekarang. Dan bagian
yang sudah jadi tetap berguna tanpa sambungan apa pun: **ekspor** — yang juga
tetap bekerja ketika integrasinya mati, endpoint-nya berubah bentuk, atau sebuah
fakultas meminta angkanya dalam bentuk lembar kerja.

---

## Unsur Pendidikan Dihitung, Bukan Diketik

Inilah alasan modul ini ada.

Apa yang diajarkan, berapa SKS-nya, berapa mahasiswa dibimbing, berapa sidang
diuji, berapa mahasiswa diwalikan — semuanya sudah tersimpan di sini sebagai
efek samping menjalankan semester. Yang dilakukan dosen selama ini adalah membuka
SIAKAD di satu tab dan mengetik ulang isinya ke borang di tab lain.

`BebanKerjaService` menurunkannya:

| Sumber | Aturan |
|---|---|
| Kelas diampu | `porsi_sks` pada pivot bila terisi; bila tidak, SKS kelas **dibagi** antar pengampu |
| Bimbingan TA | Per mahasiswa, utama ≠ pendamping, dibatasi jumlahnya |
| Menguji sidang | Per mahasiswa, tanggal ujian di dalam rentang semester, sidang batal tidak dihitung |
| Perwalian | Per **rombongan**, bukan per kepala |

Pembagian pada baris pertama itu bukan detail. Tanpanya, satu kelas 4 SKS yang
diampu berdua terhitung 8 SKS di tingkat kampus — angka yang tidak pernah benar
dan tidak pernah kentara pada satu laporan pun.

Tiga unsur lainnya — penelitian, pengabdian, penunjang — **tidak pernah melewati
sistem akademik**, dan tidak ada kepintaran yang bisa mengubah itu. Semuanya
dibaca dari `penugasan_dosen`, tempat dosen mencatatnya sendiri beserta bukti.

---

## Satu Catatan Kegiatan, Tiga Pembaca

`penugasan_dosen` sudah ada sejak Fase 4 sebagai sumber IKU 3 dan 4. Modul ini
memperluasnya alih-alih membuat tabel kedua:

```
unsur              → BKD memilah dan menimbangnya
peran, tingkat     → pengali rubrik, dan sumbu pelaporan IKU 5
luaran_jenis/…     → portofolio SISTER
```

Tabel `kegiatan_bkd` yang terpisah akan berarti satu penelitian dicatat dua kali,
dan kedua salinannya berbeda pada semester kedua. Pelajaran yang sama dengan
menyatukan tiga perhitungan IPK menjadi `PerolehanAkademik`.

`unsur` tidak diturunkan dari `jenis`, dan itu disengaja: perjalanan konferensi
yang sama adalah *penelitian* bila yang bersangkutan mempresentasikan makalah,
dan *penunjang* bila ia mengetuai panitianya. Hanya orang yang pergi yang tahu.

---

## Pengajuan Membekukan Laporan

Aturan yang menentukan seluruh bentuk tabelnya.

Laporan dinilai asesor, dan penilaian itu menentukan tunjangan. Data yang
mendasarinya terus bergerak: kelas dialihkan bulan depan, pembimbing ditukar,
jadwal dikoreksi. Bila baris laporan membaca data hidup, penilaian yang
ditandatangani bulan Maret diam-diam berubah menjadi penilaian atas sesuatu yang
lain — dan tanda tangannya melekat pada angka yang tak pernah dilihat siapa pun.

Karena itu `bkd_baris` adalah **cuplikan**, ditulis saat pengajuan dan tidak
pernah dihitung ulang. Sebelum diajukan, lembar kerja dihitung setiap kali dibuka
dan tidak menyimpan apa pun; sesudahnya, baris tersimpan itulah laporannya.

Prinsip yang sama dengan isi surat yang dibekukan saat terbit dan rumusan
pertanyaan EDOM yang terkunci setelah jawaban pertama.

`penugasan_dosen_id` tetap disimpan sebagai jejak asal, tetapi tidak ada yang
membaca nilainya lewat sana — dan `nullOnDelete`, supaya menghapus kegiatan
setahun kemudian tidak ikut menghapus baris laporan yang sudah ditandatangani.

---

## Bobot SKS Adalah Kebijakan, Bukan Fakta

Seluruh rubrik ada di [`config/bkd.php`](../config/bkd.php), tidak satu pun di
dalam service.

Angka-angka itu adalah tafsir kampus atas pedoman yang berubah tiap beberapa
tahun, dan berbeda antar perguruan tinggi untuk pedoman yang sama. Yang dijamin
Open Academic adalah **cacahnya benar**: berapa kelas, berapa SKS, berapa
mahasiswa. Berapa nilainya dalam SKS BKD diletakkan di tempat yang dapat diubah
tanpa menyentuh kode.

Sikap yang sama dengan `IkuDataController`, yang mencacah fakta dan menolak
menerapkan ambang.

Rentang 12–16 SKS pun diperlakukan begitu: dilaporkan sebagai pengaturan kampus
di samping angkanya, bukan diubah menjadi lulus/tidak.

Semua nilai disimpan dalam **perseratus SKS** sebagai integer, alasannya sama
dengan uang: angka ini dijumlahkan dari belasan baris lalu dibandingkan dengan
ambang yang menentukan pembayaran, dan selisih 0,01 di sekitar 12,00 adalah beda
antara memenuhi dan tidak.

---

## Alur, dan Empat Penolakan di Dalamnya

```
Draf ──ajukan──► Diajukan ──nilai──► Dinilai ──sahkan──► Disahkan
                     │
                     └──kembalikan──► Dikembalikan ──► (dapat disunting lagi)
```

| Ditolak | Sebabnya |
|---|---|
| Menjadi asesor laporan sendiri | Bukan konflik yang perlu dikelola, melainkan ketiadaan penilaian |
| Menilai laporan yang bukan tugasnya | Hak menilai melekat pada kolom `asesor_1`/`asesor_2`, bukan pada peran |
| Kesimpulan bukan "memenuhi" tanpa catatan | Alasannya justru satu-satunya hal yang harus dihasilkan asesor |
| Mengesahkan sebelum dinilai | Menjadikan asesor hiasan, dan tanda tangan menjamin penilaian yang tak pernah ada |

Yang **tidak** ditolak: laporan di bawah 12 SKS. Semester yang memang kurang
harus terlaporkan apa adanya — menolaknya hanya menghasilkan semester yang tidak
terlaporkan sama sekali, dan asesorlah yang memutuskan artinya. Kelebihan beban
juga dilaporkan, bukan digagalkan: dosen yang benar-benar memikul dua puluh SKS
punya masalah yang layak terlihat oleh yang membagi kelas.

Pengajuan ulang menghapus penilaian lama, karena penilaian itu menggambarkan
cuplikan yang lama.

---

## Siapa yang Wajib

Bawaannya hanya pemegang **Sertifikat Pendidik (Serdos)** — sertifikasi itulah
yang membuat laporan ini punya konsekuensi. Menuntut BKD dari seluruh dosen
membebankan administrasi yang tidak diminta regulasi.

Kampus dapat memperluasnya lewat `BKD_WAJIB=semua`, dan itu keputusan sadar,
bukan bawaan yang aman.

---

## Portofolio SISTER

Tiga tabel riwayat, karena SISTER menanyakan riwayat dan bukan nilai terkini:
setiap ijazah, bukan yang tertinggi; setiap jabatan beserta SK dan angka
kreditnya, bukan label yang diketik hari ini.

| Tabel | Isi |
|---|---|
| `riwayat_pendidikan_dosen` | Jenjang, kampus, bidang, negara, tahun, nomor ijazah |
| `jabatan_fungsional_dosen` | Jabatan, angka kredit, SK, TMT, penanda yang berlaku |
| `sertifikasi_dosen` | Serdos, profesi, kompetensi, beserta masa berlakunya |

Kolom datar `dosen.jabatan_fungsional` dan `dosen.pendidikan_tertinggi` tetap
ada — daftar kelas dan blok tanda tangan butuh satu nilai, bukan tangga. Keduanya
adalah **singgahan** dari tabel di atas, dan `PortofolioService` satu-satunya
tempat yang menulisnya, supaya keduanya tidak pernah berbeda.

Jabatan yang berlaku dijaga kolom `dosen_aktif_id` — unik dan nullable, pola
"satu yang aktif" yang sama dengan `tugas_akhir.mahasiswa_aktif_id` dan portabel
di MySQL maupun PostgreSQL.

Angka kredit yang tidak mencapai syarat jabatannya **dilaporkan, bukan ditolak**.
Kampus yang memasukkan riwayat dua puluh tahun punya SK dengan skema angka kredit
berbeda; menolaknya berarti riwayat itu tak pernah masuk sama sekali.

---

## Ekspor

| Bentuk | Untuk |
|---|---|
| PDF lembar BKD | Ditandatangani dosen, dua asesor, dan pengesah |
| CSV rekap BKD | Rekap satu semester, satu baris per dosen |
| CSV kegiatan dosen | Baris yang paling langsung memetakan ke borang isian massal |
| JSON portofolio per dosen | Bentuk yang akan dikonsumsi skrip integrasi nanti |

JSON-nya membawa `versi` supaya konsumen dapat membedakan perubahan bentuk dari
perubahan data, dan sengaja dapat diunduh sekarang agar pemetaan ke SISTER bisa
ditulis atas data sungguhan alih-alih atas tebakan.

Lembar PDF mencetak kolom **Sumber** pada tiap baris — tarikan sistem atau
laporan sendiri. Tidak lazim ada di borang BKD mana pun, dan itu justru yang
paling dicari asesor: baris tarikan sistem dapat dicek ke daftar kelas dalam
hitungan detik, baris laporan sendiri harus dibuka buktinya. Tanpa pembedaan itu
semua baris sama-sama mencurigakan, dan pada praktiknya tak satu pun diperiksa.

Lembar yang belum disahkan mencetak keterangan bahwa ia belum menjadi apa-apa.

---

## Campus Bridge

`GET /api/bridge/v1/lecturer-workload` — butuh scope `workload.read` **dan**
`lecturers.read`.

Dua bentuk dari satu endpoint: tanpa parameter mengembalikan cacahan se-kampus,
dengan `?nidn=` mengembalikan berkas satu dosen untuk disuapkan ke integrasi.
Keduanya butuh scope dosen karena bentuk per-dosennya adalah berkas kepegawaian
seseorang, bukan sekadar angka.

**NIK dan alamat rumah tidak pernah ikut**, di bentuk mana pun. Bentuk yang sama
dipakai Bridge, CSV, dan JSON yang dikirim lewat surel; payload yang aman di satu
kanal dan tidak di kanal lain akan bocor lewat kanal yang paling ceroboh.

---

## Izin

| Izin | Guard | Untuk |
|---|---|---|
| `bkd.lapor` | dosen | Mengajukan laporan sendiri |
| `bkd.nilai` | dosen | Menilai — dibatasi per laporan oleh kolom asesor, bukan oleh peran |
| `portofolio.kelola` | dosen | Riwayat & kegiatan sendiri |
| `bkd.view` | staff | Rekap dan ekspor |
| `bkd.manage` | staff | Menetapkan asesor, mengesahkan |

---

## Yang Belum Ada, Dinyatakan

- **Klien SISTER.** Menunggu kredensial. Tidak ada mode `fake` seperti Neo Feeder
  karena kontraknya belum dapat diuji, dan menulisnya melawan tebakan akan
  membekukan tebakan itu ke dalam model data.
- **Riwayat perwalian.** Perwalian dihitung dari daftar bimbingan hari ini, bukan
  keadaan pada semester yang dilaporkan. Laporan untuk semester lampau memakai
  daftar terkini. Layak diperbaiki; tidak layak dipura-purakan sudah beres.
- **Aturan dosen dengan tugas tambahan.** Rektor, dekan, dan kaprodi punya
  ketentuan beban tersendiri. Tidak diterapkan otomatis — asesor yang memutuskan,
  dan lembar penilaian menyediakan tempat menuliskan alasannya.

---

## Berkas

```
database/migrations/2026_08_14_100000_create_portofolio_dosen_tables.php
database/migrations/2026_08_14_100001_extend_penugasan_dosen_for_bkd.php
database/migrations/2026_08_14_100002_create_bkd_tables.php
config/bkd.php

app/Enums/{UnsurBkd,StatusBkd,KesimpulanBkd,JabatanFungsional,JenisSertifikasi,
           PeranKegiatan,TingkatKegiatan,JenisLuaran}.php
app/Models/Sdm/{BkdLaporan,BkdBaris,RiwayatPendidikanDosen,
                JabatanFungsionalDosen,SertifikasiDosen}.php
app/DTOs/Sdm/BarisBeban.php

app/Services/Sdm/BebanKerjaService.php   — unsur pendidikan dari data hidup
app/Services/Sdm/BkdService.php          — ajukan, bekukan, nilai, sahkan
app/Services/Sdm/PortofolioService.php   — satu jabatan berlaku
app/Services/Sdm/PortofolioDosen.php     — bentuk yang diminta SISTER
app/Services/Sdm/EksporSdm.php           — PDF, CSV, JSON

app/Http/Controllers/Dosen/{BkdController,PortofolioController}.php
app/Http/Controllers/Admin/BkdController.php
app/Http/Controllers/Api/Bridge/LecturerWorkloadController.php

resources/views/dosen/{bkd,bkd-penilaian,portofolio}.blade.php
resources/views/admin/bkd.blade.php
resources/views/pdf/bkd.blade.php

database/seeders/Demo/KepegawaianSeeder.php
tests/Feature/Sdm/BkdTest.php
```
