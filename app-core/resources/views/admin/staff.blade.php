@extends('layouts.app')

@section('title', 'Akun Staf')

@section('content')
    @if (session('kata_sandi_baru'))
        <x-kredensial-baru :data="session('kata_sandi_baru')" />
    @endif

    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-5">
            <x-card>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <x-field label="Cari" name="cari" type="search" :value="$filter['cari'] ?? ''"
                        placeholder="Nama, NIP, atau surel…" class="min-w-[220px] flex-1" />
                    <x-button type="submit">Cari</x-button>
                    @if (array_filter($filter))
                        <x-button variant="ghost" :href="route('admin.staff')">Reset</x-button>
                    @endif
                </form>
            </x-card>

            <x-card flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">NIP</th>
                                <th class="px-5 py-3 font-semibold">Nama</th>
                                <th class="px-5 py-3 font-semibold">Unit</th>
                                <th class="px-5 py-3 font-semibold">Peran</th>
                                <th class="px-5 py-3 text-center font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $s)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="tabular px-5 py-3">{{ $s->nip ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $s->nama }}</div>
                                        <div class="text-[11.5px] text-ink-faint">{{ $s->email }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-ink-muted">
                                        {{ $s->unit ?? '—' }}
                                        @if ($s->jabatan)
                                            <div class="text-[11.5px] text-ink-faint">{{ $s->jabatan }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @foreach ($s->roles as $peran)
                                            <x-chip tone="info">{{ $peran->name }}</x-chip>
                                        @endforeach
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        @if ($s->is_active)
                                            <x-chip tone="success" dot>Aktif</x-chip>
                                        @else
                                            <x-chip tone="neutral">Nonaktif</x-chip>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <form method="POST" action="{{ route('admin.staff.reset-sandi', $s) }}"
                                                onsubmit="return confirm('Terbitkan kata sandi baru untuk {{ $s->nama }}?');">
                                                @csrf
                                                <x-button type="submit" variant="outline" size="sm">Reset Sandi</x-button>
                                            </form>

                                            @if ($s->is_active)
                                                <form method="POST" action="{{ route('admin.staff.nonaktifkan', $s) }}">
                                                    @csrf
                                                    <x-button type="submit" variant="danger" size="sm">Nonaktifkan</x-button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.staff.aktifkan', $s) }}">
                                                    @csrf
                                                    <x-button type="submit" size="sm">Aktifkan</x-button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12">
                                        <x-empty-state title="Tidak ada akun yang cocok" description="Ubah kata kunci pencarian." />
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
        </div>

        <x-card title="Akun Staf Baru">
            <form method="POST" action="{{ route('admin.staff.store') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Nama" name="nama" required />
                <x-field label="Surel" name="email" type="email" required />
                <x-field label="NIP" name="nip" />
                <x-field label="Unit" name="unit" placeholder="BAAK" />
                <x-field label="Jabatan" name="jabatan" placeholder="Kepala Bagian Akademik" />
                <x-field label="Telepon" name="telepon" />

                <x-field label="Peran" name="peran" required :options="$daftarPeran"
                    hint="Peran menentukan apa yang boleh dilakukan akun ini — termasuk mendorong data ke PDDIKTI." />

                <p class="rounded-card border border-line bg-canvas px-3.5 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
                    Kata sandi dibuat otomatis dan ditampilkan sekali setelah disimpan.
                </p>

                <x-button type="submit" class="w-full">Tambah Akun</x-button>
            </form>
        </x-card>
    </div>
@endsection
