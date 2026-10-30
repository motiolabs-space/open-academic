# SSO — Open Academic sebagai Penyedia Identitas

Open Academic menjalankan server OAuth2 (Laravel Passport). Aplikasi kampus lain
mengarahkan penggunanya ke sini untuk masuk. Arah sebaliknya — Open Academic
menerima identitas dari IdP kampus — ada di bagian terakhir dokumen ini.

```
                    ┌─────────────────┐
                    │ Open Academic   │  ← sumber identitas
                    │ (server OAuth2) │
                    └────────┬────────┘
         ┌───────────────┬───┴────┬──────────────┐
    Open Campus      LMS /      Repositori     Perpustakaan
                     Moodle     / OJS          / SLiMS
```

**PDDIKTI bukan penyedia identitas.** Neo Feeder adalah endpoint pelaporan satu
arah. Akun Kemdiktisaintek dipakai operator untuk masuk ke portal kementerian,
bukan untuk aplikasi kampus mendelegasikan login.

---

## Mengaktifkan

```env
SSO_ENABLED=true
```

```bash
php artisan passport:keys
```

Kunci ditulis ke `storage/oauth-*.key` dan sudah masuk `.gitignore`. **Jangan
pernah menyalin kunci antar instalasi** — kunci yang sama berarti token terbitan
satu kampus berlaku di kampus lain.

Mendaftarkan aplikasi — dari layar **Klien SSO** (`/admin/sso-klien`, izin
`integrasi.kredensial`; peran `super-admin` dan `tik`), atau dari terminal:

```bash
php artisan openacademic:sso-client "Open Campus" --redirect=https://campus.kampus.ac.id/auth/callback
```

Secret hanya ditampilkan sekali. Redirect non-HTTPS ditolak di luar localhost:
kode otorisasi dikirim lewat URI itu, dan di HTTP polos siapa pun di jalur
jaringan dapat mencurinya lalu menukarnya lebih dulu — yang berarti mereka
mendapat akunnya, bukan sekadar kodenya.

Perintah lain:

```bash
php artisan openacademic:sso-client --daftar
php artisan openacademic:sso-client --cabut=<client-id>
```

### Layar Klien SSO (Sesi 69)

Hanya terdaftar bila `SSO_ENABLED`. Per klien: URI redirect, jumlah orang yang
izinnya masih dapat dipakai (token akses belum kedaluwarsa atau refresh
token-nya masih hidup, dihitung sekali per orang), dan tiga aksi:

| Aksi | Akibat |
|---|---|
| Ganti rahasia | Rahasia baru tampil sekali (tersimpan ter-hash Passport); yang lama langsung tidak berlaku untuk tukar kode dan refresh |
| Lewati / wajibkan persetujuan | Penanda pihak pertama di tabel `sso_klien` |
| Cabut | Klien `revoked`, lalu seluruh token akses **dan refresh token-nya** dicabut (`PencabutanTokenSso`). Final — daftarkan ulang bila keliru |

Yang dicatat di log aktivitas adalah peristiwanya (didaftarkan, pihak pertama
diubah, rahasia diganti, dicabut) pada baris `sso_klien`, tidak pernah
rahasianya. `--cabut` di terminal tetap hanya mencabut token, tanpa mencabut
kliennya.

---

## Alur untuk Konsumen

Authorization code + PKCE. Empat langkah.

**1. Arahkan pengguna ke Open Academic**

```
GET https://akademik.kampus.ac.id/oauth/authorize
  ?client_id=<client-id>
  &redirect_uri=https://campus.kampus.ac.id/auth/callback
  &response_type=code
  &scope=identitas akademik.baca
  &state=<acak, simpan di sesi>
```

**2. Pengguna menyetujui**, lalu dikembalikan dengan `?code=...&state=...`.
Bandingkan `state` dengan yang disimpan — bila berbeda, hentikan. Itu tanda CSRF.

**3. Tukar kode dengan token**

```bash
curl -X POST https://akademik.kampus.ac.id/oauth/token \
  -d grant_type=authorization_code \
  -d client_id=<client-id> \
  -d client_secret=<secret> \
  -d redirect_uri=https://campus.kampus.ac.id/auth/callback \
  -d code=<code>
```

**4. Tanyakan siapa pemegang token**

```bash
curl https://akademik.kampus.ac.id/api/sso/userinfo \
  -H "Authorization: Bearer <access_token>"
```

```json
{
  "sub": "2066e3c4-7ee9-4978-ae5e-39bec8a20eae",
  "peran": "mahasiswa",
  "nama": "Ira Bella Pratiwi",
  "email": "mahasiswa1@demo.test",
  "nomor_induk": "25572010001",
  "prodi": { "kode": "57201", "nama": "Sistem Informasi" },
  "angkatan": 2025,
  "status": "A"
}
```

`status` memakai **kode PDDIKTI**, bukan kata: `A` aktif, `C` cuti, `L` lulus,
`D` drop out, `K` keluar. Sengaja demikian agar konsumen yang juga melapor ke
PDDIKTI tidak perlu memetakan ulang.

Bidang di luar `identitas` bergantung peran: dosen mendapat `prodi`, staf
mendapat `unit` dan `jabatan`.

---

## `sub` — Baca Ini Sebelum Menyimpan Apa Pun

`sub` adalah **UUID**, bukan angka.

Open Academic punya tiga tabel identitas terpisah (`mahasiswa`, `dosen`,
`staff`), sehingga `id` tidak unik lintas orang: id 1 ada di ketiganya. Subject
yang dibangun dari `id` akan memberi satu identitas yang sama kepada tiga orang
berbeda, dan konsumen yang menyimpannya akan menggabungkan tiga rekaman menjadi
satu tanpa sadar.

Karena itu subject-nya UUID, dan kolom `user_id` pada tabel Passport diubah dari
`bigint` menjadi `char(36)` (lihat migrasi `create_oauth_*`).

**Simpan `sub` sebagai string.** Jangan mengasumsikan angka, jangan memakai
`nomor_induk` sebagai kunci — NIM dapat berubah, UUID tidak.

---

## Scope

Deskripsi scope ada di `config/sso.php` dan **itulah kalimat yang dibaca
mahasiswa** pada layar persetujuan. Tulis apa yang benar-benar dibagikan, dalam
bahasa orang yang menyetujuinya.

| Scope | Artinya bagi pengguna |
|---|---|
| `identitas` | Mengetahui nama, peran, dan nomor induk Anda |
| `akademik.baca` | Membaca riwayat akademik: KRS, nilai, IPK |
| `keuangan.baca` | Melihat status tagihan dan pembayaran |
| `aktivitas.tulis` | Mencatatkan aktivitas MBKM atas nama Anda |

Scope yang tidak terdaftar ditolak. Minta sesempit mungkin — layar persetujuan
yang meminta segalanya akan ditolak orang, dan sepantasnya begitu.

---

## Bedanya dengan Campus Bridge

Dua kanal berbeda yang sering tertukar.

| | Campus Bridge | SSO |
|---|---|---|
| Token diterbitkan untuk | **Aplikasi** konsumen | **Seseorang** yang menekan "Izinkan" |
| Jenis token | Sanctum | OAuth2 |
| Menjawab | "Data kampus apa yang boleh dibaca aplikasi ini" | "Siapa orang yang sedang masuk" |
| Endpoint | `/api/bridge/v1/...` | `/oauth/*`, `/api/sso/userinfo` |

Open Campus memakai keduanya: SSO untuk mengenali penggunanya, Bridge untuk
menarik data akademik massal.

---

## Yang Perlu Diputuskan Kampus

**`SSO_FIRST_PARTY`** — client id yang boleh melewati layar persetujuan, di
samping penanda pihak pertama di layar Klien SSO (keduanya dibaca
`KlienOAuth::skipsAuthorization()`; sebelum 30 Oktober 2026 variabel ini
didokumentasikan tetapi tidak pernah dibaca saat otorisasi). Isi
hanya dengan aplikasi yang dijalankan kampus sendiri. Menaruh aplikasi pihak
ketiga di sini menghapus satu-satunya titik di mana mahasiswa bisa menolak.

**`SSO_ALLOWED_ROLES`** — persempit bila kebijakan untuk akun staf belum selesai:

```env
SSO_ALLOWED_ROLES=mahasiswa,dosen
```

**Masa berlaku token** — access token 60 menit secara bawaan. Token yang bocor
hanya berguna selama masih berlaku.

---

## Mencabut Akses

Pengguna: menu **Aplikasi Terhubung** di portalnya masing-masing.

Administrator: tombol **Cabut** di layar Klien SSO (klien beserta seluruh
tokennya), atau `php artisan openacademic:sso-client --cabut=<client-id>`
(tokennya saja).

Menonaktifkan akun (`is_active = false`) **langsung** memutus akses seluruh
konsumen atas nama orang itu — tidak menunggu tokennya kedaluwarsa.

---

## Masuk lewat IdP Eksternal

Arah sebaliknya dari seluruh dokumen di atas. Sebagian kampus sudah punya
Google Workspace, Microsoft Entra ID, atau Keycloak; sejak 24 Oktober 2026
Open Academic dapat menjadi *relying party* OpenID Connect terhadap salah
satunya, sehingga staf (dan, bila dipilih, dosen) masuk dengan akun direktori
kampus. Formulir kata sandi tetap ada: hari IdP-nya padam adalah hari BAAK
tetap harus bekerja.

```
   IdP kampus ──(OIDC authorization-code)──▶ Open Academic ──(OAuth2)──▶ Open Campus, LMS, …
   Google / Entra / Keycloak                 konsumen di sini,
                                             penyedia di atas
```

### Mengaktifkan

```env
IDP_ENABLED=true
IDP_DRIVER=oidc
IDP_NAME="Akun Google Kampus"
IDP_ISSUER=https://accounts.google.com
IDP_CLIENT_ID=…
IDP_CLIENT_SECRET=…
```

Daftarkan `https://<APP_URL>/masuk/idp/callback` sebagai redirect URI di IdP.
`IDP_ISSUER` harus **HTTPS** dan persis sama dengan klaim `iss` pada ID token;
dokumen discovery dibaca dari `<issuer>/.well-known/openid-configuration`:

| IdP | `IDP_ISSUER` |
|---|---|
| Google Workspace | `https://accounts.google.com` |
| Microsoft Entra ID | `https://login.microsoftonline.com/<tenant-id>/v2.0` |
| Keycloak | `https://sso.kampus.ac.id/realms/<realm>` |

Driver `fake` tidak pernah menyentuh jaringan dan dipakai suite uji; di
instalasi sungguhan ia tidak berguna.

### Keputusan pemetaan — yang dulu menahan fitur ini

Pertanyaannya: identitas `budi@kampus.ac.id` yang datang dari IdP **dipetakan
ke tabel yang mana?** Jawabannya diambil sesempit mungkin (ADR-019):

1. **Hanya guard yang disebut `IDP_GUARDS`** — `staff` secara bawaan,
   `staff,dosen` bila direktori kampus juga memuat dosen. **Mahasiswa tidak
   dapat dipilih.** Dosen yang kebetulan juga kuliah di sini tidak boleh satu
   tabrakan surel jauhnya dari portal mahasiswa.
2. **Subject menang atas surel.** Surel dipakai sekali untuk menautkan; sesudah
   itu `idp_subject` pada baris staf/dosen yang menentukan. Kampus mengganti
   nama kotak surat dan memberikan alamat bekas ke pegawai baru — akun yang
   terus mengikuti alamat akan mengikutinya ke orang yang salah.
3. **Surel yang cocok di dua tabel ditolak**, bukan diselesaikan lewat urutan.
   Kebijakan institusi mengatakan itu mustahil; kode tidak mempertaruhkan
   satu sesi masuk pada kebijakan.
4. **Tidak ada provisioning bawaan.** Direktori kampus memuat jauh lebih banyak
   orang daripada yang boleh membuka BAAK. `IDP_PROVISION=true` membuat akun
   staf **tanpa peran**; admin tetap harus memberinya peran. Dosen tidak
   pernah dibuat otomatis — ia butuh NIDN dan prodi. Akun hanya dibuat untuk
   surel terverifikasi yang domainnya tercantum di `IDP_PROVISION_DOMAINS`
   (kosong = tidak ada): penerbit publik seperti Google meloloskan `iss`/`aud`
   untuk setiap pemilik akunnya, bukan hanya warga kampus.
5. **Akun nonaktif tetap ditolak**, tertaut atau belum.
6. Hanya surel yang `email_verified` oleh IdP yang dipakai menautkan. Entra ID
   tidak mengirim klaim itu untuk anggota tenant; setel
   `IDP_REQUIRE_VERIFIED_EMAIL=false` di sana **dengan sadar**.

### Titik temu dengan verifikasi dua langkah

Staf yang memasang TOTP **tetap ditantang** sesudah kembali dari IdP. Itu
bawaannya, dan ia bukan kelalaian: faktor kedua lokal melindungi dari akun
direktori yang dibajak, dan IdP yang tidak memaksakan MFA untuk semua staf
tidak mengganti apa pun. `IDP_SATISFIES_2FA=true` melewati tantangan itu —
setel hanya bila kebijakan akses bersyarat IdP memang mewajibkan faktor kedua
untuk seluruh akun staf. Keduanya memakai layar tantangan dan bentuk sesi yang
sama dengan formulir kata sandi, jadi dua jalurnya tidak bisa saling menjauh.

### Yang diperiksa pada callback

`state` dibandingkan dengan yang disimpan di sesi dan dikonsumsi sekali (URL
callback yang diputar ulang tidak menemukan apa pun). Klaim `iss`, `aud`
(dan `azp` bila audiensnya lebih dari satu), `exp`, dan `nonce` divalidasi
sebelum satu pun klaim lain dibaca — oleh kelas yang sama untuk driver `oidc`
dan `fake`, sehingga penyedia palsu tidak bisa lebih longgar daripada yang
sungguhan.

Tanda tangan ID token **tidak** diverifikasi terhadap JWKS. Token diterima
langsung dari token endpoint lewat TLS dengan client secret — kasus yang oleh
OIDC Core §3.1.3.7.6 diizinkan memakai validasi TLS sebagai gantinya, dan
alasan mengapa issuer non-HTTPS ditolak sebelum menyentuh jaringan. Kampus yang
menginginkan verifikasi JWKS di atasnya mengikat implementasi
`IdentityProviderInterface`-nya sendiri.

Setiap percobaan, berhasil atau gagal, meninggalkan satu baris di **Log
Aktivitas** (`idp_masuk` / `idp_gagal`) dengan kode alasannya — dan tidak
pernah kode otorisasi, token, atau `error_description` dari IdP, yang pernah
terlihat menggemakan parameter permintaan.
