# AKUNTANSI.md — Integrasi Easy Accounting

Open Academic mencatat **tagihan dan pembayaran**. Ia tidak menyusun jurnal
berpasangan, tidak punya bagan akun, dan tidak akan pernah punya — itu pekerjaan
sistem akuntansi.

Modul ini adalah jembatannya ke **Easy Accounting (easyERP)**.

---

## Opsional, dan Mati Sampai Dinyalakan

`AKUNTANSI_DRIVER` punya tiga nilai, bawaannya **`nonaktif`**:

| Nilai | Yang terjadi |
|---|---|
| `nonaktif` | Tidak ada yang dicatat. Tanpa baris outbox, tanpa menu Akuntansi, tanpa perintah terjadwal. |
| `palsu` | Dokumen diantre dan dapat diekspor, tetapi tidak ada yang dikirim. |
| `easyerp` | Terhubung sungguhan. |

**Penagihan berjalan sama persis pada ketiganya.** Mematikan integrasi tidak
mengubah satu pun angka di sisi Open Academic — ada tesnya, dan itu yang
dimaksud "tidak mengikat".

Banyak kampus memegang buku besarnya di tempat lain atau mengerjakannya manual.
Mereka tidak boleh menanggung antrean dokumen yang tidak akan pernah dikirim,
menu untuk sistem yang tidak mereka punya, atau proses tiap lima menit yang
tidak ada kerjanya.

Bawaannya `nonaktif` dan bukan `palsu` karena instalasi baru tidak semestinya
diam-diam mulai menumpuk data untuk sistem yang belum tentu dipakai. Layar
Akuntansi tetap dapat dijangkau saat nonaktif — ia menjelaskan cara
menyalakannya, bukan mengembalikan 404.

`AKUNTANSI_DRIVER=` yang kosong dibaca sebagai `nonaktif`, bukan sebagai driver
tak dikenal. Menebak ke arah sebaliknya berarti setiap penerbitan tagihan
melempar exception.

Tabelnya tetap dibuat oleh migrasi meski nonaktif. Dua tabel kosong tidak
berbiaya, dan migrasi bersyarat membuat skema berbeda antar lingkungan — jenis
perbedaan yang baru terasa saat pemulihan cadangan.

---

## Kenapa Ini Berbeda dari SISTER

API v1 easyERP dirancang persis untuk kasus ini. Dokumennya sendiri menyebut:
*"integrasi aplikasi vertikal ... mendorong master data & transaksi ke easyERP.
Mapping akuntansi dikerjakan otomatis server-side — app vertikal cukup kirim data
bisnis."*

Kontraknya terdokumentasi dan dapat diuji, jadi **klien sungguhannya ditulis
sekarang** — tidak seperti SISTER, yang seam-nya ada tetapi adaptornya tidak.
Tidak ada satu baris pun di sini yang ditulis melawan tebakan.

---

## Pemetaan

| Peristiwa di Open Academic | Dokumen | Jurnal yang terbentuk |
|---|---|---|
| Mahasiswa ditagih pertama kali | `POST /contacts` | — |
| Tagihan terbit | `POST /invoices` | Dr Piutang, Cr Pendapatan *(oleh easyERP)* |
| Beasiswa / keringanan | `POST /journals` | Dr Beban Beasiswa, Cr Piutang |
| Pembayaran diterima | `POST /journals` | Dr Kas/Bank, Cr Piutang |
| Pembayaran dibatalkan | `POST /journals` | Dr Piutang, Cr Kas/Bank |

Invoice tidak membawa kode akun: easyERP menurunkannya sendiri dari `sub_type`
Receivable dan `type` revenue. Yang menyebut kode akun hanya jurnal yang kita
susun sendiri.

---

## Outbox, Bukan Panggilan Langsung

Setiap peristiwa ditulis ke `akuntansi_dokumen` lebih dulu, lalu dikirim
terpisah tiap lima menit. Dua alasan, keduanya pernah menjatuhkan instalasi
sungguhan:

- Menerbitkan tagihan untuk lima ribu mahasiswa tidak boleh menunggu lima ribu
  panggilan HTTP.
- Sistem akuntansi yang sedang mati **tidak boleh dapat menggagalkan penagihan.**
  Utangnya ada, terbukukan atau tidak.

Bentuknya mengikuti `feeder_sync_logs` yang sudah ada — repo ini sudah
menyinkronkan ke sistem luar dengan cara itu, dan pola ketiga untuk pekerjaan
yang sama hanya menambah tempat orang harus belajar.

`PenjurnalanService` juga menelan kegagalannya sendiri dan mencatatnya ke log,
prinsip yang sama dengan `Notifier`: **mencatat peristiwa di buku besar tidak
boleh dapat membatalkan peristiwanya.** Uangnya sudah diterima.

---

## Kunci Idempotensi

Kolom paling menentukan di seluruh modul ini.

```
oa-inv-<uuid tagihan>
oa-bayar-<uuid pembayaran>
oa-diskon-<id baris tagihan>
oa-kontak-<uuid mahasiswa>
```

**Diturunkan dari peristiwanya, tidak pernah diacak.** Kunci acak yang dibuat
ulang saat retry bukan kunci idempotensi — ia jaminan duplikat pertama kali
jaringan menjatuhkan respons setelah easyERP terlanjur commit.

Uniknya dijaga di dua tempat: indeks unik lokal (sehingga duplikat tidak dapat
mengantre) dan header `Idempotency-Key` (sehingga duplikat tidak dapat
terbukukan). Dibuktikan mengikat — mengganti kuncinya jadi acak membuat dua tes
gagal seketika, dan salah satunya menagih mahasiswa dua kali.

Perhatikan `oa-diskon` memakai **id baris**, bukan nominalnya. Seorang mahasiswa
dapat menerima dua keringanan bernilai persis sama dalam satu semester; kunci
berbasis nominal akan menelan yang kedua sebagai duplikat, dan kampus sudah
memberikan uangnya tanpa pernah membukukannya.

---

## Bruto, Bukan Netto

Pilihan yang diambil kampus lewat `AKUNTANSI_PERLAKUAN_BEASISWA`, bawaannya
`bruto`:

```
Invoice     Dr Piutang        5.000.000
              Cr Pendapatan               5.000.000
Jurnal      Dr Beban Beasiswa 2.000.000
              Cr Piutang                  2.000.000
                              ─────────
Piutang bersih                3.000.000   ← yang benar-benar ditagihkan
Pendapatan                    5.000.000   ← tarif penuh
Beban beasiswa                2.000.000   ← yang dikeluarkan kampus
```

Pada perlakuan `netto`, invoice-nya langsung 3.000.000 dan angka beasiswa lenyap
dari laporan: yang terlihat hanya pendapatan yang lebih kecil, tanpa sebab. Itu
sah secara pembukuan, tetapi menghapus angka yang biasanya dicari yayasan dan
pemberi hibah.

---

## Kode Akun Adalah Kebijakan

Seluruhnya di [`config/akuntansi.php`](../config/akuntansi.php), tidak satu pun
di dalam service. Bagan akun berbeda antar kampus. Sikap yang sama dengan bobot
SKS di `config/bkd.php` dan dengan `IkuDataController` yang menolak menerapkan
ambang.

**Penyebab kegagalan tersering** adalah kode di config yang belum dibuat di
easyERP. Layar Akuntansi menampilkan pesan asli dari sana, bukan sekadar
"gagal", karena "422 Unprocessable" tidak memberi tahu siapa pun apa yang harus
diperbaiki.

PPN bawaannya **dikecualikan** — jasa pendidikan tidak kena PPN. Kampus yang
menagih komponen kena pajak lewat badan yang sama perlu menyalakan
`AKUNTANSI_KENA_PPN` *dan* memastikan akun "PPN Keluaran" ada di seberang.

---

## Kegagalan: Menunda, Lalu Menyerah

| Sebab | Perlakuan |
|---|---|
| Tidak ada jawaban (jaringan) | Tunda, backoff 1→2→4→8 menit |
| 5xx, 429 | Tunda |
| 422, 401, 4xx lain | **Langsung gagal** |
| Sudah `maks_percobaan` kali | Gagal |

Menyerah itu disengaja. Dokumen yang diulang selamanya menyembunyikan sebabnya
di balik angka percobaan yang membesar dan tidak dibaca siapa pun. Yang gagal
muncul di layar Akuntansi, tempat seseorang memperbaiki sebabnya lalu menekan
Ulangi.

Satu pembedaan halus yang ditemukan lewat tes dan bukan lewat pemikiran:
**kegagalan sesaat saat membuat kontak menunda invoice-nya, bukan mematikannya.**
Versi pertama memperlakukan semua kegagalan dependensi sebagai terminal, yang
mengubah gangguan jaringan lima detik menjadi antrean dokumen mati yang harus
dikembalikan manusia satu per satu.

---

## Ekspor CSV

Bukan sekadar cadangan untuk API, melainkan yang tetap bekerja ketika API tidak:
kunci kedaluwarsa, host tidak terjangkau, atau kampus memang belum menyambungkan
apa pun. Pelaporan keuangan tidak boleh tergantung pada sehatnya sebuah
integrasi.

Satu baris per **baris jurnal**, bukan per dokumen, supaya total debit dan kredit
dapat dijumlahkan dan dicocokkan tangan — hal pertama yang akan dilakukan
siapa pun yang menerimanya.

---

## Privasi

Kontak yang dikirim memuat nama, surel, telepon, dan **NIM** — tidak NIK, tidak
alamat rumah, tidak data orang tua. Bentuk yang sama dipakai Campus Bridge dan
berkas yang dikirim lewat surel; payload yang aman di satu kanal dan tidak di
kanal lain akan bocor lewat kanal yang paling ceroboh. Ada tesnya.

---

## Menyalakan

```bash
php artisan openacademic:kirim-akuntansi --kering
```

```env
AKUNTANSI_DRIVER=easyerp
AKUNTANSI_BASE_URL=https://app.easyaccounting.co.id/api/v1
AKUNTANSI_API_KEY=...
```

Urutan yang disarankan: `palsu` dulu selama satu siklus penagihan, periksa
antrean dan ekspor CSV-nya, cocokkan kode akun — baru `easyerp`. Layar Akuntansi
mengatakan dengan keras bahwa `palsu` tidak mengirim apa pun, karena instalasi
yang mengira dirinya terhubung akan berjalan berbulan-bulan dengan buku besar
kosong dan tidak ada yang memberi tahu.

API Key diterbitkan per tenant di **Pengaturan Usaha → Integrasi** pada sisi
easyERP. Satu kunci = satu badan hukum.

---

## Yang Belum Ada di Sisi easyERP

**Endpoint pembayaran.** API v1 mencantumkannya sebagai rencana lanjutan.
Penerimaan kas karenanya dikirim sebagai jurnal Dr Kas/Bank, Cr Piutang: buku
besarnya benar, tetapi status invoice di sana tidak ikut berubah menjadi lunas.
Status pelunasan yang sahih untuk saat ini ada di Open Academic, dan layar
Akuntansi menyatakan itu.

Bila endpoint itu ditambahkan, yang berubah hanya `PenjurnalanService` —
`pembayaranDiterima()` mengantre dokumen invoice-payment alih-alih jurnal.
Sisanya tidak tersentuh.

**Webhook `invoice.paid` keluar.** Juga direncanakan. Ketika ada, Open Academic
dapat berhenti menjadi satu-satunya sumber kebenaran status pelunasan untuk
pembayaran yang masuk lewat easyERP.

---

## Berkas

```
config/akuntansi.php
database/migrations/2026_08_15_100000_create_akuntansi_tables.php

app/Enums/{JenisDokumenAkuntansi,StatusDokumenAkuntansi,JenisEntitasAkuntansi}.php
app/Models/Akuntansi/{DokumenAkuntansi,PemetaanAkuntansi}.php

app/Services/Akuntansi/Contracts/AkuntansiClientInterface.php
app/Services/Akuntansi/EasyAccountingClient.php   — klien sungguhan
app/Services/Akuntansi/AkuntansiPalsu.php         — tes & demo
app/Services/Akuntansi/PenjurnalanService.php     — peristiwa → dokumen
app/Services/Akuntansi/PengirimAkuntansi.php      — outbox → easyERP
app/Services/Akuntansi/EksporJurnal.php           — CSV
app/Services/Akuntansi/{HasilKirim,DependensiGagal}.php

app/Console/Commands/KirimAkuntansiCommand.php
app/Http/Controllers/Admin/AkuntansiController.php
resources/views/admin/akuntansi.blade.php

database/seeders/Demo/AkuntansiSeeder.php
tests/Feature/Akuntansi/AkuntansiTest.php
```
