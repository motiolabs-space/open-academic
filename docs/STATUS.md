# STATUS.md — Catatan Progres

> Diperbarui di akhir setiap sesi pengembangan (lihat CLAUDE.md §14).

---

## Sesi 62 — 2026-10-27 · Klaim mahasiswa lampau

Roadmap #44b, branch `feature/feeder-klaim-lampau`, satu PR. Berjalan paralel
dengan tarif per SKS #41 (Sesi 60) dan monitoring ruang #42 (Sesi 61); konflik
teks di berkas ini, `ROADMAP.md`, dan `DECISIONS.md` wajar. Keputusan: ADR-032.

Mahasiswa lama dapat tercatat di PDDIKTI tanpa muncul di Feeder kampus, jadi
adopsi #44 tidak menemukannya dan sinkronisasi pertama akan mendaftarkannya
ulang. Kini ada langkah sebelum adopsi:

- **Baca**: `FeederKlaimLampau::baca()` membaca `GetListDaftarLampau` per prodi
  + periode masuk (paged, penolakan Feeder menghentikan proses seperti adopsi)
  dan menyimpan laporan `feeder_past_students` tanpa NIK. Setiap baris dinilai
  dengan **pencocok adopsi yang sama**, kini `PencocokMahasiswaFeeder` (dipindah
  dari `FeederAdopsi` tanpa perubahan aturan): cocok, ambigu (alasan ADR-020),
  atau tidak ada di SIAKAD.
- **Pilih & periksa**: staf mencentang baris di `/admin/feeder/lampau`
  (`feeder.sync`). Validasi pra-kirim membaca ulang PDDIKTI dan melaporkan
  **semua** baris yang akan gagal sekaligus — tanpa mahasiswa lokal, ambigu
  tanpa alasan, kunci klaim tidak lengkap, tidak lagi terdaftar, registrasi
  dipegang mahasiswa lain — dan tidak mengirim apa pun bila ada satu saja.
- **Klaim**: `KlaimMahasiswaLampau` per baris; setiap panggilan satu baris
  buku besar (`entity = mahasiswa_lampau`, `direction = claim`). Yang sukses
  tidak dikirim ulang (diklaim dua kali → *dilewati*), yang ditolak dicatat
  dengan pesan dan jumlah percobaan, tampil di barisnya, dan dapat dicoba lagi.
  Baris yang diklaim menyimpan pelaku, waktu, alasan (Log Aktivitas) dan
  bertahan saat daftar dibaca ulang.
- **Mengalir ke #44**: sesudah klaim, adopsi dijalankan untuk prodi dan
  periode itu. Yang cocok teradopsi (`feeder_id`, `feeder_registrasi_id`, baris
  buku besar `adopt`; dorongan berikutnya *Skipped*); yang ambigu menunggu
  keputusan di layar adopsi. Klaim sendiri tidak pernah menautkan.
- **Validator**: `klaim_lampau_belum_diadopsi` menahan biodata mahasiswa yang
  sudah diklaim tetapi belum ber-`feeder_id` dan tidak dipisahkan.
- **Fake**: `FakeFeederClient::mahasiswaLampau()` memindahkan baris yang
  diklaim ke dua daftar adopsi seperti layanan sungguhan; `_tolak` pada fixture
  dan `tolakKlaim()` untuk penolakan. Fixture `mahasiswa-lampau.json`.

**Tes baru:** FeederKlaimLampauTest 35, PencocokMahasiswaFeederTest 4,
SmokeLayarTest 1. FeederAdopsiTest tetap hijau tanpa perubahan.

### Belum

Nama aksi, filter, dan kunci klaim (`config/feeder.php` → `adopt.lampau`)
belum diverifikasi terhadap build Feeder sungguhan. Mahasiswa lampau yang belum
dimigrasi ke SIAKAD tidak dapat diklaim dari sini. Tidak ada perintah artisan
(klaim butuh pilihan orang).

---

## Sesi 61 — 2026-10-27 · Monitoring pemakaian ruang

Roadmap #42, branch `feature/monitoring-ruang`, satu PR. Berjalan paralel
dengan #41 (Sesi 60) dan #44b (Sesi 62); konflik teks di berkas ini,
`ROADMAP.md`, `MODUL.md`, dan `DECISIONS.md` wajar. Keputusan: ADR-031.
Tanpa migrasi.

- **Laporan, bukan pemesanan.** `PemanfaatanRuangService::laporan()` membaca
  slot `jadwal_kuliah` semester terpilih dan ruang `jadwal_ujian` **terbit**,
  lalu menelusuri semester per tanggal: libur kampus terbit dari kalender #35
  keluar dari jam tersedia dan terpakai, libur satu prodi hanya meniadakan
  kuliah prodi itu, ujian hanya pada tanggalnya. Hari biasa dihitung sekali per
  hari-pekan. Enam kueri berapa pun jumlah ruang (dipaku tes).
- **Angka per ruang** (jam per pekan, rata-rata semester): tersedia (jam
  operasional `config/ruang.php`, bawaan Senin–Jumat 07.00–17.00), terpakai
  (gabungan rentang — kuliah dan ujian yang menumpuk dihitung sekali), kosong,
  di luar jam operasional (terpisah, tidak menggelembungkan persen), okupansi
  kursi (peserta KRS disetujui × menit / kapasitas × menit; ujian mengisi kursi
  berurutan per ruang seperti kartu ujian), jumlah slot dan sesi.
- **Layar** AKADEMIK → Pemakaian Ruang (`/admin/pemanfaatan-ruang`): filter
  semester, gedung, jenis, urutan (gedung, paling penuh, paling kosong); tabel
  per ruang; peta panas hari × jam per ruang atau rerata semua ruang; daftar
  kelebihan peserta (kuliah dan ujian) dan jarang dipakai (di bawah
  `RUANG_AMBANG_JARANG`, bawaan 25 %); ekspor CSV. Pencarian ruang kosong
  `/admin/pemanfaatan-ruang/ruang-kosong` per hari (jadwal mingguan) atau per
  tanggal (ditambah ujian terbit dan libur), kapasitas minimum, terkecil yang
  muat lebih dulu, dengan daftar ruang yang terpakai dan oleh apa — hanya baca.
- **Izin**: `ruang.view` baru untuk BAAK dan pimpinan, plus peran staf baru
  `sarpras` (`master.view`, `ruang.view`, helpdesk). Policy `RuangPolicy::
  viewUtilization`. Instalasi berjalan: jalankan ulang `RolePermissionSeeder`.
- `Format::daftarHari()` baru ("Senin–Jumat"); enam variabel `RUANG_*` di
  `.env.example`.

**Tes baru:** PemanfaatanRuangTest 20 (jam terpakai kuliah + ujian dan
tumpukannya, okupansi dan pengisian kursi ujian, kelebihan peserta, di luar jam,
libur kampus/prodi/draf/staf, peta panas, urutan dan ambang jarang, filter,
jumlah kueri tetap, ruang kosong per hari dan per tanggal, CSV, layar tiga
peran, validasi, policy); SmokeLayarTest 5 baris anggaran.

### Belum

Kuliah diasumsikan berjalan setiap pekan semester, termasuk pekan ujian —
model jadwal belum mengenal pekan tanpa kuliah. Ujian di luar rentang tanggal
semester tidak dihitung. Jam operasional satu untuk seluruh kampus, belum per
gedung. Sidang tugas akhir (`tugas_akhir_ujian.ruang_id`) dan kegiatan kalender
belum ikut sebagai pemakai ruang.

---

## Sesi 60 — 2026-10-27 · Tarif per SKS dan per mata kuliah

Roadmap #41, branch `feature/keuangan-tarif-per-sks`, satu PR. Berjalan paralel
dengan #42 dan #44b; konflik teks di berkas ini, `ROADMAP.md`, dan
`DECISIONS.md` wajar. Keputusan: ADR-030. Rincian: `docs/MODUL.md` §Tarif per
SKS / per mata kuliah.

- **Komponen** pindah dari konstanta controller ke enum `KomponenTarif`, dengan
  dua anggota baru yang ditagih dari KRS: `per_sks` (tarif satu SKS, matriks
  seperti baris lain) dan `mata_kuliah` (`tarif.mata_kuliah_id`, nominal
  tetap). `TarifResolver::untuk()` kini hanya komponen tetap, jadi penerbitan
  massal tidak berubah; `perSks()` dan `perMataKuliah()` baru, `rincian()`
  memuat keduanya untuk simulator.
- **Penagihan**: `TagihanKrsService::sinkronkan()` dipanggil
  `KrsService::putuskan()` pada setiap persetujuan. Membandingkan KRS dengan
  baris KRS di tagihan semester dan menulis selisihnya saja; idempoten.
  Kampus tanpa tarif per SKS/per MK: tidak ada tagihan yang muncul.
- **Revisi KRS** — yang ternyata belum ada sama sekali: KRS disetujui bersifat
  final (`Disetujui → []`), dan "masa perubahan KRS" hanya memperpanjang masa
  isi untuk draf. Kini `bukaRevisi()` (tombol di portal mahasiswa) membuka
  KRS disetujui ke draf selama `krsDibuka()`; persetujuan ulang menagih
  selisihnya. Konsekuensinya diterima: selama revisi, mahasiswa tidak berada
  di daftar kelas (yang membaca KRS disetujui).
- **Pembatalan** dijawab baris koreksi (`koreksi_atas_item_id`): negatif
  sebelum batas `PAYMENT_KRS_BATAS_PENGURANGAN`, Rp0 beralasan sesudahnya.
  Dicocokkan per mata kuliah — pindah kelas tidak menagih ulang; dibatalkan
  sesudah batas lalu diambil lagi tidak menagih dua kali.
- **Gerbang KRS** diukur terhadap komponen tetap (`pokokGerbangKrs()`,
  potongan dibagi proporsional; `tagihan.nominal_krs` baru supaya tagihan tanpa
  baris KRS tidak memerlukan kueri tambahan).
- **Akuntansi**: `PenjurnalanService::tagihanDisesuaikan()` — jurnal per baris.
  Notifikasi `TagihanDisesuaikan` ke mahasiswa.
- **Demo**: Sistem Informasi menagih SPP Tetap + Rp175.000/SKS + dua praktikum
  Rp300.000; 15 tagihan demo sudah memuat baris KRS, 9 KRS masih menunggu wali.

**Tes baru:** TarifPerSksTest 36 (resolusi, penerbitan dari KRS, revisi
dalam/luar batas, batas pengurangan, gerbang, beasiswa/kelebihan bayar/denda,
jurnal, layar tarif).

### Belum

Perubahan potongan beasiswa akibat penyesuaian tidak dijurnalkan (celah yang
sama dengan `BeasiswaService` sesudah terbit). Baris KRS hasil konversi
KKN/MBKM tidak memicu penagihan sendiri. Revisi tidak punya layar staf.

---

## Sesi 59 — 2026-10-27 · KKN/PKL terkelola dan tahap proposal tugas akhir

Roadmap #37 dan #38, branch `feature/kkn-proposal-ta`, satu PR dengan satu
commit per butir. Berjalan paralel dengan kalender peristiwa #35 dan SDM
#39/#40; konflik teks di berkas ini, `ROADMAP.md`, dan `DECISIONS.md` wajar.
Keputusan: ADR-029.

### #37 — KKN / PKL sebagai kegiatan terkelola

Peserta KKN/PKL adalah baris `aktivitas_mahasiswa` biasa — jenis baru `kkn`
dan `pkl`, atau `membangun_desa`/`magang` bila kampus menjalankannya sebagai
MBKM — sehingga mapper aktivitas, anggota, bimbing, dan konversi #22/#23
mengirimnya tanpa entitas Feeder baru. Empat tabel baru hanya untuk yang tak
punya tempat di baris aktivitas: periode (`kegiatan_lapangan`, aturan kelompok
dan bobot nilai disalin dari `config/kegiatan_lapangan.php`), lokasi desa/
instansi dengan kuota kelompok, kelompok dengan DPL dan pembimbing mitra, dan
logbook (FK ke aktivitas). `KegiatanLapanganService` menulis ulang judul,
mitra, wilayah, tanggal, dan DPL ke setiap baris aktivitas tiap kali periode,
lokasi, atau kelompok berubah.

- **Kelompok**: manual, atau otomatis lewat `PembentukKelompok` — urut prodi →
  jenis kelamin → NIM lalu dibagikan bergiliran, ukuran diratakan; aturan
  campur yang dimatikan memartisi lebih dulu. Lokasi diisi sesuai kuota, sisa
  kelompok tanpa lokasi.
- **Nilai**: DPL dan mitra (mitra bukan akun; nilainya diisi DPL atau staf),
  akhir hanya bila semua nilai berbobot ada.
- **Konversi per kelas**: kelas mata kuliah KKN/PKL memilih peserta prodinya,
  menulis atau menempelkan baris KRS bertanda aktivitas dengan nilai final,
  memverifikasi aktivitas, mengisi `sks_konversi`, menghitung ulang IPS/IPK.
  Sesudahnya nilai kegiatan terkunci.
- **Yang tersingkap**: belum ada satu pun kode yang menulis baris KRS
  bertanda aktivitas sejak #23 — konversi MBKM hanya bisa dikirim, tidak bisa
  dibuat. Dan baris seperti itu, bila ada di kelas yang juga dinilai dosen,
  akan menghalangi finalisasi sebagai baris kosong lalu ditimpa dari komponen
  nol. `PenilaianService` kini mengeluarkannya dari lembar nilai.
- **Layar**: KEMAHASISWAAN → KKN / PKL (staf, `mahasiswa.view`/`.manage`),
  Bimbingan KKN / PKL (dosen, per kelompok yang dibimbing), KKN / PKL
  (mahasiswa: daftar mandiri bila dibuka, kelompok, logbook). Lampiran logbook
  di `/berkas/kegiatan-lapangan/{logbook}` di balik policy.
- **Demo**: `KegiatanLapanganSeeder` — satu periode KKN di semester aktif,
  tiga desa, 18 peserta dalam tiga kelompok campuran, dosen1 membimbing
  Kelompok 01 (dengan tiga logbook), mahasiswa1 ikut. Belum dinilai: mengisi
  nilai dan mengonversinya adalah demonya.

### #38 — Tahap dokumen proposal tugas akhir

Saklar per prodi `wajib_proposal_ta` (bawaan mati, di layar master prodi),
**dikunci pada tugas akhir saat judul diajukan** (`tugas_akhir.wajib_proposal`)
— tugas akhir yang sudah berjalan tetap sah di bawah aturan lamanya, dan
kampus yang tidak menyalakannya tidak melihat apa pun berubah.

- **Alur**: mahasiswa mengunggah dokumen (PDF lewat `BerkasService`) dengan
  calon pembimbing → calon pembimbing (portal dosen) atau staf tugas akhir
  menelaah: revisi (catatan wajib) → versi berikutnya, diterima → judul dapat
  disetujui lalu pembimbing dan seminar proposal seperti dulu, ditolak →
  tugas akhir ditutup dan slot mahasiswa bebas.
- **Riwayat**: `tugas_akhir_proposal` dan `tugas_akhir_proposal_telaah` hanya
  bertambah; judul tersimpan per versi; berkas lama tidak dihapus.
- **Gerbang**: `TugasAkhirService::setujuiJudul()` menolak judul
  ber-`wajib_proposal` tanpa versi diterima.
- **Layar**: kartu proposal di tugas akhir mahasiswa dan rincian staf, antrean
  "Proposal menunggu telaah Anda" dan halaman telaah di portal dosen, berkas di
  `/berkas/proposal-ta/{proposal}` di balik `ProposalPolicy`. Notifikasi ke
  calon pembimbing saat diunggah, ke mahasiswa saat ditelaah.
- **Demo**: `ProposalTugasAkhirSeeder` — prodi dengan mahasiswa bebas
  terbanyak mewajibkan proposal; satu mahasiswa di versi 2 menunggu telaah
  dosen1 (versi 1 diminta revisi), satu diterima dan menunggu persetujuan judul.

**Tes baru:** KegiatanLapanganTest 37, ProposalTugasAkhirTest 16, SmokeLayarTest 2
(anggaran kueri lima layar KKN/PKL dan empat layar proposal).

### Belum

Lokasi belum dapat disunting dari layar (service-nya sudah); peserta belum
dapat membatalkan pendaftaran mandirinya sendiri; ketua kelompok belum dikirim
sebagai peran *ketua* ke PDDIKTI karena setiap peserta adalah aktivitas
tersendiri. Proposal: calon pembimbing tidak dapat diganti di tengah telaah
(hanya pada versi berikutnya), dan belum ada batas waktu telaah.
Tindak lanjut: periode KKN/PKL belum tampil di kalender peristiwa (#35,
`KalenderService`); layak menjadi sumber periode berikutnya di sana.

---

## Sesi 58 — 2026-10-27 · Riwayat kepegawaian lengkap dan buku besar angka kredit

Roadmap #39 dan #40, branch `feature/sdm-riwayat-angka-kredit`, dua commit
(#39 lebih dulu karena #40 membacanya). Berjalan paralel dengan Sesi 57
(kalender #35) dan Sesi 59 (KKN/PKL #37, proposal TA #38); konflik teks di
berkas ini, `ROADMAP.md`, `MODUL.md`, dan `DECISIONS.md` wajar. Keputusan:
ADR-028. Dokumen: `docs/KEPEGAWAIAN.md`.

### #39 — empat belas riwayat, satu alur validasi

Delapan tabel baru (diklat, pengalaman kerja, studi lanjut / tugas belajar,
tugas dinas, jabatan struktural, inpassing, kontrak, pensiun) dan layar untuk
enam yang tabelnya sudah ada. Kelimanya membawa kolom validasi yang sama;
bawaannya *diajukan*, dan enam tabel lama di-backfill *disetujui*. Dosen
mengajukan di `/dosen/kepegawaian` (tab per riwayat, dokumen lewat
`BerkasService`), staf menyetujui atau menolak dengan alasan di
`/admin/kepegawaian/validasi` atau dari berkas per dosen
`/admin/dosen/{uuid}/kepegawaian`; isian staf langsung disetujui. Mutasi,
kontrak, dan pensiun diisi staf saja. Satu `JenisRiwayat`, satu
`FormulirRiwayat`, satu policy, satu FormRequest untuk keempat belasnya.

Aturan hanya di empat tempat: pangkat berlaku = disetujui ber-TMT terakhir;
mutasi tidak disunting/dihapus (koreksinya mutasi baru); tugas belajar
disetujui membebaskan BKD selama beririsan dengan semester (`wajibMelapor()`
dan `wajibBkd()`; izin belajar tidak); pensiun disetujui menutup akun pada
TMT-nya lewat `halanganNonaktifDosen()` — yang masih mengampu atau mewalikan
dibiarkan aktif dan dilaporkan, dicoba lagi tiap hari oleh
`openacademic:tutup-akun-pensiun`. Ekspor SISTER membuka penghargaan & sanksi,
bahasa, organisasi, diklat, inpassing, jabatan struktural, studi lanjut,
pengalaman kerja — hanya baris disetujui; catatan "belum ada layar" pada
pangkat dan mutasi dilepas.

### #40 — buku besar Kum A–E, bukan penilaian

`angka_kredit_dosen` per butir per unsur dalam perseratus, tanpa total
tersimpan. Kum B–E dari BKD **disahkan** (dikreditkan di transaksi
pengesahan), Kum A dari ijazah S2/S3 portofolio, ditambah konversi semester
lama dan penyesuaian beralasan oleh staf. Kinerja yang mulai sebelum 2026:
SKS × 4; sesudahnya faktor kampus bertanda *perkiraan*. DUPAK = baris sesudah
TMT jabatan berlaku; batas D/E 10 % pada rekap sebagai titik tetap terhadap
total sesudah pembatasan. Rekap per dosen dan per jenjang, kesiapan usulan
(selisih AK dan bagian Kum C, syarat di `config/angka_kredit.php`), lembar
penyetaraan .xlsx dari `LembarKerjaXlsx` (ext-zip, tanpa pustaka spreadsheet;
NUPTK dan TMT sebagai sel teks). `dosen.nuptk` baru, dapat diisi di form dosen
dan di layar buku besar. Layar `/admin/angka-kredit`, `/admin/angka-kredit/{uuid}`,
`/dosen/angka-kredit`; `openacademic:susun-angka-kredit`. Tanpa alur tim
penilai, plotting, atau TTE.

### Yang tersingkap

- `Dosen::wajibBkd()` hanya memeriksa Serdos dan mengabaikan `BKD_WAJIB=semua`,
  sementara `BkdService::wajibMelapor()` menghormatinya — layar BKD dosen dan
  daftar kampus dapat berbeda pendapat. Tidak diubah di sini selain menambahkan
  aturan tugas belajar ke keduanya.
- Dokumen portofolio G7 (ijazah, SK jabatan, sertifikat) tersimpan tetapi tidak
  punya rute unduh sama sekali; rute berkas umum hanya `auth:staff,mahasiswa`.
  Dokumen riwayat #39 punya rutenya sendiri (`/berkas/kepegawaian/...`).
- Tiga riwayat G7 belum melewati alur validasi, jadi ijazah yang diketik dosen
  langsung menjadi Kum A dan langsung terbaca di ekspor SISTER.

### Demo

`RiwayatKepegawaianSeeder` (sesudah `MutuSeeder`): dosen pertama mendapat
seluruh riwayat kecuali pensiun — dua pangkat, inpassing, jabatan struktural di
LPM, tugas belajar S3 2015–2019, dua diklat, pengalaman kerja, tugas dinas,
penghargaan, organisasi, bahasa, keluarga, kontrak, dua mutasi. Pensiun pada
dosen terakhir, TMT bulan depan. Antrean berisi tiga ajuan dan satu yang ditolak
beralasan. `AngkaKreditSeeder`: NUPTK untuk semua dosen demo, sembilan baris
konversi dari empat semester sebelum 2026 dan satu penyesuaian negatif untuk
dosen pertama (Kum D 72,00 terpangkas menjadi 34,66; Kum C kurang untuk Guru
Besar), lalu menyusun buku besar seluruh dosen — enam dosen masuk lembar
penyetaraan. `migrate:fresh --seed` dicoba di MariaDB 10.4 lokal. SmokeLayarTest
memaku anggaran kueri enam layar baru (berkas kepegawaian 25/29, antrean 28,
buku besar 12/10/6; lembar penyetaraan memakai rekap layar, tidak menghitung ulang).

**Tes baru:** RiwayatKepegawaianTest 47, AngkaKreditTest 40; EksporSisterTest
diperbarui untuk katalog baru.

### Belum

Tiga riwayat G7 di bawah alur validasi; notifikasi ke dosen saat ajuan
diputuskan; riwayat tenaga kependidikan; rute unduh dokumen portofolio G7;
pengali predikat kinerja tahunan pasca-2026 (faktor "sesudah" masih perkiraan).

---

## Sesi 57 — 2026-10-27 · Kalender akademik: satu lapisan baca, libur yang tidak punya pemilik, feed iCalendar

Roadmap #35, branch `feature/kalender-peristiwa`, dari `main` sesudah #32/#34.
Berjalan paralel dengan sesi SDM #39/#40 dan KKN/PKL #37 + proposal TA #38;
konflik teks di berkas ini, `ROADMAP.md`, dan `DECISIONS.md` wajar. Keputusan:
ADR-027.

### Lapisan baca, bukan salinan

`KalenderService` menyatukan tanggal dari pemiliknya pada saat dibaca:
perkuliahan, KRS, perubahan KRS, dan nilai dari `tahun_akademik` (kolom yang
sama dengan `krsDibuka()`/`penilaianDibuka()`), masa UTS/UAS per prodi dari sesi
**terbit** #34 (satu kueri ber-`GROUP BY`, rentang utuh walau melewati batas
bulan), EDOM yang aktif, yudisium sebagai jumlah lulusan per hari per prodi
(tanpa nama), wisuda, dan gelombang PMB (staf saja). Mengubah tanggal di layar
sumber langsung terlihat; tabel kalender tidak pernah menyimpan tanggal turunan.

### Peristiwa yang tidak punya pemilik

Tabel `kalender_peristiwa` hanya untuk libur akademik, kegiatan kampus, dan
batas administrasi: judul, rentang, sasaran (semua/mahasiswa/dosen/staf), prodi
opsional, draf → terbit, `HasLogAktivitas`, hapus lunak. Layar BAAK
`/admin/kalender/peristiwa` di bawah izin baru `kalender.manage` (peran `baak`;
instalasi berjalan perlu `db:seed --class=RolePermissionSeeder` seperti
biasa). Jenis turunan ditolak di FormRequest dan service.

### Tiga portal, satu aturan pembaca

`/admin/kalender`, `/dosen/kalender`, `/mahasiswa/kalender`: tampilan bulan
(Senin–Minggu, hari libur ditandai) dan daftar, filter jenis, sasaran, dan prodi.
Dosen melihat yang untuk dosen, mahasiswa, dan semua; mahasiswa yang untuk
mahasiswa dan semua, **dipaku** ke prodinya apa pun isi URL-nya, plus sesi ujiannya
sendiri (berjam, berruang duduk); dosen sesi yang diampu atau diawasi. Draf hanya
untuk staf ber-`kalender.manage`. Peristiwa manual dapat langsung diubah dari
daftar; turunan menyebut layar sumbernya.

### Langganan iCalendar

Tautan per orang `/kalender/{token}.ics` untuk Google/Outlook: token 48 karakter,
hanya SHA-256-nya di `calendar_feed_tokens`, URL tampil sekali; buat ulang
mencabut yang lama, cabut tanpa mengganti tersedia, feed berhenti bila akun
dinonaktifkan. Isinya tanpa nama, NIM, atau surel, tanpa draf; 3 bulan ke
belakang sampai 12 bulan ke depan, seharian sebagai `VALUE=DATE`, sesi berjam
dalam UTC, baris dilipat per 75 oktet tanpa memotong UTF-8.

### Libur di jadwal ujian

Sesi pada libur terbit (semua prodi atau prodi kelasnya; libur khusus staf tidak
dihitung) mendapat `BentrokJadwal::libur()` — peringatan, **bukan** penghalang —
di formulir, laporan bentrok, dan detail; penyusun massal melewati hari libur.
Gerbang KRS, nilai, pertemuan, dan pengingat sengaja tidak disentuh (ADR-027).

### Demo

`KalenderSeeder` (sesudah `JadwalUjianSeeder`) menanam delapan peristiwa terbit
dan satu draf di semester aktif: libur nasional, cuti bersama, dies natalis,
orientasi prodi Informatika (mahasiswa), sosialisasi MBKM yang mengapit hari ini
(mahasiswa), rapat kerja (staf), batas cuti akademik (mahasiswa), batas unggah
RPS (dosen), dan libur akhir tahun berstatus draf. Liburnya di luar pekan UTS,
jadi UTS demo tetap terbit tanpa peringatan — SmokeLayarTest memakunya. Dicoba
pada basis data demo utuh lewat `artisan serve`: ketiga portal 200 di kedua
tampilan, feed mahasiswa demo 20 acara tanpa NIM, nama, atau surel.

**Tes baru:** KalenderTest 21 (agregasi tiap sumber termasuk tanggal dan jumlah
yang dibaca balik, peristiwa manual + audit, filter per portal, policy, ICS dan
pencabutannya, pelipatan baris, peringatan libur dan penyusun massal),
SmokeLayarTest 6 (anggaran kueri layar kalender ketiga portal dan layar kelola —
mahasiswa 25, dosen 26, staf 18 — plus isi kalender demo). Peringatan libur
menambah satu kueri pada `/admin/jadwal-ujian`; anggarannya tidak berubah.

### Belum

Tidak ada pengingat/notifikasi untuk peristiwa manual; libur nasional dan cuti
bersama masih diketik tangan per tahun, belum diimpor; `MODUL.md` belum memuat
layar kalender (sama seperti jadwal ujian #34).

---

## Sesi 56 — 2026-10-26 · PMB daring: calon mendaftar sendiri, portal keempat

Roadmap #32, branch `feature/pmb-daring`. Berjalan paralel dengan sesi #34
(jadwal ujian) dan paket kecil #45/#27b/OpenAPI; konflik teks di berkas ini,
`ROADMAP.md`, dan `DECISIONS.md` wajar. Keputusan: ADR-024. Dokumen:
`docs/PMB.md`.

### Akun yang bukan identitas kampus

Guard kelima `pendaftar` di atas tabel `pmb_akun` — bukan kolom kata sandi di
`pmb_pendaftar`. Halaman masuk sendiri (`/pmb/masuk`), verifikasi surel wajib
sebelum portal terbuka, lupa sandi lewat broker dengan tabel token sendiri,
batas laju per IP untuk daftar dan kirim surel, per surel + IP untuk masuk.
Tidak dikenal SSO dan API; `UserRole` tetap tiga. `/masuk` utama tidak mencoba
guard ini, dan masuk di satu sisi mengeluarkan sesi sisi lain. Sesi pendaftar
tetap tidak dapat membuka `/mahasiswa` bahkan sesudah NIM terbit — itu akun
lain, kata sandinya dari bagian akademik. Tamu `/pmb/*` diarahkan ke halaman
masuk PMB.

### Alur

`draf` (status baru, di luar setiap hitungan pendaftar) → ajukan → bayar
formulir → verifikasi berkas → seleksi → kartu ujian → pengumuman. Gelombang
baru terbuka daring bila staf menyalakan `is_daring`; prodi yang ditawarkan,
daya tampung, biaya formulir, ambang, dan berkas tambahan diatur per prodi di
layar gelombang. Biodata divalidasi seperti `FeederValidator` (nama ibu dan
agama yang di sana peringatan, di sini wajib) dan `daftarUlang()` kini
menyalin seluruhnya, termasuk agama, orang tua, dan alamat lengkap — mahasiswa
hasil PMB daring lolos pemeriksaan biodata Feeder tanpa satu isu pun.

### Biaya formulir bukan `tagihan`

Penyimpangan dari rancangan yang diminta, dicatat di ADR-024: setiap invarian
`tagihan` menganggap pemiliknya mahasiswa (unik per mahasiswa per semester,
denda, pengingat, gerbang KRS, kontak akuntansi). Buku besar `pmb_pembayaran`
dengan aturan `PembayaranService`, dicatat kasir di layar baru KEUANGAN →
Biaya Formulir PMB. Bagian keuangan hanya dapat membuka bukti bayar calon.

### Seleksi dan pengumuman

Jadwal seleksi per gelombang, tercetak di kartu. Nilai diimpor dari CSV CBT mana
pun (`nomor_pendaftaran`, `nilai`; koma atau titik koma, BOM, koma desimal),
seluruh baris diperiksa dulu dan satu baris salah membatalkan semuanya.
Kelulusan otomatis per ambang prodi (`PMB_KELULUSAN_OTOMATIS`, bawaan mati).
Keputusan tidak terlihat calon sampai gelombang diumumkan; tiap hasil dikirim
sekali per calon, koreksi dikirim ulang. Pendaftar daring baru dapat diputuskan
sesudah masuk seleksi; pendaftar yang diinput panitia tetap seperti dulu.
Pindahan/RPL diteruskan ke intake #33, yang kini terisi PT/prodi asal dari
calon. Penanda calon penerima KIP dengan filter di daftar staf.

### Yang tersingkap

- `jalur` gelombang adalah string bebas tanpa enum — kini `JalurPmb`.
- `PmbService::daftarUlang()` tidak menyalin agama, nama orang tua, dan alamat
  rinci; mahasiswa hasil PMB selalu berangkat dengan peringatan Feeder.
- Verifikasi berkas di layar PMB hanya membalik bendera; kini lewat service yang
  sekaligus memeriksa kesiapan seleksi.

### Demo

`PmbSeeder` menambah gelombang daring terbuka hari ini (tiga prodi, dua jadwal
seleksi) dengan enam akun `calon.{draf,bayar,verifikasi,seleksi,lulus,tidaklulus}@demo.test`
(kata sandi `password`), belum diumumkan — menekan "Umumkan Hasil" adalah
demonya. Berkas contoh berupa PDF kosong di disk privat (tidak ditulis saat tes).

**Tes baru:** AkunPendaftarTest 24, PendaftaranDaringTest 56, SeleksiPmbTest 31,
DemoPmbDaringTest 1, SmokeLayarTest 3 (anggaran kueri layar PMB daring dan dua
halaman publik).

### Belum

Biaya formulir belum dijurnalkan; calon belum dapat mengganti surel akunnya;
panitia belum punya layar menonaktifkan akun pendaftar; `jalur_masuk` mahasiswa
belum diisi dari jalur gelombang.

---

## Sesi 55 — 2026-10-26 · Jadwal UTS/UAS: entitas sendiri, bentrok mahasiswa, kartu dari jadwal ujian

Pekerjaan P6 #34. Branch `feature/jadwal-ujian`, dari `main` sesudah PR #17.
Berjalan paralel dengan sesi #32 (PMB daring) dan paket kecil #45/#27b; konflik
teks di berkas ini, `ROADMAP.md`, dan `DECISIONS.md` wajar.

### Yang dibangun

Tabel `jadwal_ujian` (per kelas per `JenisUjianKelas` UTS/UAS, tanggal, jam,
status draf → terbit, `tahun_akademik_id` disalin dari kelas), `jadwal_ujian_ruang`
(beberapa ruang, kursi per ruang), dan `jadwal_ujian_pengawas` (dosen **atau**
staf, opsional ditempatkan di satu ruang). `JadwalUjianService` memegang
seluruhnya: simpan, ubah, hapus draf, laporan bentrok satu semester, terbitkan,
susun massal + simpan rencana, daftar peserta dengan kelayakan, dan pembaca untuk
portal. Layar BAAK **Jadwal Ujian** (`/admin/jadwal-ujian`: daftar dengan bentrok
di atas, formulir, detail peserta per ruang, susun massal dengan pratinjau),
`/mahasiswa/jadwal-ujian` (dengan ruang duduknya), `/dosen/jadwal-ujian` (yang
diawasi dan yang diampu). `JadwalUjianPolicy` di atas `kelas.view`/`kelas.manage`.
String di `lang/id/jadwal_ujian.php`. Keputusan: ADR-025.

### Bentrok

Ruang ganda, pengawas ganda, peserta melebihi kursi (termasuk sesi tanpa ruang),
dan **mahasiswa dengan dua ujian bersamaan** — dari KRS disetujui yang belum
dihapus, satu kueri untuk seluruh kelas semester. Kursi di atas kapasitas ruang
hanya peringatan. Draf boleh disimpan dengan bentrok; terbit semua-atau-tidak
per semester × jenis × prodi, dan jadwal terbit yang diubah langsung melewati
gerbang yang sama. `BentrokJadwal` mendapat `pengawas()` dan `kursi()`.

### Kartu ujian dua mode

Bila ada sesi terbit pada semester itu, `CetakService::kartuUjian()` mencetak
tanggal, jam, dan **ruang duduk mahasiswa** dari jadwal ujian jenis terakhir yang
terbit (UAS mengalahkan UTS); bila tidak, perilaku lama dari jadwal kuliah.
Penahanan karena kehadiran kini hanya berlaku pada UAS di mode jadwal ujian.
Ambang kehadiran diangkat ke `PresensiService::memenuhiAmbangUas()` supaya daftar
peserta yang menghitung sekelas sekaligus tidak menyalin angkanya.

### Demo

`JadwalUjianSeeder` menyusun UTS semester aktif lewat penyusun massal (dua pekan
mulai pekan ke-8) dan menerbitkannya lewat gerbang bentrok yang sama — 20 sesi;
lima kelas pilihan tanpa peserta KRS disetujui sengaja tidak terjadwal. UAS
dibiarkan kosong untuk didemokan. SmokeLayarTest memaku anggaran kueri layar baru
dan bahwa UTS demo terbit tanpa bentrok penghalang.

### Yang tersingkap

`PresensiService::pesertaKelas()` berdoc "through an approved study plan" tetapi
tidak menyaring status KRS — absensi cetak dan rekap kehadiran ikut memuat
mahasiswa yang KRS-nya masih draf. Tidak diubah di sini; dicatat untuk perbaikan
terpisah. Jadwal ujian memakai kueri pesertanya sendiri yang menyaring status.

Migrasi dan `JadwalUjianSeeder` dicoba di MariaDB 10.4 lokal (20 sesi terbit).
`migrate:fresh --seed` di sana gagal **sesudahnya**, di `KonversiSeeder`: NIM
dirakit dari `kode_pddikti` berpanjang UUID — diperbaiki terpisah di Sesi 53
(PR #23) dan sudah tergabung ke branch ini. Belum diubah: alasan penahanan kartu ujian membagi tunggakan dengan 100, sedangkan
CLAUDE.md §8 menyimpan rupiah penuh; tesnya menulis nominal dalam sen
(`500_000_00`), jadi satuan mana yang benar perlu diputuskan dulu.

**48 tes baru** (JadwalUjianTest 42, SmokeLayarTest 6).

---

## Sesi 54 — 2026-10-26 · Tiga sisa kecil: klaim flagship MBKM, surat PDM/PJK, spec Bridge yang dipaku tes

Branch `feature/paket-kecil-flagship-surat-spec`, dari `main` sesudah PR #17;
satu commit per butir. Berjalan paralel dengan #32 (PMB daring) dan #34
(jadwal ujian) — konflik teks di berkas ini dan `ROADMAP.md` wajar.

### #45 — klaim program flagship MBKM

Daftar program flagship ditarik sebagai referensi (`GetListAktivitasMBKMFlagship`,
tipe `program_flagship`), tidak di-seed. Penandanya `aktivitas_mahasiswa.flagship_kode`,
dipilih per aktivitas di kolom baru layar Verifikasi Data IKU; pilihan hanya
dari referensi, dan `FlagshipMbkmService` menolak kode yang tidak ada di sana.

Entitas ke-26, `flagship_mbkm` (`InsertAktivitasMBKMFlagship`), bergantung pada
`anggota_mbkm`: program mengakui peserta, bukan judul. Validator melaporkan
aktivitas atau anggota yang belum terkirim, dan program yang hilang dari tarikan
referensi terakhir (program yang dipensiunkan atau dinomori ulang kementerian).

Klaimnya **dialamatkan pada `id_aktivitas`**, bukan pada id apa pun yang
dibalas Feeder: PDDIKTI memegang paling banyak satu klaim per aktivitas.
`feeder_flagship_id` menyimpan id aktivitas tempat klaim diterima; ulangan
dilewati lewat hash, perpindahan program dikirim sebagai
`UpdateAktivitasMBKMFlagship` berkunci aktivitas yang sama. Penanda yang
klaimnya sudah diterima **tidak dapat dicabut dari sini** — web service tidak
punya penarikan yang dapat diandalkan sinkron, dan mengosongkan kolom diam-diam
akan meninggalkan klaim di PDDIKTI yang tidak diingat siapa pun di sini.

Nama aksi dan medan payload (`id_aktivitas`, `id_flagship`) mengikuti pemakaian
komunitas dan belum diverifikasi terhadap dokumentasi build Feeder; aksi yang
salah muncul sebagai galat Feeder di ledger, bukan sebagai "tidak ada program".
`FakeFeederClient` membalas insert-nya dengan `id_aktivitas` dan menyajikan
fixture lima program untuk kampus demo.

**8 tes** (`FeederFlagshipTest`).

### #27b — surat permohonan PDM/PJK

Dua jenis surat baru, `PermohonanPdm` dan `PermohonanPjk` (kode PDM/PJK, urutan
nomor sendiri lewat `PenomoranSurat`, tanpa masa berlaku). Keduanya tidak dapat
diajukan mahasiswa: `JenisSurat::dapatDiajukan()` menyaringnya, layar mahasiswa
kini memvalidasi dengan daftar itu (`Rule::enum()->only()`), dan
`SuratService::ajukan()` menolaknya.

Terbit dari panel Perubahan Data di `/admin/feeder`. Satu surat per mahasiswa
per prosedur memuat **seluruh** permintaan yang masih menunggu surat
(`FeederChangeRequest::perluSurat()`): jalur PDDIKTI admin berstatus belum
diajukan, **atau jalur web service yang ditolak** — penolakannya menjadi alasan
di surat, sesuai catatan #27 bahwa NIM yang ditolak karena pelaporan tertutup
diajukan lewat PDDIKTI admin. Formulirnya tampil sekali per kelompok, bukan per
baris; alasan wajib, disimpan di `surat.keperluan`.

`PermohonanFeederSurat` menautkan permintaan ke surat (`feeder_change_requests.surat_id`,
migrasi baru) **sebelum** menerbitkannya, sehingga `PerakitKonten` merakit isi
dari baris itu seperti surat lain merakit dari catatannya: kolom, nilai tercatat
di PDDIKTI → seharusnya (status keluar ditulis namanya beserta kodenya), alasan
per baris, dan lampiran yang diturunkan dari kolomnya — SPTJM selalu, lalu KTP,
akta, ijazah sebelumnya, KK, SK NIM, SK yudisium, transkrip, ijazah, atau SK
keluar sesuai kolom, sekali masing-masing. Daftar lampiran ikut dibekukan,
jadi kampus yang LLDIKTI-nya meminta lebih mengubah katalognya tanpa mengubah
surat yang sudah terkirim. Penerimanya `config('surat.tujuan_pddikti')`.

Sesudah terbit, setiap permintaan melewati `FeederPerubahanService::ajukan()`
dengan nomor surat itu: status *diajukan*, `nomor_surat` tercatat balik, dan
konsol menautkan unduhan PDF-nya. Mahasiswa menerima notifikasi `SuratTerbit`
seperti surat lain dan melihat suratnya di portal — surat itu tentang datanya
sendiri. Keputusan: ADR-026.

**12 tes** (`SuratPermohonanFeederTest`).

### Spec OpenAPI Bridge — dari dua endpoint tertinggal ke tes yang membacanya

`GET /classes/{uuid}` dan `GET /iku-data` masuk `bridge.yaml` dengan skema,
scope, dan contoh yang diambil dari respons sebenarnya. `/iku-data` tidak
berhalaman; cacahan per kode dikirim sebagai `[]` bila kosong dan `{}` bila
berisi — dinyatakan di spec (`CacahPerKode`), tidak diubah. Skema `CourseClass`
yang ikut tertinggal (mode, status nilai, jadwal, NIDN dan instansi pengajar;
`prodi` tanpa jenjang) disamakan dengan `ClassResource`, dan `/classes` kini
menyebut `links`/`meta` paginasinya.

**Yang tersingkap:** berkas itu tidak pernah sah sebagai YAML — koma di dalam
deskripsi sebaris `/lecturer-workload`. Tidak satu alat pun pernah membacanya.
`SpecOpenApiTest` kini mem-parse-nya (`symfony/yaml` dideklarasikan di
`require-dev`) dan memaku: setiap rute `bridge.*` punya path dan metode di spec;
**scope di spec sama persis dengan middleware rutenya** — dinyatakan per operasi
pada `security` (daftar peran skema bearer, sah di OpenAPI 3.1), sehingga
scope yang ditambah di `routes/api.php` tanpa spec ikut gagal; tidak ada path
yang sudah tidak dilayani; dan medan respons kedua endpoint tadi, beserta
contohnya, sama dengan skemanya.

**6 tes** (`SpecOpenApiTest`). Total sesi ini **26 tes baru**.

---

## Sesi 53 — 2026-10-26 · NIM tidak lagi dirakit dari id_sms

Perbaikan, branch `fix/nim-pindahan-mysql`. Menutup tindak lanjut yang dicatat
sesi 51. Ditemukan saat memverifikasi #34 di MariaDB: `migrate:fresh --seed`
berhenti di `KonversiSeeder` dengan *Data too long for column 'nim'*.

`NimGenerator` mengutamakan `prodi.kode_pddikti` untuk segmen `{prodi}`. Kolom
itu id_sms PDDIKTI — **selalu** UUID — jadi setiap kampus yang sudah mengisinya
untuk Feeder mendapat NIM 42 karakter yang ditolak kolom `nim` (32) di MySQL.
Ini bug produksi, bukan hanya data demo; SQLite tidak menegakkan panjang kolom,
jadi suite hijau. Data demo sudah benar (`kode` = `55201`).

- `{prodi}` kini selalu `prodi.kode`, baik di `NimGenerator` maupun nomor
  ijazah lokal di `WisudaService` (yang memuat, tetapi mencetak UUID di ijazah).
- `NimGenerator` menolak pola yang melampaui 32 karakter dengan pesan aturan,
  bukan 500 di tengah daftar ulang.
- Fixture tes PMB, pindahan, wisuda, dan PIN memakai `kode` = `55201` dengan
  id_sms UUID, sesuai kenyataan; sebelumnya `kode_pddikti` = `55201` menyamarkan
  bug ini.

Terverifikasi: seed demo penuh selesai di MariaDB 10.4; NIM Nadia Paramitha
`26552010049`.

---

## Sesi 52 — 2026-10-26 · P5 tuntas: wisuda didaftar sendiri, dan lulusan kesehatan menunggu ukom dengan nama

Pekerjaan P5 #30 dan #31. Branch `feature/wisuda-mandiri-ukom`, dari `main`
sesudah PR #14; dua commit, satu per butir. Berjalan paralel dengan sesi #44 dan
#33 — konflik teks di berkas ini dan `ROADMAP.md` wajar.

### #30 — satu daftar periksa, tiga pembaca

Sebelum ini satu-satunya syarat wisuda adalah satu `if` di
`WisudaService::daftarkan()`. Layar mahasiswa yang menyalakan tombol "Daftar"
akan butuh salinannya, dan salinan itu akan menyimpang begitu syarat bertambah.
`SyaratWisudaService` kini dibaca layar `/mahasiswa/wisuda`, antrean "Menunggu
Periode Wisuda" di layar staf, dan `WisudaService` sendiri sebelum menulis.
Akibatnya staf juga tidak lagi dapat mendaftarkan lulusan yang menunggak —
pendaftaran massal melewatinya dan menyebut namanya.

Tunggakan dibaca dari `Tagihan::tunggakanPerMahasiswa()`, definisi yang kini
juga dipakai daftar periksa yudisium. **Denda yang belum dibebaskan dihitung
tunggakan**; yang sudah dibebaskan tidak, karena baris pembebasannya sudah
mengurangi `total`. Kesiapan PIN tampil sebagai baris informasi yang tidak
menghalangi. Tidak ada baris bebas pinjaman: modul perpustakaan tidak ada.

Mahasiswa mendaftar dan membatalkan sendiri hanya selama periode dibuka; rute
berparameter periode, bukan orang. Notifikasi dikirim pada kedua arah, dari
jalur mana pun — lulusan yang dikeluarkan staf juga diberi tahu.

### Yang tersingkap di #30

Nomor urut peserta dihitung `count() + 1`. Begitu satu orang mundur, peserta
berikutnya menerima nomor yang sudah dipegang orang lain. Selama hanya staf yang
bisa mengeluarkan peserta, ini jarang; dengan pembatalan mandiri, ini pasti.
Kini `max(nomor_urut) + 1`.

### #31 — kode lokal, bukan tebakan

`StudentStatus::MenungguUkom = 'U'` adalah kode lokal pertama di enum yang
selama ini meniru kode PDDIKTI. `AktivitasKuliahMapper` menjatuhkan setiap
status balik ke kode lokalnya bila pemetaan kosong; untuk status ini tidak —
barisnya dikirim tanpa `id_status_mahasiswa` dan validator menolaknya sebagai
"belum dipetakan" sebelum Feeder sempat menolak kode yang tidak pernah ia
terbitkan.

Alurnya: prodi ber-`wajib_ukom` → `tetapkan()` mencatat SK, IPK, predikat, lalu
berhenti di MenungguUkom tanpa tanggal lulus, alumni, webhook lulus, atau SKPI →
staf mencatat hasil per sitting di layar Yudisium (tabel `uji_kompetensi`; yang
gagal ikut tercatat) → hasil lulus meluluskan pada tanggal ujian, atau tanggal
yudisium bila ujian lebih dulu. Semester hasil itu menjadi periode keluar
Lulus/DO, sehingga lulusan tidak terutang di semester yudisium yang sudah lama
tidak disinkron.

Mahasiswa yang menunggu tidak mengisi KRS, jadi tidak ada yang membuat catatan
semesternya. Penutupan semester kini menulisnya: beku, status `U`, SKS dan IPS
nol, IPK dibawa dari semester terakhir — itu yang dikirim sebagai AKM. Evaluasi
studi melewati mereka dan tidak menghitung semester `U` sebagai semester tempuh.
Validator Lulus/DO menolak Lulus pada prodi wajib ukom tanpa hasil yang lulus;
DO tidak disyaratkan ukom. Keputusan: ADR-022.

### Demo

Seeder baru `UjiKompetensiSeeder`: Fakultas Ilmu Kesehatan, D3 Keperawatan
(wajib ukom, tanpa kurikulum sendiri), satu mahasiswa menunggu ukom
(`mahasiswa.ukom@demo.test`) dengan catatan AKM `U` semester aktif dan satu
sitting yang belum lulus. Pemetaan demo `U → U` di `IntegrasiSeeder`.

### Sesudah #44 dan #33 masuk

Digabung dengan `main` sesudah PR #15 dan #16. Satu interaksi: `KonversiService::cabut()`
menolak mencabut kredit diakui milik mahasiswa Lulus, tetapi tidak milik mahasiswa
yang menunggu ukom — padahal total SKS-nya sudah ditetapkan yudisium. Kini keduanya
ditolak. Pindahan yang menunggu tidak dievaluasi meski `masa_studi_dari_asal` aktif,
dan mahasiswa adopsi mengirim AKM `U` dengan id registrasi dari PDDIKTI; keduanya
dipaku tes. Migrasi ukom dipindah ke `120000`/`120001`, sesudah migrasi #33.

**52 tes baru** (WisudaMandiriTest 27, UjiKompetensiTest 24, KonversiKreditTest 1).

---

## Sesi 51 — 2026-10-26 · Mahasiswa pindahan: kolom #25 akhirnya punya layar

Pekerjaan P6 #33. Branch `feature/mahasiswa-pindahan`, dari `main` sesudah PR
#14. Berjalan paralel dengan sesi #44 dan #30/#31; konflik teks di berkas ini
dan `ROADMAP.md` wajar.

### Satu angka, satu sumber

`mahasiswa.sks_diakui` tidak pernah diketik. `KonversiService` menulisnya pada
setiap persetujuan dan pencabutan sebagai jumlah konversi yang disetujui —
hanya untuk jenis daftar yang `butuhAsal()`, dan `null` selama jumlahnya nol,
supaya validator Feeder tetap berkata "belum ada SKS diakui". Semester masuk
(1 + SKS diakui ÷ 20) dan batas SKS KRS pertama (rata-rata nilai konversi
bernilai lewat matriks IPS yang sama) membaca angka yang sama. ADR-021.

### Dua pintu, satu layar transkrip

Layar baru **Pindahan & RPL** (`/admin/pindahan`): antrean pendaftar PMB
gelombang transfer/RPL yang lulus seleksi, dan pendaftaran langsung oleh BAAK.
Layar PMB kini mengarahkan pendaftar gelombang itu ke intake, bukan daftar ulang
biasa yang akan mencatatnya sebagai peserta didik baru. Sesudah disimpan,
operator mendarat di profil pindahan: ringkasan batas/diakui/sisa/menunggu,
semester masuk, batas KRS pertama, isu Feeder (dari `FeederValidator` yang sama
dengan sinkronisasi, lewat `isuUntuk()` baru), dan formulir transkrip asal
banyak baris yang mencatat seluruh usulan atau tidak sama sekali. Keputusan
tiap usulan tetap di layar Konversi Kredit, yang kini menautkan balik.

### Yang baru

`PindahanService`, DTO `AsalPendidikanData`/`IdentitasMahasiswaData`/
`BarisTranskripData`/`RingkasanPindahan`, tiga FormRequest dengan aturan asal
yang memakai predikat `butuhAsal()` yang sama dengan validator dan mapper,
`MahasiswaPolicy::createFromPmb` (butuh `mahasiswa.manage` **dan**
`pmb.manage`), migrasi `term_masuk_asal`, `config/academic.php` bagian
`pindahan` (`sks_per_semester`, `masa_studi_dari_asal` bawaan mati), referensi
Feeder `perguruan_tinggi` (`GetAllPT`) untuk saran nama PT, prop `list` pada
`x-field`, `lang/id/pindahan.php`. Seeder demo: Nadia Paramitha dibuat lewat
pintu langsung dengan tiga konversi diakui dan satu menunggu; gelombang
pindahan dengan satu pendaftar yang menunggu intake.

### Yang tersingkap

- `PmbService::daftarUlang()` tidak pernah mengisi `term_masuk`, jadi setiap
  mahasiswa hasil PMB gagal pemeriksaan periode masuk Feeder. Kini diisi.
- `NimGenerator` memakai `prodi.kode_pddikti` sebagai segmen NIM, padahal kolom
  itu menyimpan `id_prodi` (UUID) yang dikirim mapper. Di kampus demo NIM hasil
  PMB berbentuk `26f127ba98-…0001`. **Tidak diperbaiki di sini** — tindak lanjut.

**37 tes baru** (MahasiswaPindahanTest 36, SmokeLayarTest profil pindahan 1;
dua layar ditambahkan ke daftar anggaran kueri).

---

## Sesi 50 — 2026-10-26 · Adopsi mahasiswa PDDIKTI: kampus yang pindah tidak lagi mendaftarkan ulang angkatannya

Roadmap #44, branch `feature/feeder-adopsi-mahasiswa`. Berjalan paralel dengan
sesi #33 dan #30/#31; konflik teks di ROADMAP/STATUS diterima sebagai wajar.

### Masalahnya

Kampus yang datang dari SIAKAD lain sudah punya seluruh mahasiswanya di PDDIKTI.
`MahasiswaMapper` hanya `InsertBiodataMahasiswa`, dan rekonsiliasi sengaja tidak
membaca biodata — jadi sinkronisasi pertama mendaftarkan ulang semua orang itu.

### Rancangan (ADR-020)

`FeederAdopsi` membaca `GetListRiwayatPendidikanMahasiswa` (NIM, registrasi) dan
`GetListMahasiswa` (NIK) per prodi + periode masuk, digabung pada `id_mahasiswa`.
NIM menemukan, NIK menegaskan, nama hanya pengganti NIK yang kosong. Yang jelas
diadopsi: `feeder_id`/`feeder_registrasi_id` diisi dan buku besar mendapat satu
baris per entitas (`direction = adopt`, hash muatan lokal) — hanya bila belum
pernah ada sukses, supaya hash dorongan sungguhan tidak tertimpa. Dorongan
berikutnya mencatat *Skipped*; tidak ada `Insert`/`Update` yang terkirim.
Yang ragu (dua kandidat, NIM sama NIK beda, NIK sama NIM beda, sudah tertaut ke
data lain) tidak pernah diadopsi otomatis: ditautkan atau dipisahkan di
`/admin/feeder/adopsi` dengan alasan wajib, ter-audit, dan bertahan saat adopsi
diulang. Validator menolak biodatanya (`kandidat_feeder_belum_diadopsi`) sampai
diputuskan. NIK tidak disimpan di laporan — hanya `nik_cocok`.

### Yang ditemukan saat menulis tes

Sesudah satu dari dua registrasi kembar ditautkan manual, kembarannya muncul
sebagai "hanya di Feeder". Tes awalnya mengharapkan sebaliknya; kodenya yang
benar — itu registrasi ganda di PDDIKTI yang memang perlu dilihat operator.
Cabang "satu baris Feeder diperebutkan dua mahasiswa lokal" dibuang: NIM unik,
jadi dua adopsi atas satu baris tidak mungkin terjadi.

### Yang baru

Migrasi `feeder_adoptions`, enum `StatusAdopsiFeeder` dan `AlasanAmbiguAdopsi`,
model `FeederAdoption` (`HasLogAktivitas`), `FeederAdopsi`,
`FeederAdopsiController` + policy + dua FormRequest, layar `feeder-adopsi`
(tautan dari konsol Feeder), `openacademic:feeder-adopsi {prodi|semua}
[--periode=]`, `config/feeder.php` → `adopt`, string di `lang/id/feeder.php`.
`FakeFeederClient::isi()` menghormati filter (prodi/periode) dan
`mahasiswaTerdaftar()` memisah fixture ke dua daftar seperti layanan aslinya;
fixture kasus di `tests/Fixtures/feeder/mahasiswa-terdaftar.json`.

**33 tes baru** (FeederAdopsiTest). Suite penuh paralel: 1.294 tes hijau (3.292 asersi).

### Tersisa

- #44b: klaim mahasiswa lampau (`GetListDaftarLampau`/`KlaimMahasiswaLampau`).
- Nama aksi dan field Feeder untuk kedua daftar perlu diverifikasi terhadap
  dokumentasi build Feeder kampus; keduanya dapat diganti di konfigurasi.
- Validator hanya tahu kandidat yang sudah pernah dibaca: kampus yang lupa
  menjalankan adopsi belum diperingatkan.

---

## Sesi 49 — 2026-10-26 · CI ikut versi produksi: PHP 8.2, 8.4, 8.5

Bukan fitur. Produksi berjalan di PHP 8.4 atau 8.5, sementara CI dan laptop
pengembang menguji di 8.2 — tes hijau tidak membuktikan apa pun tentang versi
tempat kode benar-benar dijalankan. CI kini matriks 8.2/8.4/8.5 (ADR-023); Pint
dan build aset sekali saja, di 8.4. Batas bawah `^8.2` tidak diubah.

Sebelum matriks dibuka, suite penuh dijalankan di PHP 8.4.27: 1.261 tes hijau
(3.179 asersi), **nol** peringatan deprecation.

Di laptop pengembang, XAMPP dinaikkan dari 8.2.12 (terdampak celah inti PHP
21 Oktober) ke 8.4.27. Dua hal yang tersingkap dan layak diingat siapa pun yang
mengulanginya di XAMPP:

1. **Apache memuat DLL pendukung PHP dari `apache\bin`, bukan dari `php\`.**
   XAMPP menyalin `icu71`, `libssh2`, `nghttp2`, dan lainnya ke sana untuk 8.2.
   Tanpa salinan versi 8.4 (`icu77`, `brotlidec`, `libsodium`, `libssh2`
   1.11.1, `nghttp2` 1.70), `curl` dan `intl` lolos di CLI tetapi gagal dimuat di
   Apache. OpenSSL milik Apache (3.1.3) lebih baru daripada milik PHP (3.0.22)
   dan dibiarkan. `sodium` ternyata **selalu** gagal dimuat di Apache sejak
   sebelum upgrade — 96 baris di log — dan kini termuat.
2. **`imap` keluar dari inti PHP di 8.4.** `php.ini` mengaktifkannya; barisnya
   dinonaktifkan. Tidak ada proyek di `htdocs` yang benar-benar butuh: klien
   IMAP yang ada memakai socket sendiri.

Folder lama disimpan di `C:\xampp\php-8.2.12-backup`, DLL Apache yang ditimpa
di `C:\xampp\apache\bin-backup-2026-10-26`.

---

## Sesi 45 — 2026-10-26 · Pembaruan keamanan Oktober: Laravel 12.70.1 dan lima paket lain

Bukan fitur. `laravel/framework` di `main` masih 12.65.0, di bawah perbaikan tiga
advisori yang terbit 19 Oktober dan sesudahnya. Yang kritis — CVE-2026-114509,
bypass TokenGuard lewat token non-string di MySQL/MariaDB — ternyata **tidak
terjangkau** di sini: tak satu pun guard memakai driver `token`; portal memakai
session, SSO memakai Passport (13.7.5, sudah di atas perbaikan CVE-2026-39976),
dan Bridge memakai Sanctum. Inklusi relasi JSON:API (CVE-2026-114508) juga tidak:
resource Bridge adalah `JsonResource` biasa dan tidak membaca `include`. XSS
halaman debug (CVE-2026-102279) terjangkau bila `APP_DEBUG=true` di produksi —
dan `.env.example` menyetelnya `true`.

Yang benar-benar terjangkau tanpa login justru dari Symfony: ReDoS di
`HeaderUtils::split()` lewat header `Cache-Control` (CVE-2026-108082). Ikut
ditutup: enam advisori `league/commonmark`, `phpseclib` 3.0.57, `flysystem`
3.36.0, `symfony/yaml`. Semua dalam Laravel 12 / Symfony 7.4, hanya
`composer.lock` yang berubah. `npm audit fix` membereskan tujuh temuan rantai dev
(vite, concurrently, axios) tanpa perubahan `package.json`.

Satu yang tidak bisa diperbaiki dari repo: celah inti PHP 21 Oktober
(CVE-2026-109618/109619, "PHP2Shell"). XAMPP pengembang di 8.2.12 terdampak;
setiap server harus memperbarui interpreternya sendiri. Dicatat di ROADMAP
§Menunggu di Luar Repo.

Catatan alat: `composer audit` sempat tertinggal — basis data advisori Packagist
belum memuat CVE-2026-114509 beberapa hari setelah terbit — jadi audit yang
bersih pada pekan itu bukan bukti aman.

---

## Sesi 44 — 2026-10-24 · Delapan PR masuk, dan ROADMAP dibersihkan dari baris basi

Bukan fitur. PR #6–#13 di-merge berurutan (#6, #7, #11, #8, #9, #10, #12, #13);
setiap PR digabung ulang dengan `main` sebelum gilirannya, dan nomor Sesi serta
ADR diurutkan mengikuti urutan merge itu. Suite penuh di `main` gabungan: 1.261
tes hijau (3.179 asersi).

Membaca ulang ROADMAP sesudahnya menemukan enam baris yang tidak lagi benar.
Dua utang teknis pembayaran — driver `fake` dan direktori kosong `Services/Payment`
— sudah lunas sejak 20 Agustus tetapi tidak pernah dicoret. #43 masih terbuka
padahal denda dan helpdesk selesai hari ini. Career center bertanda ⬜ di Peta
Cakupan sementara tiga bagian lain menyatakannya milik Open Campus; kini 🚫.
Paragraf urutan masih menyebut #32 berikutnya; kajian Sesi 39 menyimpulkan #44
harus lebih dulu, dan #33 murah sesudah #25. Baris federasi IdP di §Menunggu di
Luar Repo sudah dibereskan PR #13 sendiri.

---

## Sesi 43 — 2026-10-24 · Masuk lewat IdP kampus: Open Academic kini juga konsumen identitas

Pekerjaan P3 #16 bagian yang tersisa. Branch `feature/auth-idp-federasi`, dari
`main` sesudah PR #5. Berjalan paralel dengan sesi denda (#15), helpdesk (#18),
dan Feeder (#24b/#27); konflik teks di ROADMAP/STATUS diterima sebagai wajar.

### Keputusan yang dulu menahan fitur ini

`SSO.md` menahan federasi pada satu pertanyaan: identitas dari IdP dipetakan ke
tabel yang mana. Dijawab sempit (ADR-019): hanya guard di `IDP_GUARDS` — staf
secara bawaan, dosen bila dipilih, mahasiswa tidak bisa. Surel dipakai sekali
untuk menautkan, lalu `idp_subject` yang dipegang; cocok di dua tabel ditolak
sebagai ambigu, bukan diselesaikan lewat urutan; tidak ada provisioning bawaan.

### Klien OIDC ±150 baris, tanpa dependensi baru

`laravel/socialite` tidak ada di `composer.json` dan tidak ditambahkan: dua
panggilan HTTP (discovery, tukar kode) dan satu validator klaim tidak
membenarkan katalog penyedia. `ValidatorKlaim` dipakai bersama oleh driver
`oidc` dan `fake`, jadi tes yang menolak `iss`/`aud`/`nonce` salah menguji
kode yang sama dengan produksi. Tanda tangan JWS tidak diverifikasi — token
datang langsung dari token endpoint lewat TLS (OIDC Core §3.1.3.7.6), maka
issuer non-HTTPS ditolak sebelum jaringan disentuh.

### Titik temu 2FA

Staf ber-TOTP yang kembali dari IdP mendarat di layar tantangan yang sama
dengan jalur kata sandi, dengan bentuk sesi `dua_faktor` yang sama.
`IDP_SATISFIES_2FA=true` melewatinya, bawaan `false`.

### Yang baru

`config/idp.php` (`IDP_ENABLED=false` bawaan), `IdentityProviderInterface` +
`FakeIdentityProvider` + `OidcIdentityProvider`, `PenautanIdpService`,
`IdpLoginController` (`/masuk/idp`, `/masuk/idp/callback`, terdaftar hanya
bila aktif), migrasi `idp_subject` pada `staff` dan `dosen`, tombol
"Masuk dengan <IDP_NAME>" di halaman masuk, string di `lang/id/auth.php`.
Setiap percobaan ditulis ke Log Aktivitas (`idp_masuk`/`idp_gagal`) tanpa kode
atau token. `phpunit.xml` memaku `IDP_ENABLED=true` dengan pola `SSO_ENABLED`;
tes "rute tidak terdaftar" mem-boot proses anak dengan `IDP_ENABLED=false`.

**29 tes baru** (IdpLoginTest 23, OidcIdentityProviderTest 6).
## Sesi 42 — 2026-10-24 · Helpdesk: loket yang bukan loket akhirnya punya nomor antrean

Pekerjaan P3 #18. Branch `feature/helpdesk`, dari `main` sesudah PR #5.
Dikerjakan paralel dengan sesi denda (#15), federasi IdP (#16), dan Feeder
#24b/#27 — konflik teks di berkas ini dan `ROADMAP.md` wajar.

### Batas yang ditarik lebih dulu

Helpdesk ini **administratif**: mahasiswa atau dosen → unit kerja. Bukan
forum, bukan chat. Tidak ada tiket yang terbaca pemohon lain, tidak ada
lingkaran "dosen wali" di policy — tiket bisa memuat bukti transfer atau surat
dokter. Pengaduan etik dan percakapan antarmahasiswa tetap wilayah Open Campus.

### Tabel transisi, bukan tombol

`StatusTiket::tujuanSah()` adalah satu-satunya tempat yang menyatakan langkah
mana yang sah; service menolak sisanya, dari formulir maupun perintah. Beberapa
langkah adalah **efek samping**: balasan publik staf memindahkan ke "menunggu
pemohon" dan petugas pertama yang menjawab mengambil tiketnya; balasan pemohon
pada tiket "selesai" membukanya kembali; balasan pemohon pada tiket baru tidak
berpura-pura ada yang sedang memproses. Ditutup tidak ke mana-mana — pemohon
membuka tiket baru, jejak lama utuh.

### SLA menandai, tidak memutuskan

`openacademic:helpdesk-sla` tiap jam hanya menstempel `lewat_sla_at` pada tiket
yang masih menunggu kampus. Tidak menutup, tidak mengalihkan, tidak memberi tahu
pemohon. Tiket yang menunggu pemohon tidak terlambat, apa pun kata jam. Tenggat
dibekukan saat tiket diajukan; kategori yang disunting bulan depan tidak
memindahkan garis finis antrean yang ada.

### Yang tersingkap

- Komponen `x-field` menaruh atribut tambahan di pembungkus, bukan di input —
  `x-model` dan `form=` di atasnya diam-diam tidak melakukan apa pun. Dua layar
  menulis `<select>`-nya sendiri karena itu.
- Policy ditemukan otomatis lewat penggantian `\Models\` → `\Policies\`
  milik Laravel; tidak ada registrasi di provider mana pun, dan memang tidak
  perlu.
- `phpunit.xml` tidak menyetel `APP_LOCALE`; modul ini modul pertama yang
  seluruh teks UI-nya lewat `lang/id/`, dan tes layarnya bergantung pada itu.
  Kini dipaku di sana.

Notifikasi kategori baru `helpdesk`, **dapat dimatikan** — garis NOTIFIKASI.md:
melewatkannya merugikan berupa jawaban yang lambat, bukan satu semester.

**57 tes baru** (service, policy per portal, transisi, SLA, layar, FAQ publik).
Keputusan: ADR-018.

---

## Sesi 41 — 2026-10-24 · Denda keterlambatan: akrual harian yang tidak memperketat KRS

Pekerjaan #15. Branch `feature/keuangan-denda`, dari `main` sesudah PR #5,
di-merge dengan `main` sesudah PR #6, #7, dan #11.
Mengikuti keputusan 24 Oktober bahwa payment gateway opsional: denda harus
bekerja penuh pada pembayaran manual yang dicatat kasir, dan memang begitu.

### Denda adalah baris tagihan

Satu baris `tagihan_item` berjenis `denda` per tagihan, sehingga `total` tetap
jumlah barisnya dan tak satu pun dari sepuluh pembaca `total` perlu diubah.
Yang baru di `tagihan`: `denda` (neto yang sedang ada di `total`),
`denda_dihitung_sampai`, `denda_dibebaskan_at`. Aturannya di
`config/payment.php` dengan bawaan **nonaktif**: nominal per hari/minggu atau
persen dari sisa pokok (dibulatkan ke bawah, `intdiv`), tenggang, maksimum.

### Akrual, bukan hitung ulang

Pilihan yang menentukan. Denda yang dihitung ulang dari nol tiap malam menyusut
sesudah pembayaran sebagian — seolah keterlambatan sebelumnya tidak terjadi —
dan "idempoten" lalu bergantung pada rumus yang kebetulan setuju dengan dirinya.
Yang dilakukan: tiap jalan hanya menagih periode yang dimulai sesudah
`denda_dihitung_sampai`, pada sisa pokok hari itu. Jalan kedua di hari yang
sama menemukan nol periode baru. Jalan untuk hari yang lebih lampau tidak
membuka kembali periode yang sudah ditagih. Dispensasi menggeser awal jam
denda ke akhir dispensasi — bagian keuangan yang berkata "sampai tanggal ini"
tidak boleh dibantah oleh tagihan mahasiswa sendiri.

### Gerbang KRS mengukur pokok

`memenuhiSyaratKrs()` kini membaca `persenTerbayarPokok()` = terbayar terhadap
`total − denda`. Mahasiswa yang tepat di ambang 50 % sebelum denda tetap lolos
sesudahnya; yang memang di bawah ambang tetap terkunci. Dipaku di
`GerbangPembayaranKrsTest` dan `DendaTest`.

### Pembebasan adalah baris, bukan penghapusan

Staf keuangan membebaskan denda lewat layar Tagihan dengan alasan wajib
(`BebaskanDendaRequest`, policy `waiveLateFee`). Hasilnya baris potongan
negatif bernama tetap, menyimpan alasan, staf, dan waktu; bruto–pembebasan–neto
tetap terbaca di layar mahasiswa, akrual berhenti, mahasiswa diberi tahu.
`PotonganService::hapus()` menolak menghapus baris ini — kolom di tagihan akan
menggambarkan pembebasan yang sudah tidak ada.

### Yang sengaja belum

Denda tidak dijurnalkan ke integrasi akuntansi; pendapatan denda butuh kode
akun yang belum ada di bagan dan keputusan perlakuannya. Dicatat di MODUL.md.

**34 tes baru** (DendaTest 32, GerbangPembayaranKrsTest +2). Seeder demo
mengenakan denda lewat service pada tagihan yang menunggak dan membebaskan satu
di antaranya, dengan aturan eksplisit — `.env` kampus tidak disentuh.

---

## Sesi 40 — 2026-10-24 · Payment gateway dinyatakan opsional

Bukan kode. Pemilik repo memutuskan transfer manual yang dicatat kasir adalah
jalur utama, dan kanal bayar terintegrasi bukan syarat v1.0 — ADR-014. ROADMAP
mengosongkan P0 dan menurunkan adaptor gateway ke P3 sebagai butir opsional;
kalimat "satu-satunya kekalahan nyata" di empat tempat ditulis ulang menjadi
"model berbeda". Pada hari yang sama empat sesi paralel mulai mengerjakan P4
#24b/#27, P3 #15, #18, dan #16.

---

## Sesi 39 — 2026-10-24 · Cek ulang roadmap dan skrip terhadap kode

Bukan fitur. Setiap butir #19–#43 dicocokkan ke kode, setiap perintah
`openacademic:*` dijalankan di basis data kosong, dan dokumen yang menyebut
perintah dibandingkan dengan yang ada. Branch `claude/hopeful-elion-ee011b`,
dari `main` sesudah PR #5, digabung ulang dengan `main` sesudah PR #11.
Catatan lengkapnya lokal di `docs/lokal/` (menyebut nama pembanding).

### Tiga butir roadmap yang sebenarnya sudah ada

**#36 pengingat mahasiswa yang belum KRS** sudah ada sejak G2: perintah
`kirim-pengingat` mengirim H-7, H-3, H-1 hanya kepada mahasiswa aktif tanpa KRS
diajukan atau disetujui. Dicatat sebagai kesenjangan sehari sebelumnya karena
daftarnya disusun dari halaman fitur pembanding, bukan dari kode sendiri.
**2FA staf** ada sejak 22 Agustus tetapi masih ⬜ di dua tempat. **#35** dan
**#38** separuh ada — tanggal KRS dan nilai sudah menggerbang, seminar proposal
sudah jenis ujian — dan deskripsinya kini mengatakan yang mana yang kurang.

### Satu yang tercecer dari kajian

**#44 — adopsi mahasiswa yang sudah ada di PDDIKTI.** Mapper biodata hanya
`Insert`, dan rekonsiliasi tidak membaca daftar mahasiswa kembali. Kampus yang
pindah dari SIAKAD lain akan mencoba mendaftarkan ulang seluruh mahasiswanya
pada sinkronisasi pertama. Lebih mendesak dari #32 untuk instalasi sungguhan.

### Dua skrip yang tidak jalan

`openacademic:sso-client --daftar` — dicontohkan di README dan SSO.md — mati
dengan "missing: nama", karena nama aplikasi argumen wajib dan tidak ada tes
untuk perintah ini. Kini `nama?` dan tiga tes. Lalu `composer test:par`
terbunuh **batas waktu 300 detik Composer** di mesin ini: suite paralel 319–670
detik, dan `disableProcessTimeout` hanya dipasang di `dev`. CI selamat karena
runner-nya lebih cepat. Dipasang di `test` dan `test:par`.

### Yang tersingkap dan belum diputuskan

Kampus demo **tidak pernah bisa `feeder-sync semua` utuh**: satu mahasiswa
sengaja dibuat tidak lolos ("Siap PDDIKTI" merah), dan validasi pra-kirim
membatalkan satu entitas penuh bila ada satu baris yang gagal — jadi entitas
pertama batal, dan sisanya tidak pernah dicoba. `FEEDER_ENABLED=false` di
`.env.example` juga tidak disebut README. Dan `docs/openapi/bridge.yaml`
mendokumentasikan 12 dari 14 endpoint; dicatat di §Utang Teknis.

---

## Sesi 38 — 2026-10-24 · Feeder: skala & periode per prodi, dan koreksi yang tidak diam-diam (PDM/PJK)

Pekerjaan #24b dan #27. Branch `feature/feeder-skala-periode-pdm`, ditumpuk di
atas PR #6 (#26) dan PR #7 (#23–#25) yang belum merge. P4 tuntas; sisa #27b.

### Keputusan pemodelan #24b: tabel per prodi yang diturunkan

`InsertSkalaNilaiProdi` dan `InsertPeriodePerkuliahan` butuh baris per prodi,
dan mesin sinkron butuh model Eloquent per baris. Jawabannya bukan mapper atas
konfigurasi melainkan dua tabel — `skala_nilai_prodi` (per prodi per huruf)
dan `periode_perkuliahan` (per prodi per semester) — yang **diturunkan** oleh
`SkalaPeriodeProdiService` dari skala kampus dan kalender semester, hanya untuk
prodi yang belum punya baris (ADR-016). Batas atas huruf adalah batas bawah
huruf di atasnya, huruf teratas 100; angka PMB periode dihitung dari pendaftar
semester itu dan dibiarkan kosong bila tidak ada gelombang — "tidak dihitung",
bukan nol. Penurunan terjadi saat mapper membaca barisnya dan di
`IntegrasiSeeder`, jadi kampus demo langsung mengantre 14 skala dan 2 periode.
Pembaruan berkunci `id_bobot_nilai` dan (`id_prodi`, `id_semester`) — yang
terakhir disimpan gabungan di `feeder_id`, pola yang sama dengan matkul
kurikulum. Keduanya ditaruh paling awal di `config/feeder.php`, dan dapat
dibandingkan lewat `GetListSkalaNilaiProdi` / `GetListPeriodePerkuliahan`.

`FakeFeederClient` kini membalas insert dengan nama id yang benar per aksi
(`id_bobot_nilai` untuk skala) — sebelumnya semua balasan memakai
`id_registrasi_mahasiswa`, dan mapper skala akan menyimpan nama yang salah.

### #27: mencatat saat berubah, bukan mengunci di layar

Enam kolom identitas dan tujuh kolom keluar dikunci PDDIKTI setelah tersinkron.
`KunciFeederObserver` dipasang pada `mahasiswa`, `yudisium`, `wisuda_peserta`,
dan `evaluasi_studi`; begitu salah satu kolom di katalog `KolomTerkunci`
berubah pada mahasiswa yang sudah ber-`feeder_id` (PDM) atau
`feeder_keluar_id` (PJK), ia menulis `feeder_change_requests` — satu baris per
kolom, nilai lama dan baru dalam kosakata PDDIKTI (tanggal `Y-m-d`, periode
sebagai kode semester). Permintaan terbuka disunting, bukan digandakan; nilai
yang dikembalikan mencabutnya (ADR-017).

Validator pra-kirim menolak biodata (`mahasiswa`) dan catatan Lulus/DO
(`lulus_do`) dengan aturan `kolom_terkunci_berubah` selama ada permintaan
jalur PDDIKTI admin yang terbuka, menyebut kolomnya. Yang web service terima —
NIM dan periode masuk — dikirim lewat `UpdateRiwayatPendidikanMahasiswa`
berkunci `id_registrasi_mahasiswa`, dicatat di buku besar; penolakan Feeder
disimpan pada permintaan dan membiarkannya terbuka untuk diajukan lewat admin.
Panel baru di `/admin/feeder`: jenis, kolom, lama → baru, jalur ("harus
diajukan lewat PDDIKTI admin" / "dapat dikirim lewat web service"), status
belum diajukan → diajukan (nomor surat) → selesai, tiga aksi di
`FeederPerubahanController` dengan `FeederChangeRequestPolicy` (`feeder.sync`).
String UI-nya di `lang/id/feeder.php`.

Yang tersingkap: mapper biodata hanya membaca mahasiswa semester berjalan
(atau yang belum pernah terkirim), jadi perubahan identitas mahasiswa angkatan
lama memang tidak pernah dikirim ulang oleh sinkron — permintaannya tetap
tercatat dan tampak di konsol.

**27 tes baru** (133 asersi) di `FeederSkalaPeriodeTest` dan
`FeederPerubahanMahasiswaTest`, termasuk kampus demo yang mengirim kedua
entitas lewat driver palsu tanpa satu pun gagal. Belum: surat permohonan
PDM/PJK dari modul surat (#27b).

---

## Sesi 37 — 2026-10-22 · Feeder: referensi dulu, baru kredit dari luar, dan cara mahasiswa masuk

Pekerjaan #23, #24, #25. Branch `feature/feeder-prestasi-kurikulum`, dari `main`
sesudah PR #5. Enam entitas baru, dua puluh empat seluruhnya.

### "Referensi dulu" ternyata belum berlaku untuk referensi kita sendiri

CLAUDE.md §5 mewajibkan menarik referensi Feeder sebelum mengirim apa pun. Yang
terlewat: kurikulum dan mata kuliah adalah referensi *kita* yang harus sampai di
Feeder lebih dulu, dan tidak pernah dikirim. Payload kelas dan KRS sejak Agustus
mengirim `id_matkul` berisi **kode lokal** — yang Feeder tidak kenal. Instalasi
demo tidak pernah menemukannya karena *fake client* menerima apa saja.

Kini tiga entitas referensi berjalan paling awal: kurikulum, mata kuliah, dan
penempatan mata kuliah dalam kurikulum. Payload kelas dan KRS memakai id Feeder
begitu ada, dan validator memperingatkan kelas yang mata kuliahnya belum
terkirim. Penempatan dikunci pada *pasangan* (kurikulum, mata kuliah) karena
PDDIKTI tidak memberi id untuknya; pasangan itu disimpan bergabung dan dipecah
lagi saat update — dan *fake client* yang mengembalikan UUID untuk segala hal
sempat menggantikannya, sampai mapper memaksa pasangannya.

### Tiga catatan kredit dari luar

Prestasi (hanya yang terverifikasi staf), transfer kredit (hanya yang disetujui;
asal dikirim sebagai teks karena PDDIKTI tak punya id untuk mata kuliah kampus
lain), dan konversi MBKM — dikunci pada *anggota* aktivitas dari #22, bukan
aktivitasnya, karena PDDIKTI mengonversi kredit untuk peserta. Baris KRS yang
mewakilinya kini membawa dua id Feeder: milik KRS dan milik konversi.

### Cara mahasiswa masuk

`JenisDaftar` enam nilai dan empat kolom di `mahasiswa`. Pindahan dan RPL wajib
membawa SKS diakui dan asal — Feeder menolaknya tanpa itu, dan portal PIN
menolaknya lagi bila SKS-nya kurang; validator menangkap keduanya di sini.
Hanya "peserta didik baru" yang kodenya di-seed; selebihnya dipetakan kampus
sesudah `GetJenisPendaftaran`. Belum ada layar untuk mengisinya — itu #33.

### Yang sengaja tidak ikut

Skala nilai prodi dan periode perkuliahan (#24b). Keduanya baris *per prodi* di
PDDIKTI, sedangkan di sini skala nilai satu untuk seluruh kampus dan periode
tidak berdimensi prodi. Itu keputusan pemodelan, bukan mapper, dan ditulis
sebagai butir sendiri alih-alih dipaksakan.

### Anggaran kueri monitor, untuk kedua kalinya

Pada enam entitas, satu kueri hitung per entitas tidak terasa. Pada dua puluh
tiga, layar monitor melewati anggaran 30 kueri — dan suite penuh yang
menangkapnya. Kini seluruh hitungan antrean dijadikan **satu** SELECT berisi
sub-select skalar per entitas (`FeederSyncService::jumlahAntre()`), jadi
jumlah entitas boleh terus bertambah tanpa anggaran ikut naik.

**16 tes baru** (70 asersi); folder Feeder hijau seluruhnya.

---

## Sesi 36 — 2026-10-22 · Feeder: menghapus yang sudah tidak ada di sini

Pekerjaan #26. Branch `feature/feeder-hapus-koreksi`, dari `main` sesudah PR #4, di-rebase sesudah PR #5.

### Separuh koreksi yang hilang

`Update*` sudah berjalan sejak Agustus: ledger menyimpan hash muatan, dan baris
yang berubah dikirim ulang dengan kunci balasan Feeder. Yang tidak pernah ada
adalah kebalikannya. Kelas yang dibatalkan sesudah dilaporkan, baris KRS yang
dicabut saat revisi, mahasiswa yang dihapus — semuanya tetap di PDDIKTI persis
seperti saat dikirim, dan rasio atau total SKS yang dihitung darinya salah
sejak itu.

`FeederSyncService::hapusYangTerhapus()` membaca ledger: aksi sukses terakhir
per baris lokal, lalu dicocokkan dengan tabelnya. Yang tidak ditemukan — terhapus
lunak dihitung tiada, karena kelas di keranjang sampah bukan kelas yang
diselenggarakan — dikirim `Delete*` dengan kunci yang sama seperti `Update*`.
Setiap penghapusan menulis baris ledger sendiri, jadi putaran berikutnya melihat
aksi sukses terakhirnya adalah hapus dan melewatinya: idempoten dengan cara yang
sama seperti dorongan.

### Tiga hal yang sengaja

1. **Nama aksi diturunkan, bukan ditulis per entitas.** `InsertX` → `DeleteX`.
   Entitas yang dibawa aksi *upsert* (nilai per komponen) tidak punya *Insert*
   untuk dicerminkan dan dinyatakan "tanpa aksi hapus" — di layar, di perintah,
   dan di hasil — alih-alih ditebak.
2. **Penghapusan yang ditolak Feeder dicoba lagi.** Baris ledger gagal bukan
   aksi *sukses* terakhir, jadi putaran berikutnya mencobanya kembali.
3. **Berjalan di dalam permintaan, bukan antrean**, seperti pembanding:
   penghapusan sedikit, dan operator yang menekan tombolnya ingin angkanya
   sekarang.

Yang **bukan** penghapusan dan tidak disentuh: mahasiswa yang yudisiumnya
dibatalkan masih ada — statusnya berubah, barisnya tidak; itu urusan `Update`
entitas Lulus/DO, bukan `Delete`.

Kontrak `FeederClientInterface` bertambah satu metode, `delete()`, di client
sungguhan maupun palsu. **10 tes baru**; folder Feeder 87 hijau.

---

## Sesi 35 — 2026-10-22 · Layar rencana evaluasi: jenis komponen akhirnya punya tempat diisi

Pekerjaan #20b. Branch `feature/rencana-evaluasi-kelas`, dari `main` sesudah
PR #3, di-rebase sesudah PR #4.

### Panel di layar Input Nilai

Sejak #20, tiap komponen nilai punya *jenis* (partisipatif, proyek, tugas,
kuis, UTS, UAS) — dan sejak #20 pula tidak ada satu layar pun tempat dosen
mengisinya. Kini ada: panel "Rencana Evaluasi" di atas lembar nilai. Nama,
bobot, jenis per baris; tambah dan hapus baris; total bobot dan bagian
partisipatif + proyek dihitung di layar saat diketik, dan tombol simpan mati
sampai totalnya 100. Server mengulangi perhitungannya di `FormRequest` dan di
service; layar hanya pratinjau. Kelas kolaboratif yang rencananya di bawah 50 %
mendapat jawaban di detik yang sama ia menyimpan — satu-satunya saat mengubah
rencana masih murah.

### `simpanKomponen()` berhenti menghapus semuanya

Versi lama menghapus seluruh komponen lalu membuat ulang — dan `nilai_komponen`
ber-`cascadeOnDelete`, jadi setiap penyimpanan rencana **melenyapkan nilai yang
sudah diisi**. Tidak pernah terasa karena tidak ada yang memanggilnya. Sekarang:
baris ber-`id` diubah di tempat, baris tanpa `id` baru, komponen yang tak ada
di rencana dihapus — **kecuali yang berisi nilai**, yang ditolak dengan menyebut
namanya. Layar menandai komponen itu "berisi nilai" dan menyembunyikan tombol
hapusnya; ubah nama, bobot, atau jenisnya tetap boleh.

Rencana terkunci bersama nilai: kelas yang sudah final atau periode penilaian
yang tertutup menampilkan panelnya hanya-baca.

**13 tes baru** (33 asersi).

---

## Sesi 34 — 2026-10-22 · Nomor ijazah dicatat dari PIN, bukan dibangkitkan

Pekerjaan #29. Branch `feature/ijazah-nomor-pin`, dari `main` sesudah PR #2, di-rebase sesudah PR #3.
Menutup satu-satunya risiko terbuka di MODUL.md.

### Keputusan di sisi yang aman

`ACADEMIC_IJAZAH_SUMBER` dengan bawaan `pin` (ADR-013). Pembangkit nomor tetap
ada untuk mode `lokal`, tetapi di bawaan ia **menolak** dengan pesan yang
menyebut jalurnya — bukan diam-diam tidak melakukan apa pun. Instalasi yang
sudah menyimpan nomor lokal tidak kehilangan apa pun; yang berhenti hanya
pembangkitnya.

### Mencatat, bukan mengimpor berkas

Portal PIN memberi daftar; operator menempelkannya ke satu kolom teks: NIM dan
nomor per baris, pemisahnya terserah (koma, titik koma, tab, spasi). Tiap baris
dinilai sendiri — yang baik dicatat, yang buruk dilaporkan dengan sebabnya —
karena all-or-nothing berarti mengetik ulang empat puluh baris baik demi satu
salah ketik. Tiga penolakan: NIM tidak ada di periode, nomor sudah tercatat
berbeda (tidak pernah ditimpa), dan satu nomor untuk dua lulusan (di dalam
tempelan maupun terhadap yang tersimpan). Impor ulang yang sama dihitung
dilewati, bukan ditolak.

### Dua hal kecil yang tersingkap

Rute model memakai UUID, bukan id — tes layar pertama 404 karena itu. Dan
`WisudaTest` lama memanggil pembangkit tanpa menyetel mode; kini ia menyatakan
`lokal` secara eksplisit di `beforeEach`, karena bawaannya sudah berubah.

**9 tes baru** (32 asersi). Pembanding PIN di layar Yudisium (#28) kini punya
pasangannya: periksa dulu, reservasi, lalu catat.

---

## Sesi 33 — 2026-10-22 · Aktivitas mahasiswa ke Feeder, dan daftar periksa PIN

Pekerjaan #22 dan #28. Branch `feature/feeder-mbkm-pin`, dari `main` sesudah
PR #1, di-rebase ke `main` sesudah PR #2 (#20) masuk.

### Satu entitas PDDIKTI, tujuh entitas di sini

PDDIKTI memodelkan "Aktivitas Mahasiswa" sebagai satu catatan dengan tiga
satelit: anggota, pembimbing, penguji. Di sini faktanya hidup di dua tempat —
`aktivitas_mahasiswa` (MBKM) dan `tugas_akhir` — dan mesin sinkron mengirim satu
aksi per baris. Maka tujuh mapper kecil: aktivitas MBKM → anggota → pembimbing,
dan tugas akhir → anggota → pembimbing → penguji. Setiap satelit menyimpan id
balasan Feeder di kolomnya sendiri (`feeder_anggota_id`, `feeder_bimbing_id`,
dan `feeder_id` pada baris pembimbing/penguji), karena satu baris lokal kini
mewakili dua atau tiga catatan PDDIKTI.

Inilah jalur **pembimbingan dan pengujian ke SISTER**: SISTER membacanya dari
PDDIKTI, dan sampai hari ini tidak ada yang pernah dikirim.

### Yang sengaja tidak di-seed

Kode jenis aktivitas (`GetJenisAktivitasMahasiswa`) dan kategori kegiatan
(`GetKategoriKegiatan`) **tidak** dipetakan di `IntegrasiSeeder`, berbeda dari
status mahasiswa dan jenis keluar. Daftarnya berubah tiap siklus MBKM, dan kode
yang ditebak melaporkan magang seseorang sebagai hal lain. Validator menolak
dengan pesan yang menyebut referensi mana yang harus ditarik. Konsekuensinya
jujur: di instalasi demo, ketujuh entitas ini merah sampai operator memetakan
kodenya — dan itu memang urutan yang benar ("referensi dulu", CLAUDE.md §5).

Hanya penguji **sidang yang sudah terlaksana** yang dikirim; seminar proposal
dan panel yang baru dijadwalkan bukan ujian di mata PDDIKTI.

### Kesiapan PIN

`KesiapanPinService` menjawab, per lulusan, delapan hal yang membuat reservasi
nomor ijazah ditolak portal tanpa penjelasan: NIK 16 digit, identitas PDM
lengkap, registrasi tersinkron, AKM tiap semester tersinkron, seluruh nilai
final tersinkron, SKS memenuhi, yudisium ditetapkan dengan nomor SK, dan
status Lulus sudah sampai di PDDIKTI (`feeder_keluar_id` dari #21). Satu kohort
diperiksa dalam ≤ 6 kueri — tesnya memaku angkanya. Layar Yudisium mendapat
kolom "Siap PIN"; yang belum siap membuka daftar butirnya, bukan sekadar
jumlahnya, karena tiap butir diperbaiki di layar Feeder yang berbeda.

Yang belum: **konversi MBKM** (`InsertKonversiKampusMerdeka`) — digeser ke #23,
karena anggota aktivitasnya kini sudah punya id. Dan SKS diakui mahasiswa
pindahan sebagai butir PIN menunggu #25/#33.

**18 tes baru** (64 asersi).

---

## Sesi 32 — 2026-10-22 · Feeder: komponen evaluasi, dan IKU 7 yang akhirnya punya bukti

Pekerjaan #20. Branch `feature/feeder-komponen-evaluasi`, dibuka sesudah PR #1
di-*squash* ke `main` — CI hijau pertama repo ini sejak Agustus.

### Dua entitas, satu kait baru

| Entitas | Aksi | Sumber lokal |
|---|---|---|
| `komponen_evaluasi` | `InsertKomponenEvaluasiKelas` | `komponen_nilai` |
| `nilai_komponen` | `UpdateNilaiPerkuliahanKelasKomponenEvaluasi` | `nilai_komponen`, hanya di balik nilai final |

Yang kedua tidak punya aksi *Insert*: satu aksi yang meng-*upsert* dengan kunci
gabungan (registrasi, kelas, komponen). Mesin sinkron selama ini berasumsi
"insert dulu, update pakai id balasan". Tambahan kecilnya `kunciAlami()` di
`FeederMapper`: bila mapper menyatakan kunci alami, setiap dorongan adalah
*update* dengan kunci itu, dan balasan Feeder — yang untuk aksi ini tidak
membawa apa pun yang berarti — diabaikan. Tes pertama gagal justru karena
*fake client* mengembalikan UUID untuk segala hal dan mesin menyimpannya.

### Yang tersingkap: IKU 7 adalah flag tanpa bukti

`kelas_kuliah.is_case_method` sudah dikirim ke Feeder sejak Agustus. Tetapi
PDDIKTI tidak mengakui flag itu; ia mengakui **rencana evaluasi** yang bobot
partisipatif + proyeknya mencapai 50 %. Dan `komponen_nilai` tidak pernah punya
*jenis* — hanya nama dan bobot. Jadi setiap kelas kolaboratif di data demo
dilaporkan sebagai kolaboratif dan tidak akan dihitung.

Sekarang: enum `JenisEvaluasi` (partisipatif, proyek, tugas, kuis, UTS, UAS),
kolom `komponen_nilai.jenis_evaluasi`, komponen bawaan di `config/academic.php`
membawa jenisnya, dan validator menghitung ambangnya **sekali per kelas** —
sebagai peringatan, bukan penolakan: kelas tetap boleh dilaporkan, kampus
hanya diberi tahu bahwa IKU 7-nya tidak akan diakui. Angka 30 % dan 50 % ada
di pesannya, dan tesnya memaku angka itu.

Komponen lama di basis data yang sudah berjalan tidak ditebak jenisnya dari
nama; validator melaporkannya sebagai kosong. Bawaan baru (Tugas/UTS/UAS) tidak
mencapai ambang — dan itu benar: rencana evaluasi yang isinya tiga ujian memang
bukan kelas kolaboratif.

### Yang belum ada

Layar untuk mengubah komponen kelas. `simpanKomponen()` sudah menerima
`jenis`, tetapi tidak ada yang memanggilnya — komponen hanya lahir dari
konfigurasi bawaan. Dosen yang ingin kelasnya diakui IKU 7 belum punya tempat
menyusun rencananya. Dicatat di ROADMAP sebagai #20b.

**15 tes baru** (42 asersi); folder Feeder 65 hijau.

---

## Sesi 31 — 2026-10-22 · Feeder: dosen pengajar kelas dan Lulus/DO — dua entitas yang selalu punya tabel

Pekerjaan #19 dan #21 dari §Peta Jalan Lanjutan. Branch
`feature/feeder-pengajar-lulus-do`.

### Yang dikirim sekarang

| Entitas | Aksi | Sumber lokal |
|---|---|---|
| `dosen_pengajar` | `InsertDosenPengajarKelasKuliah` | `kelas_dosen` — kini punya model `KelasDosen` sendiri |
| `lulus_do` | `InsertMahasiswaLulusDO` | `mahasiswa` berstatus L/D/K, dari yudisium yang ditetapkan atau keputusan evaluasi studi |

Keduanya memakai mesin yang sama: hash payload, ledger, validator pra-kirim,
urutan dependensi. Tidak ada jalur khusus.

### Yang tersingkap

**`dosen.feeder_id` tidak pernah diisi.** Mapper kelas kuliah sudah mengirim
`id_dosen` sejak Agustus — tetapi kolomnya kosong di setiap baris, dan
`array_filter` membuangnya diam-diam. Dosen didaftarkan di PDDIKTI admin, bukan
lewat Feeder, jadi identitasnya hanya bisa datang dari **membaca daftar dosen
Feeder kembali** (`GetListDosen`), dicocokkan NIDN. Itu ditambahkan ke
`tarikReferensi()`. Praktisi tanpa NIDN tetap tanpa `id_dosen`, dan validator
mengatakannya — bukan menebak.

**Dua entitas, dua cara mengunci.** Dosen pengajar mendapat id baru dari Feeder
(`id_aktivitas_mengajar`). Lulus/DO dikunci pada `id_registrasi_mahasiswa` yang
sudah dipegang mahasiswa, dan balasan Feeder tidak membawa apa pun yang layak
disimpan. Tanpa penanganan, baris itu akan di-*insert* ulang tiap sinkron dan
ditolak sebagai duplikat sejak yang kedua. Jadi `FeederMapper` punya satu kait
baru, `feederIdBawaan()`: identitas yang dicatat bila balasan tidak membawanya.

### Tiga penolakan yang disengaja

1. **Team teaching tanpa porsi SKS** melaporkan seluruh SKS kelas ke tiap dosen —
   sebagai peringatan, bukan dibagi rata diam-diam. Membagi rata adalah
   kebijakan, dan `config/bkd.php` sudah menunjukkan kampus berbeda memilih beda.
2. **Mahasiswa berstatus Lulus yang yudisiumnya masih "diajukan"** masuk daftar
   dan ditolak validator — bukan jatuh dari daftar dan tetap *Aktif* di PDDIKTI
   satu semester lagi.
3. **Alasan tertulis keputusan DO tidak pernah dikirim.** `keterangan` berisi
   label keputusan; `catatan` milik kampus.

Pemetaan `jenis_keluar` (L→1, G→2, D→3, K→4) ditambahkan ke `IntegrasiSeeder`,
dengan catatan yang sama seperti status mahasiswa: kampus menimpanya dari
tarikan referensi, bukan dari kode.

**Layar monitor melewati anggaran kueri** — 39 dari 30 — begitu dua entitas
bertambah, karena `antre` dihitung dengan memuat seluruh baris beserta
relasinya lalu `count()`. Sekarang tiap mapper mendefinisikan `query()`, dan
`rows()` serta `jumlah()` diturunkan darinya: menghitung tidak lagi memuat.

**18 tes baru** (60 asersi); folder Feeder 50 hijau. Suite penuh di mesin
pengembang: 1.016 lolos dan 8 kegagalan yang sudah ada di `main` — fixture
jendela KRS yang rapuh tanggal, diperbaiki di sesi terpisah dan di-*cherry-pick*
ke branch ini (`krsDibuka()` pada `TahunAkademikFactory`).

### CI merah sejak Agustus, dan tidak ada yang melihat

PR pertama repo ini membuka hal yang lebih besar dari 8 tes: **170 kegagalan di
CI**, dan `main` sendiri sudah merah sejak 22 Agustus. Penyebabnya bukan kode
akademik:

1. **`Vite manifest not found`.** CI menjalankan Pest *sebelum* `npm run build`,
   dan sejak tata letak dipisah, `build/` ada di root dan di-ignore. Setiap
   layar membalas 500. Di mesin pengembang `build/` selalu ada — itulah sebabnya
   suite terlihat hijau dua bulan. Perbaikannya di suite, bukan di urutan CI:
   `TestCase::setUp()` memanggil `withoutVite()`. Suite tidak boleh bergantung
   pada artefak yang di-ignore git.
2. **14 tes SSO membalas 404.** Rute SSO didaftarkan saat boot hanya bila
   `sso.enabled`, jadi `config(['sso.enabled' => true])` di `beforeEach` sudah
   terlambat. Lolos di lokal karena `.env` pengembang menyetel `SSO_ENABLED=true`
   — temuan sesi perbaikan tes. `phpunit.xml` kini menyetelnya untuk suite.

Pelajarannya sama dengan yang di CLAUDE.md §9 tentang SQLite: **lingkungan
pengembang yang "kebetulan lengkap" menyembunyikan kegagalan yang pasti terjadi
di tempat lain.** Diverifikasi dengan menyembunyikan `build/` lalu menjalankan
suite penuh paralel di lokal.

---

## Sesi 30 — 2026-10-22 · Repo dibuka ke publik, dan peta jalan lanjutan dari dua kajian

### Repo publik

Remote GitHub dipasang dan 25 branch didorong. Sebelum itu diaudit: tidak ada
berkas `.env`, kunci, dump, atau log yang ter-track; `.env.example` tidak pernah
berisi nilai sepanjang riwayat; tidak ada pola token/kunci di 75 commit seluruh
branch. Satu hal kecil yang sudah telanjur publik: benih entropi `.rnd` (1 KB
acak, bukan kunci) ada di commit awal — tidak layak menulis ulang riwayat.

### Dua kajian, satu temuan yang mengubah premis

Dua hal dikaji: daftar fitur publik pembanding komersial, dan perilaku terkini
aplikasi pelaporan PDDIKTI (Neo Feeder) serta SISTER. Catatan sumbernya disimpan
lokal di `docs/lokal/` (dikecualikan lewat `.git/info/exclude`) — dokumen publik
hanya memuat kesimpulannya.

Temuannya: **SISTER tidak lagi punya web service per kampus.** Pengajaran,
pembimbingan, dan pengujian dosen masuk ke SISTER dari PDDIKTI. Jadi "Klien
SISTER menunggu kredensial" — yang bertahan di roadmap sejak 14 Agustus —
bukan tertahan, melainkan salah alamat. Baris itu dicabut.

Yang menggantikannya lebih memalukan sekaligus lebih murah: enam entitas Feeder
yang **sudah punya tabel lokal tetapi tidak pernah dikirim** — dosen pengajar
kelas (penghitung rasio, dan sumber pengajaran SISTER), komponen evaluasi (wajib
sejak 2024 gasal; IKU 7 diakui hanya bila partisipatif + proyek ≥ 50 %),
Lulus/DO (lulusan kita masih *Aktif* di PDDIKTI), aktivitas MBKM beserta
pembimbing dan pengujinya, prestasi, transfer kredit. Ditambah tidak adanya
`Update*`/`Delete*` sama sekali.

Risiko nomor ijazah lokal yang tercatat di MODUL.md kini punya rencana: daftar
periksa kesiapan PIN dari data lokal, dan `nomor_ijazah` yang diimpor, bukan
dibangkitkan.

Dari pembanding, kesenjangan besar yang baru hanya satu: **pendaftaran PMB
daring oleh calon** — pendaftar hari ini tidak punya akun. Sisanya kecil atau
sudah tercatat. Seluruhnya ditulis sebagai P4–P6 (#19–43) di ROADMAP.md.

### Dua koreksi dokumen

ROADMAP masih menyatakan "belum ada remote" — diperbarui. Nama produk
pembanding dihapus dari ROADMAP dan STATUS; dokumen publik cukup menyebut
"pembanding komersial".

### Delapan tes yang gagal sendiri — kalender yang berjalan, bukan kode

Suite dijalankan hari ini dan 8 tes gagal di `main` tanpa ada perubahan:
7 di `KurikulumLanjutanTest` (`kuliah paket`, `kurikulum konsentrasi`) dan 1 di
`KonversiKreditTest` (`kredit ganda`). Semuanya kandas di gerbang yang sama:
"Pengisian KRS belum dibuka atau sudah ditutup".

Kedua `beforeEach` membuka jendela KRS dengan menimpa `krs_mulai` dan
`krs_selesai` ke `now() ± 1 pekan`. Tetapi `TahunAkademik::krsDibuka()` menutup
jendela pada `krs_perubahan_selesai` bila kolom itu terisi — dan factory
`term(2026, Ganjil)` mengisinya: dua pekan sesudah 1 September, yakni 15
September 2026. Pada Agustus tanggal itu masih di depan; sejak 16 September
setiap tes yang menempuh `KrsService::tambahKelas()` lewat fixture ini
ditolak kalender. `PaketKuliahService::terapkan()` menelan pengecualian itu per
baris, sehingga di sana gejalanya `ditambahkan = 0`, bukan exception.

Aturan akademiknya benar dan tidak disentuh. Yang diperbaiki fixture-nya:
state factory baru `krsDibuka()` menjangkarkan ketiga tanggal ke hari ini,
dan kedua tes memakainya. Baris utangnya di ROADMAP ditandai lunas.

---

## Sesi 29 — 2026-08-11 · Rencana Kinerja & SPMI — dan sebuah batas yang turun satu tingkat

Dua modul di atas pohon unit kerja. Yang menarik bukan kodenya, melainkan
keputusan tentang **apa yang tidak dibangun.**

### IKU, SPMI, dan OKR adalah tiga bentuk, bukan satu

Pembedanya satu pertanyaan: **boleh tidak definisinya disunting?**

| | Definisi | Kalau disatukan |
|---|---|---|
| IKU | Ditetapkan kementerian, tidak boleh disentuh | Seseorang menyunting rumus IKU dari layar OKR, dan pelaporan ke kementerian rusak tanpa ada yang tahu |
| SPMI | Ditetapkan kampus, boleh direvisi antar-siklus | |
| OKR | Disusun ulang tiap periode | |

Karena itu yang dibangun adalah lapisan **perencanaan** (sasaran per unit +
cascade + target/realisasi), bukan dasbor IKU.

### Empat dari delapan IKU bukan data aplikasi ini

Dipetakan satu per satu ke pemilik datanya. Keterserapan lulusan, kemanfaatan
riset, kemitraan, dan pemeringkatan internasional tidak pernah lewat sini —
**itulah alasan batas dasbor bertahan**, dan alasan itu berlaku juga untuk borang
akreditasi.

### Batas SPMI: bukan digeser, tapi diletakkan lebih tepat

Dua dokumen yang baru ditulis di sesi ini menempatkan SPMI di Open Campus. Itu
disebutkan lebih dulu, bukan dilewati.

Penyelesaiannya: satu kata "SPMI" ternyata menutupi dua hal. **AMI** bersubjek
unit kerja dan temuannya kualitatif — dibangun di sini. **Borang akreditasi**
butuh data penelitian, PkM, keuangan, alumni — tetap di sana.

### Yang membuatnya audit, bukan daftar tugas

Tiga penolakan:

1. **Auditor tidak boleh mengaudit unitnya sendiri.** Yang mengaudit kantornya
   sendiri melaporkan pekerjaannya sendiri — dan temuan yang ia angkat terhadap
   dirinya adalah temuan yang boleh ia tutup sendiri.
2. **Temuan tertutup tidak dapat disunting.** Temuan yang bisa diubah setelah
   ditutup adalah temuan yang bisa dihaluskan menjelang asesmen lapangan.
3. **Tindak lanjut tidak diverifikasi pencatatnya.** Perbaikan yang diverifikasi
   pelaksananya hanya pernyataan kedua dari orang yang sama.

Tenggat diambil dari beratnya temuan, bukan dari formulir — mayor 30 hari, minor
90. Observasi boleh ditutup tanpa perbaikan, karena mewajibkannya membuat auditor
berhenti menuliskan observasi.

### Verifikasi yang hampir gagal — untuk keempat kalinya

Pembuktian ketiga penjaga di atas menghasilkan **`ganti=0` untuk ketiganya**:
substitusinya tidak cocok sama sekali, dan ketiga "lolos" itu tidak berarti
apa-apa. Pemeriksaan jumlah penggantian yang dipasang sejak modul kinerja
menangkap ketiganya sekaligus.

Ini pola yang sama dengan dua kerusakan di Sesi 28 (`KepegawaianService` yang
tertimpa, `Navigation.php` yang terpotong): **alat melapor sukses untuk pekerjaan
yang tidak dilakukannya.** `php -l` bilang "No syntax errors" pada berkas yang
kehilangan satu metode; `sed` mengembalikan 0 tanpa mengeluh.

### Satu N+1 yang hanya ditangkap angka

Layar `/admin/spmi` menghabiskan **26 kueri untuk 3 temuan**. Sebabnya
`rekapTemuan()` dan `temuanTerbuka()` menjalankan kueri yang identik —
duplikasi yang sama persis dengan `/dosen/rps` di Sesi 25. Dipecah jadi
`rekapDari(Collection)`; turun ke **21**, dan anggarannya dipaku di situ.

`preventLazyLoading` tidak menangkapnya: tidak ada yang di-*lazy load*, aplikasi
hanya bertanya dua kali.

### Tiga layar yang selama ini tanpa data demo

`unit-kerja`, `kinerja`, dan `spmi` tidak punya satu baris pun di
`DemoCampusSeeder`, jadi ketiganya tidak pernah masuk `SmokeLayarTest` —
anggaran kueri di atas tabel kosong tidak menguji apa pun. Ditambah
`MutuSeeder`, dan ketiganya dipaku: **12 / 16 / 21**.

Auditnya sengaja ditangkap di tengah siklus: satu temuan mayor lewat tenggat,
satu minor yang perbaikannya menunggu verifikasi orang kedua, satu observasi
yang sudah ditutup tanpa perbaikan sama sekali.

**894 tes hijau (1.924 asersi)**, naik dari 875.

---

## Sesi 28 — 2026-08-18 · P2 Tuntas: Evaluasi, Poin, Unit Kerja, Kepegawaian, Kuesioner

Lima modul, dan tiga di antaranya memutuskan hal yang sama: **sistem
mengamati, orang memutuskan.**

### Evaluasi studi — enum tanpa case "putus studi"

`HasilEvaluasi` sengaja tidak punya nilai bernama DO. Yang ada `TidakMemenuhi`.
Sistem boleh mengamati bahwa seorang mahasiswa di bawah semua ambang; pengamatan
itu tetap bukan keputusan mengakhiri pendidikan seseorang.

Sapuan selalu menulis `keputusan = menunggu` dan tidak pernah menyentuh
`mahasiswa.status`. Hanya `putuskan()` yang bisa, dan ia menuntut staf **plus
alasan tertulis** — karena kampus rutin memutuskan melawan aturannya sendiri,
dan memang seharusnya.

Semester cuti tidak dihitung sebagai semester tempuh. `status_mahasiswa.semester_ke`
menghitung semester kalender (konvensi PDDIKTI), jadi evaluasi menurunkannya
sendiri. Melepas filternya membuat dua tes gagal.

Ambang ikut dibekukan, bukan hanya angkanya: "24 SKS, gagal" kehilangan makna
begitu kampus menurunkan syaratnya jadi 20.

### Poin kemahasiswaan — dua buku besar yang tidak pernah dijumlahkan

Menjumlahkan prestasi dan pelanggaran berarti membiarkan mahasiswa menebus
sanksi dengan kemenangan lomba. Tidak ada metode yang mengembalikan selisihnya,
dan ada tes yang memeriksa daftar metode service serta menolak nama seperti
`bersih`, `total`, atau `saldo`.

Katalog di basis data (panjang, direvisi tiap tahun); ambang di config (angka
tunggal, keputusan kebijakan). Nilai poin disalin ke tiap catatan.

### Unit kerja — kolom teks bebas yang menghitung tiga kantor jadi satu

`staff.unit` berisi `BAAK`, `Baak`, `Bag. Akademik`. Kolomnya **tidak dihapus** —
ia bukti bagaimana seseorang dulu diarsipkan. Backfill sengaja bodoh: tebakan
salah tidak terlihat dan permanen, baris duplikat terlihat dan hilang satu klik.

Pohon berbasis penunjuk induk punya tepat satu mode kegagalan struktural:
lingkaran, dan ia senyap. Ditolak saat ditulis; melepas penjaganya membuat dua
tes gagal.

Dikerjakan **sebelum** #11 karena mutasi merujuk unit.

### Kepegawaian mendalam — hanya dua yang punya aturan

Keluarga, bahasa, organisasi, penghargaan dan sanksi adalah riwayat datar.
Pangkat dan mutasi berbeda: masing-masing punya nilai berlaku yang dibaca hal
lain.

Mutasi mengambil unit asal dari penunjuk, bukan formulir — formulir bisa dikirim
setelah orang lain memindahkannya. Dan keluar mengosongkan unitnya, karena itulah
cara sebuah rekap terus menghitung orang yang sudah mengundurkan diri.

### Kuesioner umum — anonimitas sebagai properti skema

Yang paling dijaga. Anonimitas EDOM struktural: tidak ada kolom yang bisa
menunjuk pengisi.

Godaan jelasnya satu tabel jawaban dengan kolom responden nullable. Dengan bentuk
itu anonimitas jadi properti **baris**, dan satu bug atau satu niat baik "ayo isi
datanya siapa yang mengisi" mengakhirinya diam-diam, untuk data yang sudah
terkumpul di bawah sebuah janji.

Dua tabel jawaban. Tesnya memeriksa **daftar kolom**, bukan perilaku.

### Dua tes saya yang tidak memaku apa pun

Pola yang berulang cukup sering untuk dicatat:

- **Poin**: versi pertama memberi 60 prestasi + 100 pelanggaran lalu memastikan
  syarat 50 terpenuhi — lolos juga tanpa pemisahan, karena 160 pun lewat 50.
  Arah berbahayanya justru pelanggaran ikut *menggenapi* syarat.
- **Evaluasi**: "tidak menimpa keputusan" memeriksa kolom yang `updateOrCreate`
  memang tidak sentuh, jadi lolos meski penjaganya dilumpuhkan.

Keduanya ditulis ulang dan diverifikasi dengan melumpuhkan penjaganya.

### Satu jebakan model

`PoinKategori::create()` mengembalikan instance yang `is_active`-nya masih null —
defaultnya milik kolom, bukan model. Baris berikutnya melihat kategori baru
sebagai tidak aktif. Diperbaiki dengan `$attributes`.

---
## Sesi 27 — 2026-08-17 · Cetak & Pengaturan Dokumen — P1 Perkuliahan Tuntas

Dua sisa P1 sekaligus, dan urutannya menentukan: **pengaturan dokumen dulu**.
Kebalikannya berarti membangun empat PDF dengan kop yang di-hardcode lalu
membongkarnya lagi.

### Item roadmap yang saya tolak apa adanya

Bunyinya "**kustomisasi templat dokumen**". Yang dibangun bukan itu.

Templat Blade yang tersimpan di basis data berarti **mengeksekusi kode yang
tersimpan di basis data**. Satu form admin yang bocor, satu peran yang salah
dipetakan, dan kampus kehilangan servernya — ditukar dengan keluwesan yang
hampir tidak pernah dipakai.

Yang sebenarnya berbeda antar kampus adalah **isinya**: baris alamat, siapa yang
bertanda tangan, catatan kaki. Itu setelan. Tata letaknya tetap di Blade, ikut
kendali versi, dan dapat ditinjau.

### Penandatangan yang sengaja tidak ada

Absensi dan jurnal ditandatangani **di kertas oleh yang hadir**. Nama pejabat
tercetak di situ bukan sekadar mubazir — ia keliru, karena orang itu tidak ada di
ruangan. Yang dibutuhkan ruang kosong.

Hal yang sama pada kolom kehadiran daftar hadir: dibiarkan kosong, tidak diisi
dari presensi digital. Tanda tangan basah adalah buktinya; mencetak apa yang
sistem sudah percayai ke dalamnya berarti mengganti bukti dengan salinan dugaan.

### Yang tidak tercetak di KTM

NIK, alamat rumah, dan nama orang tua tidak pernah masuk. Kartu adalah dokumen
yang paling sering hilang, difoto, dan diunggah.

QR-nya mengkodekan **NIM**, yang sudah tercetak di sebelahnya — sengaja bukan URL
verifikasi, karena itu berarti endpoint publik baru yang mengonfirmasi apakah
seseorang terdaftar di sini.

Tesnya memeriksa **HTML sebelum dirender**, bukan PDF-nya. dompdf memampatkan
aliran teksnya, jadi mencari string di dalam PDF akan selalu "lolos" dan tidak
membuktikan apa pun — tes yang berpura-pura memverifikasi.

### Penembusan loket yang saya buang

Rancangan awal kartu ujian punya `?paksa=1` agar loket dapat mencetak melewati
tahanan keuangan — untuk yang membayar tunai lima menit lalu.

Dibuang. **Unduhan tidak meninggalkan baris audit**, jadi penembusan itu membuat
tahanan keuangan dapat dilewati tanpa jejak sama sekali. Dua perbaikan yang
sebenarnya sama-sama berjejak: catat pembayarannya, atau setujui KRS-nya.

Penahanannya sendiri **kebijakan, bukan fakta**, jadi dapat dimatikan lewat
config — dan alasannya selalu disebutkan kepada mahasiswanya. Ambang kehadiran
ditanyakan ke `PresensiService::layakUas`, bukan dihitung ulang.

### Rute mahasiswa tanpa parameter

`/mahasiswa/ktm` dan `/mahasiswa/kartu-ujian` tidak menerima identitas sama
sekali. Endpoint yang menerima NIM butuh pemeriksaan kepemilikan, dan pemeriksaan
bisa salah ditulis — tidak menerima parameternya tidak bisa.

Jalur dosen memeriksa **pengampu**, bukan sekadar peran: daftar hadir memuat nama
seluruh peserta kelas.

Rinciannya di [`CETAK.md`](CETAK.md).

### Bug yang menunggu hari Minggu

Suite yang hijau kemarin gagal hari ini. Bukan karena pekerjaan hari ini —
**karena hari ini Minggu.**

`JadwalController::kelompokPerHari` mengisi Senin–Sabtu meski kosong agar grid
tidak berubah bentuk, jadi hanya kunci 7 yang benar-benar tidak ada. Kartu "Hari
Ini" mengindeksnya langsung: `$perHari[$hariIni]`. Setiap mahasiswa, setiap
Minggu, layar jadwalnya melempar `Undefined array key`.

Diperbaiki jadi `->get()`, dan ditambah tes dengan waktu dibekukan ke hari
Minggu — bug bergantung tanggal tidak boleh menunggu kalender untuk terlihat.
Hanya Minggu yang diuji; hari lain selalu punya kuncinya, jadi menyertakannya
berarti menambah kasus yang tidak bisa gagal.

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

**Dan sempat tidak ada yang memanggilnya.** Servicenya lengkap dan teruji, tapi
`berpaket()` nol pemanggil di aplikasi — mahasiswa di prodi berpaket tetap
memilih satu per satu. Fitur yang hanya dapat dijalankan oleh tes bukan fitur.
Layar KRS kini menawarkannya bila prodinya berpaket dan rencana masih draf, dan
menampilkan alasan tiap mata kuliah yang dilewati.

### Jalur menggerbang katalog

Mata kuliah bersama (jalur null) terbuka untuk semua — itu sebagian besar gelar.
Mata kuliah jalur ditolak bagi yang **belum** memilih jalur, bukan diloloskan:
meloloskannya berarti ia menempuh sesuatu yang dihitung ke syarat yang tidak
berlaku baginya, dan menemukannya saat yudisium jauh lebih mahal.

### Layar yang setuju dengan servicenya

Gerbang jalur mula-mula hanya dipasang di service. Katalog KRS punya filter
kurikulum sendiri lewat SQL dan tidak tahu apa pun tentang konsentrasi, jadi
mata kuliah jalur lain **tetap tampil dengan tombol "Ambil" yang hidup** lalu
ditolak saat ditekan — persis kegagalan yang docblock layar itu klaim dicegahnya.

`dalamKurikulum` kini publik dan ditanya oleh katalog, bukan diduplikasi.
Dibuktikan mengikat: melepas penjaganya membuat tesnya gagal seketika.

**Dan percobaan pertamanya salah.** Karena katalog menanyakannya sekali per
baris, saya memoisasi peta kurikulum pada objek servicenya. Cepat — dan **basi**
begitu ada mata kuliah ditambahkan ke kurikulum dalam proses yang sama. Dua tes
KRS yang sudah ada langsung gagal, dan queue worker atau proses Octane akan rusak
persis begitu tanpa ada yang menyadarinya.

Cache tersembunyinya dibuang. Peta itu sekarang **dioper**: layar mengambilnya
sekali lewat `petaKurikulum()` dan menyerahkannya per baris. Aturannya tetap satu
tempat di service; hanya datanya yang dibagi. Ditambah satu tes yang memaku
persis kebasian itu, supaya cachenya tidak diam-diam kembali.

Dua sebab dibedakan di layar, karena hanya satu yang dapat ditindaklanjuti
mahasiswa: "bukan untuk konsentrasi X" versus "tetapkan konsentrasi Anda dulu".

### Seeder yang diam-diam tidak melakukan apa pun

`KurikulumLanjutanSeeder` versi pertama mencari prodi lewat `kode` `'IF'`/`'SI'`.
Kolom itu berisi nomor PDDIKTI (`55201`), jadi pencariannya gagal — dan karena
ia `return` diam-diam, seluruh seeder berjalan "sukses" sambil menulis nol baris.
Ditemukan hanya karena datanya dihitung setelahnya.

Sekarang prodi ditemukan lewat mata kuliah yang memang dibutuhkan seeder ini,
dan ketiadaannya melempar exception. Seeder demo yang diam-diam tidak melakukan
apa pun lebih buruk daripada yang berhenti: layar kosong yang dihasilkannya
terbaca sebagai fitur rusak.

Demonya kini punya sejarah, bukan cuma fitur: satu kurikulum lama yang tidak
berlaku dengan 3 padanan ke penggantinya, dua konsentrasi dengan 4 mata kuliah
jalur beserta kelasnya, 17 dari 50 mahasiswa sudah memilih jalur (sisanya
sengaja belum), dan satu prodi berpindah ke `mode_krs = paket` dengan 2 paket
berisi 10 mata kuliah.

### N+1 yang tersembunyi di balik data demo yang kurus

Seeder yang lebih kaya membuat layar RPS dosen melampaui anggaran kuerinya:
**30 > 20**. Bukan karena datanya lebih banyak, tapi karena N+1-nya sudah ada
sejak awal dan hanya lolos selama dosen demo mengampu sedikit kelas.

`Rps::untuk()` dicari per kelas — dua kali, sekali di controller dan sekali lagi
di dalam `keterlaksanaan()` — ditambah `pertemuan` yang dimuat per baris. Kini
semua RPS untuk term itu diambil sekali dan dikunci per mata kuliah, `pertemuan`
di-eager-load, dan `keterlaksanaan()` menerima RPS-nya alih-alih mencarinya lagi.

**30 → 10 kueri**, dan yang lebih penting: tidak lagi tumbuh mengikuti beban
mengajar. Anggarannya diketatkan ke 12, karena anggaran longgar hanya
menyembunyikan kembalinya N+1 itu sampai ada dosen dengan beban penuh.

Anggaran `/mahasiswa/krs` naik 34 → 36 — tiga kueri konstan (peta kurikulum,
konsentrasi, prodi), bukan per baris katalog.

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

Satu batas yang perlu disebut terang: padanan mata kuliah **ada di demo tapi
belum menyentuh siapa pun** — kurikulum lama beserta padanannya terisi, namun
tidak ada mahasiswa demo yang memegang nilai dari kurikulum itu. Jadi layar admin
menunjukkan pemetaannya, sedangkan efeknya pada prasyarat hanya terlihat di tes.
Mahasiswa pindahan/angkatan lama di seeder adalah pekerjaan tersendiri.

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
pembandingnya kini satu SIAKAD komersial yang dipakai ratusan kampus.

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
