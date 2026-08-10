# SPMI.md — Standar Mutu & Audit Mutu Internal

> **Status: dibangun 11 Agustus 2026.**
>
> Yang dibangun di sini adalah **AMI** — standar mutu, audit, temuan, tindak
> lanjut. **Borang akreditasi tetap di Open Campus.**
>
> Ini mempertegas §Sengaja Bukan di Sini, bukan membatalkannya. Lihat
> [Batas yang ditarik ulang](#batas-yang-ditarik-ulang) di bawah.

---

## Batas yang ditarik ulang

Dua dokumen menempatkan "SPMI" di Open Campus: `ROADMAP.md` §Sengaja Bukan di
Sini, dan keputusan C di [`KINERJA.md`](KINERJA.md). Keduanya benar — untuk hal
yang mereka maksud.

Masalahnya, "SPMI" dipakai untuk dua hal yang bentuk datanya berbeda:

| | Subjeknya | Datanya | Di mana |
|---|---|---|---|
| **AMI** — audit mutu internal | Unit kerja | Kualitatif: temuan, akar masalah, tindak lanjut | **Open Academic** |
| **Borang akreditasi** | Institusi / prodi | Kuantitatif lintas domain: penelitian, PkM, keuangan, alumni | **Open Campus** |

Subjek AMI adalah pohon unit kerja — yang sudah dimiliki aplikasi ini, dan yang
tidak dimiliki Open Campus. Temuannya kualitatif, jadi ia tidak menunggu data
dari mana pun.

Borang meminta angka penelitian, PkM, keuangan, dan keterserapan alumni.
**Empat dari delapan IKU pun bukan data Open Academic** — itulah alasan batas
dasbor bertahan, dan alasan yang sama berlaku untuk borang.

Jadi batasnya bukan bergeser, melainkan turun satu tingkat: **yang bersubjek
unit kerja dibangun di sini; yang butuh data lintas domain tetap di sana.**

---

## Kenapa ini audit, bukan daftar tugas

Tiga penolakan. Tanpa ketiganya, modul ini hanya daftar pekerjaan dengan kolom
tanggal.

### 1. Auditor tidak boleh mengaudit unitnya sendiri

Yang mengaudit kantornya sendiri sedang melaporkan pekerjaannya sendiri — dan
temuan yang ia angkat terhadap dirinya adalah temuan yang boleh ia tutup
sendiri. Seluruh instrumen bergantung pada ini.

```php
// SpmiService::pastikanAuditorIndependen()
if ($unitAuditor !== null && (int) $unitAuditor === (int) $unit->id) {
    throw new AturanAkademikException('… tidak dapat mengaudit unitnya sendiri.');
}
```

Dapat dimatikan lewat `SPMI_TOLAK_AUDIT_SENDIRI=false`, karena kampus kecil
kadang benar-benar tidak punya cukup auditor. Bawaannya **menolak** — dan
mematikannya jadi keputusan sadar yang tercatat di config, bukan kelalaian yang
tidak pernah terlihat.

### 2. Temuan yang sudah ditutup tidak dapat disunting

Temuan yang dapat diubah setelah ditutup adalah temuan yang dapat dihaluskan
menjelang asesmen lapangan. Menutup searah; bila persoalannya kembali, ia
dicatat sebagai temuan baru pada audit berikutnya.

### 3. Tindak lanjut tidak diverifikasi oleh pencatatnya

Perbaikan yang diverifikasi pelaksananya bukan verifikasi — ia hanya pernyataan
kedua dari orang yang sama, dan jejaknya tidak dapat membedakan keduanya
sesudahnya.

---

## Dua keputusan yang mungkin tak terduga

**Tenggat diambil dari beratnya temuan, bukan dari formulir.** Mayor 30 hari,
minor 90 — angkanya di `config/spmi.php`. Ketidaksesuaian mayor yang diberi
sembilan puluh hari oleh yang mengetiknya adalah kampus yang diam-diam
menurunkan aturannya sendiri.

**Observasi dan saran boleh ditutup tanpa perbaikan.** Mewajibkan tindak lanjut
untuk keduanya membuat auditor berhenti menuliskannya — dan justru catatan
ringan itulah yang paling sering berguna tahun berikutnya. Pembedanya
`wajib_tindak_lanjut` di config.

**Menutup audit tidak menutup temuannya.** Temuan lazim berumur lebih panjang
daripada audit yang mengangkatnya; perbaikannya berjalan berminggu-minggu
sesudahnya. Memaksakan penutupan berarti memalsukan catatan atau menghalangi
auditor menyelesaikan pekerjaannya.

---

## Skema

Lima tabel, `2026_08_19_100001_create_spmi_tables.php`.

| Tabel | Isi |
|---|---|
| `standar_mutu` | Standar + **pernyataan** (kolom teks tersendiri) + siklus PPEPP + `melampaui_sndikti` |
| `indikator_standar` | Indikator per standar; `indikator_kunci` **nullable** |
| `audit_mutu` | Satu audit: unit, auditor, tanggal, status |
| `temuan_audit` | Temuan: jenis, uraian, akar masalah, tenggat, status |
| `tindak_lanjut_temuan` | Rencana, realisasi, dan verifikasi oleh orang lain |

Dua pilihan kolom yang perlu dijelaskan:

**`pernyataan` terpisah dari `nama`.** Sebuah standar *dirujuk* dengan namanya
dan *diaudit* dengan pernyataannya. Auditor yang harus menyimpulkan pernyataan
dari sebuah nama akan menyimpulkannya berbeda dari auditor berikutnya.

**`indikator_kunci` boleh null.** Ia menunjuk katalog indikator rencana kinerja
bila angkanya memang dapat dihitung aplikasi ini. Null berarti indikator ini
diperiksa auditor dengan mata, bukan dihitung — dan itu mayoritas standar mutu.
Menyediakan kolomnya membuat yang dapat dihitung tidak perlu diketik ulang;
tidak mewajibkannya membuat sisanya tetap jujur sebagai penilaian manusia.

**`auditor_dosen_id` / `auditor_staff_id`, keduanya nullable, hanya satu diisi.**
Alasan yang sama dengan kepala unit: auditor mutu lazim seorang dosen, tapi bisa
juga staf penjaminan mutu. Memaksakan satu tabel berarti mengarang baris palsu
di tabel lainnya.

**PPEPP disimpan sebagai keadaan standar, bukan tabel.** PPEPP adalah putaran
yang dilalui satu standar berulang kali; tabel per tahap menghasilkan lima baris
yang menceritakan satu hal.

---

## Alur

```
Standar mutu ditetapkan
        ↓
Audit direncanakan  →  auditor diperiksa independensinya  →  ditolak bila unitnya sendiri
        ↓
Audit dimulai       →  hanya status "berlangsung" menerima temuan
        ↓
Temuan dicatat      →  tenggat diturunkan dari jenisnya
        ↓
Tindak lanjut dicatat  →  temuan pindah ke "ditindaklanjuti"
        ↓
Diverifikasi orang lain  →  ditolak bila pencatatnya sendiri
        ↓
Temuan ditutup      →  uraiannya beku
```

Audit boleh ditutup kapan saja di antara itu; temuannya berjalan terus.

---

## Layar

`/admin/spmi` — satu layar, izin `pengaturan.view` untuk membaca dan
`pengaturan.manage` untuk mengubah.

Isinya: rekap temuan terbuka (yang lewat tenggat berdiri sendiri sebagai satu
angka yang menuntut tindakan hari ini), daftar temuan terbuka dengan tindak
lanjutnya, daftar audit, dan daftar standar mutu.

---

## Config

`config/spmi.php`:

| Kunci | Isi |
|---|---|
| `jenis_temuan` | mayor / minor / observasi / saran — label, tone, `wajib_tindak_lanjut`, `tenggat_hari` |
| `ppepp` | Lima tahap siklus |
| `tolak_audit_unit_sendiri` | `SPMI_TOLAK_AUDIT_SENDIRI`, bawaan `true` |

Ambang dan tenggat adalah **kebijakan**, jadi ia di config — bukan konstanta di
dalam service, dan bukan kolom yang boleh diketik ulang tiap temuan.

---

## Tes

`tests/Feature/Spmi/AuditMutuTest.php` — 19 tes.

Ketiga penolakan di atas dibuktikan mengikat dengan melumpuhkan barisnya satu
per satu dan memastikan tesnya gagal. Percobaan pertama menghasilkan `ganti=0`
untuk ketiganya — substitusinya tidak cocok sama sekali, dan ketiga "lolos" itu
tidak berarti apa-apa. Pemeriksaan jumlah penggantian yang dipakai sejak modul
kinerja menangkap ketiganya.

---

## Yang sengaja tidak ada

| | Alasan |
|---|---|
| Borang akreditasi (LKPS/LED) | Butuh data penelitian, PkM, keuangan, alumni — Open Campus |
| Dasbor 12 IKU | Idem; lihat [`KINERJA.md`](KINERJA.md) |
| Templat dokumen audit yang dapat disunting pengguna | Templat di basis data berarti mengeksekusi kode yang disimpan pengguna — ditolak sebagai desain, sama seperti di [`CETAK.md`](CETAK.md) |
| Pembukaan kembali temuan yang sudah ditutup | Itu persis yang dijaga |
