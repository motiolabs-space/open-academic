# Audit Keamanan Menjelang v1.0 — Oktober 2026

> Fase 5 (`CLAUDE.md` §11), Sesi 63, branch `fix/review-keamanan-v1`.
> Keputusan yang tidak jelas dengan sendirinya: ADR-033.

Audit pertama yang utuh sejak permukaan publik bertambah dalam sepekan
terakhir — portal pendaftar PMB (#32), helpdesk, federasi IdP, denda, jadwal
ujian, kalender dengan umpan iCalendar, KKN/PKL, proposal TA, riwayat
kepegawaian, tarif per SKS, klaim mahasiswa lampau. Kode dibaca per rute dan
per jalur data, bukan hanya di-grep: 314 metode publik controller admin, semua
controller portal dosen/mahasiswa/pendaftar/helpdesk/SSO, `BerkasService` dan
seluruh pemanggilnya, 46 titik SQL mentah, seluruh `{!! !!}` dan atribut skrip
di view, enam pengekspor CSV, pembangkit PDF dan iCalendar, alur masuk kelima
guard, IdP, Passport, Sanctum Bridge, dan webhook.

## Ringkasan

| Tingkat | Ditemukan | Diperbaiki di PR ini | Ditunda |
|---|---|---|---|
| Kritis | 0 | — | — |
| Tinggi | 3 | 3 | 0 |
| Sedang | 8 | 8 | 0 |
| Rendah | 21 | 5 | 16 |

Setiap perbaikan disertai tes yang gagal sebelum perbaikan dan lolos
sesudahnya (`tests/Feature/Keamanan/*`, ditambah tes di modulnya masing-masing).
`composer audit` dan `npm audit` bersih per 27 Oktober 2026.

Tingkat ditetapkan dari siapa yang dapat memicu dan apa yang terjangkau:
**tinggi** — dapat dipicu orang di luar kampus atau orang yang seharusnya sudah
tidak punya akses, dan menjangkau sesi staf; **sedang** — butuh akun atau
konfigurasi tertentu, tetapi melewati batas yang dijanjikan sistem; **rendah**
— pertahanan berlapis, kebocoran kecil, atau butuh pemegang izin yang sah
bertindak curang dengan jejak audit.

---

## Diperbaiki di PR ini

### T-1 · Tinggi · Skrip tersisip lewat penangan `onsubmit` sebaris

**Lokasi.** 25 view, a.l. `resources/views/admin/pmb.blade.php` (tombol
*Daftarkan*), `admin/cuti`, `admin/koreksi-nilai`, `admin/wisuda`,
`sso/aplikasi-terhubung`.

**Skenario.** Konfirmasi ditulis `onsubmit="return confirm('… {{ $p->nama }} …')"`.
`{{ }}` meloloskan HTML, bukan JavaScript: pengurai atribut mengembalikan
`&#039;` menjadi tanda kutip sebelum skrip berjalan. Pendaftar yang mendaftar
di portal PMB publik dengan nama berisi tanda kutip menjalankan kode di sesi
staf yang menekan tombolnya. Nama itu ikut menjadi nama mahasiswa, sehingga
layar cuti, koreksi nilai, dan wisuda ikut terdampak. CSP mengizinkan
`'unsafe-inline'` untuk skrip, jadi tidak ada lapis kedua.

**Perbaikan.** Seluruh 36 penangan sebaris diganti atribut `data-confirm`
(teks biasa) yang ditangani satu pendengar di `resources/js/app.js`;
`data-confirm-if` untuk konfirmasi bersyarat. Tanpa penangan sebaris tersisa,
`script-src` tidak lagi memuat `'unsafe-inline'`. Tes memindai seluruh view
agar penangan `on*=` atau `<script>` sebaris tidak kembali. Ikut dibereskan:
`old()` di dalam string `x-data` (helpdesk) memakai `@js`.

### T-2 · Tinggi · Sesi bertahan setelah akun dinonaktifkan atau kata sandi diganti

**Lokasi.** `LoginController` (cek `is_active` hanya saat `attempt()`),
`KepegawaianService::nonaktifkanStaff/resetKataSandi`,
`Mahasiswa\ProfilController::gantiKataSandi`.

**Skenario.** Staf yang diberhentikan tetap memegang sesinya — dan cookie
"ingat saya" yang umurnya berbulan-bulan — lengkap dengan `nilai.manage` atau
`keuangan.manage`. Pengaturan ulang kata sandi oleh admin atau oleh pemiliknya
juga tidak mengakhiri sesi lain, sehingga sesi atau cookie yang dicuri tetap
berlaku.

**Perbaikan.** Middleware grup web `PastikanAkunAktif` memeriksa keempat guard
portal setiap permintaan: akun nonaktif atau kata sandi yang berubah sejak sesi
dibuka (sidik HMAC, terikat ke akunnya) mengeluarkan sesi itu. Trait
`PutarTokenIngat` memutar `remember_token` setiap kali kata sandi berubah.
Perangkat yang mengganti kata sandinya sendiri tetap masuk.

### T-3 · Tinggi · Contoh Nginx di panduan pemasangan tidak menutup `app-core/`

**Lokasi.** `docs/DEPLOYMENT.md` §1–4, `SECURITY.md` butir 5.

**Skenario.** Sejak tata letak app-core, document root adalah direktori repo
dan `app-core/` ada di dalamnya. Apache menolaknya lewat dua `.htaccess`, tetapi
Nginx tidak membacanya. Contoh Nginx masih mengarahkan `root` ke `public/` yang
sudah tidak ada dan tidak punya aturan untuk `app-core/`; pemasang yang
membetulkan `root` supaya aplikasinya jalan membuka kunci privat OAuth (dengan
itu token SSO dapat dipalsukan), log, dan jalur berkas unggahan privat.

**Perbaikan.** `root` ke direktori repo, blok `location` yang menolak
`app-core/`, `docs/`, `tests/`, dan berkas repo sebelum blok PHP; perintah
pemasangan, hak akses, worker, cron, dan cadangan memakai `app-core/`;
pemeriksaan sesudah deploy menguji `/app-core/…`. Tes mem-parse contoh Nginx
supaya tidak tertinggal lagi.

### S-1 · Sedang · Pengambilalihan akun pendaftar sebelum pemiliknya mendaftar

**Lokasi.** `Pmb\AkunController::store`, `Pmb\SandiController::aturUlang`.

**Skenario.** Siapa pun dapat mendaftarkan surel orang lain dan menjaga sesinya
tetap hidup; ketika pemilik asli memakai *lupa sandi*, surelnya terverifikasi
dan sesi penyerobot ikut lolos gerbang `verified`, lalu melihat NIK dan berkas
yang diunggah pemiliknya.

**Perbaikan.** Tertutup oleh T-2: atur ulang kata sandi mengakhiri sesi lain.

### S-2 · Sedang · Pemegang `pengaturan.manage` dapat mengangkat dirinya menjadi super-admin

**Lokasi.** `Admin\StaffController::store/update/resetKataSandi/nonaktifkan`.

**Skenario.** Izin yang sama membuka Pengumuman, SPMI, dan Kinerja, jadi wajar
diberikan ke peran humas atau LPM. Pemegangnya dapat memberi siapa pun —
termasuk dirinya — peran `super-admin`, atau mengatur ulang kata sandi
super-admin dan membaca yang baru di layar. Mengganti peran super-admin
terakhir juga tidak dicegah.

**Perbaikan.** `StaffPolicy`: hanya super-admin yang memberi peran itu atau
mengelola akun yang memegangnya; tidak seorang pun mengubah perannya sendiri;
`KepegawaianService::ubahPeranStaff` menolak menurunkan super-admin aktif
terakhir.

### S-3 · Sedang · Mencabut aplikasi SSO tidak menghentikannya

**Lokasi.** `Sso\AplikasiTerhubungController::cabut`, `SsoClientCommand --cabut`.

**Skenario.** Hanya access token yang dicabut. Grant refresh Passport memeriksa
baris refresh token saja, sehingga aplikasi menukar refresh token 30 harinya
dengan access token baru sesaat setelah mahasiswa menekan *Cabut*.

**Perbaikan.** `PencabutanTokenSso` mencabut access dan refresh token dalam
satu transaksi; dipakai layar dan perintah artisan.

### S-4 · Sedang · RPS yang sudah berlaku masih dapat diubah teksnya

**Lokasi.** `Dosen\RpsController::simpan`.

**Skenario.** Deskripsi dan pustaka ditulis sebelum pemeriksaan status draf,
jadi dosen pengampu menerima pesan galat sementara RPS yang dibekukan sudah
berubah.

**Perbaikan.** `RpsService::simpanIsi` memeriksa status lebih dulu.

### S-5 · Sedang · Batas percobaan masuk hanya per identitas + alamat

**Lokasi.** `LoginRequest`, `MasukPendaftarRequest`.

**Skenario.** Berganti alamat memberi jatah tebakan baru untuk akun yang sama,
dan satu alamat dapat mencoba satu kata sandi ke setiap NIM berurutan tanpa
pernah tertahan.

**Perbaikan.** `App\Support\BatasMasuk`: tiga penghitung kegagalan — per
identitas + alamat (seperti sebelumnya), per akun dari alamat mana pun
(`AUTH_BATAS_PER_AKUN`, bawaan 20 per 15 menit), per alamat ke akun mana pun
(`AUTH_BATAS_PER_ALAMAT`, bawaan 50 per 5 menit). Masuk yang berhasil tidak
pernah membersihkan penghitung per alamat.

### S-6 · Sedang · Tantangan dua langkah hanya dibatasi per alamat

**Lokasi.** `Auth\DuaFaktorController::verifikasi`.

**Skenario.** Yang sampai ke layar ini sudah memegang kata sandi; berganti
alamat memberi lima tebakan kode baru setiap kali.

**Perbaikan.** Penghitung kedua per akun (10 kegagalan per 15 menit).

### S-7 · Sedang (bersyarat) · Provisioning IdP tanpa batas domain

**Lokasi.** `PenautanIdpService::buatBila`; hanya bila `IDP_PROVISION=true`.

**Skenario.** Penerbit menjamin tanda tangan dan audiens, bukan keanggotaan
kampus. Dengan penerbit publik, setiap pemilik akunnya menjadi staf tanpa
peran.

**Perbaikan.** `IDP_PROVISION_DOMAINS` wajib memuat domain surelnya (kosong =
tidak ada yang dibuatkan), dan surel harus terverifikasi IdP meski
`IDP_REQUIRE_VERIFIED_EMAIL=false`. Instalasi yang sudah menyalakan
provisioning perlu mengisi variabel ini.

### S-8 · Sedang · Injeksi rumus pada ekspor CSV

**Lokasi.** `EksporKipk`, `EksporSdm`, `EksporSister`, `EksporJurnal`,
`PemanfaatanRuangService`, `LkpsController`.

**Skenario.** Nama dari pendaftaran PMB publik dan judul yang diketik dosen
masuk ke sel CSV apa adanya; sel yang diawali `=` dijalankan lembar kerja staf
yang membukanya. Escape garis miring terbalik bawaan `fputcsv` juga membuat
nilai dapat memecah selnya sendiri.

**Perbaikan.** Seluruh ekspor menulis lewat `App\Support\Csv`: sel teks yang
diawali `= + - @`, tab, atau CR diberi apostrof (angka, termasuk nominal
negatif, tidak disentuh), dan kutipan murni RFC 4180. XLSX tidak terdampak
(`inlineStr`, tanpa elemen rumus).

### Rendah yang ikut diperbaiki

| # | Temuan | Perbaikan |
|---|---|---|
| R-1 | CSP `object-src none` tanpa kutip (dibaca sebagai nama host) | `'none'` |
| R-2 | Tanpa HSTS | `Strict-Transport-Security` pada permintaan HTTPS, tanpa `includeSubDomains` |
| R-3 | Rahasia 2FA staf dan rahasia webhook konsumen Bridge ikut tercatat di Log Aktivitas | Dikecualikan dari log |
| R-4 | Disk `local` menyalakan rute `/storage` bertanda tangan yang tidak dipakai | `serve => false` |
| R-5 | Opsi keamanan dompdf bergantung pada bawaan paket | `enable_remote`, `enable_php`, `enable_javascript` dipaku `false` |

---

## Ditunda — Rendah

Dicatat juga di `ROADMAP.md` §Utang Teknis. Rinciannya sengaja ringkas karena
belum diperbaiki.

| # | Area | Temuan | Alasan ditunda |
|---|---|---|---|
| D-1 | Akademik | Konversi kredit dan verifikasi poin kemahasiswaan dapat diajukan dan disetujui orang yang sama | Pemisahan tugas, bukan celah akses: butuh pemegang izin sah, tercatat di Log Aktivitas, dan kampus kecil sering hanya punya satu operator. Butuh keputusan proses |
| D-2 | Akademik | Kunci semester hanya ditegakkan saat membuka kelas, tidak pada perubahan kelas lain | Butuh keputusan perubahan mana yang sah sesudah semester dikunci |
| D-3 | Akademik | Jalur koreksi nilai menerima nilai yang belum final | Hanya super-admin yang memegang `nilai.manage` secara bawaan |
| D-4 | Akademik | Jurnal pertemuan dapat merujuk rencana pertemuan RPS lain | Integritas laporan, terbatas pada dosen pengampu |
| D-5 | Admin | Dasbor admin menampilkan angka agregat ke staf tanpa izin terkait | Agregat, bukan data per orang; perlu penyaringan panel per izin |
| D-6 | Feeder | Menjalankan validasi hanya butuh `feeder.view` | Menulis laporan validasi, bukan data |
| D-7 | SDM | Pendidikan dan jabatan fungsional yang dientri dosen langsung berlaku tanpa telaah | Data diri pemiliknya; sebagian diakui ADR terdahulu. Butuh memperluas alur telaah #39 |
| D-8 | Berkas | Unduhan memakai nama berkas asli dan tipe isi bawaan | `attachment` + `nosniff` mencegah eksekusi di origin aplikasi |
| D-9 | Kalender | Umpan iCalendar tetap hidup setelah pemiliknya kehilangan izin | Umpan hanya memuat jadwal sesuai audiens, tanpa data pribadi |
| D-10 | PMB | Pendaftaran membedakan surel yang sudah terdaftar | Pencacahan, dibatasi per alamat |
| D-11 | Auth | Masuk lewat IdP atau dua langkah tidak mengeluarkan sesi pendaftar di peramban yang sama | Butuh peramban bersama |
| D-12 | Auth | Tabel token atur ulang sandi dipakai bersama tiga broker | Laten: belum ada alur atur ulang publik selain PMB (tabel sendiri) |
| D-13 | Auth | Konfirmasi kata sandi untuk mematikan 2FA dan mengganti sandi tidak dibatasi | Butuh sesi yang sudah masuk |
| D-14 | IdP | Skema `token_endpoint` hasil discovery belum diwajibkan HTTPS; subjek tidak diikat ke penerbit | Penerbit sudah wajib HTTPS; mengganti IdP adalah tindakan admin |
| D-15 | Ekspor | Karakter kendali dapat merusak berkas XLSX | Kerusakan berkas, bukan eksekusi |
| D-16 | Auth | Pemeriksaan ulang kode TOTP tidak atomik terhadap dua permintaan serentak | Jendela milidetik, butuh kode yang sah |

### Catatan, bukan temuan

- CSP masih `'unsafe-eval'` untuk Alpine — utang tersendiri (ROADMAP §Utang
  Teknis), sengaja tidak dikerjakan di sini.
- Token Sanctum Bridge tidak kedaluwarsa dan konsumen tanpa rahasia sendiri
  berbagi `BRIDGE_WEBHOOK_SECRET`; alamat webhook hanya dapat diisi lewat CLI
  atau basis data.
- 2FA staf opsional (`DUA_FAKTOR_WAJIB=false`), `SESSION_SECURE_COOKIE=false` di
  `.env.example`, dan `APP_DEBUG=true` untuk pengembangan — semuanya sudah
  diwajibkan dibalik di `SECURITY.md` §Wajib Dilakukan Sebelum Produksi.
- Metode `fromRequest()` yang membaca `$request->all()` di empat DTO tidak
  dipanggil di mana pun; semua jalur memakai `validated()`.

## Yang diperiksa dan tidak bermasalah

- **Batas guard.** Setiap rute admin di belakang `auth:staff` plus izin
  (middleware, policy, atau `FormRequest::authorize`); rute multi-guard gagal
  tertutup. Rute bersarang memeriksa induknya (`scopeBindings` atau
  pemeriksaan eksplisit). KRS, nilai, presensi, tagihan, surat, tugas akhir,
  logbook KKN, riwayat kepegawaian, tiket helpdesk, dan berkas pendaftar
  dibatasi ke pemilik atau unit berwenang.
- **Unggahan.** Semua lewat `BerkasService::aturan()` — `mimes` diperiksa dari
  isi, maks 4 MB, disk privat yang menolak `public`, nama UUID, tanpa jalur
  dari masukan pengguna, unduhan lewat policy sebagai `attachment`.
- **Mass assignment.** Tidak ada `request()->all()`/`input()` utuh yang sampai
  ke model; larik bersarang dipetakan ulang secara eksplisit.
- **SQL.** 46 titik SQL mentah: semua nilai di-bind atau konstanta enum; urutan
  dari masukan di-whitelist.
- **XSS.** Satu-satunya `{!! !!}` mengeluarkan warna yang divalidasi regex;
  tidak ada `x-html`/`innerHTML`; PDF tanpa keluaran tak-teresc.
- **iCalendar.** Teks di-escape per RFC 5545 dan dilipat 75 oktet; token umpan
  acak 48 karakter, disimpan sebagai SHA-256, dapat dicabut.
- **Autentikasi.** Regenerasi sesi di semua jalur masuk; status 2FA tertunda
  hanya di sesi; kode pemulihan bcrypt sekali pakai; state/nonce IdP sekali
  pakai dengan `hash_equals`, `iss`/`aud`/`azp`/`exp` diperiksa; Passport
  tanpa password grant, rahasia klien di-hash, redirect URI persis; Bridge
  menolak token selain konsumen dan memeriksa scope setiap rute; HMAC webhook
  menutup stempel waktu dan badan.
- **Konfigurasi.** `app-core/` ditolak dua lapis `.htaccess`; tidak ada
  Telescope, Pulse, atau debugbar.
