# PMB.md — Penerimaan Mahasiswa Baru Daring

Calon mahasiswa mendaftar sendiri: membuat akun, memilih gelombang dan program
studi, mengisi biodata, mengunggah berkas, membayar biaya formulir, mencetak
kartu ujian, melihat jadwal seleksi, dan menerima pengumuman. Panitia
memeriksa berkas, mencatat pembayaran, mengimpor nilai seleksi dari CBT mana
pun, memutuskan kelulusan, dan mengumumkannya. Yang diterima menjadi mahasiswa
lewat jalur daftar ulang yang sudah ada — atau intake pindahan/RPL (#33) bila
gelombangnya jalur pindahan.

Roadmap #32. Keputusan: [ADR-024](DECISIONS.md).

---

## Portal Pendaftar

Portal keempat, di atas guard kelima `pendaftar`.

| | |
|---|---|
| Beranda publik | `/pmb` — gelombang yang terbuka, prodi, biaya, daya tampung. Satu-satunya halaman yang **boleh** diindeks mesin pencari |
| Akun | `/pmb/daftar`, `/pmb/masuk`, `/pmb/lupa-sandi` |
| Portal | `/pmb/portal` — pendaftaran saya, mulai pendaftaran baru, pemberitahuan |
| Satu pendaftaran | `/pmb/portal/{uuid}` — langkah, biodata, berkas, biaya formulir, jadwal, kartu ujian, hasil |
| Akun demo | `calon.draf@demo.test`, `calon.bayar@demo.test`, `calon.verifikasi@demo.test`, `calon.seleksi@demo.test`, `calon.lulus@demo.test`, `calon.tidaklulus@demo.test` · kata sandi `password` |

### Batas dengan portal lain

Akun pendaftar (`pmb_akun`) **bukan identitas kampus**:

- Tidak punya peran atau izin Spatie. Tidak dikenal penyedia SSO (`sso`) dan
  guard API (`api`) — `UserRole` tetap tiga.
- `/masuk` utama tidak pernah mencoba guard `pendaftar`; `/pmb/masuk` tidak
  pernah mencoba tiga guard kampus. Kredensial yang satu tidak berlaku di yang
  lain.
- Satu sesi, satu portal: masuk sebagai pendaftar mengeluarkan sesi
  staf/dosen/mahasiswa di peramban itu, dan sebaliknya.
- `auth:mahasiswa` tidak menerima sesi pendaftar — **termasuk sesudah
  pendaftar itu diberi NIM**. Akun mahasiswa adalah akun terpisah yang dibuat
  daftar ulang; kata sandi awalnya diserahkan bagian akademik, tidak pernah
  dikirim lewat surel.
- Tamu yang membuka `/pmb/*` diarahkan ke `/pmb/masuk`, bukan ke halaman masuk
  kampus yang meminta NIM.

### Akun

| Hal | Bagaimana |
|---|---|
| Daftar | Nama, surel (dikecilkan, unik di `pmb_akun`), kata sandi dengan kebijakan `Password::defaults()` |
| Verifikasi surel | **Wajib** sebelum portal terbuka (`verified` middleware). Tautan bertanda tangan, kedaluwarsa, berisi UUID akun — dan harus dibuka oleh akun itu sendiri; tautan yang diteruskan tidak memverifikasi siapa pun |
| Lupa sandi | Broker `pendaftar` dengan **tabel token sendiri** (`pmb_password_reset_tokens`) — surel calon bisa saja juga surel mahasiswa/staf. Jawaban formulir sama untuk surel terdaftar maupun tidak. Tautan hanya memuat token; surel diketik di formulir, tidak dibawa di URL. Mengikuti tautan atur ulang sekaligus memverifikasi surel |
| Batas laju | Daftar: `PMB_BATAS_DAFTAR_PER_JAM` per IP (bawaan 10). Masuk: `PMB_BATAS_MASUK` percobaan per surel + IP (bawaan 5), kunci terpisah dari halaman masuk utama. Kirim ulang verifikasi & lupa sandi: `PMB_BATAS_SUREL_PER_MENIT` per IP (bawaan 3) |
| Nonaktif | `is_active = false` ditolak dengan pesan yang sama seperti kata sandi salah |

---

## Alur dan status

```
draf ──ajukan──▶ mendaftar ──formulir lunas──▶ verifikasi ──berkas wajib diterima──▶ seleksi
  │                (biaya > 0)                    ▲                                     │
  └──ajukan (gratis)──────────────────────────────┘                                     ▼
                                                     lulus / tidak_lulus ◀── keputusan staf atau ambang
                                                       │
                                     daftar ulang (reguler) / intake #33 (pindahan, RPL)
                                                       ▼
                                                   mahasiswa
```

`batal` dapat dicapai dari mana pun sebelum keputusan. Seluruh perpindahan
lewat `PendaftaranPmbService::pindahkan()`, yang menolak sisi yang tidak
tercantum di `ApplicantStatus::bolehMenujuKe()` dan mencatatnya ke log aktivitas
(`status_changed`) dengan kedua ujungnya.

`draf` baru, dan **tidak dihitung sebagai pendaftar** di mana pun: corong dasbor,
keketatan LKPS, dan skala PDDIKTI per prodi. Akun yang dibuka lalu ditinggalkan
bukan pendaftar. Daftar pendaftar staf menyembunyikannya kecuali difilter.

| Langkah | Siapa | Aturan |
|---|---|---|
| Mulai | Calon | Gelombang `is_daring` dan periodenya berjalan; prodi ditawarkan gelombang itu; pilihan kedua berbeda; satu pendaftaran aktif per akun per gelombang. Nomor `<kode gelombang>-0001`, berurutan, termasuk yang dihapus. **Biaya formulir dibekukan** saat itu |
| Biodata | Calon | Hanya selama `draf`. Lihat *Validasi biodata* |
| Berkas | Calon | Jenis wajib gelombang ∪ tambahan prodi, plus `bukti_bayar` dan `lainnya`. Unggahan ulang jenis yang sama menggantikan yang lama (berkas fisiknya dihapus). Berkas terverifikasi tidak dapat diganti. Sesudah diajukan, hanya berkas yang **dikembalikan** dan bukti bayar yang boleh diunggah |
| Ajukan | Calon | Biodata lengkap, berkas wajib ada, **kuota gelombang** belum penuh. Biodata terkunci sesudahnya |
| Bayar | Kasir | Lihat *Biaya formulir* |
| Periksa berkas | Panitia (`pmb.manage`) | Terima, cabut, atau kembalikan dengan alasan (calon diberi tahu). Berkas wajib terakhir diterima + formulir lunas → `seleksi`, calon diberi tahu kartu ujian siap |
| Kartu ujian | Calon | PDF (`pdf.cetak.kartu-ujian-pmb`, kerangka kartu ujian yang ada) hanya untuk status seleksi ke atas dan formulir lunas; penolakan menyebut alasannya. Berisi nomor pendaftaran (juga sebagai QR), nama, TTL, pilihan prodi, jalur, jadwal seleksi. **Tanpa NIK dan alamat** |
| Seleksi | Panitia | Jadwal per gelombang; nilai **diimpor** |
| Keputusan | Panitia / ambang | Lihat *Kelulusan* |
| Pengumuman | Panitia | Lihat *Pengumuman* |
| Batal | Calon | Sebelum ada keputusan. Boleh mendaftar lagi di gelombang yang sama |

### Validasi biodata

`BiodataPendaftarRequest` meminta apa yang diminta `FeederValidator::mahasiswa()`
— NIK 16 digit, tempat dan tanggal lahir, jenis kelamin (galat di Feeder) dan
nama ibu kandung serta agama (peringatan di Feeder, **wajib** di sini) — plus
alamat yang dikirim `MahasiswaMapper` (jalan, kelurahan, kecamatan,
kabupaten/kota, provinsi; kode pos opsional 5 digit), telepon, NISN opsional 10
digit. Jalur pindahan/RPL mewajibkan PT dan prodi asal, yang kemudian menjadi
isian awal formulir intake #33.

Agama memakai enum `Agama` (kode lokal `1`–`6`, `99`) yang dipetakan ke Feeder
lewat `feeder_mappings` seperti kode lain.

`PmbService::daftarUlang()` kini menyalin seluruh kolom ini ke mahasiswa.
Tes `diterima menjadi mahasiswa` memaku bahwa mahasiswa hasil PMB daring lolos
pemeriksaan biodata `FeederValidator` tanpa satu pun galat atau peringatan
biodata.

### Penanda calon penerima KIP

`is_calon_kip` (+ `nomor_kip` opsional) diisi calon. **Penanda, bukan
keputusan**: tidak memengaruhi seleksi, tidak menerbitkan beasiswa. Panitia
melihat lencananya di daftar dan detail, dan dapat memfilter "Hanya calon KIP".
Nomor KIP dibuang bila penandanya tidak dicentang.

---

## Periode & syarat per gelombang dan prodi

Layar `/admin/pmb/gelombang/{uuid}` (kode gelombang di layar PMB dapat diklik).

| Tingkat | Kolom | Arti |
|---|---|---|
| Gelombang | `is_daring` | Tampil di `/pmb` dan menerima pendaftaran daring. **Bawaan mati** — gelombang lama tidak berubah menjadi formulir publik sesudah pembaruan |
| | `jalur` | Enum `JalurPmb`: reguler, prestasi, RPL, pindahan (`transfer`). RPL dan pindahan diteruskan ke intake #33 |
| | `tanggal_mulai/selesai` | Periode pendaftaran |
| | `biaya_pendaftaran` | Biaya formulir bawaan |
| | `kuota` | Batas pendaftaran yang **diajukan** (bukan draf) di gelombang |
| | `dokumen_wajib` | Jenis berkas wajib semua prodi (kunci `config('berkas.pmb')`) |
| | `keterangan` | Teks untuk calon di beranda |
| Prodi (`pmb_gelombang_prodi`) | — | Prodi yang tidak ada di sini tidak dapat dipilih |
| | `daya_tampung` | Batas yang **diterima** (lulus, daftar ulang, mahasiswa) per prodi per gelombang; ditegakkan `PmbService::luluskan()` di bawah kunci baris gelombang |
| | `biaya_formulir` | Menimpa biaya gelombang |
| | `ambang_lulus` | Hanya dibaca kelulusan otomatis |
| | `dokumen_wajib` | **Ditambahkan** ke daftar gelombang, tidak pernah mengurangi |

Prodi yang sudah dipilih pendaftar tidak dapat dihapus dari gelombang.

---

## Biaya formulir

Bayar manual (ADR-014), dicatat kasir. **Bukan baris `tagihan`** — lihat ADR-024.

- `pmb_pendaftar.biaya_formulir`: nominal yang dibekukan saat pendaftaran dibuat.
- `pmb_pembayaran`: buku besar tersendiri. Aturan yang sama dengan
  `PembayaranService` untuk UKT: tanpa kelebihan bayar, cicilan boleh, status
  lunas **diturunkan** dari baris berstatus berhasil, pembatalan tidak menghapus
  baris (status `refund` + alasan + log `payment_reversed`).
- Calon mengunggah bukti transfer sebagai berkas `bukti_bayar`.
- Layar kasir `/admin/pmb/formulir` (menu KEUANGAN → Biaya Formulir PMB):
  antrean yang menunggu bayar, urut tanggal pengajuan, beserta bukti
  transfernya; riwayat pembayaran terakhir dengan tombol batal. Dapat dibuka
  `keuangan.view` atau `pmb.view`; mencatat butuh `keuangan.manage` atau
  `pmb.manage`.
- **Bagian keuangan hanya dapat membuka bukti bayar**, bukan KTP, KK, atau
  ijazah calon (`BerkasController::pmb`).
- Pembatalan hanya selama pendaftar masih di `mendaftar`/`verifikasi`;
  pembatalan yang membuat belum lunas mengembalikan `verifikasi` → `mendaftar`.
- Formulir gratis (`biaya = 0`) melompati langkah ini.
- Belum dijurnalkan ke integrasi akuntansi (kontak di sana adalah mahasiswa).

---

## Seleksi

### Jadwal

Per gelombang: kegiatan, mulai, selesai, lokasi, keterangan. Tampil di portal
dan tercetak di kartu ujian. Ujiannya sendiri tidak diselenggarakan di sini
(ROADMAP §Kenapa CBT dan LMS tidak akan masuk).

### Impor nilai

CSV dari CBT mana pun dengan kolom berjudul **`nomor_pendaftaran`** dan
**`nilai`** (0–100). Urutan kolom bebas; kolom lain diabaikan. Pemisah koma atau
titik koma (diterka dari baris judul), BOM UTF-8 dan koma desimal diterima.
Paling banyak `PMB_IMPOR_MAKS_BARIS` baris (bawaan 5000).

```csv
nomor_pendaftaran;nama;nilai
PMB-20261-D1-0004;Fajar Nugroho;78,5
```

**Semua atau tidak sama sekali.** Seluruh baris diperiksa dulu — nomor tidak
dikenal di gelombang ini, nomor ganda, nilai bukan angka atau di luar rentang,
pendaftar yang tidak berstatus `seleksi` — dan **satu** baris salah membatalkan
seluruh impor; semua baris salah dilaporkan sekaligus, dengan nomor barisnya.
Ini aturan validasi pra-kirim Feeder (CLAUDE.md §5) dipakai untuk arah masuk.

### Kelulusan

Diputuskan staf (layar PMB, seperti sebelumnya). Untuk pendaftar daring,
keputusan hanya boleh sesudah ia masuk `seleksi` — pendaftar yang diinput
panitia tetap boleh diputuskan dari tahap mana pun. Daya tampung prodi
ditegakkan; keputusan dapat dikoreksi (lulus ↔ tidak lulus) sampai daftar
ulang, dan koreksi membebaskan kursi.

**Kelulusan otomatis** — `PMB_KELULUSAN_OTOMATIS=true`, **bawaan mati**.
Sesudah setiap impor: urut nilai tertinggi, coba pilihan pertama lalu kedua;
lulus bila nilai ≥ ambang prodi itu dan kursi masih ada. Tidak lulus hanya bila
**setiap** pilihannya punya ambang dan nilai di bawah semuanya. Prodi tanpa
ambang, atau yang gagal hanya karena kursi penuh tanpa pilihan lain berambang,
dibiarkan untuk staf. Keputusan otomatis lewat `PmbService` yang sama — log,
daya tampung, dan pengumuman berlaku sama. Menyalakannya **tidak** mengumumkan
apa pun.

---

## Pengumuman

Memutuskan dan mengumumkan terpisah. Sebelum gelombang diumumkan, calon yang
sudah diputuskan tetap melihat status "Seleksi" dan pesan "menunggu
pengumuman" (`PmbPendaftar::statusTerlihat()`), dan tidak menerima surel apa
pun. Panitia menekan **Umumkan Hasil** di layar gelombang:

- `pmb_gelombang.diumumkan_at` diisi (sekali, dicatat `results_announced`).
- Setiap pendaftar daring yang sudah lulus/tidak lulus diberi tahu —
  catatan di portal dan surel (`HasilSeleksiDiumumkan`).
- Keputusan yang diambil **sesudah** pengumuman dikirim saat diambil.
- Setiap hasil berbeda dikirim **sekali** per pendaftar
  (`Notifier::kirimSekali`, kunci `pmb.hasil.{id}.{status}`): menekan Umumkan
  dua kali tidak mengirim ulang; koreksi lulus ↔ tidak lulus mengirim hasil
  yang baru.

---

## Diterima → mahasiswa

Tidak ada jalur baru. Panitia menekan **Daftar Ulang** di layar PMB
(`PmbService::daftarUlang()`: NIM dari pola kampus, akun mahasiswa, tagihan
awal, biodata disalin). Pendaftar gelombang **pindahan/RPL** diarahkan ke
intake #33 (`/admin/pindahan/baru?pendaftar=…`), yang memakai `daftarUlang()`
yang sama di dalam transaksinya dan kini berisi PT/prodi asal yang diketik
calon. Calon menerima pemberitahuan berisi NIM (tanpa kata sandi), dan
portalnya menampilkan NIM serta petunjuk masuk portal akademik.

---

## Pemberitahuan

Kategori **Kemahasiswaan** (wajib), dikirim ke `PmbAkun` — catatan di portal
(5 terakhir di beranda portal) dan surel. Bukan kategori sendiri karena daftar
kategori juga layar preferensi mahasiswa dan staf, yang tidak punya apa pun
untuk diatur tentang PMB.

| Kejadian | Notifikasi |
|---|---|
| Akun dibuat / kirim ulang | `VerifikasiSurelPendaftar` (surel saja) |
| Lupa sandi | `AturUlangSandiPendaftar` (surel saja) |
| Pembayaran formulir dicatat | `PembayaranFormulirDiterima` |
| Berkas dikembalikan | `BerkasDikembalikan` (dengan alasannya) |
| Masuk seleksi | `SiapSeleksi` |
| Hasil diumumkan / diputuskan sesudah pengumuman | `HasilSeleksiDiumumkan` |
| Daftar ulang selesai | `MenjadiMahasiswa` (NIM, tanpa kata sandi) |

---

## Konfigurasi

| Kunci `.env` | Bawaan | |
|---|---|---|
| `PMB_KELULUSAN_OTOMATIS` | `false` | Kelulusan berdasarkan ambang prodi sesudah impor |
| `PMB_BATAS_DAFTAR_PER_JAM` | `10` | Pembuatan akun per IP per jam |
| `PMB_BATAS_MASUK` | `5` | Percobaan masuk per surel + IP |
| `PMB_BATAS_SUREL_PER_MENIT` | `3` | Kirim ulang verifikasi / lupa sandi per IP |
| `PMB_IMPOR_MAKS_BARIS` | `5000` | Baris CSV nilai |

Surel butuh `MAIL_*` dan **worker antrean** (NOTIFIKASI.md §Prasyarat
Operasional) — tanpa worker, calon tidak pernah menerima tautan verifikasi dan
tidak dapat melanjutkan pendaftaran.

---

## Belum

- Biaya formulir belum dijurnalkan ke integrasi akuntansi.
- Pembayaran formulir lewat payment gateway (ADR-014: opsional).
- Calon belum dapat mengubah surel akunnya sendiri; panitia juga belum punya
  layar untuk menonaktifkan akun pendaftar (kolom `is_active` sudah ada).
- `jalur_masuk` mahasiswa hasil PMB belum diisi dari jalur gelombang (pemetaan
  `jalur_masuk` Feeder memakai label lain).
