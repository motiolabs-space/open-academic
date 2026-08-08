@extends('layouts.app')

@section('title', 'Preferensi Notifikasi')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    <div class="max-w-3xl space-y-5">
        <x-card>
            <p class="text-[13px] leading-relaxed text-ink-muted">
                Catatan dalam aplikasi adalah catatan resmi — itulah yang dapat Anda tunjukkan bila
                kelak dipersoalkan apakah Anda pernah diberi tahu. Surel hanyalah cara pengantaran,
                dan selalu boleh dimatikan.
            </p>
            <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
                Sebagian kategori tidak dapat dimatikan pada aplikasi. Bukan karena lebih penting,
                melainkan karena melewatkannya berakibat administratif bagi Anda — kehilangan
                semester, atau tagihan yang menunggak.
            </p>
        </x-card>

        <form method="POST" action="{{ route('notifikasi.preferensi.simpan') }}">
            @csrf @method('PUT')

            <x-card flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                <th class="w-32 px-5 py-3 text-center font-semibold">Aplikasi</th>
                                <th class="w-32 px-5 py-3 text-center font-semibold">Surel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($baris as $b)
                                @php $k = $b['kategori']; @endphp
                                <tr class="border-b border-line/50 align-top last:border-b-0">
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $k->label() }}</div>
                                        <p class="mt-0.5 max-w-md text-[12px] text-ink-muted">
                                            {{ $k->deskripsi() }}
                                        </p>
                                    </td>

                                    <td class="px-5 py-3 text-center">
                                        {{-- Ditampilkan nonaktif, bukan disembunyikan: seseorang
                                             berhak melihat bahwa kampus tidak mengizinkan yang satu
                                             ini dibungkam, dan mengapa. --}}
                                        <input type="hidden" name="kategori[{{ $k->value }}][aplikasi]"
                                            value="{{ $b['terkunci'] ? 1 : 0 }}">
                                        <input type="checkbox" class="accent-navy"
                                            name="kategori[{{ $k->value }}][aplikasi]" value="1"
                                            @checked($b['aplikasi']) @disabled($b['terkunci'])
                                            aria-label="Notifikasi aplikasi untuk {{ $k->label() }}">
                                        @if ($b['terkunci'])
                                            <div class="mt-1 text-[10.5px] text-ink-faint">Selalu aktif</div>
                                        @endif
                                    </td>

                                    <td class="px-5 py-3 text-center">
                                        <input type="hidden" name="kategori[{{ $k->value }}][email]" value="0">
                                        <input type="checkbox" class="accent-navy"
                                            name="kategori[{{ $k->value }}][email]" value="1"
                                            @checked($b['email'])
                                            aria-label="Surel untuk {{ $k->label() }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div class="mt-4 flex gap-2">
                <x-button type="submit">Simpan preferensi</x-button>
                <x-button href="{{ route('notifikasi') }}" variant="outline">Kembali</x-button>
            </div>
        </form>
    </div>
@endsection
