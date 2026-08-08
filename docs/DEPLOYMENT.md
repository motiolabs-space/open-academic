# Panduan Deploy Produksi

Ditujukan untuk satu VPS yang melayani satu kampus. Untuk instalasi berskala
lebih besar, bagian aplikasi dan basis data dipisah tetapi urutan langkahnya
sama.

Baca [`SECURITY.md`](../SECURITY.md#wajib-dilakukan-sebelum-produksi) lebih
dulu. Panduan ini mengasumsikan daftar itu sudah dipenuhi.

---

## 1. Prasyarat Server

| Komponen | Minimum | Catatan |
|---|---|---|
| CPU / RAM | 2 vCPU · 4 GB | 4 vCPU · 8 GB untuk >3.000 mahasiswa |
| PHP | 8.2+ | ekstensi `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath` |
| MySQL / MariaDB | 8.0 / 10.11 | `utf8mb4_unicode_ci` |
| Web server | Nginx atau Apache | document root **wajib** `public/` |
| Node | 20+ | hanya saat build aset |
| Supervisor | — | menjaga worker antrean tetap hidup |

---

## 2. Pemasangan

```bash
git clone https://github.com/motiolabs-space/open-academic.git /var/www/open-academic
cd /var/www/open-academic

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

Sunting `.env`, lalu:

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
```

`RolePermissionSeeder` membuat peran dan izin — bukan data demo, jadi aman di
produksi. **`DemoCampusSeeder` tidak boleh dijalankan** dan akan menolak sendiri
bila `APP_ENV=production`.

Buat akun administrator pertama:

```bash
php artisan tinker
```

```php
$staff = App\Models\Sdm\Staff::create([
    'nip' => '198001012005011001',
    'nama' => 'Nama Administrator',
    'email' => 'admin@kampus.ac.id',
    'password' => Hash::make('kata-sandi-yang-kuat'),
    'is_active' => true,
]);
$staff->assignRole('super-admin');
```

Tetapkan semester aktif lewat menu Master Akademik — tanpa satu pun tahun
akademik aktif, seluruh portal mengembalikan 503 secara sengaja.

---

## 3. Hak Akses Berkas

```bash
chown -R www-data:www-data /var/www/open-academic
chmod -R 755 /var/www/open-academic
chmod -R 775 /var/www/open-academic/storage /var/www/open-academic/bootstrap/cache
chmod 640 /var/www/open-academic/.env
```

---

## 4. Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name akademik.kampus.ac.id;

    # Hanya public/ yang boleh terekspos. Mengarahkan root ke direktori
    # proyek membuat .env dapat diunduh siapa pun.
    root /var/www/open-academic/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/akademik.kampus.ac.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/akademik.kampus.ac.id/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Transkrip PDF dan unggahan bukti bisa besar.
    client_max_body_size 12M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}

server {
    listen 80;
    server_name akademik.kampus.ac.id;
    return 301 https://$host$request_uri;
}
```

---

## 5. Worker Antrean — Wajib

Jejak audit, sinkronisasi Feeder, dan pengiriman webhook semuanya ter-antre.
**Tanpa worker ketiganya gagal diam-diam:** perubahan nilai tidak pernah
tercatat di `log_aktivitas`, dan jejak audit yang diam adalah masalah keamanan,
bukan sekadar fitur yang mati.

`/etc/supervisor/conf.d/open-academic-worker.conf`:

```ini
[program:open-academic-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/open-academic/artisan queue:work --queue=bridge,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/open-academic-worker.log
stopwaitsecs=3600
```

```bash
supervisorctl reread && supervisorctl update && supervisorctl start open-academic-worker:*
```

Antrean `bridge` didahulukan agar webhook tidak tertahan di belakang ribuan
tulisan audit saat sinkronisasi Feeder berjalan.

---

## 6. Penjadwal

```cron
* * * * * cd /var/www/open-academic && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Optimasi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Ulangi keempatnya setiap kali `.env` atau kode berubah. `config:cache`
membekukan `.env` — mengubah `.env` tanpa membangun ulang cache tidak akan
berpengaruh apa pun, dan ini penyebab paling umum "sudah saya ubah tapi tidak
berubah".

---

## 8. Cadangan

Basis data adalah catatan akademik resmi kampus. Kehilangannya bukan gangguan
operasional, melainkan hilangnya riwayat studi mahasiswa.

```bash
#!/bin/bash
# /usr/local/bin/backup-open-academic.sh
TANGGAL=$(date +%Y%m%d-%H%M)
TUJUAN=/var/backups/open-academic

mkdir -p "$TUJUAN"
mysqldump --single-transaction --quick --routines \
    -u "$DB_USER" -p"$DB_PASS" open_academic \
    | gzip > "$TUJUAN/db-$TANGGAL.sql.gz"

tar czf "$TUJUAN/storage-$TANGGAL.tar.gz" -C /var/www/open-academic storage/app

find "$TUJUAN" -name '*.gz' -mtime +30 -delete
```

Jalankan harian lewat cron, **salin ke luar server**, dan uji pemulihannya
sekali. Cadangan yang belum pernah dipulihkan belum diketahui berfungsi.

---

## 9. Pembaruan

```bash
cd /var/www/open-academic
php artisan down --render="errors::503"

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force

php artisan config:cache && php artisan route:cache && php artisan view:cache
supervisorctl restart open-academic-worker:*

php artisan up
```

Migrasi bersifat *append-only*, sehingga `migrate` tidak akan menghapus kolom
yang datanya masih dipakai. Tetap ambil cadangan sebelum memperbarui.

---

## 10. Pemeriksaan Setelah Deploy

| Periksa | Cara | Harapan |
|---|---|---|
| Aplikasi hidup | `curl -I https://…/up` | `200` |
| Debug mati | buka URL yang tidak ada | halaman galat polos, bukan jejak Laravel |
| `.env` tertutup | `curl https://…/.env` | `403`/`404` |
| Header keamanan | `curl -I https://…/masuk` | ada `Content-Security-Policy`, `X-Frame-Options` |
| Cookie aman | periksa `Set-Cookie` | ada atribut `Secure` dan `HttpOnly` |
| Worker jalan | `supervisorctl status` | `RUNNING` |
| Audit tercatat | ubah satu nilai, cek `log_aktivitas` | baris baru muncul |
