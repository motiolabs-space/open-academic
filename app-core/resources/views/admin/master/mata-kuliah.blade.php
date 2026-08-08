@extends('layouts.app')

@section('title', 'Mata Kuliah')

@section('content')
    @include('admin.master.partials.tabs')

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field label="Cari" name="cari" type="search" :value="$filter['cari'] ?? ''"
                placeholder="Kode atau nama…" class="min-w-[220px] flex-1" />
            <x-field label="Program Studi" name="prodi" :value="$filter['prodi'] ?? ''"
                :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />
            <x-button type="submit">Terapkan</x-button>
            @if (array_filter($filter))
                <x-button variant="ghost" :href="route('admin.master.mata-kuliah')">Reset</x-button>
            @endif
        </form>
    </x-card>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Kode</th>
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 text-center font-semibold">SKS (T/P/L)</th>
                            <th class="px-5 py-3 font-semibold">Prasyarat</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $mk)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                                <td class="tabular px-5 py-3 font-semibold">{{ $mk->kode }}</td>
                                <td class="px-5 py-3">
                                    {{ $mk->nama }}
                                    @unless ($mk->is_active)
                                        <x-chip tone="neutral" class="ml-1">nonaktif</x-chip>
                                    @endunless
                                    <div class="text-[11.5px] text-ink-faint">{{ $mk->prodi->nama }}</div>
                                </td>
                                <td class="tabular px-5 py-3 text-center">
                                    <span class="font-semibold">{{ $mk->sks }}</span>
                                    <div class="text-[11px] text-ink-faint">
                                        {{ $mk->sks_teori }}/{{ $mk->sks_praktik }}/{{ $mk->sks_praktik_lapangan }}
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @forelse ($mk->prasyarat as $p)
                                        <form method="POST" class="mb-1 inline-block"
                                            action="{{ route('admin.master.mata-kuliah.prasyarat.hapus', [$mk, $p]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-full border border-line bg-line/40 px-2.5 py-1 text-[11.5px] hover:border-danger-line hover:text-danger"
                                                title="Hapus prasyarat {{ $p->kode }}">
                                                {{ $p->kode }}
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </form>
                                    @empty
                                        <span class="text-ink-faint">—</span>
                                    @endforelse

                                    <details class="mt-1.5">
                                        <summary class="cursor-pointer text-[11.5px] text-navy">+ tambah</summary>
                                        <form method="POST" class="mt-2 flex gap-1.5"
                                            action="{{ route('admin.master.mata-kuliah.prasyarat.tambah', $mk) }}">
                                            @csrf
                                            <select name="prasyarat_id" required
                                                class="min-w-0 flex-1 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                @foreach ($daftar->where('prodi_id', $mk->prodi_id)->where('id', '!=', $mk->id) as $opsi)
                                                    <option value="{{ $opsi->id }}">{{ $opsi->kode }} — {{ $opsi->nama }}</option>
                                                @endforeach
                                            </select>
                                            <select name="jenis"
                                                class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <option value="prasyarat">Prasyarat</option>
                                                <option value="bersamaan">Bersamaan</option>
                                            </select>
                                            <x-button type="submit" size="sm">Simpan</x-button>
                                        </form>
                                    </details>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.master.mata-kuliah.destroy', $mk) }}"
                                        onsubmit="return confirm('Hapus {{ $mk->kode }}?');">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12">
                                    <x-empty-state title="Tidak ada mata kuliah"
                                        description="Tambahkan mata kuliah lewat formulir di sebelah, lalu susun ke dalam kurikulum." />
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

        <x-card title="Mata Kuliah Baru">
            <form method="POST" action="{{ route('admin.master.mata-kuliah.store') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Program Studi" name="prodi_id" required
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />
                <x-field label="Kode" name="kode" required placeholder="IF2101" />
                <x-field label="Nama" name="nama" required placeholder="Struktur Data" />
                <x-field label="Nama (Inggris)" name="nama_en" placeholder="Data Structures" />

                <div class="grid grid-cols-3 gap-2.5">
                    <x-field label="Teori" name="sks_teori" type="number" required :value="0" />
                    <x-field label="Praktik" name="sks_praktik" type="number" required :value="0" />
                    <x-field label="Lapangan" name="sks_praktik_lapangan" type="number" required :value="0" />
                </div>
                <p class="-mt-1.5 text-[11.5px] text-ink-faint">
                    PDDIKTI melaporkan ketiganya terpisah; totalnya dihitung otomatis.
                </p>

                <x-field label="Deskripsi" name="deskripsi" type="textarea" />
                <x-button type="submit" class="mt-1 w-full">Tambah Mata Kuliah</x-button>
            </form>
        </x-card>
    </div>
@endsection
