# Notifikasi Instalasi ke Humanix

> Keputusan desain: [ADR-043](DECISIONS.md). Kode: `app-core/app/Services/Instalasi/`.

Open Academic gratis dan berlisensi MIT. Siapa pun boleh memasangnya tanpa
memberi tahu siapa pun, dan itu memang disengaja. Akibatnya pemelihara tidak
tahu kampus mana yang memakai aplikasi ini, versi mana yang masih berjalan,
dan mesin basis data apa yang perlu terus diuji.

Notifikasi instalasi menjawab pertanyaan itu **hanya bila kampus setuju**.
Halaman ini menjelaskan apa yang dikirim, kapan dikirim, ke mana tujuannya,
dan cara mematikannya.

## Ringkas

- **Bawaannya mati.** Tidak ada yang dikirim sampai seseorang mencentang kotak
  persetujuan di langkah terakhir wizard setup, atau di **Pengaturan →
  Notifikasi Instalasi ke Humanix**. Kotak itu tidak pernah tercentang dengan
  sendirinya.
- **Isinya sepuluh medan tetap** tentang institusi dan perangkat lunaknya, ditambah
  satu medan opsional. Tidak ada data mahasiswa, dosen, atau staf.
- **Tidak berkala.** Satu kabar saat pertama dipasang, lalu satu lagi hanya
  ketika versi aplikasi berubah.
- **`HUMANIX_TELEMETRI=false` mematikannya untuk selamanya**, apa pun pilihan
  di layar.
- **Kegagalan tidak berdampak.** Pengiriman lewat antrean dengan batas waktu
  pendek. Bila humanix.id tidak terjangkau, setup dan deploy tetap berjalan
  dan kegagalannya hanya dicatat.

## Yang dikirim

Satu permintaan `POST` berisi JSON ke `HUMANIX_TELEMETRI_URL`:

| Medan | Isi | Contoh |
|---|---|---|
| `installation_id` | UUID acak yang dibuat sekali, saat setup atau saat pengiriman pertama. **Tidak diturunkan** dari data kampus, jadi tidak dapat dicocokkan balik dengan daftar kode PT. | `3f0c…-…` |
| `event` | `installed` (kabar pertama) atau `upgraded` (versi berubah) | `installed` |
| `institution_name` | Nama perguruan tinggi | `Institut Teknologi Lembah Hijau` |
| `institution_code` | Kode PT PDDIKTI, enam digit | `041234` |
| `city` | Kota/kabupaten | `Salatiga` |
| `province` | Provinsi | `Jawa Tengah` |
| `app_version` | Versi Open Academic (`config/instalasi.php`) | `1.1.0-dev` |
| `php_version` | Versi PHP, **hanya mayor.minor**. Versi patch tidak dikirim karena menunjukkan perbaikan keamanan mana yang belum terpasang. | `8.4` |
| `db_engine` | Jenis mesin basis data saja, tanpa host, nama basis data, atau versinya | `mariadb` |
| `date` | Tanggal pengiriman | `2026-10-31` |
| `app_url` | **Opsional.** `APP_URL`, hanya bila kotak kedua dicentang. Biarkan kosong bila alamatnya hanya dapat diakses dari jaringan kampus. | `https://siakad.itlh.ac.id` |

**Tidak pernah dikirim:** data mahasiswa, dosen, atau staf dalam bentuk apa
pun (termasuk jumlahnya), alamat dan kontak kampus, kata sandi atau kredensial
apa pun, `APP_KEY`, isi `.env`, serta alamat server atau jaringan internal.

Isi persis kabar berikutnya selalu dapat dilihat sebelum dikirim, tanpa
mengirim apa pun:

- di **Pengaturan → Notifikasi Instalasi ke Humanix**, atau
- lewat perintah:

```bash
php artisan openacademic:telemetri
```

Menambah medan di daftar ini adalah perubahan janji kepada kampus. Perubahan
seperti itu wajib lewat ADR baru, memperbarui halaman ini, dan meminta
persetujuan ulang. Tes `TelemetriTest` memaku muatannya medan demi medan
supaya perubahan yang tidak disengaja langsung ketahuan.

## Kapan dikirim

1. **Saat setup selesai**, bila kotak persetujuan dicentang.
2. **Saat persetujuan dinyalakan di Pengaturan**, bila kabar untuk versi yang
   berjalan belum pernah terkirim.
3. **Setelah `php artisan migrate`**, yang dijalankan setiap pembaruan. Bila
   versi aplikasi sudah berbeda dari kabar terakhir, satu kabar `upgraded`
   dikirim. Menjalankan `migrate` berkali-kali pada versi yang sama tidak
   mengirim apa pun.

Tidak ada jadwal atau cron, dan tidak ada "masih hidup" berkala. Satu versi
paling banyak tercatat satu kali: tabel `installation_pings` punya indeks unik
pada `app_version`. Kabar yang dulu gagal dikirim ulang memakai baris yang sama,
bukan menambah baris baru.

## Ke mana

`HUMANIX_TELEMETRI_URL`, bawaannya
`https://humanix.id/api/open-academic/v1/installations`.

> **Catatan.** Endpoint penerima di humanix.id **tidak ada di repo ini** dan
> dibangun terpisah. Selama endpoint itu belum ada, setiap kabar tercatat
> lokal sebagai gagal. Akibatnya tidak ada, kecuali satu baris di riwayat.

Kampus yang ingin menerima kabarnya sendiri (misalnya yayasan yang membawahi
beberapa PT) dapat mengarahkan `HUMANIX_TELEMETRI_URL` ke endpoint miliknya.

## Mematikan

| Cara | Akibat |
|---|---|
| Hapus centang di **Pengaturan** | Berhenti mengirim. Kabar yang masih antre dibatalkan, bukan dikirim. Tercatat di Log Aktivitas. |
| `HUMANIX_TELEMETRI=false` di `.env` | Berhenti untuk selamanya, **apa pun pilihan di layar**. Kotak centang tidak ditampilkan. Jalankan `php artisan config:cache` bila konfigurasi di-cache. |
| `HUMANIX_TELEMETRI_DRIVER=palsu` | Kabar disusun dan dicatat, tetapi tidak pernah menyentuh jaringan. Cocok untuk server tanpa akses internet. |

`HUMANIX_TELEMETRI=true` **tidak** menyalakan pengiriman. Lingkungan hanya
dapat mencabut persetujuan, tidak dapat memberikannya.

## Yang tercatat di instalasi Anda

Setiap kabar, berhasil maupun tidak, tersimpan di tabel `installation_pings`:

- versi,
- jenis kabar,
- status (`queued`, `sent`, `failed`, `cancelled`),
- jumlah percobaan,
- kode HTTP,
- pesan galat,
- waktu kirim,
- **isi persis yang dikirim**.

Sepuluh baris terakhir tampil di Pengaturan dan di `openacademic:telemetri`.

Perubahan persetujuan tercatat di Log Aktivitas sebagai
`telemetry_consent_changed`, lengkap dengan nama yang mengubahnya.

## Rincian teknis

- Klien di balik `InstalasiReporterInterface`. Ada dua implementasi:
  `HttpInstalasiReporter` dan `InstalasiReporterPalsu`.
- `KirimPingInstalasiJob` memeriksa ulang persetujuan dan menyusun muatan pada
  saat kirim. Persetujuan yang dicabut ketika kabar masih antre tetap dihormati.
- Batas waktunya 5 detik, dengan batas tersambung 3 detik. Setiap kabar dicoba
  paling banyak 3 kali, dengan jeda 1 menit lalu 10 menit
  (`config/instalasi.php`).
- Pada `QUEUE_CONNECTION=sync` tidak ada percobaan ulang. Percobaan "tertunda"
  di driver sync langsung berjalan, dan orang yang sedang menunggu setup akan
  ikut menunggu setiap batas waktunya.
