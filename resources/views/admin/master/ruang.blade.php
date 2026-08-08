@extends('layouts.app')

@section('title', 'Gedung & Ruang')

@section('content')
    @include('admin.master.partials.tabs')

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex min-w-0 flex-col gap-5">
            @forelse ($daftarGedung as $gedung)
                <x-card :title="$gedung->kode.' — '.$gedung->nama"
                    :meta="$gedung->ruang_count.' ruang'" flush>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Kode</th>
                                    <th class="px-5 py-3 font-semibold">Nama</th>
                                    <th class="px-5 py-3 font-semibold">Jenis</th>
                                    <th class="px-5 py-3 text-center font-semibold">Kapasitas</th>
                                    <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($gedung->ruang as $r)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="tabular px-5 py-3 font-semibold">{{ $r->kode }}</td>
                                        <td class="px-5 py-3">
                                            {{ $r->nama }}
                                            @unless ($r->is_active)
                                                <x-chip tone="neutral" class="ml-1">nonaktif</x-chip>
                                            @endunless
                                        </td>
                                        <td class="px-5 py-3 text-ink-muted">{{ $jenisPilihan[$r->jenis] ?? $r->jenis }}</td>
                                        <td class="tabular px-5 py-3 text-center">{{ $r->kapasitas }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <form method="POST" action="{{ route('admin.master.ruang.destroy', $r) }}"
                                                onsubmit="return confirm('Hapus ruang {{ $r->kode }}?');">
                                                @csrf @method('DELETE')
                                                <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-[13px] text-ink-faint">
                                            Gedung ini belum memiliki ruang.
                                            <form method="POST" class="mt-3 inline-block"
                                                action="{{ route('admin.master.gedung.destroy', $gedung) }}"
                                                onsubmit="return confirm('Hapus gedung {{ $gedung->nama }}?');">
                                                @csrf @method('DELETE')
                                                <x-button type="submit" variant="danger" size="sm">Hapus Gedung</x-button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @empty
                <x-card>
                    <x-empty-state title="Belum ada gedung"
                        description="Jadwal kuliah membutuhkan ruang, dan ruang membutuhkan gedung." />
                </x-card>
            @endforelse
        </div>

        <div class="flex flex-col gap-5">
            <x-card title="Gedung Baru">
                <form method="POST" action="{{ route('admin.master.gedung.store') }}" class="flex flex-col gap-3.5">
                    @csrf
                    <x-field label="Kode" name="kode" required placeholder="A" />
                    <x-field label="Nama" name="nama" required placeholder="Gedung Rektorat" />
                    <x-field label="Alamat" name="alamat" />
                    <x-button type="submit" class="mt-1 w-full">Tambah Gedung</x-button>
                </form>
            </x-card>

            <x-card title="Ruang Baru">
                @if ($daftarGedung->isEmpty())
                    <p class="text-[13px] text-ink-muted">Tambahkan gedung lebih dulu.</p>
                @else
                    <form method="POST" action="{{ route('admin.master.ruang.store') }}" class="flex flex-col gap-3.5">
                        @csrf
                        <x-field label="Gedung" name="gedung_id" required
                            :options="$daftarGedung->pluck('nama', 'id')" />
                        <x-field label="Kode" name="kode" required placeholder="A-201" />
                        <x-field label="Nama" name="nama" required placeholder="Ruang Kuliah 201" />
                        <x-field label="Jenis" name="jenis" required :options="$jenisPilihan" />
                        <x-field label="Kapasitas" name="kapasitas" type="number" required :value="40"
                            hint="Kelas yang kuotanya melebihi kapasitas ruang menghasilkan jadwal yang tidak bisa diajarkan." />
                        <x-button type="submit" class="mt-1 w-full">Tambah Ruang</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection
