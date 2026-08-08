@extends('layouts.app')

@section('title', 'Pengumuman')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Judul</th>
                            <th class="px-5 py-3 font-semibold">Portal</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $p)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                                <td class="px-5 py-3">
                                    <div class="font-medium">
                                        @if ($p->is_pinned)
                                            <span aria-hidden="true">📌</span>
                                        @endif
                                        {{ $p->judul }}
                                    </div>
                                    @if ($p->ringkasan)
                                        <p class="mt-0.5 max-w-md text-[12px] text-ink-muted">{{ $p->ringkasan }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($p->target_roles as $peran)
                                            <x-chip tone="info">{{ $peranPilihan[$peran] ?? $peran }}</x-chip>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($p->published_at === null)
                                        <x-chip tone="neutral">Draf</x-chip>
                                    @elseif ($p->published_at->isFuture())
                                        <x-chip tone="warning">Terjadwal</x-chip>
                                        <div class="tabular mt-1 text-[11px] text-ink-faint">
                                            {{ $p->published_at->translatedFormat('j M Y H:i') }}
                                        </div>
                                    @else
                                        <x-chip tone="success" dot>Terbit</x-chip>
                                        <div class="tabular mt-1 text-[11px] text-ink-faint">
                                            {{ $p->published_at->translatedFormat('j M Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <form method="POST" action="{{ route('admin.pengumuman.terbitkan', $p) }}">
                                            @csrf
                                            <x-button type="submit" variant="outline" size="sm">
                                                {{ $p->published_at === null ? 'Terbitkan' : 'Tarik' }}
                                            </x-button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.pengumuman.sematkan', $p) }}">
                                            @csrf
                                            <x-button type="submit" variant="ghost" size="sm">
                                                {{ $p->is_pinned ? 'Lepas Sematan' : 'Sematkan' }}
                                            </x-button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.pengumuman.hapus', $p) }}"
                                            onsubmit="return confirm('Hapus pengumuman ini?');">
                                            @csrf @method('DELETE')
                                            <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12">
                                    <x-empty-state title="Belum ada pengumuman"
                                        description="Pengumuman muncul di dasbor portal yang dipilih." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($daftar->hasPages())
                <div class="border-t border-line px-5 py-3">{{ $daftar->links() }}</div>
            @endif
        </x-card>

        <x-card title="Pengumuman Baru">
            <form method="POST" action="{{ route('admin.pengumuman.store') }}" class="flex flex-col gap-3.5">
                @csrf

                <x-field label="Judul" name="judul" required />
                <x-field label="Ringkasan" name="ringkasan"
                    hint="Satu kalimat yang tampil di dasbor." />
                <x-field label="Isi" name="isi" type="textarea" required />

                <div>
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        Tampil di Portal <span class="text-danger" aria-hidden="true">*</span>
                    </span>
                    <div class="flex flex-col gap-1.5">
                        @foreach ($peranPilihan as $nilai => $label)
                            <label class="flex items-center gap-2 text-[13px]">
                                <input type="checkbox" name="target_roles[]" value="{{ $nilai }}"
                                    class="accent-navy" @checked(in_array($nilai, old('target_roles', []), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-field label="Waktu Terbit" name="published_at" type="datetime-local"
                    hint="Kosongkan untuk menyimpan sebagai draf. Isi waktu mendatang untuk menjadwalkan." />

                <label class="flex items-center gap-2 text-[13px]">
                    <input type="hidden" name="is_pinned" value="0">
                    <input type="checkbox" name="is_pinned" value="1" class="accent-navy">
                    Sematkan di puncak daftar
                </label>

                <x-button type="submit" class="mt-1 w-full">Simpan</x-button>
            </form>
        </x-card>
    </div>
@endsection
