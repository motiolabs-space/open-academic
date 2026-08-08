@extends('layouts.base')

@section('title', 'Semester Belum Dibuka')

@section('body')
<div class="flex min-h-screen items-center justify-center px-6 py-12">
    <div class="w-full max-w-lg text-center">
        <div class="guilloche-navy rounded-card-lg border border-line bg-surface px-8 py-12">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-navy font-serif text-xl text-gold">
                ◆
            </div>

            <h1 class="mt-6 font-serif text-[26px] font-semibold">Belum Ada Semester Aktif</h1>

            <p class="mx-auto mt-3 max-w-md text-[13.5px] leading-relaxed text-ink-muted">
                @if ($dapatMemperbaiki)
                    Sistem tidak dapat menampilkan data akademik sebelum satu tahun akademik
                    ditetapkan sebagai semester berjalan.
                    <br>
                    Buka <strong>Pengaturan &rarr; Kalender Akademik</strong>, lalu aktifkan
                    semester yang sedang berlangsung.
                @else
                    Bagian akademik belum menetapkan semester yang sedang berjalan.
                    Data Anda aman — portal akan terbuka kembali begitu semester diaktifkan.
                    <br>
                    Silakan hubungi BAAK bila keadaan ini berlanjut.
                @endif
            </p>

            @if ($dapatMemperbaiki)
                <div class="mt-6 rounded-card border border-line bg-canvas px-4 py-3 text-left">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">
                        Lewat baris perintah
                    </div>
                    <code class="tabular mt-1.5 block text-[12.5px] text-navy">
                        php artisan tinker --execute="App\Models\Akademik\TahunAkademik::where('kode','20261')->update(['is_active'=&gt;true]);"
                    </code>
                </div>
            @endif

            <div class="mt-7 flex justify-center gap-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button variant="outline" type="submit">Keluar</x-button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
