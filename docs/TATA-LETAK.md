# TATA-LETAK.md — app-core & Root Publik

```
open-academic/                  ← document root
├── index.php                   ← front controller
├── .htaccess                   ← rewrite + penolakan app-core
├── build/                      ← keluaran Vite
├── storage                     → symlink ke app-core/storage/app/public
├── favicon.ico, robots.txt
│
├── app-core/                   ← APLIKASINYA
│   ├── .htaccess               ← Require all denied
│   ├── .env, artisan, composer.json, vendor/, node_modules/
│   └── app/ bootstrap/ config/ database/ lang/ resources/ routes/ storage/ tests/
│
└── docs/ README.md .github/    ← meta repo (tidak dilayani)
```

Alasannya satu: **hosting bersama tidak selalu mengizinkan document root
diarahkan ke subfolder.** Tata letak Laravel bawaan mengandaikan `public/` yang
dapat dijadikan docroot; ketika itu tidak bisa, seluruh isi repo — termasuk
`.env` — berada di jangkauan web.

---

## Konsekuensinya: app-core ada DI DALAM document root

Itu bukan kelalaian, itu memang harga dari polanya. Karena itu penolakannya
**digandakan**:

| Lapis | Berkas | Bertahan ketika |
|---|---|---|
| 1 | `.htaccess` root — `RewriteRule ^(app-core\|docs\|tests)/ - [F,L]` | mod_rewrite aktif |
| 2 | `app-core/.htaccess` — `Require all denied` | mod_rewrite **tidak** aktif |

Lapis kedua yang penting. Lapis pertama hilang begitu mod_rewrite dimatikan —
dan yang bocor kalau keduanya tidak ada bukan sekadar kode, melainkan `.env`,
kunci OAuth, dan `storage/app/private` yang berisi pindaian KTM, kartu keluarga,
serta surat keterangan sehat.

Terverifikasi lewat HTTP, bukan diasumsikan:

| Permintaan | Hasil |
|---|---|
| `/app-core/.env` | 403 |
| `/app-core/config/database.php` | 403 |
| `/app-core/storage/logs/laravel.log` | 403 |
| `/composer.json`, `/CLAUDE.md`, `/docs/CETAK.md` | 403 |
| `/build/manifest.json` | 200 |

---

## Empat sambungan yang harus diubah

Memindahkan berkas hanya separuh pekerjaan. Empat hal menyimpan asumsi tentang
tata letak lama, dan tiga di antaranya gagal dengan cara yang tidak menyebut
sebabnya.

### 1. Direktori publik — di `bootstrap/app.php`, bukan `index.php`

```php
$app->usePublicPath(dirname(__DIR__, 2));
```

Godaannya menaruh ini di `index.php`, karena hanya berkas itu yang ada gara-gara
tata letak ini. Tapi **artisan, worker antrean, dan test runner tidak pernah
melewati `index.php`** — dan ketiganya memanggil `public_path()`: `storage:link`
menaruh tautannya di situ, pembantu Vite mencari manifesnya di situ.

### 2. Vite — `publicDirectory: '..'`

Tanpa ini Vite menulis ke `app-core/public/build`, di dalam direktori yang
justru ditolak Apache. Halamannya terbit tanpa CSS, tanpa galat apa pun.

### 3. dompdf — `config/dompdf.php`

`barryvdh/laravel-dompdf` mencari `base_path('public')`, di-hardcode, mengabaikan
`usePublicPath()`. Direktori itu tidak ada lagi, jadi `realpath()`-nya gagal dan
**setiap** PDF — transkrip, SKPI, BKD, keempat dokumen cetak — berakhir dengan
`RuntimeException: Cannot resolve public path`.

Satu kunci saja yang ditimpa; sisanya tetap dari bawaan paket lewat
`mergeConfigFrom`.

### 4. `APP_URL` di `phpunit.xml`

Ini yang paling menipu. Klien tes menyusun URL relatif di atas `APP_URL`. Begitu
`.env` memuat awalan path, `$this->get('/berkas/...')` menjadi
`/open-academic/berkas/...` dan tidak cocok dengan rute mana pun — **404 di
setiap tes HTTP**, dengan pesan yang tidak menyebut APP_URL sama sekali.

`phpunit.xml` kini memakukannya ke `http://localhost`, jadi tes tidak lagi
peduli di mana aplikasinya dipasang.

---

## Menjalankan perkakas

Semua dari `app-core/`:

```bash
cd app-core && php artisan migrate
```

```bash
cd app-core && composer test
```

```bash
cd app-core && npm run build
```

CI memakai `defaults.run.working-directory: app-core`.

---

## Deploy ke SiteGround

Isi `public_html/` adalah root repo apa adanya. Yang perlu diperhatikan:

1. `app-core/.htaccess` **wajib** ikut terunggah — ia yang menahan `.env`.
2. `php artisan storage:link` dijalankan di server; symlink tidak ikut Git.
3. `npm run build` menghasilkan `build/` di root — bukan di app-core.
4. `.env` tidak pernah ikut Git; disalin dari `app-core/.env.example` di server.
