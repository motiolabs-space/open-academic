# CLAUDE.md — Open Academic

> Sumber kebenaran untuk setiap sesi pengembangan di repositori ini, baik oleh
> kontributor manusia maupun agen AI. Baca penuh sebelum menulis kode.
> Bila ragu, ikuti berkas ini, bukan asumsi.

---

## 1. Ringkasan Proyek

**Open Academic** adalah SIAKAD open source untuk perguruan tinggi Indonesia,
berperan sebagai **system of record** dalam ekosistem Motiolabs Open Education.

- Repo: `motiolabs-space/open-academic` · Lisensi MIT
- Saudara: [Open Campus](https://github.com/motiolabs-space/open-campus) — lapisan
  ekosistem & engagement
- Analogi: Open Academic = kantor rektorat/BAAK digital; Open Campus = ruang komunitas

### Batas tanggung jawab

Fitur tentang **catatan akademik resmi & transaksi administratif** → repo ini.
Fitur tentang **engagement, evidence, analitik, jejaring** → Open Campus.
Jangan pernah menduplikasi fitur di dua sisi.

Kalau sebuah permintaan terasa seperti wilayah Open Campus (feed sosial, review
evidence, dasbor IKU, marketplace) — **hentikan dan tanyakan**, jangan bangun di sini.

### Bukan cakupan

Bukan LMS/e-learning · tanpa fitur sosial/portofolio · tanpa dasbor IKU ·
tanpa aplikasi mobile di v1 (API-first menyiapkan jalannya) · tanpa AI agent di core.

---

## 2. Stack

| Layer | Pilihan | Catatan |
|---|---|---|
| Framework | Laravel 12, PHP 8.2+ | Lihat ADR-002 soal jalur upgrade ke Laravel 13 |
| Database | MySQL 8 / MariaDB 10.11 | utf8mb4, InnoDB |
| Frontend | Blade + Alpine.js + Tailwind 4 (Vite) | Open Campus memakai React/Inertia — dua aplikasi hanya berbagi API, tidak pernah frontend |
| Auth web | Session, guard `staff` / `dosen` / `mahasiswa` | Lihat ADR-003 |
| Auth API/SSO | Sanctum (token) + OAuth2 untuk SSO | Fase 3 |
| Izin | Spatie Laravel Permission | Peran & izin terpisah per guard |
| Antrean | Redis (produksi) / database (dev) | **Wajib jalan** — audit log, Feeder sync, webhook semuanya ter-antre |
| Pembayaran | Midtrans di balik `PaymentGatewayInterface` | |
| Uji | Pest | Minimal satu feature test per PR fitur |
| Dokumen API | OpenAPI 3.1 di `docs/openapi/` | Bridge = spec-first |

**Aturan bahasa.** Kode, komentar, commit, migrasi, dan dokumen teknis dalam
**bahasa Inggris**. String UI dalam **Bahasa Indonesia** lewat `lang/id/` —
jangan hardcode teks Indonesia di Blade.

---

## 3. Arsitektur

```
app/
├── Console/Commands/          # openacademic:* artisan commands
├── DTOs/                      # final readonly, fromRequest()/fromArray()
├── Enums/                     # backed enum, sejajar kode PDDIKTI
├── Http/
│   ├── Controllers/{Admin,Dosen,Mahasiswa,Auth,Api/V1,Api/Bridge}
│   ├── Middleware/
│   ├── Requests/              # semua validasi lewat FormRequest
│   └── Resources/Bridge/
├── Jobs/                      # RecordActivityLogJob, SyncFeederEntityJob, ...
├── Models/{Akademik,Kemahasiswaan,Sdm,Pmb,Keuangan,Feeder,Bridge,System}
├── Policies/
├── Services/{Akademik,Feeder,Bridge,Payment,Branding}
├── Support/                   # Format, Navigation, Portal
└── Traits/                    # HasUuid, HasLogAktivitas
```

### Aturan keras

1. **Controller tipis.** FormRequest → Service → view/resource. Tanpa query
   Eloquent di controller selain `find` trivial.
2. **Service pemilik business logic**, menerima/mengembalikan DTO, bukan array
   request mentah.
3. **Enum untuk setiap kolom status/tipe**, plus cast di model.
4. **`HasLogAktivitas` wajib** di setiap model yang memutasi data akademik/keuangan.
5. **Migrasi append-only** setelah masuk `main`.
6. **Semua I/O eksternal** (Feeder, Midtrans, Open Campus) di balik interface,
   dengan implementasi fake untuk uji.
7. **UUID untuk identitas publik** (URL/API); `id` auto-increment hanya internal.
8. **Basis data tidak pernah diekspos ke Open Campus** — semua lewat Campus Bridge.
   Dua aplikasi harus bisa di-deploy di server terpisah.
9. **Model memakai `$guarded`, bukan `$fillable`** (ADR-007). Konsekuensinya:
   controller **tidak pernah** meneruskan `request()->all()` ke model. Ini wajib
   dicek saat review PR.

---

## 4. Domain Inti

```
TahunAkademik (20261 = 2026/2027 Ganjil — encoding PDDIKTI)
  └─ Prodi → Kurikulum (berversi) → MataKuliah (SKS, posisi semester, prasyarat)
       └─ KelasKuliah (dosen pengampu, jadwal, ruang, kuota,
          flag case_method / team_based_project → IKU 7)
            └─ KRS (draft → diajukan → disetujui oleh Dosen Wali;
               batas SKS dari IPS; cek prasyarat; cek kuota;
               terkunci sampai pembayaran minimum)
                 ├─ Presensi (16 pertemuan; minimum kehadiran untuk UAS)
                 ├─ Penilaian (komponen berbobot per kelas)
                 └─ KHS → IPS, IPK → Transkrip (PDF)
```

**Prinsip integritas.** Nilai, persetujuan KRS, dan perubahan status adalah
**peristiwa**: soft delete + audit log, tidak pernah hard delete dan tidak pernah
ditimpa diam-diam. Perbaikan nilai final hanya lewat jalur koreksi ter-audit.

---

## 5. Neo Feeder PDDIKTI

Neo Feeder adalah web service yang dipasang lokal oleh kampus
(default `http://<host>:3003/ws/live2.php`) dengan aksi JSON `GetToken`,
`InsertBiodataMahasiswa`, `InsertKRSMahasiswa`, `InsertNilaiPerkuliahanKelas`, dst.

Empat properti yang tidak boleh dikompromikan:

1. **Referensi dulu.** Tarik data referensi Feeder ke `feeder_refs`; enum lokal
   dipetakan ke kode Feeder lewat `feeder_mappings` — jangan pernah mengasumsikan
   enum lokal sama dengan kode PDDIKTI.
2. **Idempotent.** Setiap dorongan menulis satu baris `feeder_sync_logs` dengan
   `payload_hash`. Menjalankan ulang membandingkan hash dan mencatat `Skipped`,
   bukan mengirim duplikat. Sinkronisasi yang terputus harus selalu aman diulang.
3. **Ter-antre & berbatch.** Job per entitas per semester, dengan UI monitor.
4. **Validasi pra-kirim.** Laporkan seluruh baris yang akan ditolak Feeder
   *sebelum* sinkronisasi dijalankan, bukan satu per satu saat gagal di tengah jalan.

---

## 6. IKU Data Provider — bukan dasbor

Dasbor, review evidence, dan tata kelola **12 IKU** milik Open Campus.
Tugas repo ini hanya menyediakan kebenaran transaksionalnya:

| IKU | Sumber di Open Academic |
|---|---|
| 1 — Kesiapan lulusan | `yudisium`, `alumni` |
| 2 — Pengalaman luar kampus | `aktivitas_mahasiswa` (MBKM, `sks_konversi`) |
| 3 — Dosen di luar kampus | `penugasan_dosen` |
| 4 — Praktisi mengajar | `kelas_dosen.peran = praktisi` |
| 7 — Kelas kolaboratif | `kelas_kuliah.is_case_method` / `is_team_based_project` |
| 11 — Efisiensi edukasi | Statistik operasional agregat |

**Jangan membangun kalkulator atau dasbor IKU di sini.**

---

## 7. Campus Bridge

Tiga kanal, spec-first di `docs/openapi/bridge.yaml`:

1. **SSO** — Open Academic sumber identitas; OAuth2 authorization-code.
2. **Read API** `/api/bridge/v1/...` — token Sanctum per aplikasi konsumen,
   dibatasi scope, paginated, rate-limited.
3. **Webhook** — event bertanda tangan HMAC, pengiriman ter-antre dengan backoff,
   log pengiriman lengkap.

Event: `student.enrolled`, `student.status_changed`, `krs.approved`,
`grade.finalized`, `student.graduated`, `activity.recorded`,
`lecturer.assignment_recorded`.

---

## 8. Konvensi Database

- Nama tabel domain akademik dalam **bahasa Indonesia** (`mahasiswa`, `dosen`,
  `krs`, `mata_kuliah`); tabel infrastruktur dalam **bahasa Inggris**
  (`feeder_sync_logs`, `bridge_webhook_deliveries`, `settings`).
- Setiap tabel akademik: `uuid` unik, timestamps, `deleted_at` bila bisa dimutasi pengguna.
- FK selalu dengan constraint; `restrictOnDelete` untuk referensi akademik —
  nilai tidak boleh ikut terhapus.
- Uang disimpan sebagai integer rupiah penuh. **Tidak ada float untuk nominal.**
- Encoding semester mengikuti PDDIKTI: `20261` Ganjil, `20262` Genap, `20263` Antara.
- Seeder demo wajib tetap menghasilkan kampus utuh yang bisa mendemokan satu siklus
  semester penuh.

---

## 9. Uji & Gerbang Mutu

- Pest wajib untuk: setiap method publik Service, setiap endpoint API/Bridge,
  aturan KRS (batas SKS, prasyarat, kuota), perhitungan nilai, mapper Feeder
  (snapshot payload vs fixture), penandatanganan & pengiriman webhook.
- Suite berjalan di SQLite in-memory; jaga migrasi tetap portabel.
  **SQLite tidak punya batas panjang identifier, MySQL punya (64 karakter).**
  Beri nama indeks eksplisit begitu nama otomatis Laravel — `{tabel}_{kolom…}_index`
  — melewati 64. Migrasi kuesioner pernah lolos seluruh suite sambil tidak dapat
  dipasang di MySQL sama sekali; lihat `docs/MODUL.md`.
- Jalankan paralel: `composer test:par` (paratest, satu SQLite `:memory:` per
  proses). **Apa pun yang ditulis ke jalur bersama harus di-scope per proses**
  dengan `ParallelTesting::token()` — kunci Passport pernah membuat tes SSO
  gagal 3 dari 5 kali sebelum dipisahkan; lihat `siapkanKunciPassport()` di
  `tests/Pest.php`. `composer test` tetap serial untuk menelusuri kegagalan.
- CI: Pint → Pest (paralel) → `npm run build` harus hijau sebelum merge.
- Tidak ada PR fitur tanpa minimal satu feature test.

---

## 10. Git & Alur Kerja

- Branch: `main` (protected) ← `dev` ← `feature/<modul>-<deskripsi-singkat>`
- Conventional commit dalam bahasa Inggris: `feat(krs): add dosen wali approval flow`
- Satu modul per PR, diff kecil dan bisa direview.
- Jangan pernah commit rahasia; jaga `.env.example` tetap lengkap dan mutakhir.

---

## 11. Roadmap

| Fase | Cakupan | Definition of Done |
|---|---|---|
| 0 — Fondasi | Skema, enum, model, auth, shell UI, seeder demo, CI | ✅ Instalasi fresh langsung bisa didemokan, CI hijau |
| 1 — Inti Akademik | Service KRS/KHS, penilaian, presensi, transkrip PDF | Satu siklus semester penuh jalan end-to-end |
| 2 — Feeder | Client, tarik referensi, mapper + ledger, validator, UI sinkron | Data demo terdorong bersih ke sandbox Neo Feeder |
| 3 — Campus Bridge | SSO, Read API, webhook, OpenAPI | Instance Open Campus login via SSO & mengonsumsi data |
| 4 — Data IKU | Endpoint aktivitas, penugasan, lulusan | Engine IKU Open Campus menghitung IKU 1/2/3/4/7 |
| 5 — Polish & Rilis | Migrasi UI penuh, audit N+1, review keamanan, docs site | Rilis publik v1.0 |

Kerjakan berurutan. Nyatakan fase/modul di awal setiap sesi.

---

## 12. Branding & UI

- Design tokens "Midnight Executive" ada di `resources/css/app.css` —
  navy `#1E2761`, emas `#C9A961` (dipakai hemat), latar `#F8F8F6`,
  Source Serif 4 (judul) + Public Sans (antarmuka).
- **Jangan pernah menulis hex langsung di Blade.** Pakai token Tailwind.
- Angka wajib `tabular-nums`; format Indonesia (koma desimal, titik ribuan,
  `Rp4.850.000`, `29 Jul 2026`, `07.30 WIB`) lewat `App\Support\Format`.
- Kartu memakai border, bukan shadow. Hanya overlay yang boleh terangkat.
- Nada copy: Bahasa Indonesia formal-ramah.

---

## 13. Etika Sesi

1. Mulai dengan membaca berkas ini + `docs/STATUS.md`; perbarui `STATUS.md` di akhir sesi.
2. Catat keputusan non-obvious di `docs/DECISIONS.md` (Konteks → Keputusan → Konsekuensi).
3. Sebelum menyentuh modul yang sudah ada, baca kodenya utuh; utamakan refactor
   di tempat daripada menulis ulang.
4. Bila codebase bertentangan dengan berkas ini, laporkan dan tanyakan —
   jangan menyimpang diam-diam.
