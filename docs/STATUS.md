# STATUS.md — Catatan Progres

> Diperbarui di akhir setiap sesi pengembangan (lihat CLAUDE.md §14).

---

## Sesi 26 — 2026-08-17 · Padanan, Konsentrasi, dan Paket Kuliah

Tiga dari lima sisa P1 perkuliahan. Ketiganya ada karena alasan yang sama:
kurikulum bukan daftar yang diam. Ia diganti, ia bercabang jadi jalur, dan di
vokasi ia diserahkan kepada mahasiswa alih-alih dipilihnya.

### Padanan mendarat di satu tempat

`PrasyaratChecker::mataKuliahLulus` adalah satu-satunya metode yang menjawab
"apa yang sudah dilulusi mahasiswa ini" — prasyarat, aturan sudah-ambil di KRS,
dan daftar periksa kelulusan semuanya lewat sana. Memperluas himpunannya di situ
membuat ketiganya menghormati pergantian kurikulum **tanpa satu pun tahu padanan
itu ada**, dan yang lebih penting: tanpa satu pun bisa berselisih tentangnya.

**Arahnya mengikat, dan itu dibuktikan.** Membuat resolvernya simetris membuat
tes "tidak berlaku terbalik" gagal seketika. Mata kuliah pengganti biasanya
mencakup lebih banyak; menerimanya mundur akan meloloskan mahasiswa sekarang
dari prasyarat yang silabus lama tidak pernah ajarkan.

Transitif, karena 2018 → 2022 → 2026 itu bentuk biasa. Lingkaran ditolak saat
ditulis: cincin padanan membuat setiap mata kuliah di dalamnya setara dengan
semua yang lain, dan mustahil terlihat setelahnya.

### Paket mendelegasi, tidak menulis sendiri

`PaketKuliahService` memanggil `KrsService::tambahKelas`, bukan menyisipkan
`krs_detail`. Itu seluruh desainnya: paket yang menulis barisnya sendiri akan
melewati kunci kuota, deteksi bentrok, prasyarat, dan penjaga hitung-ganda —
diam-diam, untuk satu angkatan sekaligus. Ada tesnya yang memaku persis itu.

Kegagalan dikumpulkan per mata kuliah alih-alih menggugurkan seluruh penerapan.
Satu mahasiswa mengulang yang sudah memegang satu mata kuliah tidak boleh
menghentikan tujuh lainnya.

### Jalur menggerbang katalog

Mata kuliah bersama (jalur null) terbuka untuk semua — itu sebagian besar gelar.
Mata kuliah jalur ditolak bagi yang **belum** memilih jalur, bukan diloloskan:
meloloskannya berarti ia menempuh sesuatu yang dihitung ke syarat yang tidak
berlaku baginya, dan menemukannya saat yudisium jauh lebih mahal.

### Tes yang memeriksa test suite

Tabrakan nama helper Pest terjadi **ketiga kalinya** (`tagihanUji`,
`pesertaKelas`, `kelasUji`), dan tiap kali ditemukan dengan cara mahal: run
tersaring hijau, suite penuh fatal — bukan satu tes gagal, melainkan galat fatal
yang menghentikan semua tes sesudahnya.

Sekarang suite memeriksa dirinya sendiri. Satu detik waktu jalan, dan tabrakan
berikutnya disebut namanya di titik ia diperkenalkan.

Penjaga lazy-loading ketat juga menangkap N+1 sungguhan di pengirim paket.

### Yang belum dikerjakan

Dua sisa P1: **cetak** (KTM, kartu ujian, absensi, jurnal) dan **pengaturan
dokumen**.

Untuk yang kedua, item roadmap-nya semula berbunyi "kustomisasi templat
dokumen". Templat Blade yang dapat disunting pengguna berarti mengeksekusi kode
yang tersimpan di basis data. Bentuk yang aman adalah kop, logo, penandatangan,
dan catatan kaki yang dapat dikonfigurasi per jenis dokumen — dan itulah yang
kini tertulis di ROADMAP.

---

## Sesi 25 — 2026-08-16 · RPS, Jurnal Perkuliahan, dan Analitik

**743 tes hijau (1.632 asersi)**, naik dari 715.

P1 perkuliahan, dengan analitik yang diminta menyertainya. Urutannya ditentukan
oleh permintaan itu sendiri: **"potensi penguasaan materi" hanya bermakna kalau
ada peta materi untuk mengukurnya**, jadi RPS harus lebih dulu — tanpa peta
MK→CPL, penguasaan materi hanya dapat dijawab dengan nilainya, yaitu
pertanyaannya diulang dan bukan dijawab.

### Yang menopang angkanya

Komponen nilai dipetakan ke CPL lewat pivot berbobot, bukan kolom tunggal. Satu
UTS lazim mengukur dua atau tiga CPL; memaksa memilih satu membuat CPL yang
dibuang justru tampak tidak pernah diukur.

Penguasaan = rerata nilai ditimbang **bobot komponen × porsi CPL**. UTS berbobot
30% yang 60%-nya mengukur CPL-01 menyumbang 18 satuan — bukan 30, bukan 1.
Dibuktikan mengikat: mengabaikan `porsi` mengubah hasil dari 66,21 menjadi 68,57
dan tesnya gagal seketika.

RPS terbit dibekukan. Nilai yang dicatat pekan keempat terhadap CPL-01 harus
tetap milik CPL-01 pada pekan kedua belas — prinsip yang sama dengan isi surat
terbit, rumusan EDOM, dan baris BKD.

### Yang modul ini menolak lakukan

**Tidak ada prediksi.** Permintaannya menyebut "potensi", dan itu godaan paling
jelas untuk mengarang statistik. Yang dibangun dua jenis saja: **fakta**
(persentase, rerata, ketercapaian — dicacah) dan **aturan** (peringatan saat
angka melewati ambang yang dikonfigurasi kampus).

Daftar "perlu perhatian" berisi alasan tertulis, bukan skor risiko. Indeks
berperingkat mengundang pembacanya memperlakukan kombinasi aritmetik dua angka
tak sejenis sebagai ramalan; daftar beralasan mengundangnya memeriksa.

Dan ketika komponen belum dipetakan, layarnya berkata **belum dipetakan** — tidak
menampilkan nol. Nol akan terbaca "mahasiswa tidak menguasai apa pun", padahal
artinya "belum ada yang menyatakan ujian ini mengukur apa".

### Dua angka, dan jaraknya

Jurnal melaporkan `terlaksana` dan `berjurnal` terpisah. Kelas dengan empat belas
terlaksana dan empat berjurnal bukan mengajar lebih sedikit — ia
mendokumentasikan lebih sedikit, dan satu angka gabungan menyembunyikan mana dari
dua masalah itu yang dipunyai kampusnya.

Jurnal juga sengaja boleh berbeda dari RPS. Libur menggeser pekan, materi
digabung — memaksa keduanya cocok justru menghapus informasi yang dicari orang
yang bertanya kenapa hanya dua belas dari enam belas tersampaikan.

### Tiga hal yang ditemukan, bukan dipikirkan

- **Tabrakan nama scope, untuk kedua kalinya.** `Rps::berlaku()` sebagai scope
  bertabrakan dengan instance method bernama sama — persis seperti
  `JabatanFungsionalDosen` di G7. Diseragamkan menjadi `scopeAktif`.
- **`nilai_huruf` ternyata enum `GradeLetter`**, bukan string. Perbandingan
  `=== 'E'` diganti `isPassing()` — ambang kelulusan properti skala huruf yang
  dapat dikonfigurasi kampus, dan literal di sini akan diam-diam salah begitu
  ada yang menambah D- atau mengganti nama huruf gagalnya.
- **Seeder awal menghasilkan layar kosong.** Pemetaan CPL hanya menyentuh
  semester berjalan, padahal mid-semester belum punya nilai sama sekali — 687
  nilai komponen seluruhnya di semester lampau. Pemetaan diperluas ke semua
  semester, yang juga persis yang dilakukan kampus saat mengadopsi modul ini:
  tanpa baseline tidak ada yang dapat dibandingkan.

### Berikutnya

Sisa P1 perkuliahan: padanan mata kuliah, kuliah paket, kurikulum konsentrasi,
cetak KTM/kartu ujian, kustomisasi templat dokumen.

---

## Sesi 24 — 2026-08-15 · Integrasi Easy Accounting

**715 tes hijau (1.573 asersi)**, naik dari 684.

Open Academic mencatat tagihan dan pembayaran; ia tidak menyusun jurnal
berpasangan dan tidak akan pernah. Sesi ini membangun jembatannya ke Easy
Accounting (easyERP).

**Berbeda dari SISTER, klien sungguhannya ditulis sekarang.** API v1 easyERP
memang dirancang untuk kasus ini — dokumennya sendiri menyebut "integrasi
aplikasi vertikal ... mapping akuntansi dikerjakan otomatis server-side" — jadi
kontraknya terdokumentasi dan dapat diuji. Tidak ada satu baris pun yang ditulis
melawan tebakan.

### Tiga keputusan yang diambil pemilik repo

Ditanyakan lebih dulu karena ketiganya mengubah angka di laporan keuangan, bukan
sekadar cara kode ditulis: invoice **per mahasiswa per semester**, beasiswa
dibukukan **bruto + beban**, pembayaran lewat **jurnal Dr Kas/Cr Piutang**.

### Outbox, bukan panggilan langsung

Peristiwa ditulis ke `akuntansi_dokumen` lebih dulu, dikirim tiap lima menit.
Menerbitkan tagihan untuk lima ribu mahasiswa tidak boleh menunggu lima ribu
panggilan HTTP, dan sistem akuntansi yang mati tidak boleh dapat menggagalkan
penagihan — utangnya ada, terbukukan atau tidak.

Bentuknya mengikuti `feeder_sync_logs`, bukan pola ketiga untuk pekerjaan yang
sama.

### Kunci idempotensi

Kolom paling menentukan di modul ini, dan diturunkan dari peristiwanya
(`oa-inv-<uuid>`), tidak pernah diacak. Kunci acak yang dibuat ulang saat retry
bukan kunci idempotensi — ia jaminan duplikat pertama kali jaringan menjatuhkan
respons setelah easyERP terlanjur commit.

Dibuktikan mengikat: menggantinya jadi acak membuat dua tes gagal seketika, dan
salah satunya menagih mahasiswa dua kali.

Untuk potongan, kuncinya baris tagihan — bukan nominal. Satu mahasiswa bisa
menerima dua keringanan bernilai persis sama, dan kunci berbasis nominal akan
menelan yang kedua sebagai duplikat: kampus sudah memberikan uangnya tanpa pernah
membukukannya.

### Satu cacat yang ditemukan tes, bukan pemikiran

Versi pertama memperlakukan **semua** kegagalan dependensi sebagai terminal.
Artinya gangguan jaringan lima detik saat membuat kontak akan mematikan invoice
di belakangnya secara permanen — antrean dokumen mati yang harus dikembalikan
manusia satu per satu.

Ketahuan karena tes backoff gagal dengan pesan yang salah, bukan karena
angkanya. Diperbaiki dengan `DependensiGagal` yang membawa `HasilKirim`-nya,
sehingga pengirim tetap dapat bertanya apakah sebabnya layak diulang.

### Yang belum ada di sisi easyERP, dinyatakan

API v1-nya belum punya endpoint pembayaran. Penerimaan kas karenanya dikirim
sebagai jurnal: buku besarnya benar, tetapi status invoice di sana tidak ikut
lunas. Layar Akuntansi mengatakan itu alih-alih membiarkannya ditemukan saat
rekonsiliasi pertama.

Bila endpoint itu ada nanti, yang berubah hanya satu metode di
`PenjurnalanService`.

### Opsional, dan mati sampai dinyalakan

Diminta setelah modulnya jadi, dan benar: versi pertama mengikat semua orang —
dokumen tetap tertulis, menu tetap muncul, penjadwal tetap jalan, untuk kampus
yang mungkin tidak memakai Easy Accounting sama sekali.

`AKUNTANSI_DRIVER` kini punya tiga nilai dengan bawaan **`nonaktif`**: tidak ada
yang dicatat, tidak ada menu, tidak ada proses tiap lima menit. Penagihan
berjalan sama persis pada ketiganya, dan itu yang diuji.

Dua hal ketahuan saat menulis tesnya, bukan saat memikirkannya:

- `AKUNTANSI_DRIVER=` yang kosong tadinya terbaca sebagai driver tak dikenal,
  yang berarti **exception pada setiap penerbitan tagihan**. Sekarang dibaca
  sebagai nonaktif — arah tebakan yang aman.
- Tes "nonaktif secara bawaan" versi pertama saya menguji `config()` yang sudah
  disetel `beforeEach`, jadi ia menguji dirinya sendiri. Diganti dengan membaca
  ulang berkas config-nya, sehingga yang diuji adalah nilai yang benar-benar
  dikirimkan repo ini.

### Berikutnya

Dua hal yang ditemukan saat menelusuri modul keuangan dan **belum dikerjakan**:
`config/payment.php` menjanjikan driver `fake` yang kelasnya tidak ada, dan
`app/Services/Payment/{Contracts,Gateways}` adalah direktori kosong sisa
scaffolding Fase 1 yang menyesatkan.

---

## Sesi 23 — 2026-08-14 · G7 SISTER & BKD

**684 tes hijau (1.509 asersi)**, naik dari 638.

Kesenjangan ketujuh dan terakhir. Yang dibangun adalah **bahannya**; klien SISTER
belum ada karena kredensialnya belum tersedia, dan itu dinyatakan di layar admin
alih-alih disamarkan sebagai "terintegrasi".

Alasan mengerjakannya sekarang: bagian mahal sebuah integrasi tidak pernah
panggilan HTTP-nya, melainkan menemukan dua minggu setelah mulai bahwa kampus
tidak pernah mencatat ijazah seseorang berasal dari negara mana atau perannya di
sebuah penelitian apa. Menyiapkan datanya lebih dulu memindahkan penemuan itu ke
sekarang.

### Unsur pendidikan dihitung, bukan diketik

Apa yang diajarkan beserta SKS-nya, berapa mahasiswa dibimbing, berapa sidang
diuji, berapa mahasiswa diwalikan — semuanya sudah tersimpan sebagai efek samping
menjalankan semester. `BebanKerjaService` menurunkannya.

Satu keputusan di dalamnya bukan detail: **SKS kelas dibagi antar pengampu**.
Tanpa itu, satu kelas 4 SKS yang diampu berdua terhitung 8 SKS di tingkat
kampus — angka yang tidak pernah benar dan tidak pernah kentara pada satu laporan
pun. `porsi_sks` pada pivot menang bila terisi; kampus sudah memutuskan, dan
menghitung ulang di atasnya menghapus keputusan itu.

Tiga unsur lainnya tidak pernah melewati sistem akademik, dan tidak ada
kepintaran yang mengubah itu. Semuanya dilaporkan sendiri beserta bukti.

### Pengajuan membekukan laporan

Aturan yang menentukan seluruh bentuk tabelnya. Laporan dinilai asesor dan
penilaiannya menentukan tunjangan; data yang mendasarinya terus bergerak. Bila
baris laporan membaca data hidup, kelas yang dialihkan bulan depan diam-diam
menulis ulang penilaian yang sudah ditandatangani.

`bkd_baris` karenanya cuplikan, ditulis saat pengajuan dan tidak pernah dihitung
ulang. Dibuktikan mengikat di dua tempat: di service (kelas dilepas setelah
diajukan → total tetap), dan di layar (cabang `status->beku()` dilepas → tesnya
langsung gagal).

### Satu catatan kegiatan, tiga pembaca

`penugasan_dosen` diperluas alih-alih dibuatkan tabel kedua. IKU 3/4 mencacahnya,
BKD memilah dan menimbangnya, portofolio SISTER melaporkan luarannya. Tabel
`kegiatan_bkd` terpisah berarti satu penelitian dicatat dua kali dan kedua
salinannya berbeda pada semester kedua — pelajaran yang sama dengan menyatukan
tiga perhitungan IPK menjadi `PerolehanAkademik`.

`unsur` sengaja tidak diturunkan dari `jenis`: perjalanan konferensi yang sama
adalah penelitian bila mempresentasikan makalah dan penunjang bila mengetuai
panitianya. Hanya orang yang pergi yang tahu.

### Yang ditolak, dan yang sengaja tidak

| Ditolak | Sebabnya |
|---|---|
| Menjadi asesor laporan sendiri | Bukan konflik yang perlu dikelola, melainkan ketiadaan penilaian |
| Menilai laporan yang bukan tugasnya | Haknya melekat pada kolom asesor, bukan pada peran — sama seperti `tugas_akhir.bimbing` |
| Kesimpulan bukan "memenuhi" tanpa catatan | Alasannya justru satu-satunya hal yang harus dihasilkan asesor |
| Mengesahkan sebelum dinilai | Menjadikan asesor hiasan |

**Tidak** ditolak: laporan di bawah 12 SKS. Semester yang kurang harus
terlaporkan apa adanya — menolaknya menghasilkan semester yang tidak terlaporkan
sama sekali. Kelebihan beban juga dilaporkan: dosen yang memikul dua puluh SKS
punya masalah yang layak terlihat oleh yang membagi kelas.

### Bobot SKS di config, bukan di service

Seluruh rubrik di `config/bkd.php`. Angka-angka itu tafsir kampus atas pedoman
yang berubah tiap beberapa tahun dan berbeda antar perguruan tinggi untuk pedoman
yang sama. Yang dijamin Open Academic adalah cacahnya benar — sikap yang sama
dengan `IkuDataController` yang menolak menerapkan ambang.

Rentang 12–16 SKS pun diperlakukan begitu di Bridge: dilaporkan sebagai
pengaturan kampus di samping angkanya, bukan diubah menjadi lulus/tidak.

Semuanya perseratus SKS sebagai integer, alasannya sama dengan uang: selisih 0,01
di sekitar 12,00 adalah beda antara dibayar dan tidak.

### Dua hal yang tertangkap saat mengukur

Layar BKD dosen menghitung lembar kerja **dua kali** — sekali untuk barisnya,
sekali lagi untuk totalnya. Tidak tertangkap anggaran kueri karena dosen demo
punya laporan yang sudah disahkan, sehingga layarnya mengambil cabang beku yang
murah. Ditemukan saat membaca hasil pengukuran, bukan saat ada yang gagal.

Karena itu ditambahkan satu entri anggaran tersendiri untuk **jalur lembar kerja
hidup**, memakai dosen yang belum punya laporan — jalur termahal modul ini, dan
sebelumnya tidak teruji sama sekali.

### Yang belum ada, dinyatakan

- **Klien SISTER** — menunggu kredensial. Tidak dibuat mode `fake` seperti Neo
  Feeder, karena kontraknya belum dapat diuji dan menulisnya melawan tebakan akan
  membekukan tebakan itu ke dalam model data.
- **Riwayat perwalian** — dihitung dari daftar bimbingan hari ini, bukan keadaan
  pada semester yang dilaporkan.
- **Aturan dosen dengan tugas tambahan** — tidak diterapkan otomatis; asesor yang
  memutuskan, dan lembar penilaian menyediakan tempat menuliskan alasannya.

### Berikutnya

Tujuh dari tujuh kesenjangan SIAKAD tertutup. Yang tersisa menunggu keputusan
atau akses di luar repo: klien SISTER (kredensial), adaptor Midtrans (kredensial
merchant), federasi IdP eksternal, 2FA staf, dan **remote Git / rilis publik**,
yang tetap keputusan pemilik repo.

---

## Sesi 22 — 2026-08-13 · G6 EDOM

**638 tes hijau (1.412 asersi)**, naik dari 606.

Kesenjangan keenam dari tujuh tertutup. Yang tersisa hanya G7 SISTER/BKD, dan itu
menunggu akses ke sistem kementerian sisi dosen — bukan menunggu keputusan.

### Batas terhadap Open Campus, akhirnya diputuskan

Pertanyaan, jawaban, ambang, dan gerbang tinggal di sini, karena semuanya melekat
pada `kelas_kuliah`, daftar peserta, dan KRS. Open Campus membaca **agregatnya**
lewat `GET /teaching-evaluations`. Tidak ada lapisan kuesioner yang terduplikasi:
yang berpindah hanyalah angka jadi.

### Anonimitas sebagai bentuk tabel, bukan sebagai kebijakan

Ini inti modulnya, dan bagian yang paling mudah dirusak oleh permintaan yang
terdengar wajar.

```
edom_partisipasi  — SIAPA sudah mengisi APA.   Tidak memuat jawaban.
edom_jawaban      — APA yang dijawab.          Tidak memuat mahasiswa.
```

Keduanya **tidak berbagi kunci apa pun**. Bukan yang nullable, bukan yang tak
langsung, dan bukan pula pengenal respons — pengenal respons akan mengorelasikan
pendapat satu orang lintas pertanyaan, cukup untuk merekonstruksi individu pada
kelas kecil.

Harganya dibayar sadar: jawaban tidak dapat diubah maupun dicabut, karena untuk
itu sistem harus tahu jawaban mana milik siapa.

Sifat ini dibuktikan mengikat dengan cara yang sama seperti pengaman lain di repo
ini: menambahkan `mahasiswa_id` ke `edom_jawaban` membuat tesnya gagal seketika.

### Empat keputusan yang berpihak, dan alasannya

| Keputusan | Alternatifnya, dan mengapa tidak |
|---|---|
| Gerbang bawaan `krs`, bukan `khs` | Menahan KHS memakai catatan yang **sudah diperoleh** mahasiswa sebagai alat tukar untuk sebuah survei. Menahan pengajuan KRS menahan tindakan yang belum terjadi. Praktik `khs` lazim di Indonesia dan tetap disediakan |
| Di bawah ambang, **tidak ada apa pun** yang ditampilkan | Termasuk cacah respondennya. "Data tidak cukup (n=3)" pada kelas berisi empat orang adalah petunjuk, bukan penyembunyian |
| Komentar bawaan ke **prodi**, bukan ke dosen | Angka dapat dirata-ratakan sampai tak seorang pun dikenali; kalimat tidak. Satu komentar tajam pada kelas kecil menunjuk penulisnya lewat isinya sendiri |
| Instrumen terkunci begitu satu jawaban masuk | Mengubah rumusan menulis ulang arti angka yang sudah tersimpan. Revisi = periode baru, dengan pertanyaan **disalin**, bukan dipakai bersama |

`pimpinan` memperoleh `edom.view` tanpa `edom.manage`: instrumen yang
pertanyaannya dapat disunting oleh orang yang dinilai olehnya tidak mengukur
apa-apa.

### Yang tertangkap anggaran kueri

Layar hasil dosen mula-mula memanggil `HasilEdom::kelas()` sekali per kelas yang
diampu — dua kueri per baris, dan **tak terlihat oleh `preventLazyLoading`**
karena tidak ada relasi yang di-lazy-load; kodenya sekadar bertanya lagi ke basis
data di dalam loop. Persis defect kedua yang dicari `SmokeLayarTest`.

Diganti `beberapaKelas()`: dua kueri, berapa pun jumlah kelasnya. Anggarannya
diketatkan ke 14 supaya versi per-baris tidak dapat kembali diam-diam.

### Dibuktikan dengan dicabut

Pembatas kelas ke dosen yang sedang masuk dilepas → tes "hanya memperlihatkan
hasil dosen yang sedang masuk" langsung gagal. Layar dosen tidak memuat pengenal
dosen di URL-nya sama sekali; tidak ada bentuk permintaan yang mengembalikan
nilai kolega, bukan karena ada pemeriksaan yang menolaknya, melainkan karena
tidak ada parameter yang dapat diubah.

### Kampus demo

Periode terbuka di tengah jendela, ambang **3** (bukan bawaan 5): kampus demo
hanya punya lima peserta disetujui per kelas, jadi pada ambang 5 tak satu pun
kelas akan pernah lolos dan setiap layar hasil menampilkan empty state yang sama.
Menurunkannya justru membuat **aturannya** terlihat — 20 dari 25 baris kelas
lolos, lima tidak.

`mahasiswa1@demo.test` sengaja belum mengisi, supaya layar mahasiswa punya
pekerjaan dan gerbang KRS benar-benar dapat dilihat menyala.

### Catatan pinggir

`docs/openapi/bridge.yaml` tidak memuat `/iku-data`, padahal endpointnya ada sejak
Fase 4 dan spesifikasinya menyatakan diri *spec-first*. Bukan bagian G6, jadi
tidak disentuh — tetapi itu satu-satunya endpoint Bridge yang kontraknya tidak
tertulis.

### Berikutnya

**G7 SISTER/BKD** — satu-satunya kesenjangan yang tersisa, dan yang paling luas:
BKD per semester (pendidikan, penelitian, pengabdian, penunjang) plus sertifikasi.
`PenugasanDosen` saat ini mencatat mengajar saja, yaitu satu dari empat unsur.

---

## Sesi 21 — 2026-08-12 · G4 Keringanan & Beasiswa

**606 tes hijau (1.336 asersi)**, naik dari 583.

Yang selama empat sesi saya sebut "mustahil secara struktural":
`tagihan_item.nominal` bertipe `unsignedBigInteger`, sehingga baris potongan
bukan belum dibuat, melainkan **tidak dapat ada**.

Satu `->change()` membuka seluruh modul.

### Bentuk yang dipilih, dan mengapa itu yang menentukan

Potongan adalah **baris bernilai negatif pada tagihan**, bukan tabel terpisah
yang dikurangkan saat dibaca.

Sepuluh tempat membaca `tagihan.total` dan `tagihan.terbayar` — gerbang
pembayaran KRS, daftar periksa kelulusan, pengingat tunggakan, syarat surat
keterangan aktif kuliah, beberapa dasbor. Mempertahankan `total = jumlah baris`
berarti **tak satu pun dari kesepuluhnya perlu diubah**, dan tak satu pun dapat
menyimpang darinya.

Diverifikasi pada kampus demo dengan SQL: nol ketidakcocokan atas seluruh tagihan.

### Tiga cara kehilangan uang tanpa ada yang menyadari

| Aturan | Kegagalan yang dicegah |
|---|---|
| Total tidak pernah di bawah nol | `total` unsigned — negatif akan terbaca sebagai angka positif raksasa |
| Kelebihan bayar **ditampilkan**, bukan ditelan | Bayar Agustus, beasiswa disetujui September. Uangnya nyata dan harus dapat dipertanggungjawabkan |
| Penerapan idempoten | Penerbitan ulang menjadi beasiswa kedua — totalnya tetap seimbang, jadi tak ada yang tampak salah |

Ditambah alasan wajib pada setiap keringanan. Menurunkan tagihan adalah tindakan
paling bernilai yang dapat disalahgunakan di sistem ini, dan potongan tanpa
alasan tertulis tidak dapat dibedakan dari penyalahgunaan.

### Bug lama yang tersingkap

Aturan status pembayaran menuntut `total > 0` untuk berstatus lunas. Pembebasan
penuh karenanya menghasilkan tagihan **"belum bayar" senilai Rp0** — yang akan
menahan surat keterangan aktif kuliah dan memicu pengingat jatuh tempo atas utang
nol rupiah.

Dibuktikan dengan mengembalikan aturan lamanya: tesnya langsung gagal. Begitu
pula batas "tidak pernah negatif".

### Batas yang dinyatakan, bukan diam-diam dipilih

**Pencabutan beasiswa bersifat ke depan.** Membalik semester yang lalu akan
memunculkan kembali utang atas tagihan yang sudah dianggap selesai berbulan-bulan
sebelumnya. Pembalikan satu baris tetap tersedia untuk kasus yang memang harus —
tindakan sengaja pada tagihan bernama, bukan efek samping mengakhiri skema.

**Menagih penyandang dana beasiswa eksternal adalah piutang di sistem keuangan**,
bukan di sini. Yang dijamin modul ini: setiap potongan dapat ditelusuri ke pihak
yang menanggungnya.

### Yang hanya tertangkap suite penuh

Helper tes `tagihanUji()` bertabrakan dengan nama yang sudah ada di berkas tes
lain — fungsi Pest bersifat global. Run terfilter hijau, suite penuh fatal error.
Diganti nama.

### Berikutnya

Lima dari tujuh kesenjangan tertutup. Dua sisanya **menunggu keputusan sebelum
kode**: G6 EDOM perlu penetapan batas terhadap Open Campus, G7 SISTER/BKD perlu
akses ke sistem kementerian sisi dosen.

> Ditinjau ulang di Sesi 22: batas G6 diputuskan dan modulnya dibangun di sini.

---

## Sesi 20 — 2026-08-11 · Git + G5 Konversi Kredit

**583 tes hijau (1.291 asersi)**, naik dari 560.

### Git, akhirnya

Sembilan belas sesi kerja masuk ke satu commit awal di `main`. Diperiksa lebih
dulu bahwa `.env`, kunci OAuth Passport, dan seluruh isi `storage/app/private`
terabaikan — direktori terakhir itu berisi KTP, kartu keluarga, dan surat
keterangan sakit. 515 berkas, nol rahasia.

Belum ada remote. Itu keputusan pemilik repo, bukan saya.

Modul ini dikerjakan pada `feature/konversi-kredit` mengikuti konvensi di
CLAUDE.md.

### G5 — dan pekerjaan yang harus didahulukan

`pmb_gelombang.jalur` menerima `rpl` dan `transfer` sejak modul PMB dibangun,
tanpa tempat mencatat apa yang diakui. Pintunya terbuka tanpa lantai di baliknya:
mahasiswa pindahan semester lima masuk dengan transkrip kosong dan syarat
kelulusan yang mustahil dicapai.

Sebelum menambahkan apa pun, satu temuan menghentikan saya.

#### Tiga salinan logika yang sama

**"Ambil percobaan terbaik per mata kuliah" ditulis tiga kali** — di
`YudisiumService`, `IndeksPrestasiCalculator`, dan `TranskripService` — nyaris
identik. Menambahkan konversi ke masing-masing akan menjadikannya empat salinan,
dan driftnya akan senyap: transkrip menampilkan kredit yang tidak dihitung layar
kelulusan.

Disatukan dulu menjadi `PerolehanAkademik`, baru konversinya ditambahkan sekali.

#### Bug yang tersingkap saat menyatukannya

Ketiganya ternyata **sudah** tidak sepakat. IPK tersimpan dihitung atas seluruh
mata kuliah final; IPK pada daftar periksa kelulusan hanya atas yang lulus.

Dua angka berbeda dengan nama yang sama. Seorang mahasiswa bisa melihat 2,0 pada
catatannya dan 4,0 pada layar kelulusan, dan tidak ada satu pun tes yang
menyadarinya karena masing-masing diuji terpisah. Sekarang satu — dihitung atas
seluruh mata kuliah, karena membuang yang gagal berarti IPK naik dengan cara
menggagalkan lebih banyak.

#### Aturan inti: kredit ganda, dari dua sisi

`KonversiService` menolak mengonversi mata kuliah yang sudah ditempuh di sini;
`KrsService` menolak mengambil yang sudah dikonversi. **Keduanya harus ada.**
Tanpa salah satunya, mahasiswa pindahan yang sudah diakui Basis Data tetap dapat
mengambilnya, dan kreditnya terhitung dua kali — tanpa ada yang menyadari, karena
totalnya hanya keluar lebih besar daripada mata kuliah di belakangnya.

Dibuktikan dengan mencabut sisi KRS: tesnya langsung gagal.

#### Batas yang menjaga arti gelar

Pengakuan dibatasi persentase terhadap syarat kelulusan prodi. Tanpa batas,
seseorang dapat diakui masuk ke dalam gelar. Angkanya harus ditetapkan tiap
kampus secara sadar.

#### Keputusan yang saya ambil dan alasannya

Nilai konversi **tidak masuk IPK secara bawaan**. Itu posisi, bukan
ketidakpedulian: IPK adalah penilaian institusi ini, sedangkan nilai konversi
diberikan pihak lain dengan standar lain. Dapat dinyalakan per kampus. Kreditnya
selalu masuk total SKS — itulah arti pengakuan.

Saat dikeluarkan dari IPK, ia juga keluar dari penyebutnya. Ikut di bawah tetapi
tidak di atas akan menekan IPK setiap mahasiswa pindahan secara diam-diam.

Transkrip menandai barisnya (**T** dan **R**) dan mencantumkan totalnya. Pembaca
luar perlu tahu mana yang dinilai kampus ini.

### Berikutnya

**G4 Keringanan & beasiswa** — tersisa tiga, dan G4 satu-satunya yang menuntut
migrasi: `tagihan_item.nominal` bertipe `unsignedBigInteger`, sehingga baris
potongan mustahil disimpan.

---

## Sesi 19 — 2026-08-10 · G3 Surat Keterangan, Legalisir & SKPI

**560 tes hijau (1.245 asersi)**, naik dari 505.

Antrean terpanjang di loket BAAK mana pun adalah surat keterangan aktif kuliah.
Datanya sudah ada di sistem sejak hari pertama; yang belum ada hanyalah kabel
dari kolom status ke printer.

Lima jenis surat, satu tabel, satu deret penomoran, satu halaman verifikasi.
Rinciannya di [SURAT.md](SURAT.md).

### Keputusan yang menentukan bentuknya

**Hanya satu jenis yang swalayan.** Surat keterangan aktif kuliah adalah kampus
membacakan kolom status — tidak ada keputusan di dalamnya, dan antrean untuk itu
tidak pernah mengerjakan apa pun. Surat pengantar berbeda: ia meminjamkan nama
institusi pada proyek orang lain, dan itu keputusan yang tetap milik manusia.

**SKPI terbit otomatis saat kelulusan ditetapkan.** Regulasi mewajibkannya untuk
*setiap* lulusan. Menjadikannya permintaan berarti ia sampai kepada lulusan yang
tahu harus meminta — dan yang tidak tahu justru yang paling tidak mampu
mengejarnya.

**Satu tabel untuk semuanya, termasuk SKPI.** Membangunnya terpisah akan berarti
dua skema penomoran dan dua endpoint verifikasi, dan yang kedua selalu berakhir
lebih lemah.

### Verifikasi yang benar-benar memverifikasi

Halaman publik tanpa autentikasi — memang harus, karena yang memeriksa adalah
petugas bank dan staf kedutaan yang tidak akan pernah membuat akun di sini.

Karena terbuka, seluruh pengamanannya pindah ke tempat lain: dikunci **UUID
bukan nomor berurut**, pencarian manual menuntut nomor *dan* NIM, dibatasi
lajunya, dan hanya menampilkan yang sudah tercetak di kertas.

Nomor surat memang harus dapat ditebak — itu konvensi arsip. Menaruhnya di URL
verifikasi berarti membuat direktori semua orang yang pernah dikirimi surat oleh
kampus, dengan jawaban yang otoritatif.

**Tiga jawaban, bukan dua:** asli-dan-berlaku, asli-tetapi-kedaluwarsa,
asli-tetapi-dicabut. Surat aktif kuliah yang kedaluwarsa bukan pemalsuan; surat
yang dicabut bukan dokumen berlaku. Menggabungkan yang tengah ke salah satu
ujung membuat seluruh verifikasi tak berguna.

### Kebohongan lama yang ikut diperbaiki

Transkrip unduhan mandiri selama ini mencetak "kode verifikasi" — hash dari uuid
mahasiswa — beserta kalimat bahwa dokumen sah tanpa tanda tangan basah **apabila
kodenya cocok dengan pangkalan data institusi**.

Tidak pernah ada tempat untuk mencocokkannya. Itu lebih buruk daripada tidak
mencetak apa-apa: kode di samping kalimat semacam itu mengajak pembaca percaya
bahwa seseorang bisa memeriksanya. Lembar itu kini menyatakan dirinya salinan
tidak resmi, dan versi bernomornya adalah Transkrip Legalisir.

### Penjaga yang dibuktikan dengan dicabut

Syarat NIM pada pencarian manual dilepas sementara: nomor tebakan langsung
mengembalikan nama pemiliknya. Dikembalikan.

Untuk unduhan surat, buktinya berpasangan — rute dan binding identik menjawab
**403** bagi mahasiswa lain dan **200** bagi pemiliknya.

### Capaian pembelajaran

Tab baru pada Master Akademik, dan tanpanya bagian tengah setiap SKPI tercetak
sebagai "belum dicatatkan". Ditulis sekali per prodi, dwibahasa.

Per prodi, bukan per lulusan — itulah satu-satunya cara versi Inggrisnya
benar-benar terisi. Sebagai isian per lulusan ia menjadi pekerjaan penerjemahan
pada pagi hari wisuda, dan berhenti dikerjakan.

### Dependensi baru

`endroid/qr-code`, yang kedelapan. QR pada surat memuat **URL verifikasi saja**,
bukan faktanya: QR yang membawa isinya sendiri adalah salinan kedua yang tidak
dapat dicabut siapa pun, dan memalsukannya cukup dengan menyunting teks.

Bila pembuatannya gagal, surat tetap terbit dengan URL tercetak sebagai teks.
Surat tanpa QR masih sah; surat yang gagal dicetak adalah orang yang dikirim
kembali ke loket.

### Berikutnya

**G4 Keringanan & beasiswa** — dan ini yang paling mendesak dari empat sisanya,
karena satu-satunya yang membutuhkan **migrasi**: `tagihan_item.nominal` bertipe
`unsignedBigInteger`, sehingga baris potongan mustahil disimpan. Makin lama makin
mahal.

---

## Sesi 18 — 2026-08-09 · G2 Notifikasi

**505 tes hijau (1.116 asersi)**, naik dari 467.

Sampai sesi ini seluruh sistem bersifat **tarik**: tidak ada satu pun yang
memberi tahu siapa pun tentang apa pun. Trait `Notifiable` ada pada ketiga model
identitas sejak awal dan tidak pernah dipakai sekali pun.

Tiga tabel, enam kategori, tiga belas kelas notifikasi, satu perintah terjadwal,
satu layar untuk ketiga portal.

### Pembedaan yang menentukan seluruh rancangannya

**Catatan dalam aplikasi adalah catatan resminya.** Itulah yang dapat ditunjuk
seseorang ketika kelak dipersoalkan apakah ia pernah diberi tahu. Surel dan
WhatsApp hanyalah pengantaran.

Dari situ mengalir aturan kategori wajib: penolakan KRS dan jatuh tempo tagihan
tidak dapat dibungkam pada kanal aplikasi. Menawarkan sakelar mati untuk keduanya
berarti membiarkan seseorang mematikan satu-satunya peringatan yang ia terima,
lalu dikatakan seharusnya ia tahu.

Pembedanya **bukan tingkat kepentingan** — semua terasa penting bagi yang
menuliskannya — melainkan apakah melewatkannya merugikan orang itu dalam hal yang
kelak ditagihkan kepadanya. Karena itu Pengingat justru *tidak* wajib: keputusan
yang diperingatkannya tetap sampai lewat kategori wajib.

### Tiga aturan

| Aturan | Kegagalan yang dicegah |
|---|---|
| Mengumumkan tidak boleh membatalkan | Server surel mati membatalkan persetujuan cuti yang sudah terjadi |
| Tidak ada yang keluar sebelum transaksi commit | Mahasiswa diberi tahu KRS disetujui padahal basis data tak menyimpannya |
| Pengingat yang sama tidak dua kali | Kanal yang bicara tiap malam melatih orang mengabaikannya |

Aturan kedua semula saya tulis sebagai properti per kelas, dan PHP menolaknya —
`Queueable` sudah mendeklarasikan `$afterCommit`. Benturan itu mendorong
keputusan yang lebih baik: dipasang sekali pada koneksi antrean, sehingga berlaku
untuk **seluruh** job. Jejak audit dan penerbit Bridge punya persoalan yang sama,
dan keduanya belum tertangani sebelum ini.

### Bug yang saya temukan pada kode saya sendiri

Penerjemahan pelanggaran indeks unik saya tulis dengan mencocokkan kode SQLSTATE
sendiri, dan **tidak pernah cocok** — Laravel punya
`UniqueConstraintViolationException` yang sudah menangani perbedaan antar-mesin.

Kesalahan yang sama ternyata ada di `TugasAkhirService` dari Sesi 17. Tes di sana
melewatkannya karena menulis langsung ke tabel: itu membuktikan indeksnya bekerja,
tetapi tidak pernah menjalani jalur penerjemahannya. Permintaan yang kalah akan
menerima galat driver mentah, bukan kalimat yang dapat dibaca. Keduanya
diperbaiki, dan tes yang melalui service ditambahkan.

Satu lagi yang nyaris terulang: filter kategori semula saya tulis sebagai
`data->kategori`. Sintaks JSON path berbeda antar-mesin — persis yang saya
dokumentasikan sendiri di BASIS-DATA.md satu sesi sebelumnya. Diganti kolom asli
yang ditulis `DatabaseKategoriChannel`.

### Apa yang dikirim

Sembilan kejadian seketika — keputusan KRS, nilai final, tagihan, pembayaran,
cuti, kelulusan, dan empat kejadian tugas akhir yang sesi lalu saya catat "tidak
diberitahukan kepada siapa pun".

Empat pengingat terjadwal, dengan dua keputusan yang perlu dijelaskan:
pengingat KRS **hanya** menyasar yang belum mengajukan apa pun, dan antrean
bimbingan dikirim sebagai satu rangkuman mingguan. Pengingat yang juga menjangkau
mereka yang sudah patuh adalah cara sebuah kanal berubah menjadi kebisingan.

### WhatsApp

Seam-nya ada, adaptor penyedia tidak — alasannya sama dengan Midtrans.
Menyalakannya perlu **dua persetujuan terpisah**: driver, dan daftar kategori.
Nama driver yang tidak dikenal menggagalkan resolusi alih-alih diam-diam kembali
ke log, karena "terkonfigurasi tetapi sebenarnya tidak mengirim" adalah keadaan
yang tidak akan disadari sampai hari pengumuman.

### Biaya yang diterima, bukan disembunyikan

Lonceng menambah **satu kueri per halaman** pada setiap portal — satu `COUNT`
beririndeks. Anggaran kueri layar KRS langsung terlampaui satu, karena angka-angka
itu memang disetel mepet. Anggarannya dinaikkan satu dan alasannya ditulis di
berkas tesnya; tidak ada anggaran lain yang disentuh.

### Berikutnya

**G3 Surat keterangan & SKPI** — volume loket tertinggi, datanya sudah lengkap,
dan kini ada kanal untuk memberi tahu saat suratnya siap diambil.

---

## Sesi 17 — 2026-08-08 · Perbandingan SIAKAD + Modul Tugas Akhir

**467 tes hijau (1.038 asersi)**, naik dari 419.

### Bagian 1 — memeriksa diri terhadap ukuran luar

Sesi sebelumnya ditutup dengan "tidak ada lagi modul yang tercatat kosong". Itu
benar terhadap daftar saya sendiri, dan daftar saya sendiri ternyata bukan
ukuran yang tepat.

Dibandingkan cakupan SIAKAD yang lazim dipakai kampus Indonesia: **tujuh
kesenjangan**. Tiga di antaranya membuat kampus tetap memakai kertas. Satu —
keringanan/beasiswa — bahkan **mustahil secara struktural**, karena
`tagihan_item.nominal` bertipe `unsignedBigInteger` sehingga baris potongan tidak
dapat disimpan sama sekali.

Rincian lengkap dulu ada di ROADMAP §Perbandingan dengan SIAKAD Lain. Bagian itu
ditulis ulang pada 15 Agustus 2026 — ketujuh kesenjangan sudah tertutup, dan
pembandingnya kini SEVIMA siAkadCloud.

### Bagian 2 — G1 Tugas Akhir

Kesenjangan terbesar, dan yang paling janggal: **akhir ceritanya sudah dibangun
tanpa ceritanya.** Yudisium, wisuda, dan nomor ijazah sudah ada — sementara
`judul_tugas_akhir` hanyalah teks bebas yang diketik operator saat yudisium.

Lima tabel, tiga service, tiga portal, **41 tes baru**.

#### Satu keputusan yang menentukan bentuk sisanya

Batas "satu tugas akhir aktif per mahasiswa" **dijaga basis data**, lewat kolom
`mahasiswa_aktif_id` yang berisi id mahasiswa selagi karya berjalan dan NULL
setelah selesai. NULL tidak bertabrakan di bawah indeks unik pada MySQL maupun
PostgreSQL, sehingga riwayat boleh menumpuk sementara pengajuan kedua ditolak.

Indeks parsial (`WHERE status IN (...)`) akan menyatakannya lebih langsung tetapi
tidak portabel; pemeriksaan di service saja kalah oleh dua tab yang mengirim
bersamaan. Ada tes yang menulis langsung ke tabel, melewati seluruh lapisan
aplikasi, untuk membuktikan indeksnya yang menahan.

#### Yang membedakan perangkat lunak dari formulir

| Aturan | Kegagalan yang dicegah |
|---|---|
| Kuota pembimbing atas **bimbingan berjalan** saja | Satu dosen dengan 40 mahasiswa, tak satu pun terbimbing |
| Panel sidang wajib punya **penguji bukan pembimbing** | Karya diuji pihak yang ikut menghasilkannya |
| Minimum bimbingan yang **sudah disetujui** | Syarat disertifikasi sendiri oleh yang dibatasi olehnya |
| Bentrok ruang terhadap **jadwal kuliah mingguan** | Panel datang ke ruang yang ada kuliahnya |
| Selesai wajib lewat **sidang yang lulus** | Judul sampai ke ijazah tanpa pernah diuji |

Dua hal sengaja **tidak** diblokir: pembimbing boleh duduk di panel selama ada
penguji luar, dan seminar proposal boleh dijalankan tim pembimbing saja. Keduanya
praktik lazim; menolaknya hanya memindahkan kegiatan itu ke luar sistem.

#### Integrasi yudisium — inti dari seluruh modul

`YudisiumService` kini membaca judul dari catatan yang sudah diuji. Argumen teks
lama bertahan sebagai cadangan untuk data pindahan saja.

Ditambah satu baris daftar periksa, dihormati per prodi lewat
`prodi.wajib_tugas_akhir` (default `true`). Barisnya **dihilangkan sama sekali**
untuk prodi yang tidak mewajibkannya, bukan ditandai lolos, supaya
`persenSelesai()` tidak menghitung syarat yang tidak pernah ada.

Perubahan ini menggugurkan 10 tes lama yang meluluskan mahasiswa tanpa tugas
akhir. Itu memang perubahan yang dimaksud; fixture-nya diperbaiki dan tiga tes
baru ditambahkan untuk aturannya.

#### Bug yang saya temukan pada kode saya sendiri

`where('tanggal', $tanggal)` pada pemeriksaan bentrok tidak cocok di SQLite.
Mesin itu tak punya tipe DATE dan menyimpan apa pun yang diberikan, sehingga cast
Laravel menuliskan `2026-08-15 00:00:00` sementara kuerinya mencari
`2026-08-15`. Diperbaiki dengan `whereDate()`.

Yang membuatnya berbahaya bukan kesalahannya, melainkan **arah kegagalannya**:
pemeriksaan itu gagal *terbuka*. Nol baris terbaca sebagai "tidak ada bentrok".
Penjaga yang menjawab "aman" ketika dirinya rusak lebih buruk daripada tidak ada
penjaga.

#### Penjaga yang dibuktikan dengan mencabutnya

- **Bentrok ruang vs kuliah** — dicabut, sidang masuk ke ruang berisi kuliah.
- **Hapus log bimbingan orang lain** — dicabut, penghapusan **berhasil** (302
  alih-alih 403). Seorang mahasiswa dapat menjatuhkan temannya di bawah ambang
  sidang tanpa jejak.

Untuk penjaga akses dosen, buktinya berpasangan di berkas yang sama: rute dan
binding identik menjawab **200** bagi penguji dan **403** bagi dosen asing —
menyingkirkan kemungkinan tesnya lolos karena salah binding, kesalahan yang
pernah saya buat pada sesi terdahulu.

#### Data demo yang tidak melanggar aturannya sendiri

`TugasAkhirSeeder` berjalan **sebelum** `KelulusanSeeder`, karena lulusan perlu
tugas akhir untuk diluluskan *dari*. Diverifikasi: **9 dari 9 ijazah** kampus
demo judulnya cocok dengan catatan yang diuji.

Setengah data yang berjalan sengaja tidak sehat — satu judul menunggu keputusan,
satu disetujui empat bulan lalu tanpa pembimbing. Demo yang seluruhnya sehat
tidak menunjukkan apa pun tentang gunanya layar itu.

### Berikutnya

**G2 Notifikasi.** Modul ini baru saja menambah empat kejadian yang tidak
diberitahukan kepada siapa pun: judul diputus, pembimbing ditetapkan, sidang
dijadwalkan, batas revisi mendekat.

---

## Sesi 16 — 2026-08-07 · Pengumuman + Unggah Berkas

Dua modul terakhir yang tercatat kosong. **419 tes hijau (942 asersi).**

### Pengumuman

Sengaja tetap kecil sesuai catatan pada migrasinya — judul, isi, siapa yang
melihat, kapan tayang. Komentar dan feed adalah wilayah Open Campus.

Penjadwalan yang membuatnya berguna: `published_at` di masa depan tak terlihat
sampai saatnya tiba, jadi pengumuman KRS bisa ditulis Jumat dan muncul Senin.
Slug dijaga unik agar "Jadwal KRS" semester berikutnya tidak mengambil alih
alamat milik yang sebelumnya.

**Bug saya sendiri, tertangkap tes:** `nullable` tidak memasukkan kunci ke array
tervalidasi bila field-nya tak dikirim sama sekali, sehingga menyimpan draf
menghasilkan `Undefined array key`.

### Unggah berkas — pertama kalinya aplikasi menerima file

Isinya KTP, kartu keluarga, ijazah, surat keterangan sakit. Tiga aturan, semua
mudah dilanggar tanpa ada yang mengeluh:

1. **Tidak pernah disk publik.** `BerkasService` *menolak berjalan* bila
   `BERKAS_DISK=public`, alih-alih diam-diam menurutinya — salah konfigurasi itu
   menaruh KTP setiap mahasiswa di bawah document root dan tak ada bagian lain
   yang akan menyadarinya.
2. **Tidak pernah memakai nama dari pengunggah.** Nama datang dari klien:
   `../../.env`, null byte, `ktp.pdf.php`. Nama simpan dibangkitkan UUID; nama
   asli hanya label. Path-nya juga tak dapat ditebak — path berurutan adalah
   undangan mencoba nomor berikutnya sekalipun ada pemeriksaan izin.
3. **Jenis diperiksa dari isi**, bukan ekstensi.

**Otorisasi per berkas, bukan per sesi.** "Staf mana pun yang sudah masuk" akan
membiarkan bagian keuangan membaca kartu keluarga pendaftar, dan mahasiswa mana
pun membaca surat sakit temannya.

Penjaga itu **dibuktikan gagal** ketika sengaja dilonggarkan: tanpa dia,
permintaan mahasiswa lain menjawab 200, bukan 403. Lalu dikembalikan.

### Berikutnya

Tidak ada lagi modul yang tercatat kosong. Yang tersisa menunggu keputusan:
adaptor Midtrans (butuh kredensial merchant), federasi IdP eksternal, 2FA staf,
dan rilis publik v1.0 ke `motiolabs-space` — repo lokal masih belum di bawah Git.

---

## Sesi 15 — 2026-08-07 · Wisuda + Portabilitas Basis Data

### Wisuda

`WisudaService` + layar `admin.wisuda`. Dipisahkan dari yudisium dengan sengaja:
yudisium adalah keputusan akademik bahwa seseorang lulus; wisuda adalah acara
yang ia daftari, boleh ia lewatkan, dan boleh ia ikuti periode berikutnya.
Menggabungkan keduanya berarti lulusan yang tak hadir wisuda tercatat belum
lulus.

Yang harus benar adalah **nomor ijazah** — tercetak pada dokumen yang dipegang
seumur hidup dan dikutip di setiap lamaran kerja. Karena itu diterbitkan sekali,
tidak pernah dipakai ulang, dan **tidak pernah diterbitkan ulang**. Peserta yang
sudah bernomor juga tidak bisa dikeluarkan dari daftar.

Kuota dijaga di dalam transaksi ber-`lockForUpdate`: dua pendaftaran yang tiba
bersamaan akan membaca hitungan yang sama, lalu sama-sama lolos melewati kuota
penuh atau mengambil nomor urut yang sama.

### Portabilitas basis data

Audit menemukan **satu** ketidakportabelan yang nyata, dan itu jenis yang tidak
menimbulkan galat: `LIKE` tidak peka huruf besar-kecil di MySQL (`_ci`) tetapi
peka di PostgreSQL. Kotak pencarian akan berhenti menemukan "budi" ketika
datanya "Budi" — tanpa pesan apa pun. Staf hanya menyimpulkan mahasiswanya tidak
ada di sistem.

Ke-20 pencarian yang tersebar di 11 controller diganti satu scope
`DapatDicari::cari()` yang memakai `whereLike(caseSensitive: false)` bawaan
Laravel — `ilike` di PostgreSQL, `like` di MySQL. Ada tes yang memindai `app/`
dan gagal bila ada `'like'` literal menyelinap masuk lagi.

`NimGenerator` sengaja tetap peka huruf, dan sekarang menyatakannya eksplisit
alih-alih mewarisi kolasi mesin: awalan NIM adalah string persis.

**Yang ternyata tidak bermasalah:** `year()` dan `time()` sudah diterjemahkan
grammar Laravel untuk PostgreSQL/SQLite/SQL Server, dan seluruh SQL mentah
memakai konstruksi ANSI (`COUNT`, `SUM`, `MAX`, `CASE WHEN`) tanpa interpolasi.

**Oracle dinyatakan terbuka, bukan diklaim.** Laravel tidak menyertakan driver
Oracle; ia butuh `yajra/laravel-oci8` + ekstensi `oci8`, dan tipe `TIME` yang
dipakai deteksi bentrok jadwal perlu diverifikasi ulang di sana. Kami belum
pernah menjalankannya, jadi tidak menyebutnya "didukung" —
[`BASIS-DATA.md`](BASIS-DATA.md).

### Berikutnya

Pengumuman dan unggah berkas — dua sisa terakhir.

---

## Sesi 14 — 2026-08-07 · Matriks Tarif — dan Tiga Cacat Uang

Layar `admin.tarif` dibangun. Tetapi pekerjaan sebenarnya bukan layarnya:
**tiga cacat ditemukan saat membangunnya, semuanya pada kode yang saya tulis
sendiri pada Sesi 11.** Ketiganya menyangkut uang, dan tak satu pun menimbulkan
galat.

### 1. Tarif dijumlahkan, bukan ditimpa

Model `Tarif` mendokumentasikan aturannya sejak awal — *"baris paling spesifik
yang menang"* — lengkap dengan `spesifisitas()` untuk memutuskannya.

`PenerbitanTagihanService` dan `PmbService` **menjumlahkan seluruh baris yang
cocok**. Tarif umum 5 juta + penimpa prodi 7 juta = tagihan **12 juta**, dan
mahasiswa diminta membayarnya.

### 2. Masa berlaku diabaikan

`Tarif::scopeBerlakuPada()` ada sejak rilis pertama. Kedua pemanggil tidak
pernah memakainya, jadi jadwal biaya yang kedaluwarsa dua tahun lalu tetap
ditagihkan.

### 3. Golongan UKT tidak mungkin cocok

`tarif` punya dimensi `golongan_ukt`; **`mahasiswa` tidak punya kolomnya.**
Dimensi itu hanya bisa mencocoki wildcard null, sehingga UKT berjenjang mati
total — semua ditagih sama rata, membatalkan seluruh kebijakan UKT yang justru
bersifat berjenjang menurut kemampuan ekonomi.

Kolomnya ditambahkan lewat migrasi append-only, **disimpan** bukan dihitung
ulang dari `penghasilan_ortu`: golongan ditetapkan berdasarkan keadaan keluarga
saat diterima, dan tidak boleh bergeser diam-diam ketika seseorang menyunting
kolom penghasilan bertahun-tahun kemudian.

### Perbaikan

`TarifResolver` sebagai satu-satunya definisi aturan; kedua pemanggil
mendelegasi ke sana. Layarnya membawa **simulator** — masukkan NIM sungguhan,
ia menunjukkan komponen yang ditagih, dari baris mana angkanya berasal, dan
baris mana yang kalah.

### Temuan lain

Penjaga N+1 (`preventLazyLoading`, dipasang Fase 5) menangkap kelalaian saya
sendiri: `TarifResolver` tidak meng-eager-load `prodi` yang dipakai simulator.
Persis fungsi penjaga itu dipasang.

### Berikutnya

Wisuda (periode & peserta), pengumuman, unggah berkas.

---

## Sesi 13 — 2026-08-07 · Penutupan Semester — Menutup Gap Fungsional

**Koreksi atas laporan sesi sebelumnya.** Saya menyimpulkan IPS/IPK tidak pernah
dihitung. Itu keliru: `PenilaianService::finalisasi()` memanggil
`IndeksPrestasiCalculator::hitungUlangSeluruhTerm()` setiap kali dosen
memfinalisasi kelas, jadi angkanya selalu mutakhir.

Yang benar-benar tidak pernah terjadi hanyalah **pembekuannya**.
`status_mahasiswa.is_final` tidak pernah disetel siapa pun, dan
`BatasSksCalculator::semesterAcuan()` hanya membaca catatan yang **sudah beku**.
Akibatnya persis sama: setiap mahasiswa di instalasi sungguhan selamanya jatuh
ke `default_credits`, tangga batas SKS berbasis IPS tidak pernah menyala, dan
tidak ada galat apa pun yang memberi tahu.

**Dibangun:** `PenutupanSemesterService`, layar `admin.tutup-semester`, dan
perintah `openacademic:tutup-semester` (kampus besar akan kehabisan waktu
permintaan web jauh sebelum delapan ribu catatan selesai).

Empat sifat yang menentukan, semuanya ditutup tes:

- **Parsial.** Kampus besar selalu punya satu dosen telat. Menolak menutup apa
  pun sampai kelas terakhir masuk berarti tidak ada plafon SKS yang pernah
  diperbarui — justru kegagalan yang mau diperbaiki.
- **Idempotent.** Catatan yang sudah beku dilewati, bukan dihitung ulang.
  Menulis ulang KHS yang sudah terbit bukan wewenang proses batch.
- **Dihitung ulang tepat sebelum beku**, bukan mempercayai angka lama —
  perhitungan terakhir terjadi saat dosen lain memfinalisasi.
- **Pembukaan kembali teraudit** dengan alasan wajib: ia mengubah KHS yang sudah
  terbit dan IPK yang mungkin sudah dikutip untuk beasiswa.

Pembekuan sengaja jadi tindakan administratif tersendiri, bukan efek samping
dosen terakhir menekan "finalisasi": koreksi nilai pada pekan berikutnya adalah
hal biasa dan harus tetap mungkin tanpa pembukaan-kembali.

### Temuan & perbaikan

- **Izin salah pada percobaan pertama.** Saya memakai `nilai.manage`, yang
  dipegang dosen untuk kelasnya sendiri. Penutupan semester adalah administrasi
  kalender akademik — sedomain dengan mengaktifkan dan mengunci semester — jadi
  `master.manage`. Tertangkap tes 403 peran BAAK.
- `BatasSksCalculator::untuk()` mengembalikan `ips => null` (bukan 0.0) saat
  tidak ada acuan; asersi saya keliru dan diperbaiki.

### Terverifikasi pada data nyata

| Semester | Hasil |
|---|---|
| Berjalan (20261) | 2 siap · 48 terhalang · 20 kelas belum final |
| Selesai (20251) | 34 sudah beku · tidak menyentuh apa pun |

### Berikutnya

Matriks tarif (modul keuangan membacanya tapi tak ada layar membuatnya); lalu
wisuda, pengumuman, dan unggah berkas.

---

## Sesi 12 — 2026-08-07 · Jadwal & Kelas

**Item menu terakhir yang mati kini hidup.** Tidak ada lagi entri sidebar
ber-`route` null di ketiga portal.

`KelasKuliahService` + `JadwalService` + layar `admin.kelas`: membuka kelas
(termasuk paralel A–Z sekaligus), menugaskan dosen, menjadwalkan slot mingguan.
Ini yang mengubah kurikulum menjadi sesuatu yang benar-benar bisa diambil
mahasiswa — tanpanya katalog KRS kosong dan semester tidak bisa dimulai.

### Deteksi bentrok — memisahkan mustahil dari tidak disukai

| Bentrok | Perlakuan |
|---|---|
| Ruang dipakai dua kelas | **ditolak** — satu ruang tidak menampung dua kelas |
| Dosen mengajar dua kelas | **ditolak** — satu orang tidak berada di dua ruang |
| Kuota > kapasitas ruang | diperingatkan — keputusan registrar |
| Kelas sekohor beririsan | diperingatkan — mata kuliah pilihan lazim bertabrakan |

Perangkat lunak yang memblokir jenis kedua akan diakali, biasanya dengan
menjadwalkan di luar sistem sama sekali.

Tiga hal yang mudah terlewat dan sudah ditutup tes:

- **Pemeriksaan dosen berjalan dua arah** — saat menjadwalkan slot, *dan* saat
  menugaskan dosen ke kelas yang sudah punya slot. Hanya salah satunya berarti
  dosen tetap bisa berakhir di dua ruang sekaligus.
- **Ujung jadwal yang bersentuhan bukan bentrok.** 08:00–10:00 lalu 10:00–12:00
  berurutan, bukan bersamaan. Salah tanda pembanding di sini membuat setengah
  jadwal kampus ditolak tanpa alasan.
- **SKS dipotret, bukan direferensikan.** Revisi kurikulum yang mengubah bobot
  mata kuliah tidak boleh mengubah dasar penilaian mahasiswa yang sudah
  terlanjur mengambilnya.

Layar memimpin dengan **apa yang belum siap** — kelas tanpa dosen, kelas tanpa
jadwal — karena keduanya tidak menimbulkan galat apa pun sampai masa KRS dibuka.

### Berikutnya

Finalisasi status semester (gap fungsional, prioritas satu); matriks tarif;
lalu wisuda, pengumuman, dan unggah berkas.

---

## Sesi 11 — 2026-08-07 · Menutup Seluruh Modul yang Belum Ada

**Enam modul dibangun** — celah "datanya hanya bisa masuk lewat seeder atau
tinker" kini tertutup. Kampus sungguhan dapat menjalankan seluruh siklus dari
antarmuka.

| Modul | Isi |
|---|---|
| **Master Akademik** | Enam tab: tahun akademik, fakultas, prodi, kurikulum, mata kuliah + prasyarat, gedung & ruang |
| **SDM** | Kelola dosen & staf, peran, reset kata sandi, aktif/nonaktif |
| **PMB** | Gelombang, seleksi, dan registrasi ulang → NIM + akun + tagihan awal |
| **Cuti & Profil** | `CutiService` beserta layar staf; profil akademik mahasiswa |
| **Keuangan** | Penerbitan tagihan massal berpratinjau, rekonsiliasi, `PaymentGatewayInterface` |
| **Layar sisa** | Koreksi nilai staf, pindai QR mahasiswa, penampil log aktivitas, pengaturan |

### Aturan yang ditegakkan, bukan sekadar formulir

- **Kode PDDIKTI dihitung, bukan diketik** — salah satu digit memindahkan
  pelaporan satu semester ke semester lain.
- **Kalender divalidasi sebagai urutan.** Masa KRS yang tutup sebelum buka tidak
  menghasilkan error; ia menghasilkan semester yang tak bisa dipakai siapa pun.
- **Prasyarat menolak lingkaran** lewat penelusuran graf — A→B lalu B→A membuat
  keduanya tak pernah bisa diambil.
- **Dosen tak bisa dinonaktifkan** bila masih mengampu kelas semester berjalan
  atau menjadi wali mahasiswa aktif.
- **Super Admin terakhir tak bisa dinonaktifkan** — mengunci seluruh
  administrator tidak dapat dipulihkan lewat antarmuka.
- **NIM tidak pernah dipakai ulang**, termasuk milik mahasiswa yang dihapus
  lunak; nomornya masih tercetak di transkrip yang terlanjur terbit.
- **Cuti ditolak** bila mahasiswa masih memegang KRS aktif pada semester sama.
- **`tagihan.terbayar` dihitung ulang dari baris pembayaran**, bukan ditambahkan
  — penambahan yang berjalan dua kali diam-diam melunasi utang yang belum lunas.
- **Penerbitan tagihan massal berpratinjau dan idempotent**; mahasiswa tanpa
  tarif dilaporkan, bukan ditagih nol rupiah.

### Temuan & perbaikan

- **Instalasi baru nyaris mustahil dipakai.** Saya sempat menaruh layar master
  di bawah `term.active`. Instalasi baru belum punya semester aktif, jadi
  middleware itu menjawab 503 — dan layar yang membuat semester pertama justru
  layar itu sendiri. Rutenya dikeluarkan; ada tes yang menjaganya.
- **Penyaring yudisium memakai ambang SKS global**, padahal sebagian prodi
  menetapkan `sks_lulus` lebih rendah — seluruh mahasiswa prodi tersebut akan
  hilang dari layar yudisium tanpa jejak.
- `Staff` belum memakai `HasLogAktivitas`, padahal perubahan peran justru yang
  paling perlu terekam. Ditambahkan beserta `$logExcept` untuk kata sandi.
- Kata sandi akun baru **dibangkitkan, bukan diketik administrator**, dan
  ditampilkan sekali. Kata sandi yang dipilih operator adalah kata sandi yang
  masih diketahui operator.

### Koreksi atas klaim sesi ini

Saya sempat menyatakan "seluruh modul selesai". **Itu keliru.** Pemeriksaan
ulang terhadap kode — item menu ber-`route` null, dan tabel domain yang hanya
pernah ditulis seeder — menemukan enam hal yang masih kosong: finalisasi status
semester, Jadwal & Kelas, matriks tarif, wisuda, pengumuman, dan unggah berkas.
Keenamnya diselesaikan pada hari yang sama; lihat sesi-sesi berikutnya.

*(Rinciannya dulu ada di ROADMAP §Belum Dikerjakan. Bagian itu tidak ikut
terbawa saat ROADMAP disusun ulang pada 15 Agustus 2026 menjadi dokumen yang
menghadap ke depan.)*

Yang terpenting bukan layar yang hilang: **tidak ada kode produksi yang
memfinalisasi `status_mahasiswa`.** `BatasSksCalculator` mensyaratkan
`is_final = true` untuk membaca IPS semester lalu, sehingga di instalasi
sungguhan setiap mahasiswa selamanya jatuh ke `default_credits` — tangga batas
SKS berbasis IPS tidak pernah menyala. Kampus demo menutupinya karena
`RiwayatAkademikSeeder` menulis baris itu langsung.

### Berikutnya

Finalisasi status semester (gap fungsional, prioritas satu); layar Jadwal &
Kelas; matriks tarif. Lalu rilis v1.0 ke `motiolabs-space`.

---

## Sesi 10 — 2026-08-07 · Uji Kapasitas 5.000 Mahasiswa

**Dimuat 5.000 mahasiswa · 15.000 KRS · 118.937 baris KRS · 118.575 nilai ·
631.220 presensi · basis data 288 MB**, lalu seluruh layar diukur. Angka
lengkapnya di [`KAPASITAS.md`](KAPASITAS.md).

Tiga cacat kinerja muncul — tidak satu pun terlihat pada kampus demo 50
mahasiswa:

| Layar | Sebelum | Sesudah |
|---|---|---|
| Daftar kelas dosen | **17.917 ms** | 319 ms |
| Yudisium (kohor) | 6.338 ms | 1.176 ms |
| Agregat kehadiran (kueri tunggal) | 6.227 ms | **20 ms** |

- **Daftar kelas dosen** memanggil `rekapKelas()` per kelas, yang tiap kali
  menarik seluruh baris presensi kelas itu ke PHP hanya untuk menghitungnya.
  Diganti `rawanAbsensi()`: tiga kueri agregat untuk berapa pun jumlah kelas.
- **Indeks presensi.** Sisa waktunya ternyata ada di satu kueri. Indeks unik
  `presensi` tidak memuat `status`, jadi setiap baris harus dibaca dari tabel
  untuk membuang yang alpa. Indeks penutup `(pertemuan_kelas_id, status,
  mahasiswa_id)` menurunkannya 311×. Presensi adalah tabel terbesar dengan
  selisih jauh — 197 MB dari 288 MB.
- **Yudisium** menjalankan daftar periksa penuh atas seluruh mahasiswa aktif.
  Kini disaring agregat SKS lebih dulu.

**Jebakan yang nyaris lolos:** penyaring yudisium sempat memakai ambang SKS
global, padahal sebagian prodi menetapkan `sks_lulus` di bawah default —
seluruh mahasiswa prodi tersebut akan hilang dari layar yudisium tanpa jejak.
Tertangkap tes yang sudah ada; ditambah tes baru yang menyatakan niatnya
terang-terangan.

**Batas uji, dicatat terbuka.** Data sintetis menaruh ±1.900 mahasiswa per
kelas (5.000 dibagi 21 kelas), sedangkan kampus sungguhan berisi 30–50. Jadi
layar per-kelas teruji jauh lebih berat dari kenyataan, tetapi layar yang
panjangnya mengikuti **jumlah kelas** — terutama katalog KRS — justru teruji
lebih ringan: kampus 5.000 mahasiswa butuh ±1.000 kelas, bukan 21. Beban
bersamaan pada jam pembukaan KRS belum diuji sama sekali.

### Berikutnya

Perintah artisan pembangkit data skala agar angka ini dapat diverifikasi ulang;
ukur katalog KRS pada 1.000 kelas; uji beban bersamaan.

---

## Sesi 9 — 2026-08-07 · SSO OAuth2 (menutup Fase 3)

**Selesai dengan Laravel Passport 13.** Open Academic kini *server* OAuth2;
aplikasi kampus lain mengarahkan penggunanya ke sini. Arah ini penting: yang
sering dibayangkan justru sebaliknya, padahal identitas resmi kampus memang
sudah tinggal di sini.

### Tiga kendala nyata

Passport mengasumsikan satu tabel pengguna; kita punya tiga. Ketiganya diperiksa
di kode Passport lebih dulu, bukan ditebak:

| Kendala | Jembatan |
|---|---|
| `AuthorizationController` terikat **satu** guard (`PassportServiceProvider:113`) | `SsoGuard` — tanpa sesi sendiri, membaca guard mana pun yang sedang masuk. Guard sesi keempat justru akan meminta login kedua |
| Subject = `getAuthIdentifier()` di kolom **bigint**; id 1 ada di ketiga tabel | Subject jadi UUID, `oauth_*.user_id` diubah ke `char(36)` |
| `Token::user()` mengasumsikan satu model | `SsoUserProvider` menelusuri ketiga tabel, sekaligus menolak akun nonaktif |

### Temuan & perbaikan

- **Login rusak total di server sungguhan, sementara 227 tes tetap hijau.**
  Kolom `sessions.user_id` masih `bigint`, sehingga setiap login gagal 500
  begitu identifier menjadi UUID. Suite tidak menangkapnya karena tes memakai
  session driver `array` yang tidak menyimpan apa pun. Ditemukan hanya karena
  saya menempuh alur di server nyata, bukan karena tesnya. Kolomnya diperbaiki,
  dan penjaganya kini menguji skema langsung — tes berbasis request akan tetap
  hijau meski kolomnya kembali bigint, jadi tes seperti itu percuma di sini.
- **Jejak audit hampir kehilangan aktornya.** `causer_id` memakai
  `getAuthIdentifier()`, padahal `causer_type` + `causer_id` adalah relasi
  polimorfik yang menyelesaikan lewat primary key. Sebelumnya kebetulan bernilai
  sama; setelah identifier menjadi UUID keduanya berbeda, dan setiap catatan
  audit akan menunjuk ke bukan siapa-siapa. Diganti ke `getKey()`.
- **Ekstensi `sodium` mati** di PHP lokal, dan Passport 13 tidak dapat dipasang
  tanpanya. Diaktifkan di `php.ini`; dicatat sebagai prasyarat.
- **Migrasi terpisah untuk melebarkan kolom ternyata tidak melakukan apa-apa** —
  Passport 13 hanya mem-*publish* migrasinya, tidak memuat otomatis, sehingga
  tabelnya belum ada saat migrasi saya berjalan dan semuanya dilewati diam-diam.
  Alasannya batal, jadi pendekatannya diganti: publish lalu ubah kolomnya
  langsung.
- **Tombol "Masuk dengan Akun Kampus (SSO)" dihapus, bukan disambungkan.**
  Tombol itu menggambarkan arah yang salah — Open Academic *adalah* akun
  kampusnya, tidak ada IdP di atasnya untuk didelegasikan.
- Dua tes saya sendiri sempat memakai jalur yang salah: `createToken()`
  (personal access token, butuh provider satu-model) dan pemeriksaan substring
  `nik` yang gagal pada prodi bernama "Teknik".

### Diverifikasi di server sungguhan

Login → layar persetujuan → callback dengan `code` → tukar token (Bearer,
3600 detik, ada refresh) → `userinfo` mengembalikan mahasiswa yang benar dengan
`sub` berupa UUID.

### Berikutnya

Federasi ke IdP eksternal (Google Workspace / Entra ID / Keycloak) menunggu satu
keputusan kebijakan kampus: identitas eksternal dipetakan ke tabel yang mana.
Salah memilih berarti dosen bisa masuk ke portal mahasiswa.

---

## Sesi 8 — 2026-08-06 · Fase 5: Polish & Rilis

**Selesai.** Tiga hal yang dikerjakan berbasis bukti, bukan pemeriksaan mata.

### Audit N+1

`Model::preventLazyLoading()` dinyalakan di luar produksi, lalu
`tests/Feature/SmokeLayarTest.php` menelusuri 21 layar dan 9 endpoint Bridge
terhadap kampus demo penuh dengan **anggaran kueri** per layar.

Dua instrumen dipakai karena keduanya menangkap cacat berbeda: penjaga lazy
loading menangkap relasi yang belum dimuat, sedangkan controller yang membuat
query builder baru di dalam loop tidak melanggar apa pun — hanya cacahan kueri
yang melihatnya.

| Layar | Sebelum | Sesudah |
|---|---|---|
| `/admin/yudisium` | **171 kueri** | ≤ 30 |
| `/admin/feeder` | 50 | ≤ 30 |

Yudisium diperbaiki dengan `periksaSyaratBanyak()`; versi satu-mahasiswa kini
mendelegasi ke sana supaya aturan kelulusan tidak pernah punya dua implementasi
yang bisa berbeda hasil. Konsol Feeder diganti satu agregat berkelompok.
`PresensiService` tidak lagi mengandaikan pemanggilnya sudah meng-eager-load.

### Review keamanan — satu kerentanan nyata ditemukan

Rute presensi dosen mengikat `{kelas}` dan `{pertemuan}` secara terpisah,
tetapi otorisasi hanya diperiksa terhadap `{kelas}`. Seorang dosen dapat
mengirim id kelasnya sendiri bersama id pertemuan milik kelas rekan, dan:

- menulis presensi untuk mahasiswa kelas tersebut, dan
- membuka sesi QR presensi mandiri pada kelas yang tidak diampunya.

Pemeriksaan otorisasi lolos karena ia tidak pernah ditanyai tentang objek yang
sebenarnya ditulis. Ditutup dengan `pastikanMilikKelas()`; regresi dijaga
`tests/Feature/Keamanan/LintasObjekTest.php` — dan tes itu **sudah dibuktikan
gagal** dengan penjaganya dinonaktifkan sementara, bukan sekadar diasumsikan
menangkap.

Diperbaiki juga:

- Webhook dengan `BRIDGE_WEBHOOK_SECRET` kosong kini dibatalkan. Sebelumnya
  ditandatangani HMAC berkunci kosong, yang lolos verifikasi di sisi konsumen —
  tampak aman padahal siapa pun bisa memalsukannya.
- Masuk ke satu portal me-logout guard lain, sehingga satu peramban tidak lagi
  memegang dua identitas.
- `SecurityHeaders` (CSP, `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`) pada seluruh respons.

Sapuan mass assignment dan SQL mentah bersih: tidak ada `request()->all()`, dan
seluruh raw SQL memakai string statis tanpa interpolasi.

### Aksesibilitas & dokumen

Tautan lompat ke konten, `aria-expanded`/`aria-haspopup` pada dropdown,
`role="dialog"` pada lembar menu mobile, nama aksesibel untuk kolom pencarian
dan menu akun. `SECURITY.md`, `CONTRIBUTING.md`, `CHANGELOG.md`, dan
`docs/DEPLOYMENT.md` ditulis.

### Temuan & perbaikan

- **Tes hijau yang tidak berarti apa-apa.** Putaran pertama smoke test lolos
  29/29, dan itu mencurigakan. Canary membuktikan penjaga lazy loading memang
  tidak aktif: Laravel hanya memasangnya pada kueri yang menghidrasi lebih dari
  satu model (`Builder::hydrate`), sehingga canary yang memakai `firstOrFail()`
  tidak pernah memicunya. Setelah diulang dengan koleksi, penjaga terbukti
  bekerja — dan angka 171 kueri itu baru muncul.
- **Dua tes keamanan yang lolos karena alasan salah.** Ketiga tes IDOR memakai
  `id` pada URL, padahal route binding memakai `uuid`; semuanya 404 — termasuk
  yang seharusnya berhasil. Dua "lolos" itu palsu. Diperbaiki ke `uuid`, lalu
  ketiganya diuji ulang.
- **CSP memutus Google Fonts** pada percobaan pertama. Diizinkan eksplisit, dan
  ketergantungan pihak ketiganya dicatat terbuka di `SECURITY.md` alih-alih
  didiamkan.
- **`focus:not-sr-only` tidak membalikkan `sr-only`** — tautan lompat tetap
  sebesar 1px meski difokus. Diganti pola transform.
- Seeder melonggarkan mode ketat selama menulis fixture; penelusuran layar
  tetap berjalan ketat.

### Berikutnya

Rilis v1.0 ke `motiolabs-space` — repo lokal belum berada di bawah Git, jadi
inisialisasi dan publikasinya menunggu keputusan pemilik repo. Modul pasca-1.0
tercatat di ROADMAP: SSO OAuth2, PMB, cuti mahasiswa, rekonsiliasi pembayaran.

---

## Sesi 7 — 2026-08-06 · Fase 4: Data IKU

**Selesai — keenam indikator kini punya sumber data yang terpakai.**

- `YudisiumService`: checklist kelulusan yang menjelaskan setiap syarat beserta
  angkanya, penetapan dalam satu transaksi (yudisium + status Lulus + record
  alumni), pembatalan ter-audit, dan daftar kandidat yang sudah memenuhi syarat.
- Layar Yudisium & Wisuda: checklist per mahasiswa, penetapan bernomor SK,
  daftar lulusan.
- Layar Verifikasi Data IKU: aktivitas MBKM (IKU 2) dan penugasan dosen
  (IKU 3/4), dengan penerbitan `activity.recorded` dan
  `lecturer.assignment_recorded`.
- `GET /api/bridge/v1/iku-data`: cacahan fakta untuk IKU 1/2/3/4/7/11.
- `KelulusanSeeder`: 9 lulusan, record alumni dengan status pekerjaan campuran,
  dan satu periode wisuda — sehingga IKU 1 punya populasi untuk ditelusuri.
- 14 tes baru. Total 179 hijau (392 asersi).

**Keputusan yang perlu diketahui.**

- Syarat kelulusan **diperiksa ulang saat penetapan**, bukan dipercaya dari
  pengajuan. Berminggu-minggu dapat berlalu di antaranya, dan koreksi nilai atau
  tagihan baru tidak boleh lolos begitu saja. Ada tes yang menguncinya.
- Endpoint `iku-data` menuntut **seluruh** scope baca yang menyusunnya. Endpoint
  ringkas tidak boleh menjadi jalan pintas membaca data yang scope-nya tidak
  diberikan.
- Angka yang bergantung kebijakan dikembalikan **per bucket**, bukan diambang:
  `per_sks_konversi` melaporkan `0-5`, `6-19`, `20+`. Ambang 20 SKS diatur
  peraturan menteri dan berubah.

**Temuan & perbaikan selama sesi.**

- **Seeder MBKM menandai jauh lebih sedikit catatan terverifikasi dari yang
  dimaksud** — 2 dari 8, bukan 6. Penyebabnya `foreach` atas Collection hasil
  `filter()` mempertahankan kunci aslinya, sehingga indeks loop meloncat.
  Ditambahkan `values()`. Ini bukan cacat kosmetik: aktivitas terverifikasi
  adalah persis bukti yang diperiksa untuk IKU 2, dan data demo yang sepi
  membuat modulnya tampak tidak bekerja.

**Berikutnya:** Fase 5 — polish & rilis (audit N+1, review keamanan,
aksesibilitas, docs site), plus sisa modul: PMB, cuti, rekonsiliasi, pengaturan,
dan SSO yang masih tertunda dari Fase 3.

---

## Sesi 6 — 2026-08-06 · Fase 3: Campus Bridge

**Selesai — Read API, webhook, spec, dan konsol.**

- Token Sanctum per aplikasi konsumen, penegakan scope, pencatat lalu lintas
  API, dan perintah `openacademic:bridge-token`.
- Read API `/api/bridge/v1`: 11 endpoint atas 6 resource (students, lecturers,
  classes, student-activities, graduates, academic-terms), berpaginasi,
  berfilter, dan ber-rate-limit.
- `BridgeEventPublisher` + `PublishBridgeEventJob`: tanda tangan HMAC atas
  `{timestamp}.{body}`, backoff bertingkat 60s/5m/15m/1j/6j, log pengiriman,
  kirim ulang manual. Terpasang pada alur nyata — `krs.approved` saat dosen
  wali menyetujui, `grade.finalized` setelah IPK dihitung ulang.
- `docs/openapi/bridge.yaml` spec-first, lengkap dengan kontrak webhook.
- Konsol Bridge: aplikasi terhubung, scope, grafik penggunaan 14 hari, log
  pengiriman dengan status tanda tangan.
- 27 tes baru. Total 165 hijau (355 asersi).

**Diverifikasi dengan panggilan sungguhan** memakai token nyata terhadap data
demo: 50 mahasiswa, 12 kelas kolaboratif (IKU 7), 2 aktivitas MBKM
terverifikasi, 401 tanpa token. Satu event `krs.approved` dipicu dari alur
persetujuan sungguhan, gagal terkirim ke Open Campus yang memang belum menyala,
lalu tercatat sebagai *Gagal (akan diulang)* dengan tombol kirim ulang — persis
perilaku yang dirancang.

**Temuan & perbaikan selama sesi.**

- **Otorisasi memakai principal basi.** Middleware scope memutuskan berdasarkan
  instance yang diserahkan token guard, yang ternyata dapat berupa salinan
  cache. Akibatnya pencabutan scope oleh administrator tidak langsung berlaku —
  bentuk kegagalan otorisasi, bukan sekadar keterlambatan tampilan. Kini
  consumer di-`refresh()` sebelum keputusan diambil; ada tes yang menguncinya.
- **`BridgeConsumer` harus memenuhi kontrak Authenticatable**, karena rate
  limiter Laravel menanyakan identitas pemanggil. Bukan login: konsumen tidak
  punya kata sandi maupun sesi, hanya token.
- **`backoffSeconds()` dapat jatuh ke jeda terpanjang secara keliru** ketika
  indeks percobaan di luar jangkauan jadwal. Indeksnya kini dijepit; kunci yang
  hilang tidak boleh terbaca sebagai "coba lagi sekarang".
- Suite berjalan dengan `QUEUE_CONNECTION=sync`, sehingga `publish()` menjalankan
  job seketika dan menembak HTTP sungguhan di dalam tes. Tesnya diperbaiki
  dengan `Queue::fake()`. Ini juga catatan operasional: instalasi yang keliru
  menyetel antrean ke `sync` akan membuat dosen menunggu endpoint pihak lain
  saat memfinalisasi nilai.

**Belum dikerjakan:** SSO OAuth2. Sebaiknya memakai Laravel Passport alih-alih
ditulis tangan — server OAuth setengah jadi lebih berbahaya daripada tidak ada.

**Berikutnya:** Fase 4 — endpoint data IKU yang tersisa (aktivitas per mahasiswa,
rekap penugasan dosen), lalu Fase 5 polish & rilis.

---

## Sesi 5 — 2026-08-06 · Fase 2: Neo Feeder PDDIKTI

**Selesai — modul pelaporan PDDIKTI end-to-end.**

- `FeederClientInterface` dengan dua implementasi: `NeoFeederClient` (cache
  token, retry sekali saat token kedaluwarsa, keputusan sukses dari
  `error_code` bukan status HTTP — Feeder menjawab 200 untuk kegagalan juga)
  dan `FakeFeederClient` yang dapat diprogram menolak/membalas.
- Enam mapper: Mahasiswa, RiwayatPendidikan, AktivitasKuliah, KelasKuliah,
  KRS, Nilai. Kode lokal diterjemahkan lewat `feeder_mappings`, tidak pernah
  diasumsikan sama dengan kode PDDIKTI.
- `FeederValidator` — 20+ aturan yang menulis `feeder_validation_issues`
  sebelum satu baris pun dikirim. Error membatalkan sinkronisasi; peringatan
  tidak.
- `FeederSyncService` — idempotensi lewat `payload_hash`, urutan dependensi
  antar entitas, ulangi-yang-gagal, tarik tabel referensi.
- `SyncFeederEntityJob` pada antrean `feeder`, tiga perintah artisan, dan layar
  monitor dengan heartbeat, kartu entitas, laporan validasi, dan buku besar.
- 16 tes baru. Total 138 hijau (291 asersi).

**Dijalankan sungguhan pada data demo (driver fake):**

- Validasi menemukan 1 mahasiswa tanpa NIK dan 2 kelas berpengampu praktisi
  tanpa NIDN, lalu **membatalkan** sinkronisasi — persis perilaku yang dijanjikan.
- Setelah data diperbaiki, rantai `semua` berjalan berurutan dan mengirim
  197 baris.
- **Jalankan ulang: 0 terkirim, 197 dilewati.** Idempotensi terbukti pada
  jalankan nyata, bukan hanya di unit test.

**Temuan & perbaikan selama sesi.**

- **Kolom `feeder_id` salah tingkat.** `InsertKRSMahasiswa` melaporkan satu
  baris per mata kuliah, sehingga identitas Feeder milik `krs_detail`, bukan
  header `krs`. Rencana studi enam mata kuliah punya enam record di Feeder,
  bukan satu. Ditambahkan migrasi baru; kolom lama di `krs` ditinggalkan dan
  tidak lagi ditulis mapper mana pun.
- **PDDIKTI memberi mahasiswa dua identitas** yang tidak dapat dipertukarkan:
  `id_mahasiswa` dari biodata, dan `id_registrasi_mahasiswa` dari riwayat
  pendidikan. Seluruh payload berikutnya mereferensikan yang kedua. Ditambahkan
  kolom terpisah — menyatukannya akan salah lapor begitu mahasiswa pindah prodi.

**Berikutnya:** Fase 3 — Campus Bridge (SSO, Read API, webhook, OpenAPI).

---

## Sesi 4 — 2026-08-06 · Menutup Fase 1: Penilaian, Presensi, Transkrip

**Selesai.**

- `PenilaianService` — komponen berbobot (validasi total 100), nilai akhir
  terbobot, kunci & finalisasi, jalur koreksi ter-audit khusus staf.
- `IndeksPrestasiCalculator` — IPS per semester dan IPK kumulatif. Mata kuliah
  yang diulang dihitung **sekali** dengan perolehan terbaik; seluruh percobaan
  tetap tersimpan dan tetap dilaporkan ke PDDIKTI.
- `PresensiService` — input massal per pertemuan, rekap kelas, sesi QR dengan
  token berputar, dan aturan kelayakan UAS. Persentase dihitung terhadap
  pertemuan yang benar-benar terlaksana, bukan 16 nominal.
- `TranskripService` + PDF resmi: bingkai ganda navy/emas, hanya nilai final,
  kode verifikasi dari uuid + total SKS.
- Enam layar baru: Kelas Diampu, Input Nilai (hitung huruf saat mengetik),
  Presensi (grid 16 pekan + panel per pertemuan + kartu sesi QR), Mahasiswa
  Bimbingan (bar chart IPS + peringatan akademik), Jadwal Kuliah mahasiswa,
  dan unduh Transkrip.
- 30 tes baru. Total 122 hijau (253 asersi).

**Temuan & perbaikan selama sesi.**

- **`status_nilai` terbalik.** Kelas yang seluruh nilainya sudah terisi justru
  ditandai "belum". Diperbaiki: "belum" hanya ketika sama sekali tidak ada
  isian; terisi penuh tetap "sebagian" sampai difinalisasi, karena final adalah
  keadaan tersendiri, bukan soal kelengkapan.
- **`PertemuanKelas::sisaDetikQr()` meledak.** `diffInSeconds()` mengembalikan
  float di Carbon 3 sedangkan metodenya menjanjikan `int`. Hanya terpicu saat
  sesi QR terbuka — tidak akan tertangkap tanpa benar-benar membuka sesi.
- Skrip verifikasi saya sempat menargetkan form yang salah (`form[action*=
  "/dosen/nilai/"]` cocok lebih dulu dengan form finalisasi di header), sehingga
  sempat terbaca seolah penyimpanan nilai gagal. Kodenya baik-baik saja;
  selektornya yang keliru.
- Komponen Alpine lembar nilai semula ditulis sebagai `<script>` di luar
  `@section` — tidak akan pernah dirender. Dipindah ke bundel Vite.

**Berikutnya:** Fase 2 — Neo Feeder PDDIKTI (`NeoFeederClient`, mapper, ledger,
validator pra-kirim, layar monitor).

---

## Sesi 3 — 2026-08-06 · Fase 1.1: Modul KRS

**Selesai — modul KRS end-to-end.**

- `AturanAkademikException` dengan named constructor: pesan penolakan aturan
  berbahasa Indonesia, ditulis sekali, dirender langsung ke pengguna lewat
  `renderable()` di `bootstrap/app.php` (tanpa try/catch di controller).
- `BatasSksCalculator` — batas SKS dari IPS semester **final** terakhir.
  Semester yang nilainya belum final sengaja dilewati: IPS-nya nol dan akan
  menghukum mahasiswa karena keterlambatan penilaian.
- `PrasyaratChecker` — prasyarat hanya terbuka oleh nilai final, dimemoisasi
  per mahasiswa agar katalog KRS tidak menembak kueri per baris.
- `KrsService` — pemilik tunggal mutasi KRS: kurikulum, duplikat, sudah lulus,
  prasyarat, batas SKS, bentrok jadwal, gerbang pembayaran, kuota atomik
  (`lockForUpdate` dalam transaksi), transisi status tervalidasi.
- Layar KRS mahasiswa: katalog dengan bar kuota, tray SKS lengket, tracker
  4 tahap, dan stempel emas "KRS Diajukan".
- Layar persetujuan dosen wali: peringatan lebih-SKS/IPS rendah/bentrok jadwal,
  setuju satu klik, tolak wajib bercatatan.
- 33 tes baru (26 aturan service + 7 alur HTTP). Total 92 tes hijau.

**Temuan & perbaikan selama sesi.**

- **`Portal::role()` bisa mengembalikan aktor yang salah.** Ia memindai guard
  dengan urutan tetap, sehingga peramban yang memegang sesi pada dua guard akan
  membuat dosen ter-resolve di rute mahasiswa — bentuk kebocoran data, bukan
  cacat kosmetik. Kini guard yang diminta rute (default driver yang di-set
  middleware `auth:`) diperiksa lebih dulu.
- **Seeder tidak pernah memberi peran `mahasiswa` maupun `dosen-wali`** yang
  merata. Akibatnya pengisian KRS dan antrean persetujuan membalas 403 pada data
  demo. Baru terlihat sekarang karena KRS adalah policy pertama yang benar-benar
  memeriksa izin mahasiswa.
- **Data demo melanggar prasyaratnya sendiri** — layar KRS menampilkan baris
  bertanda "Diambil" sekaligus "Prasyarat belum lulus". Akarnya seeder menulis
  langsung ke tabel tanpa lewat service. Disiasati dengan penyaringan prasyarat
  di seeder (kini 0 pelanggaran) dan peringatan disembunyikan pada baris yang
  sudah diambil; akar masalahnya dicatat sebagai utang teknis.
- **`@disabled(...)` di dalam tag `<x-button>`** dikompilasi Blade menjadi PHP
  mentah di tengah atribut komponen dan menghasilkan `endif` yatim — layar KRS
  gagal parse. Diganti atribut terikat `:disabled`. Ditambahkan pemeriksaan
  kompilasi seluruh Blade agar kelas kesalahan ini ketahuan lebih awal.

**Berikutnya:** Fase 1.2 — `PenilaianService` + layar input nilai, lalu
Presensi dan Transkrip PDF.

---

## Sesi 2 — 2026-08-06 · Menutup Fase 0

**Selesai.**

- **Audit trail terverifikasi end-to-end.** 7 feature test (aktor, nilai lama/baru,
  kata sandi tidak pernah tercatat, soft delete sebagai peristiwa, baris bertahan
  setelah subjek dihapus permanen) plus satu putaran `queue:work --queue=audit`
  sungguhan pada driver database. Sebelumnya mekanismenya belum pernah menulis
  satu baris pun.
- **Risiko operasional yang ditemukan:** tanpa worker antrean, jejak audit gagal
  **diam-diam** — nilai berubah tanpa jejak. Kini diperingatkan tegas di README.
- **6 Policy + `authorize()`.** `MahasiswaPolicy`, `KrsPolicy`, `NilaiPolicy`,
  `TagihanPolicy`, `KelasKuliahPolicy`, `DosenPolicy`, berbagi trait
  `ResolvesActor` yang memeriksa izin Spatie pada guard milik aktor sendiri.
  `AuthorizesRequests` dipasang di controller dasar. 18 tes batas otorisasi.
- **Middleware `EnsureTermIsActive`** di ketiga grup rute portal: balasan 503
  dengan halaman penjelasan, isinya berbeda untuk staf (cara memperbaiki) dan
  untuk mahasiswa/dosen (bukan wewenang mereka).
- Total 59 tes hijau (dari 29), Pint bersih, build sukses.

**Temuan & perbaikan selama sesi.**

- `Portal::term()` memoisasi lewat properti statis — akan membocorkan semester
  basi antar tes dan antar request Octane. Dipindah ke container per-request.
- Memoisasi container sempat gagal karena `app()->instance()` dengan nilai `null`:
  Laravel memakai `isset()` untuk mendeteksi instance, sehingga "tidak ada semester
  aktif" disangka key tak terikat lalu di-resolve sebagai nama kelas. Nilainya
  kini dibungkus array.
- Satu ekspektasi tes ternyata salah, bukan kodenya: peran `keuangan` memang punya
  izin `mahasiswa.view` — staf keuangan wajar mencari mahasiswa untuk urusan
  tagihan. Tesnya diganti memakai staf tanpa peran sama sekali.
- Frasa "Kalender Akademik" terpotong newline di sumber Blade sehingga tidak
  terbaca `assertSee`. Kalimatnya dirapikan.
- `resources/views/welcome.blade.php` (bawaan Laravel, tidak terpakai) dihapus.
- Koreksi angka: skema berisi **47 tabel domain**, bukan 34 seperti dilaporkan
  pada sesi 1.

**Berikutnya:** Fase 1 — `KrsService` lebih dulu (lihat `docs/ROADMAP.md` §1.1).

---

## Sesi 1 — 2026-08-06 · Fase 0: Fondasi

**Selesai.**

- Proyek Laravel 12 baru (`motiolabs/open-academic`), PHP 8.2, MySQL/MariaDB.
- Toolchain: Pint (`declare_strict_types` wajib), Pest, GitHub Actions
  (pint → pest → `npm run build`), Tailwind 4 + Alpine via Vite.
- Konfigurasi domain: `config/academic.php`, `feeder.php`, `bridge.php`,
  `payment.php`, `branding.php` — semua aturan yang berbeda antar kampus
  jadi konfigurasi, bukan kode.
- **13 enum PDDIKTI-aligned**: `SemesterType` (encoding 20261), `StudentStatus`,
  `KrsStatus`, `GradeLetter`, `AttendanceStatus`, `InvoiceStatus`, `PaymentStatus`,
  `StudentActivityType` (9 jalur MBKM), `LecturerAssignmentType`, `ApplicantStatus`,
  `FeederSyncStatus`, `WebhookDeliveryStatus`, `Gender`, `EducationLevel`, `LeaveStatus`,
  `UserRole`.
- **Skema penuh, 47 tabel domain** (+14 tabel infrastruktur Laravel/Spatie,
  78 foreign key) dalam 12 migrasi berdomain: master akademik,
  SDM + penugasan dosen, kemahasiswaan + aktivitas MBKM, perkuliahan + presensi,
  KRS, penilaian, keuangan, PMB, wisuda/alumni, Feeder, Campus Bridge, sistem.
  Seluruh tabel akademik punya `uuid`, `softDeletes`, FK `restrictOnDelete`.
- **40 model Eloquent** lengkap relasi, cast enum, scope, dan trait
  `HasUuid` + `HasLogAktivitas` (audit trail ter-antre).
- Autentikasi 3 guard (`staff`/`dosen`/`mahasiswa`) + Spatie Permission
  (10 peran, 30 izin lintas tiga guard). Satu formulir masuk, guard dikenali
  dari identitas yang diketik.
- UI shell sesuai design system "Midnight Executive": sidebar navy collapsible,
  topbar dengan semester switcher, komponen Blade (button, chip, card, stat-card,
  page-header, alert, empty-state, toast), motif guilloché, animasi heartbeat & stempel.
- **Layar terpakai**: landing, login split-screen, dasbor mahasiswa/dosen/admin,
  KHS & transkrip, tagihan & pembayaran, data mahasiswa (filter + kolom kesiapan PDDIKTI).
- **DemoCampusSeeder**: 1 fakultas, 2 prodi, kurikulum berversi ±22 MK/prodi
  dengan prasyarat, 7 dosen (1 praktisi tanpa NIDN), 4 staf, 50 mahasiswa,
  3 semester (2 tertutup dengan nilai final → IPK & batas SKS nyata),
  63 kelas, 1.389 baris presensi, tagihan lintas tiga status, 110 pendaftar PMB,
  8 aktivitas MBKM, Open Campus terdaftar sebagai Bridge consumer.
- 29 tes Pest hijau; Pint bersih; `npm run build` sukses.

**Temuan & perbaikan selama sesi.**

- Alokasi jadwal awal mengisi ruang lebih dulu sehingga seluruh kelas satu angkatan
  jatuh di jam yang sama — tidak bentrok ruang, tapi setiap mahasiswa bentrok jadwal.
  Diperbaiki: counter slot menelusuri 25 slot hari×jam dulu, baru pindah ruang.
  Diverifikasi 0 bentrok ruang dan 0 bentrok mahasiswa.
- `config/app.php` bawaan Laravel mengunci timezone ke UTC sehingga "hari ini"
  meleset 7 jam. Diubah ke `env('APP_TIMEZONE', 'Asia/Jakarta')`.
- Semester aktif demo semula dimulai September (belum berjalan) → presensi kosong.
  Ditambah state factory `berjalan()` yang menjangkarkan kalender ke hari ini.
- **Portal tidak dapat dipakai di mobile.** Sidebar diberi `hidden md:flex` tanpa
  pengganti, sehingga di 375px tidak ada satu pun tautan navigasi yang terjangkau,
  dan topbar meluber ke 501px. Diperbaiki: bottom nav 4 tab + sheet "Lainnya"
  berisi seluruh pohon navigasi (13 item admin tetap terjangkau), pencarian dan
  lonceng disembunyikan di layar sempit, pil semester menyusut jadi kode PDDIKTI.
  Diverifikasi di 375/768/1280: nol geser horizontal, target sentuh ≥44px.
- Sheet navigasi tidak terbuka pada percobaan pertama karena dua `x-show`
  bersarang saling memotong transisi Alpine. Transisi panel diganti animasi CSS.

**Belum dikerjakan (wilayah Fase 1, masih terbuka setelah Sesi 2).**

- Service layer KRS/Penilaian belum ada — data demo ditulis langsung oleh seeder,
  sehingga aturan batas SKS, cek prasyarat, dan kuota belum dieksekusi kode produksi.
- Layar KRS mahasiswa, input nilai, presensi, persetujuan KRS dosen, master akademik,
  PMB, feeder monitor, dan Campus Bridge console belum dibuat — item navigasinya
  tampil nonaktif.
- Transkrip PDF, `NeoFeederClient`, endpoint Bridge, dan SSO belum ada.

**Prioritas sesi berikutnya (Fase 1).**

1. `KrsService` — batas SKS dari IPS, cek prasyarat, kuota atomik (locking),
   gerbang pembayaran, transisi status ter-audit. Layar KRS mahasiswa + antrean
   persetujuan dosen wali.
2. `PenilaianService` — komponen berbobot (validasi total 100), hitung nilai akhir,
   kunci & finalisasi, jalur koreksi ter-audit. Layar input nilai.
3. `PresensiService` — grid 16 pertemuan, sesi QR, aturan kehadiran minimum
   sebagai syarat UAS.
4. `TranskripService` — PDF resmi dengan border guilloché.

---

## Sesi 0 — 2026-07-28

- Paket dokumen perencanaan disusun: CLAUDE.md, KICKOFF-PROMPT.md, DESIGN-PROMPT.md,
  vault modul, dan 32 layar referensi desain.
