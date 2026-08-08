@extends('layouts.app')

@section('title', 'Fakultas')

@section('content')
    @include('admin.master.partials.tabs')

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Kode</th>
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 font-semibold">Dekan</th>
                            <th class="px-5 py-3 text-center font-semibold">Prodi</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $f)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-5 py-3 font-semibold">{{ $f->kode }}</td>
                                <td class="px-5 py-3">
                                    {{ $f->nama }}
                                    @if ($f->singkatan)
                                        <span class="text-ink-faint">({{ $f->singkatan }})</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ $f->dekan?->namaLengkap() ?? '—' }}</td>
                                <td class="tabular px-5 py-3 text-center">{{ $f->prodi_count }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.master.fakultas.destroy', $f) }}"
                                        onsubmit="return confirm('Hapus fakultas {{ $f->nama }}?');">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12">
                                    <x-empty-state title="Belum ada fakultas"
                                        description="Fakultas adalah induk program studi. Tambahkan satu untuk memulai." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Fakultas Baru">
            <form method="POST" action="{{ route('admin.master.fakultas.store') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Kode" name="kode" required placeholder="FTI" />
                <x-field label="Nama" name="nama" required placeholder="Fakultas Teknologi Informasi" />
                <x-field label="Singkatan" name="singkatan" placeholder="FTI" />
                <x-field label="Dekan" name="dekan_dosen_id"
                    :options="$daftarDosen->mapWithKeys(fn ($d) => [$d->id => $d->namaLengkap()])" />
                <x-button type="submit" class="mt-1 w-full">Tambah Fakultas</x-button>
            </form>
        </x-card>
    </div>
@endsection
