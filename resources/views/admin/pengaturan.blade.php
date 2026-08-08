@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Identitas Institusi">
            <form method="POST" action="{{ route('admin.pengaturan.simpan') }}" class="flex flex-col gap-3.5">
                @csrf @method('PUT')

                <x-field label="Nama Institusi" name="institution_name" required
                    :value="$branding['institution_name'] ?? ''" />
                <x-field label="Singkatan" name="institution_short" required
                    :value="$branding['institution_short'] ?? ''"
                    hint="Muncul pada judul tab dan kop transkrip." />
                <x-field label="Kode PDDIKTI Institusi" name="institution_code"
                    :value="$branding['institution_code'] ?? ''"
                    hint="Salah isi berarti seluruh pelaporan kampus ini masuk atas nama institusi lain." />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-field label="Warna Utama" name="primary_color" required
                            :value="$branding['primary_color'] ?? '#1E2761'" placeholder="#1E2761" />
                        <div class="mt-1.5 h-6 rounded border border-line"
                            style="background: {{ $branding['primary_color'] ?? '#1E2761' }}"></div>
                    </div>
                    <div>
                        <x-field label="Warna Aksen" name="accent_color" required
                            :value="$branding['accent_color'] ?? '#C9A961'" placeholder="#C9A961" />
                        <div class="mt-1.5 h-6 rounded border border-line"
                            style="background: {{ $branding['accent_color'] ?? '#C9A961' }}"></div>
                    </div>
                </div>

                <x-button type="submit" class="mt-1 self-start">Simpan Pengaturan</x-button>
            </form>
        </x-card>

        <x-card title="Aturan Akademik" meta="hanya dapat diubah lewat berkas konfigurasi">
            <p class="mb-4 text-[12.5px] leading-relaxed text-ink-muted">
                Aturan berikut menentukan bagaimana angka <em>dihitung</em>, bukan sekadar
                bagaimana angka ditampilkan. Karena itu ia hidup di
                <code class="rounded bg-line/50 px-1.5 py-0.5 text-[11.5px]">config/academic.php</code>
                dan ikut kendali versi — mengubah skala nilai di tengah semester akan
                menulis ulang transkrip yang sudah terlanjur terbit, tanpa jejak.
            </p>

            <dl class="flex flex-col gap-3">
                @foreach ($aturanTerkunci as $label => $nilai)
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line pb-2.5 last:border-b-0">
                        <dt class="text-[12.5px] font-semibold">{{ $label }}</dt>
                        <dd class="tabular text-[12.5px] text-ink-muted">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>
    </div>
@endsection
