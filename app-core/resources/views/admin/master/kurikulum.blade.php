@extends('layouts.app')

@section('title', 'Kurikulum')

@section('content')
    @include('admin.master.partials.tabs')

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex min-w-0 flex-col gap-5">
            <x-card flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Kode</th>
                                <th class="px-5 py-3 font-semibold">Kurikulum</th>
                                <th class="px-5 py-3 font-semibold">Program Studi</th>
                                <th class="px-5 py-3 text-center font-semibold">MK</th>
                                <th class="px-5 py-3 text-center font-semibold">Mahasiswa</th>
                                <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $k)
                                <tr @class([
                                    'border-b border-line/50 last:border-b-0',
                                    'bg-highlight' => $terpilih?->id === $k->id,
                                    'odd:bg-zebra' => $terpilih?->id !== $k->id,
                                ])>
                                    <td class="tabular px-5 py-3 font-semibold">{{ $k->kode }}</td>
                                    <td class="px-5 py-3">
                                        {{ $k->nama }}
                                        @if ($k->is_active)
                                            <x-chip tone="success" dot class="ml-1">aktif</x-chip>
                                        @endif
                                        <div class="text-[11.5px] text-ink-faint">
                                            {{ $k->tahun_mulai }}@if ($k->tahun_selesai)–{{ $k->tahun_selesai }}@endif
                                            · {{ $k->sks_lulus }} SKS
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-ink-muted">{{ $k->prodi->nama }}</td>
                                    <td class="tabular px-5 py-3 text-center">{{ $k->mata_kuliah_count }}</td>
                                    <td class="tabular px-5 py-3 text-center">{{ $k->mahasiswa_count }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <x-button size="sm" variant="outline"
                                                :href="route('admin.master.kurikulum', ['kurikulum' => $k->id])">
                                                Susun MK
                                            </x-button>

                                            @unless ($k->is_active)
                                                <form method="POST" action="{{ route('admin.master.kurikulum.aktifkan', $k) }}">
                                                    @csrf
                                                    <x-button type="submit" size="sm">Aktifkan</x-button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12">
                                        <x-empty-state title="Belum ada kurikulum"
                                            description="Kurikulum berversi: mahasiswa tetap terikat pada versi saat ia masuk, sehingga perubahan syarat tidak berlaku surut." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($terpilih)
                <x-card :title="'Mata Kuliah — '.$terpilih->nama"
                    :meta="$terpilih->mataKuliah->count().' mata kuliah · '.$terpilih->mataKuliah->sum('sks').' SKS'">
                    <form method="POST" action="{{ route('admin.master.kurikulum.mk.tambah', $terpilih) }}"
                        class="mb-4 flex flex-wrap items-end gap-2.5 border-b border-line pb-4">
                        @csrf
                        <x-field label="Mata Kuliah" name="mata_kuliah_id" required class="min-w-[240px] flex-1"
                            :options="$mkTersedia->mapWithKeys(fn ($m) => [$m->id => $m->kode.' — '.$m->nama.' ('.$m->sks.' SKS)'])" />
                        <x-field label="Semester" name="semester" type="number" required :value="1" class="w-28" />
                        <x-field label="Jenis" name="jenis" required class="w-40"
                            :options="['wajib' => 'Wajib', 'pilihan' => 'Pilihan', 'wajib_universitas' => 'Wajib Universitas']" />
                        <x-button type="submit">Tambahkan</x-button>
                    </form>

                    @php $perSemester = $terpilih->mataKuliah->groupBy(fn ($m) => $m->pivot->semester); @endphp

                    @forelse ($perSemester as $semester => $daftarMk)
                        <div class="mb-4 last:mb-0">
                            <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.1em] text-ink-muted">
                                Semester {{ $semester }} · {{ $daftarMk->sum('sks') }} SKS
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($daftarMk as $m)
                                    <form method="POST"
                                        action="{{ route('admin.master.kurikulum.mk.hapus', [$terpilih, $m]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-line bg-line/40 px-3 py-1.5 text-[12px] hover:border-danger-line hover:text-danger"
                                            title="Keluarkan {{ $m->kode }} dari kurikulum">
                                            <span class="font-semibold">{{ $m->kode }}</span>
                                            {{ $m->nama }}
                                            @if ($m->pivot->jenis !== 'wajib')
                                                <x-chip tone="info">{{ str_replace('_', ' ', $m->pivot->jenis) }}</x-chip>
                                            @endif
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <x-empty-state title="Kurikulum ini masih kosong"
                            description="Kurikulum tanpa mata kuliah tidak dapat diaktifkan — mahasiswa baru tidak akan punya apa pun untuk diambil." />
                    @endforelse
                </x-card>
            @endif
        </div>

        <x-card title="Kurikulum Baru">
            <form method="POST" action="{{ route('admin.master.kurikulum.store') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Program Studi" name="prodi_id" required
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />
                <x-field label="Kode" name="kode" required placeholder="K2026" />
                <x-field label="Nama" name="nama" required placeholder="Kurikulum 2026 (OBE)" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Tahun Mulai" name="tahun_mulai" type="number" required :value="now()->year" />
                    <x-field label="Tahun Selesai" name="tahun_selesai" type="number" />
                </div>
                <x-field label="SKS Kelulusan" name="sks_lulus" type="number" required :value="144" />
                <x-button type="submit" class="mt-1 w-full">Buat Kurikulum</x-button>
            </form>
        </x-card>
    </div>
@endsection
