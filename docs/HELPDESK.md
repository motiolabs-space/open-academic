# HELPDESK.md — Tiket Layanan Kampus

Setiap kampus punya loket yang bukan loket: nomor WhatsApp bagian akademik,
surel ke keuangan, kolega yang "kenal orang TIK". Pertanyaan yang sama dijawab
tiga kali oleh tiga orang, dan yang keempat tidak pernah dijawab karena
pesannya tenggelam.

Modul ini memberi pertanyaan itu nomor, unit yang bertanggung jawab, dan jam
yang berdetak.

---

## Apa yang Ini, dan Apa yang Bukan

**Ini helpdesk administratif.** Mahasiswa atau dosen mengajukan tiket kepada
unit kerja kampus — BAAK, keuangan, TIK — dan unit itu menjawab. Satu tiket,
satu pemohon, satu unit penanggung jawab pada satu waktu.

**Ini bukan forum, bukan chat, bukan fitur sosial.** Tidak ada tiket yang
dapat dibaca pemohon lain, tidak ada reaksi, tidak ada utas lepas dari tiket.
Pengaduan etik dan percakapan antarmahasiswa adalah wilayah Open Campus
([`CLAUDE.md`](../CLAUDE.md) §1), dan sengaja tidak punya tempat di sini.

---

## Empat Tabel

| Tabel | Isi |
|---|---|
| `helpdesk_kategori` | Nama, unit tujuan bawaan, SLA jam |
| `helpdesk_tiket` | Nomor, pemohon (polimorfik mahasiswa/dosen), status, prioritas, unit & petugas, jam SLA |
| `helpdesk_balasan` | Balasan publik dan catatan internal, lampiran |
| `helpdesk_faq` | Pertanyaan, jawaban, terbit/draf |

Kategori adalah yang mengubah "seseorang mengetik keluhan" menjadi "bagian
keuangan punya tiket": ia menamai unit dan menjanjikan waktu tanggapan. Tiket
**membekukan** keduanya saat diajukan — kategori yang disunting bulan depan
tidak memindahkan garis finis tiket yang sudah ada di antrean.

---

## Status, dan Tabel Transisinya

```
baru ─┬─▶ didisposisi ─┬─▶ diproses ─┬─▶ menunggu_pemohon ─┬─▶ selesai ─▶ ditutup
      │                │             │                     │
      └────────────────┴─────────────┴─────────────────────┴─▶ ditutup
```

Yang penting bukan urutannya, melainkan **tabel di `StatusTiket::tujuanSah()`**:
satu-satunya tempat yang menyatakan langkah mana yang sah, dan service menolak
apa pun di luarnya. Layar hanya meminta; service yang memutuskan — dan penjaga
yang sama berjalan untuk formulir, perintah artisan, maupun tes.

Beberapa langkah bukan tombol, melainkan **efek samping**:

| Peristiwa | Akibat pada status |
|---|---|
| Staf membalas publik | → menunggu pemohon; petugas pertama yang menjawab *mengambil* tiket bila belum ada yang ditunjuk |
| Pemohon membalas tiket "menunggu pemohon" | → diproses |
| Pemohon membalas tiket "selesai" | → diproses (**dibuka kembali**) |
| Pemohon membalas tiket baru/didisposisi | tidak berubah — tambahan informasi, bukan serah terima |
| Catatan internal | tidak pernah mengubah apa pun |

**Ditutup tidak ke mana-mana.** Tiket yang ditutup tidak menerima balasan dari
siapa pun; pemohon membuka tiket baru, dan jejak yang lama tetap seperti saat
ditutup. Menutup wajib beralasan — pemohon membacanya.

"Menunggu pemohon" ada supaya jam SLA bisa membedakan dua jenis menunggu: tiket
yang menunggu kampus itu terlambat; tiket yang menunggu orang yang
mengajukannya, tidak.

---

## Tiga Lingkaran Otorisasi

`HelpdeskTiketPolicy`, dari dalam ke luar:

| Siapa | Izin | Lingkup |
|---|---|---|
| Pemohon | `helpdesk.ajukan` (dosen & mahasiswa) | Tiketnya sendiri, persis |
| Staf unit | `helpdesk.view` + `helpdesk.tangani` | Antrean unitnya, plus tiket yang ditugaskan kepadanya **by name** |
| BAAK / super-admin | `helpdesk.manage` | Seluruh kampus; juga kategori & FAQ |

Tidak ada lingkaran "dosen wali", sengaja. Tiket bisa memuat bukti transfer
atau surat dokter, dan pemohon mengajukannya dengan satu unit dalam pikirannya.

Staf yang ditugaskan dari unit lain melihat tiket itu karena namanya tertulis
di sana — bukan karena unitnya. Pimpinan (`helpdesk.view` saja) membaca
antrean unitnya tanpa bisa mendisposisi atau membalas.

**Catatan internal disaring di SQL, bukan di template.** Controller pemohon
memuat `balasanPublik()`; template tidak pernah tahu ada yang disembunyikan,
jadi suntingan template tidak dapat membocorkannya. Lampiran catatan internal
mengikuti aturan yang sama di `BerkasController`.

---

## SLA: Menandai, Bukan Memutuskan

```bash
php artisan openacademic:helpdesk-sla            # tiap jam lewat scheduler
php artisan openacademic:helpdesk-sla --kering   # hitung saja
```

Perintah ini **hanya menandai** `lewat_sla_at` pada tiket yang masih menunggu
kampus dan tenggatnya lewat. Tidak menutup, tidak mengalihkan, tidak memberi
tahu pemohon. Tenggat yang terlewat adalah fakta untuk antrean unit — tampil
paling atas, bertanda merah — bukan keputusan yang boleh diambil penjadwal
atas nama unit itu.

Idempotent: tiket distempel sekali; menjalankannya tiap jam tidak menstempel
dua kali. `HelpdeskTiket::lewatSla()` juga membaca jam secara langsung, jadi
layar sudah benar sebelum perintah sempat berjalan.

| Nilai `sla_jam` kategori | Artinya |
|---|---|
| Kosong | Ikut `HELPDESK_SLA_JAM` (bawaan 72) |
| `0` | Tanpa tenggat — bukan "tidak penting", melainkan "tidak dijanjikan" |
| Angka | Jam sejak tiket diajukan |

---

## Notifikasi

Kategori `KategoriNotifikasi::Helpdesk`, **dapat dimatikan** — mengikuti garis
yang ditarik [`NOTIFIKASI.md`](NOTIFIKASI.md): melewatkan balasan helpdesk
merugikan seseorang berupa jawaban yang lebih lambat, bukan satu semester.
Keputusan yang ditanyakannya tetap tiba lewat kategori wajib masing-masing.

| Peristiwa | Pemohon diberi tahu |
|---|---|
| Didisposisi ke unit | ✅ — menyebut unitnya, supaya ada tempat untuk datang bila portal tak kunjung menjawab |
| Dibalas publik oleh staf | ✅ — baris pertama balasan |
| Selesai / ditutup | ✅ |
| Catatan internal, perubahan prioritas, "diproses" | ❌ |

---

## Layar

| Portal | Rute | Isi |
|---|---|---|
| Mahasiswa | `/mahasiswa/helpdesk` | Daftar tiket sendiri, formulir buat, lima FAQ teratas |
| Dosen | `/dosen/helpdesk` | Layar yang sama — satu controller, awalan rute berbeda |
| Staf | `/admin/helpdesk` | Antrean: lewat SLA dulu, lalu prioritas, lalu paling lama tak tersentuh |
| Staf | `/admin/helpdesk/pengaturan` | Kategori & FAQ (`helpdesk.manage`) |
| Publik | `/bantuan` | FAQ terbit, tanpa login, boleh diindeks |

Halaman FAQ publik ada tanpa login karena orang yang paling perlu membaca
"bagaimana mengatur ulang kata sandi" adalah yang sedang tidak bisa masuk. Ia
tidak memuat nama siapa pun — satu-satunya halaman publik modul ini.

Lampiran lewat [modul berkas](../app-core/app/Services/Berkas/BerkasService.php):
disk privat, nama acak, tipe diperiksa dari isi. Unduhan lewat
`/berkas/helpdesk/...` dan selalu melalui policy tiketnya.

---

## Konfigurasi

```env
HELPDESK_POLA_NOMOR="HD-{tahun}-{urut}"   # disebut lewat telepon, jadi pendek
HELPDESK_SLA_JAM=72                        # bawaan bila kategori tidak menetapkan
```

Nomor tiket diklaim di balik indeks unik `nomor` dengan percobaan ulang, bukan
dengan `max()+1` lalu berharap — dua pengajuan serentak tidak boleh berebut
nomor yang sama.

---

## Berkas

```
database/migrations/2026_10_24_100000_create_helpdesk_tables.php
app/Enums/StatusTiket.php                        — tabel transisi
app/Enums/PrioritasTiket.php
app/Models/Helpdesk/{HelpdeskKategori,HelpdeskTiket,HelpdeskBalasan,HelpdeskFaq}.php
app/DTOs/Helpdesk/{TiketBaruData,BalasanData,DisposisiData}.php
app/Services/Helpdesk/HelpdeskService.php
app/Policies/Helpdesk/HelpdeskTiketPolicy.php
app/Notifications/Helpdesk/{NotifikasiTiket,TiketDidisposisi,TiketDibalas,TiketSelesai}.php
app/Console/Commands/HelpdeskSlaCommand.php
app/Http/Requests/Helpdesk/*.php
app/Http/Controllers/Helpdesk/{TiketPemohonController,FaqPublikController}.php
app/Http/Controllers/Admin/{HelpdeskController,HelpdeskPengaturanController}.php
resources/views/helpdesk/**, resources/views/admin/helpdesk/**
lang/id/helpdesk.php                             — seluruh teks UI modul
database/seeders/Demo/HelpdeskSeeder.php
tests/Feature/Helpdesk/{HelpdeskServiceTest,HelpdeskPolicyTest,LayarHelpdeskTest}.php
```
