@extends('layouts.base')

@section('title', 'Sistem Akademik Terbuka')

{{-- Satu-satunya halaman yang meminta diindeks; sisanya noindex secara bawaan. --}}
@section('robots', 'index, follow')
@section('deskripsi', 'Sistem informasi akademik untuk mahasiswa, dosen, dan staf — KRS, nilai, transkrip, keuangan, dan pelaporan PDDIKTI dalam satu tempat.')

@php
    use App\Services\Branding\BrandingService;
    $brand = app(BrandingService::class);

    $fitur = [
        ['◫', 'KRS & KHS', 'Siklus rencana studi penuh: batas SKS dari IPS, cek prasyarat, kuota kelas atomik, persetujuan Dosen Wali, hingga transkrip resmi.'],
        ['⇅', 'Neo Feeder PDDIKTI', 'Pelaporan sebagai modul kelas satu — idempotent, ter-antre, dengan buku besar sinkron dan validasi pra-kirim.'],
        ['◈', 'Keuangan & Midtrans', 'Matriks tarif per prodi dan golongan UKT, tagihan otomatis per semester, kunci KRS berbasis pembayaran minimum.'],
        ['⌘', 'Campus Bridge API', 'Kontrak REST + webhook bertanda tangan + SSO agar sistem lain membaca data akademik tanpa menyentuh basis data.'],
        ['≡', 'Penilaian Berbobot', 'Komponen nilai per kelas, hitung nilai akhir langsung, kunci & finalisasi, koreksi hanya lewat jalur ter-audit.'],
        ['◷', 'Jejak Audit', 'Nilai, persetujuan KRS, dan perubahan status adalah peristiwa: tercatat, tidak pernah ditimpa diam-diam.'],
    ];
@endphp

@section('body')
<header class="guilloche-gold relative bg-navy text-canvas">
    <nav class="relative mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <div class="flex items-center gap-2.5">
            <div class="grid h-8 w-8 place-items-center rounded-lg border-[1.5px] border-gold font-serif text-base font-bold text-gold">
                {{ $brand->logoInitial() }}
            </div>
            <span class="text-[13.5px] font-bold">{{ config('app.name') }}</span>
        </div>

        <div class="flex items-center gap-3">
            <a href="https://github.com/motiolabs-space/open-academic"
               class="hidden text-[13px] text-canvas/75 hover:text-gold sm:block">GitHub</a>
            <x-button variant="gold" :href="route('login')">Masuk</x-button>
        </div>
    </nav>

    <div class="relative mx-auto max-w-6xl px-6 pb-24 pt-14">
        <div class="mb-6 flex flex-wrap gap-2 text-[11px] font-semibold tracking-[0.08em]">
            <span class="rounded-full border border-gold/50 px-3 py-1.5 text-gold">MIT LICENSE</span>
            <span class="rounded-full border border-canvas/25 px-3 py-1.5 text-canvas/80">LARAVEL 12 · MYSQL</span>
            <span class="rounded-full border border-canvas/25 px-3 py-1.5 text-canvas/80">SIAP NEO FEEDER</span>
        </div>

        <h1 class="max-w-3xl font-serif text-[32px] font-semibold leading-[1.12] sm:text-[44px] lg:text-[54px] lg:leading-[1.08]">
            Sistem Akademik Terbuka untuk Perguruan Tinggi Indonesia
        </h1>

        <p class="mt-5 max-w-2xl text-[16px] leading-relaxed text-canvas/70">
            Open Academic adalah SIAKAD open source yang menjadi <em>system of record</em> kampus —
            dari penerimaan mahasiswa baru sampai wisuda — dan dirancang sejak awal untuk
            terhubung dengan sistem Kementerian Pendidikan Tinggi.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-button variant="gold" :href="route('login')">Coba Demo Kampus</x-button>
            <x-button
                variant="outline-gelap"
                href="https://github.com/motiolabs-space/open-academic"
            >Lihat Kode Sumber</x-button>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl px-6 py-20">
    <section>
        <h2 class="font-serif text-[26px] font-semibold">Yang Membedakan</h2>
        <p class="mt-2 max-w-2xl text-[14px] leading-relaxed text-ink-muted">
            Sebagian besar SIAKAD open source berhenti di pencatatan. Open Academic menangani
            bagian yang paling menyita waktu kampus kecil: pelaporan PDDIKTI dan integrasi antar sistem.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($fitur as [$ikon, $judul, $deskripsi])
                <div class="rounded-card border border-line bg-surface p-6">
                    <div class="grid h-10 w-10 place-items-center rounded-lg bg-navy text-lg text-gold">{{ $ikon }}</div>
                    <h3 class="mt-4 text-[15px] font-semibold">{{ $judul }}</h3>
                    <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">{{ $deskripsi }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-20">
        <h2 class="font-serif text-[26px] font-semibold">Ekosistem Motiolabs Open Education</h2>
        <p class="mt-2 max-w-2xl text-[14px] leading-relaxed text-ink-muted">
            Dua produk yang saling melengkapi dan <strong>tidak pernah berbagi basis data</strong> —
            keduanya hanya berbicara lewat Campus Bridge di atas HTTPS.
        </p>

        <div class="mt-8 grid items-stretch gap-4 lg:grid-cols-[1fr_auto_1fr]">
            <div class="rounded-card-lg border border-navy bg-navy p-7 text-canvas">
                <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-gold">System of Record</div>
                <h3 class="mt-2 font-serif text-[22px] font-semibold">Open Academic</h3>
                <ul class="mt-4 space-y-2 text-[13px] leading-relaxed text-canvas/75">
                    <li>· PMB &amp; siklus hidup mahasiswa</li>
                    <li>· Kurikulum, mata kuliah, jadwal</li>
                    <li>· KRS/KHS, nilai, transkrip</li>
                    <li>· Presensi &amp; keuangan</li>
                    <li>· Sinkronisasi Neo Feeder PDDIKTI</li>
                    <li>· Sumber identitas (SSO)</li>
                </ul>
            </div>

            <div class="grid place-items-center px-4 py-2">
                <div class="rounded-full border border-line bg-surface px-4 py-2 text-[12px] font-semibold text-navy">
                    ⇄ Campus Bridge
                </div>
            </div>

            <div class="rounded-card-lg border border-line bg-surface p-7">
                <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-ink-faint">Ecosystem &amp; Engagement</div>
                <h3 class="mt-2 font-serif text-[22px] font-semibold">Open Campus</h3>
                <ul class="mt-4 space-y-2 text-[13px] leading-relaxed text-ink-muted">
                    <li>· Jejaring sosial kampus</li>
                    <li>· Review evidence berbantuan AI</li>
                    <li>· Dasbor &amp; tata kelola 12 IKU</li>
                    <li>· Talent marketplace &amp; industri</li>
                    <li>· Analitik eksekutif</li>
                    <li>· Network mode multi-kampus</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="mt-20 rounded-card-lg border border-line bg-surface p-8">
        <h2 class="font-serif text-[22px] font-semibold">Pasang dalam Beberapa Menit</h2>
        <p class="mt-2 text-[13px] text-ink-muted">
            Instalasi baru langsung berisi kampus demo lengkap: satu fakultas, dua program studi,
            50 mahasiswa, tiga semester dengan nilai, tagihan, dan data PMB.
        </p>

        <pre class="tabular mt-5 overflow-x-auto rounded-card bg-navy px-5 py-4 text-[12.5px] leading-relaxed text-canvas"><code>git clone https://github.com/motiolabs-space/open-academic.git
cd open-academic &amp;&amp; composer install &amp;&amp; npm install
cp .env.example .env &amp;&amp; php artisan key:generate
php artisan migrate --seed &amp;&amp; npm run build</code></pre>
    </section>
</main>

<footer class="border-t border-line bg-surface">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-8 text-[12.5px] text-ink-muted">
        <span>{{ config('app.name') }} — bagian dari Motiolabs Open Education. Lisensi MIT.</span>
        <span>Dibangun untuk perguruan tinggi Indonesia.</span>
    </div>
</footer>
@endsection
