# DESIGN.md — Sistem Desain "Midnight Executive"

> **Kit rujukan untuk proyek lain.** Ditulis 11 Agustus 2026 karena PD Dikti
> akan memakai tata letak dan CSS Open Academic sebagai acuan.
>
> Sumber kebenaran tetap `app-core/resources/css/app.css` dan
> `app-core/resources/views/`. Dokumen ini menjelaskan yang **tidak muat di
> dalam token**; tokennya sendiri ada di [`design/tokens.css`](design/tokens.css),
> siap tempel.

---

## Cara memakai kit ini

| Berkas | Isi | Sifatnya |
|---|---|---|
| [`design/tokens.css`](design/tokens.css) | 30 token warna, bentuk, elevasi, tata letak + guilloché + 3 animasi | **Salin apa adanya** |
| `DESIGN.md` (dokumen ini) | Aturan, kontrak komponen, kerangka layar | Dibaca, lalu ditulis ulang dalam kerangka tujuan |

Urutan yang saya sarankan: tempel token → bangun `Card`, `Button`, `Chip` →
bangun kerangka layar → sisanya menyusul saat dibutuhkan. Membangun sepuluh
komponen sekaligus sebelum ada satu layar pun menghasilkan komponen yang
propnya ditebak.

**Yang jangan dibawa:** ikon glyph teks (`⌗ ◎ ⚖`). Itu kompromi karena Blade
tidak punya pustaka ikon, bukan keputusan desain. PD Dikti sudah punya
`lucide-react` — pakai itu.

---

## Delapan aturan yang menjaga sistem ini tetap satu

Semua yang lain turunan dari sini.

**1. Jangan pernah menulis hex di komponen.** Selalu token. Warna yang ditulis
langsung tidak ikut ketika paletnya bergeser, dan tidak ada yang menemukannya
sampai satu layar terlihat berbeda sendiri.

**2. Kartu pakai garis, bukan bayangan.** Hanya overlay yang boleh terangkat —
toast, modal, popover. Kalau sebuah kartu butuh bayangan supaya terlihat, yang
kurang biasanya kontras garisnya.

**3. Emas dipakai hemat.** Emas menandai satu hal per layar: satu angka utama,
satu tindakan utama, atau aksen merek. Emas di tiga tempat sekaligus membuat
ketiganya berhenti berarti apa-apa.

**4. Warna semantik sengaja diredam.** Tabel penuh chip jenuh terbaca seperti
dasbor konsumer. Ini catatan resmi; warnanya tidak boleh berteriak lebih keras
daripada datanya.

**5. `tabular-nums` di semua tabel.** Nilai, SKS, NIM, rupiah, dan tanggal
dibaca dengan cara dibandingkan antar-baris. Sudah otomatis di `@layer base`;
untuk angka di luar tabel pakai kelas `.tabular`.

**6. Serif hanya untuk judul halaman dan angka utama.** Source Serif 4 dipakai
di `h1` dan angka besar `stat-card`. Sisanya sans. Serif di badan teks membuat
layar administrasi terbaca seperti artikel.

**7. Guilloché jarang.** Motif ukir dokumen resmi — header navy, empty state,
pratinjau dokumen. Ia bekerja justru karena jarang.

**8. `ink-faint` hanya untuk teks pendukung.** Ia 3,01:1 terhadap canvas —
sengaja di bawah ambang WCAG AA untuk teks normal (4,5:1), karena menaikkannya
ke sana menghasilkan `#707278` yang praktis sama dengan `ink-muted` `#6e7078`,
dan skala tinta tiga tingkat runtuh jadi dua. Harganya dibayar dengan aturan:
pakai untuk meta, hint, placeholder, dan ruas breadcrumb yang bukan terakhir —
**jangan pernah untuk informasi yang tidak ada di tempat lain di layar itu.**

### Kontras yang sudah diukur

| Pasangan | Rasio | Ambang AA |
|---|---|---|
| `ink` di canvas | 14,19:1 | 4,5 |
| `ink-muted` di canvas | 4,64:1 | 4,5 |
| `ink-faint` di canvas | **3,01:1** | 4,5 — sengaja, lihat aturan 8 |
| chip `success` | 4,56:1 | 4,5 |
| chip `warning` | 4,54:1 | 4,5 |
| chip `danger` | 5,08:1 | 4,5 |
| chip `info` | 12,06:1 | 4,5 |
| judul kelompok sidebar `gold/90` di navy | 5,25:1 | 4,5 |

Angka-angka ini hasil audit otomatis atas 53 layar, bukan taksiran. Kalau palet
digeser, ukur ulang — jangan diperkirakan.

---

## Kerangka layar

```
┌────────────┬──────────────────────────────────────────────┐
│            │  Topbar 60px                                 │
│  Sidebar   ├──────────────────────────────────────────────┤
│  248px     │                                              │
│  (68px     │   main · max-w-1240px · px-4 sm:px-7          │
│   ciut)    │   pt-6 · pb-28 mobile / pb-16 desktop        │
│            │                                              │
│  bg-navy   │   ┌── PageHeader ────────────────────────┐   │
│            │   │ breadcrumb                           │   │
│            │   │ h1 serif 28px      [aksi kanan]      │   │
│            │   │ konteks 13px muted                   │   │
│            │   └──────────────────────────────────────┘   │
│            │                                              │
│            │   konten                                     │
└────────────┴──────────────────────────────────────────────┘
             Bottom nav (mobile saja, sidebar disembunyikan <md)
```

| Ukuran | Nilai | Kenapa |
|---|---|---|
| Sidebar | `248px` / ciut `68px` | Token `--spacing-sidebar` |
| Topbar | `60px` | Token `--spacing-topbar` |
| Lebar konten | `max-w-[1240px]` | Tabel administrasi butuh lebar; lebih dari ini barisnya jadi terlalu panjang untuk dilacak mata |
| Padding bawah mobile | `pb-28` | Bottom nav melayang menutupi baris terakhir tabel kalau kurang |

Sidebar `sticky top-0 h-screen`, disembunyikan di bawah `md` dan digantikan
bottom nav.

### Sidebar: enam kelompok, bukan daftar rata

Menu dikelompokkan menurut **pekerjaan**, bukan menurut entitas. Daftar rata
berisi dua puluh tautan memaksa orang memindai seluruhnya tiap kali.

Portal staf, apa adanya dari `app-core/app/Support/Navigation.php`:

| Kelompok | Jml | Isi |
|---|---|---|
| *(tanpa judul)* | 1 | Dasbor |
| **AKADEMIK** | 5 | Master Akademik · Jadwal & Kelas · Padanan & Paket · Koreksi Nilai · Penutupan Semester |
| **MAHASISWA** | 10 | Data Mahasiswa · PMB · Cuti · Konversi Kredit · Evaluasi Studi · Poin Kemahasiswaan · Tugas Akhir · Yudisium · Wisuda · Surat & Dokumen |
| **SDM** | 5 | Kepegawaian Dosen · Akun Staf · Unit Kerja · Beban Kerja Dosen · Evaluasi Dosen |
| **KEUANGAN** | 3 (+1) | Matriks Tarif · Tagihan & Rekonsiliasi · Beasiswa & Keringanan *(+ Integrasi Akuntansi bila aktif)* |
| **PELAPORAN** | 4 | Neo Feeder PDDIKTI · Campus Bridge · Verifikasi Data IKU · Log Aktivitas |
| **SISTEM** | 4 | Rencana Kinerja · SPMI & Audit Mutu · Pengumuman · Pengaturan |

Perhatikan **MAHASISWA berisi sepuluh item** sementara yang lain tiga sampai
lima. Itu bukan kelompok yang gagal dipecah — memang di situ pekerjaan
administrasi kampus menumpuk, dan memecahnya jadi dua kelompok berisi lima
hanya memindahkan masalah memindai ke tingkat kelompok.

Judul kelompok: `text-[10px] font-bold tracking-[0.14em] text-gold/75`.
Kelompok pertama sengaja tanpa judul — "DASBOR" di atas satu item bernama
Dasbor adalah baris yang tidak mengatakan apa pun.

### Satu detail aksesibilitas yang mahal ditemukan

Tautan lompat-ke-konten **tidak boleh** pakai `sr-only` + `focus:not-sr-only`.
`sr-only` menetapkan `width:1px; height:1px`, dan `focus:not-sr-only` kalah
spesifisitas — tautannya tetap jadi titik 1px meski sedang difokus.

Yang bekerja: parkir dengan transform.

```html
<a href="#konten"
   class="fixed left-4 top-4 z-50 -translate-y-24 rounded-control bg-navy
          px-4 py-2.5 text-[13px] font-semibold text-canvas
          transition-transform focus:translate-y-0">
  Lompat ke konten utama
</a>
```

Tanpa ini, mencapai konten dengan papan ketik berarti melewati **32 tautan
sidebar** lebih dulu, di setiap halaman.

*(Komentar di `layouts/app.blade.php` masih menyebut "empat belas" — itu angka
saat komentarnya ditulis, dan sudah usang. Yang benar 32.)*

---

## Kontrak komponen

Prop dan string kelasnya lengkap, jadi versi React dapat dibangun tanpa
membuka repo ini. Sumber aslinya di `app-core/resources/views/components/`.

### Card

```
title?: string    meta?: string    flush?: boolean
```

Bungkus: `overflow-hidden rounded-card border border-line bg-surface`
Header (bila ada `title`): `flex items-baseline justify-between gap-3 border-b border-line px-5 py-3.5`
— judul `text-[14.5px] font-semibold`, meta `text-xs text-ink-faint`
Badan: `px-5 py-4`, dihilangkan bila `flush` (untuk tabel yang menempel tepi).

### Button

```
variant: primary | gold | outline | outline-gelap | ghost | danger    size: sm | md
href?: string  → render <a>, selain itu <button>
```

Dasar:
`inline-flex items-center justify-center gap-2 rounded-control font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50`

| size | Kelas |
|---|---|
| `sm` | `px-3 py-1.5 text-[12px] min-h-[32px]` |
| `md` | `px-5 py-2.5 text-[13.5px]` |

`sm` ada untuk tindakan **di dalam baris tabel** — tombol ukuran penuh akan
menentukan tinggi baris dan membuat daftar panjang tak terbaca. `min-h-[32px]`
dipertahankan supaya tetap nyaman disentuh di mobile.

| variant | Kelas |
|---|---|
| `primary` | `bg-navy text-canvas hover:bg-navy-hover` |
| `gold` | `bg-gold font-bold text-navy hover:bg-gold-hover` |
| `outline` | `border border-line-input bg-surface text-navy hover:border-navy` |
| `ghost` | `text-navy hover:bg-line/60` |
| `danger` | `border border-danger-line bg-surface text-danger hover:bg-danger-bg` |
| `outline-gelap` | `border border-canvas/30 bg-transparent text-canvas hover:border-gold` |

**Jangan mengirim `text-*` sendiri lewat `class` untuk melawan warna varian.**
Yang menentukan pemenang adalah urutan di stylesheet, bukan urutan di atribut —
jadi `text-canvas` yang dikirim pemanggil bisa kalah oleh `text-navy` milik
varian `outline`. Landing page Open Academic sempat menampilkan tombol navy di
atas hero navy: teksnya ada, warnanya sama persis dengan latarnya, dan
`assertOk()` tetap hijau. Itu sebabnya `outline-gelap` jadi varian tersendiri.

`danger` berlatar terang, bukan merah pekat. Tombol merah menyala membuat orang
ragu pada tindakan yang sebenarnya wajar (membatalkan draf), dan mati rasa pada
yang benar-benar berat.

### Chip

```
tone: neutral | success | warning | danger | info | gold    dot?: boolean
```

Dasar: `inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-[5px] text-xs font-semibold`

| tone | Kelas |
|---|---|
| `success` | `bg-success-bg text-success border-success-line` |
| `warning` | `bg-warning-bg text-warning border-warning-line` |
| `danger` | `bg-danger-bg text-danger border-danger-line` |
| `info` | `bg-info-bg text-info border-info-line` |
| `neutral` | `bg-line/50 text-ink-muted border-line` |
| `gold` | `bg-gold/15 text-warning border-gold/40` |

`whitespace-nowrap` penting: chip status yang terbelah dua baris merusak tinggi
baris tabel.

### StatCard

```
label: string    value?: string|number    meta?: string    trend?: string
feature?: boolean
```

Biasa: `rounded-card border border-line bg-surface p-[18px]`
`feature`: `relative overflow-hidden rounded-card bg-navy p-[18px] text-canvas`
+ lapisan `guilloche-gold` absolut.

Label `text-[11px] font-semibold tracking-[0.08em]` huruf besar.
Angka `tabular mt-1 font-serif text-[30px] font-semibold leading-none`
(emas bila `feature`).

**`feature` dipakai sekali per layar.** Ia untuk satu angka yang paling
penting. Dua kartu feature berdampingan berarti tidak ada yang paling penting.

`value` boleh kosong — isi slot dengan chip kalau angkanya bukan bilangan.

### Alert

```
tone: info | success | warning | danger    icon?: string (bawaan "!")
slot `action` opsional, rata kanan
```

`flex flex-wrap items-center gap-3 rounded-[10px] border px-[18px] py-3.5 text-[13px]`
+ pasangan `bg-*/border-*` sesuai nada. Teks isinya `text-ink`, bukan warna
nada — nada ada di latar dan garis, sehingga kalimat panjang tetap terbaca.

### Field

```
label: string    name: string    type: text|date|textarea|checkbox|…
value? options? required? hint? placeholder?
```

Satu definisi untuk semua form, supaya enam layar master tidak membawa enam
salinan markup yang sama — dan perbaikan aksesibilitas mendarat di satu tempat.

Label: `mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted`
Input: `w-full rounded-control border bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10`
Galat: `border-danger bg-danger-bg/30` + `aria-invalid` + `aria-describedby`
menunjuk `<p>` pesan di bawahnya.

Yang wajib ikut: `id` unik yang menyambungkan `<label for>` ke input, dan
`aria-describedby` ke pesan galat. Keduanya yang membuat pembaca layar
menyebutkan galatnya, bukan sekadar mewarnai kotaknya merah.

### PageHeader

```
title: string    context?: string    breadcrumb?: Record<string,string>|string[]
slot → tindakan, rata kanan
```

Breadcrumb `text-xs text-ink-faint`, tautan `hover:text-gold`, ruas terakhir
`font-semibold text-ink` tanpa tautan.
Judul `font-serif text-[28px] font-semibold leading-tight`.
Konteks `mt-1.5 text-[13px] text-ink-muted`.

### EmptyState

```
title: string    description?: string    slot → tombol
```

`guilloche-navy relative rounded-card border border-dashed border-line-input px-6 py-12 text-center`

Garis **putus-putus**, bukan penuh: ia menandai tempat yang akan terisi, bukan
wadah yang memang kosong.

### Toast

Bukan komponen mandiri di React — di sini ia tumpukan Alpine yang menerima
flash session. PD Dikti sudah punya `sonner`; pakai itu, dengan gaya berikut.

Posisi: `fixed inset-x-4 bottom-24 sm:inset-x-auto sm:bottom-6 sm:right-6 sm:max-w-sm`
(`bottom-24` di mobile supaya tidak tertutup bottom nav).

| Jenis | Gaya |
|---|---|
| sukses | `bg-navy text-canvas`, tanda `✓` emas |
| galat | `border-l-4 border-danger bg-surface text-ink` |
| peringatan | `border-l-4 border-warning bg-surface text-ink` |

Toast sukses navy penuh; galat dan peringatan putih dengan pita kiri. Sukses
boleh lewat tanpa dibaca — galat harus tetap terbaca meski latarnya ramai.

---

## Pola tabel

Tabel adalah bentuk utama aplikasi ini, jadi pola berikut yang paling sering
dipakai.

```html
<table class="w-full text-[12.5px]">
  <thead>
    <tr class="border-b border-line text-left text-[11px] uppercase text-ink-muted">
  <tbody>
    <tr class="border-b border-line/60 align-top">
```

Bungkus selalu `<div class="overflow-x-auto">` — kolom tabel administrasi
melebihi lebar ponsel, dan yang boleh menggulir mendatar adalah tabelnya,
bukan halamannya.

`align-top` karena satu sel sering berisi dua baris (nama + keterangan kecil),
dan penjajaran tengah membuat kolom lain melayang.

### Baris yang bisa dibuka: satu `<tbody>` per baris

Kalau baris rincian adalah **saudara** dari baris ringkasnya, keadaan
buka/tutup harus dipegang elemen yang membungkus **keduanya**. HTML mengizinkan
banyak `<tbody>` dalam satu `<table>` — itu pembungkusnya.

```html
<table>
  <thead>…</thead>
  {#each rows}
    <tbody>            ← state buka/tutup di sini
      <tr>ringkas</tr>
      <tr hidden>rincian</tr>
    </tbody>
  {/each}
</table>
```

Menaruh state di `<tr>` ringkas terlihat benar dan tidak pernah bekerja:
baris rincian di luar jangkauannya. Halaman tetap tampil normal, tombolnya
mati, dan tidak ada tes yang menangkapnya.

---

## Tipografi

| Peran | Nilai |
|---|---|
| Badan | Public Sans, `13.5px` |
| Judul halaman | Source Serif 4, `28px`, `600` |
| Judul kartu | Public Sans, `14.5px`, `600` |
| Tabel | `12.5px`, kepala `11px` huruf besar |
| Label form | `11px` huruf besar, `tracking-[0.08em]` |
| Angka utama | Source Serif 4, `30px` |

`13.5px` bukan `16px`: layar administrasi menampung tabel lebar, dan ukuran
bawaan memaksa gulir mendatar pada laptop 1366px yang masih umum di kantor
kampus.

Di Next.js muat font lewat `next/font/google` — ia meng-host sendiri, jadi
tidak ada permintaan ke pihak ketiga dan tidak ada pergeseran tata letak saat
font tiba. Open Academic masih memakai `<link>` ke Google Fonts; itu utang yang
tercatat, jangan ikut disalin.

---

## Yang sengaja tidak dibawa

| | Alasan |
|---|---|
| Mode gelap | Tidak pernah ada di sini. Token datar; kalau proyek tujuan punya `next-themes`, **copot** — kalau tidak, tombol temanya mengubah dua warna dan meninggalkan tiga puluh sisanya |
| Ikon glyph teks | Kompromi karena Blade, bukan keputusan desain. Pakai `lucide-react` |
| `[x-cloak]` | Milik Alpine.js |
| Alpine store (`$store.ui`) | Sidebar ciut & toast — di React ini state biasa |
| `BrandingService` | Warna per-tenant lewat `<style>:root{…}</style>` yang ditimpa saat runtime. PD Dikti bukan multi-tenant |
| Layout `app-core` | Pola hosting bersama, tidak ada hubungannya dengan desain — lihat [`TATA-LETAK.md`](TATA-LETAK.md) |

---

## Daftar periksa sebelum menyatakan selesai

- [ ] Tidak ada nilai hex di komponen mana pun — `grep -rn "#[0-9a-fA-F]\{6\}" src/` hanya menemukan berkas token
- [ ] Tidak ada bayangan pada kartu; `shadow-*` hanya di toast/modal/popover
- [ ] Emas paling banyak satu tempat per layar
- [ ] Setiap tabel di dalam `overflow-x-auto`; halaman tidak pernah menggulir mendatar
- [ ] Angka lurus antar-baris (`tabular-nums` bekerja)
- [ ] Tautan lompat-ke-konten muncul penuh saat difokus, bukan titik 1px
- [ ] Setiap input punya `<label for>`; galat tersambung `aria-describedby`
- [ ] Baris tabel yang bisa dibuka benar-benar terbuka saat diklik
- [ ] Tidak ada sisa tombol mode gelap
