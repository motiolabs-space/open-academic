<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Masuk lewat Penyedia Identitas Eksternal (OIDC)
    |--------------------------------------------------------------------------
    |
    | Kebalikan dari config/sso.php. Di sana Open Academic MENERBITKAN identitas
    | untuk aplikasi lain; di sini ia MENERIMA identitas dari IdP yang sudah
    | dimiliki kampus — Google Workspace, Microsoft Entra ID, Keycloak — lewat
    | OpenID Connect authorization-code.
    |
    | Nonaktif secara bawaan. Saat nonaktif: rute /masuk/idp tidak terdaftar
    | dan tombolnya tidak muncul di halaman masuk.
    |
    */

    'enabled' => (bool) env('IDP_ENABLED', false),

    /*
    | fake  — penyedia palsu untuk uji dan demo; tidak pernah ke jaringan.
    | oidc  — klien OIDC sungguhan terhadap `issuer` di bawah.
    */

    'driver' => env('IDP_DRIVER', 'fake'),

    /*
    | Nama yang tercetak pada tombol "Masuk dengan …". Pakai nama yang dikenal
    | pengguna — "Akun Google Kampus", bukan "OIDC".
    */

    'name' => env('IDP_NAME', 'Akun Kampus'),

    /*
    |--------------------------------------------------------------------------
    | Penyedia
    |--------------------------------------------------------------------------
    |
    | `issuer` adalah URL yang persis sama dengan klaim `iss` pada ID token, dan
    | dokumen discovery dibaca dari <issuer>/.well-known/openid-configuration.
    | Contoh:
    |
    |   Google    https://accounts.google.com
    |   Entra ID  https://login.microsoftonline.com/<tenant-id>/v2.0
    |   Keycloak  https://sso.kampus.ac.id/realms/kampus
    |
    | Wajib HTTPS pada driver oidc: validasi penerbit bersandar pada TLS ke
    | token endpoint (OIDC Core §3.1.3.7.6).
    |
    */

    'issuer' => env('IDP_ISSUER'),
    'client_id' => env('IDP_CLIENT_ID'),
    'client_secret' => env('IDP_CLIENT_SECRET'),

    'scopes' => array_values(array_filter(
        explode(' ', (string) env('IDP_SCOPES', 'openid email profile')),
    )),

    'timeout' => (int) env('IDP_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Siapa yang boleh masuk lewat IdP
    |--------------------------------------------------------------------------
    |
    | Guard yang diperiksa, dalam urutan ini. Bawaannya staf saja: populasinya
    | kecil dan merekalah yang punya akun di direktori kampus. Tambahkan dosen
    | bila direktorinya juga memuat dosen:
    |
    |   IDP_GUARDS=staff,dosen
    |
    | Mahasiswa sengaja tidak dapat dipilih — lihat docs/SSO.md.
    |
    */

    'guards' => array_values(array_filter(
        explode(',', (string) env('IDP_GUARDS', 'staff')),
    )),

    /*
    | Identitas yang surelnya tidak ada di tabel mana pun. Bawaannya DITOLAK:
    | akun staf adalah pemberian kewenangan, dan direktori kampus memuat jauh
    | lebih banyak orang daripada yang boleh membuka BAAK. Dengan true, akun
    | staf TANPA PERAN dibuat dan admin masih harus memberinya peran. Dosen
    | tidak pernah dibuat otomatis: ia butuh NIDN dan prodi.
    */

    'provision' => (bool) env('IDP_PROVISION', false),

    /*
    | Hanya surel yang dinyatakan terverifikasi oleh IdP (`email_verified`)
    | yang dipakai untuk menautkan akun. Entra ID tidak mengirim klaim itu
    | untuk akun anggota tenant; setel false di sana secara sadar.
    */

    'require_verified_email' => (bool) env('IDP_REQUIRE_VERIFIED_EMAIL', true),

    /*
    | Apakah masuk lewat IdP dihitung sudah melewati verifikasi dua langkah.
    |
    | Bawaannya FALSE: staf dengan TOTP tetap ditantang sesudah kembali dari
    | IdP. Setel true hanya bila IdP-nya sendiri memaksakan faktor kedua untuk
    | seluruh akun staf (kebijakan akses bersyarat, misalnya). Lihat ADR-019.
    */

    'satisfies_2fa' => (bool) env('IDP_SATISFIES_2FA', false),

    /*
    | Umur state/nonce yang menunggu di sesi, dalam detik. Pengalihan ke IdP
    | yang ditinggalkan terbuka tidak boleh dapat diselesaikan sejam kemudian.
    */

    'state_ttl' => 600,

];
