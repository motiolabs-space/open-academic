# SSO — Open Academic sebagai Penyedia Identitas

Open Academic menjalankan server OAuth2 (Laravel Passport). Aplikasi kampus lain
mengarahkan penggunanya ke sini untuk masuk; Open Academic tidak menyambung ke
mana pun.

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

Mendaftarkan aplikasi:

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

**`SSO_FIRST_PARTY`** — client id yang boleh melewati layar persetujuan. Isi
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

Administrator: `php artisan openacademic:sso-client --cabut=<client-id>`.

Menonaktifkan akun (`is_active = false`) **langsung** memutus akses seluruh
konsumen atas nama orang itu — tidak menunggu tokennya kedaluwarsa.

---

## Belum Ada: Federasi ke IdP Eksternal

Sebagian kampus sudah punya Google Workspace, Microsoft Entra ID, atau Keycloak.
Menjadikan Open Academic *client* terhadap IdP tersebut belum diimplementasikan.

Yang menahannya bukan pekerjaan teknisnya, melainkan satu keputusan kebijakan:
identitas `budi@kampus.ac.id` yang datang dari Google **dipetakan ke tabel yang
mana?** Ada tiga opsi — pencocokan surel ke ketiga tabel (dan menolak bila
ambigu), pemisahan lewat domain (`student.kampus.ac.id` vs `kampus.ac.id`), atau
klaim khusus dari IdP.

Salah memilih berarti seorang dosen bisa masuk ke portal mahasiswa. Keputusan
itu milik kampus, bukan milik kode ini, dan harus diambil sebelum fiturnya
dibangun.
