@extends('layouts.app')

@section('title', 'Kepegawaian Dosen')

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

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field label="Cari" name="cari" type="search" :value="$filter['cari'] ?? ''"
                placeholder="Nama, NIDN, atau surel…" class="min-w-[220px] flex-1" />
            <x-field label="Program Studi" name="prodi" :value="$filter['prodi'] ?? ''"
                :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />
            <label class="flex items-center gap-2 pb-2.5 text-[13px]">
                <input type="checkbox" name="tanpa_nidn" value="1" class="accent-navy"
                    @checked($filter['tanpa_nidn'] ?? false)>
                Tanpa NIDN
            </label>
            <x-button type="submit">Terapkan</x-button>
            @if (array_filter($filter))
                <x-button variant="ghost" :href="route('admin.dosen')">Reset</x-button>
            @endif
        </form>
    </x-card>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">NIDN</th>
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 font-semibold">Homebase</th>
                            <th class="px-5 py-3 text-center font-semibold">Kelas</th>
                            <th class="px-5 py-3 text-center font-semibold">Wali</th>
                            <th class="px-5 py-3 text-center font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $d)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-5 py-3">
                                    @if ($d->nidn)
                                        {{ $d->nidn }}
                                    @else
                                        <x-chip tone="danger" title="Feeder menolak dosen tanpa NIDN">belum ada</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $d->namaLengkap() }}</div>
                                    <div class="text-[11.5px] text-ink-faint">{{ $d->email }}</div>
                                    @if ($d->is_praktisi)
                                        <x-chip tone="gold" class="mt-1">Praktisi · {{ $d->praktisi_instansi }}</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ $d->prodi?->nama ?? '—' }}</td>
                                <td class="tabular px-5 py-3 text-center">{{ $d->kelas_kuliah_count }}</td>
                                <td class="tabular px-5 py-3 text-center">{{ $d->mahasiswa_bimbingan_count }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if ($d->is_active)
                                        <x-chip tone="success" dot>Aktif</x-chip>
                                    @else
                                        <x-chip tone="neutral">Nonaktif</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <form method="POST" action="{{ route('admin.dosen.reset-sandi', $d) }}"
                                            onsubmit="return confirm('Terbitkan kata sandi baru untuk {{ $d->nama }}? Kata sandi lamanya langsung tidak berlaku.');">
                                            @csrf
                                            <x-button type="submit" variant="outline" size="sm">Reset Sandi</x-button>
                                        </form>

                                        @if ($d->is_active)
                                            <form method="POST" action="{{ route('admin.dosen.nonaktifkan', $d) }}">
                                                @csrf
                                                <x-button type="submit" variant="danger" size="sm">Nonaktifkan</x-button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.dosen.aktifkan', $d) }}">
                                                @csrf
                                                <x-button type="submit" size="sm">Aktifkan</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12">
                                    <x-empty-state title="Tidak ada dosen yang cocok"
                                        description="Ubah filter, atau tambahkan dosen lewat formulir di sebelah." />
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

        <x-card title="Dosen Baru">
            <form method="POST" action="{{ route('admin.dosen.store') }}" class="flex flex-col gap-3.5"
                x-data="{ praktisi: false }">
                @csrf

                <x-field label="Nama" name="nama" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Gelar Depan" name="gelar_depan" placeholder="Dr." />
                    <x-field label="Gelar Belakang" name="gelar_belakang" placeholder="M.Kom." />
                </div>

                <x-field label="Surel" name="email" type="email" required />

                <div class="grid grid-cols-2 gap-3">
                    <x-field label="NIDN" name="nidn"
                        hint="Kosongkan untuk praktisi industri." />
                    <x-field label="NIP" name="nip" />
                </div>

                <x-field label="Homebase" name="prodi_id"
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />

                <x-field label="Status Kepegawaian" name="status_kepegawaian" required
                    :options="['tetap' => 'Tetap', 'tidak_tetap' => 'Tidak Tetap', 'luar_biasa' => 'Luar Biasa']" />

                <x-field label="Pendidikan Tertinggi" name="pendidikan_tertinggi"
                    :options="collect($jenjangPilihan)->mapWithKeys(fn ($j) => [$j->value => $j->label()])" />

                <x-field label="Jabatan Fungsional" name="jabatan_fungsional" placeholder="Lektor" />

                <label class="flex items-center gap-2 text-[13px]">
                    <input type="hidden" name="is_praktisi" value="0">
                    <input type="checkbox" name="is_praktisi" value="1" class="accent-navy" x-model="praktisi">
                    Praktisi dari industri
                </label>

                <div x-show="praktisi" x-cloak>
                    <x-field label="Instansi Asal" name="praktisi_instansi"
                        hint="Inilah bukti yang dihitung pada IKU 4." />
                </div>

                <p class="rounded-card border border-line bg-canvas px-3.5 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
                    Kata sandi dibuat otomatis dan ditampilkan sekali setelah disimpan.
                    Administrator tidak memilih kata sandi milik orang lain.
                </p>

                <x-button type="submit" class="w-full">Tambah Dosen</x-button>
            </form>
        </x-card>
    </div>
@endsection
