@extends('layouts.app')

@section('title', 'Presensi Mandiri')

@section('content')
    <div class="mx-auto max-w-md">
        @if (session('sukses'))
            <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
        @endif

        @if (session('galat'))
            <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
        @endif

        @if ($errors->any())
            <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
        @endif

        <x-card title="Catat Kehadiran">
            <p class="mb-4 text-[13px] leading-relaxed text-ink-muted">
                Pindai kode QR yang ditampilkan dosen dengan kamera ponsel Anda. Kode akan
                membuka halaman ini beserta kodenya. Bila kamera tidak terbaca, ketik kode
                di bawah secara manual.
            </p>

            <form method="POST" action="{{ route('mahasiswa.presensi.catat') }}" class="flex flex-col gap-3.5">
                @csrf

                <x-field label="Kode Presensi" name="token" :value="$token" required
                    placeholder="Tempel atau ketik kode di sini"
                    hint="Kode berganti dan kedaluwarsa dalam hitungan menit." />

                <x-button type="submit" class="w-full">Catat Kehadiran Saya</x-button>
            </form>

            <p class="mt-4 border-t border-line pt-3 text-[11.5px] leading-relaxed text-ink-faint">
                Kehadiran hanya tercatat bila Anda terdaftar pada kelas tersebut dan sesi
                presensinya masih dibuka. Kode yang diteruskan ke teman akan kedaluwarsa
                sebelum sempat berguna.
            </p>
        </x-card>
    </div>
@endsection
