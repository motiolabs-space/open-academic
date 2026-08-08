<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Front controller — tata letak app-core
|--------------------------------------------------------------------------
|
| Aplikasinya ada di app-core/, dan berkas yang boleh dilayani web ada di
| direktori ini. Itu pola hosting bersama (SiteGround dan sejenisnya), yang
| tidak selalu mengizinkan document root diarahkan ke subfolder.
|
| Konsekuensinya: app-core berada **di dalam** document root, jadi ia dijaga
| oleh app-core/.htaccess yang menolak seluruh akses. Tanpa berkas itu, .env
| dan seluruh isi storage dapat diunduh siapa saja lewat HTTP.
|
*/

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/app-core/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/app-core/vendor/autoload.php';

/*
 * Direktori publik ditetapkan di dalam bootstrap/app.php, bukan di sini —
 * artisan dan worker antrean tidak pernah melewati berkas ini, dan mereka pun
 * memanggil public_path().
 */
/** @var Application $app */
$app = require_once __DIR__.'/app-core/bootstrap/app.php';

$app->handleRequest(Request::capture());
