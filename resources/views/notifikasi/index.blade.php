@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-[220px_180px_auto]">
            <x-field label="Kategori" name="kategori" :options="$kategoriPilihan" :value="$filter['kategori'] ?? null" />
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                    Tampilkan
                </label>
                <label class="flex items-center gap-2 py-2 text-[13px]">
                    <input type="checkbox" name="belum" value="1" class="accent-navy"
                        @checked($filter['belum'] ?? false)>
                    Hanya yang belum dibaca
                </label>
            </div>
            <div class="flex items-end gap-2">
                <x-button type="submit">Terapkan</x-button>
                <x-button href="{{ route('notifikasi') }}" variant="outline">Reset</x-button>
            </div>
        </form>

        <div class="flex items-end gap-2">
            <form method="POST" action="{{ route('notifikasi.baca-semua') }}">
                @csrf
                <x-button type="submit" variant="outline">Tandai semua dibaca</x-button>
            </form>
            <x-button href="{{ route('notifikasi.preferensi') }}" variant="outline">Preferensi</x-button>
        </div>
    </div>

    <x-card flush>
        @forelse ($daftar as $n)
            @php $isi = $n->data; @endphp
            <div @class([
                'flex items-start gap-3 border-b border-line/50 px-5 py-4 last:border-b-0',
                'bg-highlight/40' => $n->read_at === null,
            ])>
                {{-- Penanda belum dibaca berupa titik, bukan warna teks: satu-satunya
                     pembeda yang tidak bergantung pada persepsi warna. --}}
                <span @class([
                    'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                    'bg-navy' => $n->read_at === null,
                    'bg-transparent' => $n->read_at !== null,
                ]) aria-hidden="true"></span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[13px] font-semibold">{{ $isi['judul'] ?? '—' }}</span>
                        <x-chip :tone="$isi['tone'] ?? 'info'">
                            {{ $kategoriPilihan[$isi['kategori'] ?? ''] ?? 'Lainnya' }}
                        </x-chip>
                        @if ($n->read_at === null)
                            <span class="sr-only">Belum dibaca</span>
                        @endif
                    </div>

                    <p class="mt-1 text-[13px] text-ink-muted">{{ $isi['ringkasan'] ?? '' }}</p>

                    <p class="tabular mt-1 text-[11.5px] text-ink-faint">
                        {{ $n->created_at->diffForHumans() }}
                    </p>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-1.5">
                    @if (! empty($isi['tautan']))
                        <x-button :href="$isi['tautan']" variant="outline" size="sm">Buka</x-button>
                    @endif
                    @if ($n->read_at === null)
                        <form method="POST" action="{{ route('notifikasi.baca', $n->id) }}">
                            @csrf
                            <x-button type="submit" variant="outline" size="sm">Tandai dibaca</x-button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <x-empty-state
                title="Belum ada notifikasi"
                description="Keputusan rencana studi, tagihan, dan jadwal ujian akan muncul di sini." />
        @endforelse
    </x-card>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
