# NOTIFIKASI.md — Pemberitahuan

Sampai Sesi 18, seluruh sistem ini bersifat **tarik**: tidak ada satu pun yang
memberi tahu siapa pun tentang apa pun. Rencana studi disetujui dan mahasiswa
yang mengajukannya mengetahuinya dengan cara masuk dan melihat; tagihan jatuh
tempo dalam senyap.

Dua hal itu berakibat administratif — semester yang hilang, registrasi yang
terblokir. Diamnya bukan kenyamanan yang belum ada.

---

## Prasyarat Operasional

Dua hal harus hidup. Keduanya gagal dalam senyap bila tidak.

```bash
php artisan queue:work --tries=3
```

```cron
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

| Tanpa | Akibatnya |
|---|---|
| Worker antrean | **Tidak ada notifikasi apa pun terkirim.** Job menumpuk di tabel `jobs` |
| Entri cron | Tidak ada pengingat tenggat. Notifikasi berbasis kejadian tetap jalan |

Ini sudah tercantum pada daftar pra-produksi di [SECURITY.md](../SECURITY.md).

---

## Kanal

| Kanal | Bawaan | Dapat dimatikan |
|---|---|---|
| **Aplikasi** (lonceng + `/notifikasi`) | Selalu | Hanya untuk kategori tak wajib |
| **Surel** | Menyala bila SMTP dikonfigurasi | Selalu |
| **WhatsApp** | **Mati** | Perlu dua persetujuan — lihat di bawah |

Pemisahan yang menjadi inti rancangannya: **catatan dalam aplikasi adalah
catatan resminya**, itulah yang dapat ditunjuk seseorang ketika kelak
dipersoalkan apakah ia pernah diberi tahu. Surel dan WhatsApp hanyalah
pengantaran.

---

## Kategori, dan mengapa sebagian tidak dapat dimatikan

Pembedanya bukan tingkat kepentingan — semua terasa penting bagi yang
menuliskannya. Pembedanya **akibat**: apakah melewatkannya merugikan orang itu
dalam hal yang kelak ditagihkan kepadanya.

| Kategori | Wajib | Isi |
|---|---|---|
| Keuangan | ✅ | Tagihan terbit, pembayaran diterima |
| Akademik | ✅ | Keputusan KRS, nilai final |
| Tugas Akhir | ✅ | Keputusan judul, pembimbing, jadwal ujian |
| Kemahasiswaan | ✅ | Keputusan cuti, penetapan kelulusan |
| Pengingat | ⬜ | Tenggat yang mendekat |
| Sistem | ⬜ | Kegagalan Feeder dan webhook (staf) |

Yang wajib tetap menyimpan catatan aplikasi walau seluruh preferensi dimatikan.
Menawarkan sakelar mati untuk penolakan KRS berarti membiarkan seseorang
membungkam satu-satunya peringatan yang ia terima, lalu dikatakan seharusnya ia
tahu.

Pengingat **tidak** wajib, dan itu disengaja: keputusan yang diperingatkannya
tetap sampai lewat kategori wajib.

---

## Yang Dikirim

### Berbasis kejadian — seketika

| Kejadian | Penerima |
|---|---|
| KRS disetujui / dikembalikan | Mahasiswa |
| Nilai kelas difinalisasi | Seluruh peserta kelas itu |
| Tagihan diterbitkan | Mahasiswa |
| Pembayaran dicatat | Mahasiswa |
| Cuti diputus | Mahasiswa |
| Kelulusan ditetapkan | Mahasiswa |
| Judul tugas akhir diputus | Mahasiswa |
| Pembimbing ditetapkan | Mahasiswa **dan** dosen |
| Ujian dijadwalkan | Mahasiswa **dan** seluruh penguji |

Empat baris terakhir menutup celah yang modul tugas akhir munculkan tetapi tidak
dapat perbaiki sendiri.

### Terjadwal — `openacademic:kirim-pengingat`

| Pengingat | Hari (relatif tenggat) | Penerima |
|---|---|---|
| Tagihan jatuh tempo | H-7, H-1, H, H+7 | Mahasiswa |
| Batas pengisian KRS | H-7, H-3, H-1 | **Hanya** yang belum mengajukan |
| Batas revisi tugas akhir | H-7, H-1, H, H+7 | Mahasiswa dan pembimbing |
| Antrean bimbingan | Mingguan | Dosen, sebagai satu rangkuman |

Diatur di [`config/notifikasi.php`](../config/notifikasi.php).

```bash
php artisan openacademic:kirim-pengingat --kering
```

Mode kering menghitung tanpa mengirim **dan tanpa mengklaim kunci** — gladi
bersih yang menghabiskan kunci akan membungkam jalannya yang sungguhan
sesudahnya.

---

## Tiga Aturan yang Ditegakkan

### 1. Mengumumkan tidak boleh membatalkan

`Notifier` menelan setiap kegagalan pengiriman dan mencatatnya. Peristiwanya
sudah terjadi — cuti itu sudah disetujui. Server surel yang tak terjangkau tidak
boleh mengubah kenyataan tersebut.

Dijaga tes yang menyabot mail driver lalu memastikan cutinya tetap disetujui.

### 2. Tidak ada yang keluar sebelum transaksinya commit

`queue.connections.database.after_commit = true`, disetel sekali untuk **seluruh**
job, bukan per kelas. Tanpa itu seorang mahasiswa bisa diberi tahu rencana
studinya disetujui padahal basis data tidak menyimpan apa pun.

### 3. Pengingat yang sama tidak terkirim dua kali

Tabel `notifikasi_kunci`, dengan indeks unik. Kuncinya memuat orangnya, tenggat
yang mana, dan offset keberapa — sehingga H-7 dan H-1 adalah dua pengingat,
sedangkan penjadwal yang berjalan dua kali semalam hanya menghasilkan satu.

Pengingat yang datang tiap malam melatih orang mengabaikan kanalnya, dan pesan
yang benar-benar penting ikut terabaikan bersamanya.

---

## WhatsApp — seam, bukan integrasi

Ini kanal yang benar-benar dipakai kampus Indonesia. Tidak ada adaptor penyedia
yang disertakan, dengan alasan yang sama seperti Midtrans: tiap penyedia punya
model akun, proses persetujuan template, dan harga per pesan sendiri, dan
adaptor yang ditebak adalah adaptor yang tidak bisa dijalankan siapa pun.

Menulisnya:

```php
class FonnteGateway implements WhatsAppGatewayInterface
{
    public function kirim(string $nomor, string $pesan): bool { /* … */ }
    public function nama(): string { return 'fonnte'; }
}
```

Daftarkan menggantikan binding di `AppServiceProvider::register()`, lalu:

```env
NOTIFIKASI_WHATSAPP_DRIVER=fonnte
NOTIFIKASI_WHATSAPP_KATEGORI=keuangan,pengingat
```

**Dua persetujuan terpisah, dan itu disengaja.** Memasang penyedia bukan berarti
memutuskan bahwa setiap nilai yang terbit harus sampai ke ponsel seseorang pukul
23.00. Tiap pesan berbiaya uang bagi kampus dan berbiaya perhatian bagi
penerimanya.

Nama driver yang tidak dikenal **menggagalkan resolusi**, bukan diam-diam
kembali ke log. "Terkonfigurasi tetapi sebenarnya tidak mengirim" adalah keadaan
yang tidak akan disadari sampai hari pengumuman.

Nomor telepon disamarkan pada log driver `log`: log dibaca lebih banyak orang
daripada basis data, dan dikirim ke agregator.

---

## Catatan Portabilitas

Kategori disimpan pada **kolomnya sendiri**, bukan hanya di dalam muatan JSON.
Menyaring `data->kategori` berjalan di MySQL dan SQLite lalu gagal di
PostgreSQL, tempat kolom teks tidak punya operator JSON — persis kelas
perbedaan yang [BASIS-DATA.md](BASIS-DATA.md) peringatkan. Ditulis oleh
`DatabaseKategoriChannel`.

Pelanggaran indeks unik ditangkap lewat `UniqueConstraintViolationException`
milik Laravel, bukan dengan mencocokkan kode SQLSTATE sendiri. Versi pertama
`Notifier` melakukan yang kedua dan tidak pernah cocok.
