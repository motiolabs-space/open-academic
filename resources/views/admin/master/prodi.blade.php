@extends('layouts.app')

@section('title', 'Program Studi')

@section('content')
    @include('admin.master.partials.tabs')

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Kode</th>
                            <th class="px-5 py-3 font-semibold">Program Studi</th>
                            <th class="px-5 py-3 font-semibold">Fakultas</th>
                            <th class="px-5 py-3 text-center font-semibold">SKS Lulus</th>
                            <th class="px-5 py-3 text-center font-semibold">Mahasiswa</th>
                            <th class="px-5 py-3 text-center font-semibold">PDDIKTI</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $p)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-5 py-3 font-semibold">{{ $p->kode }}</td>
                                <td class="px-5 py-3">
                                    {{ $p->namaLengkap() }}
                                    @unless ($p->is_active)
                                        <x-chip tone="neutral" class="ml-1">nonaktif</x-chip>
                                    @endunless
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ $p->fakultas->nama }}</td>
                                <td class="tabular px-5 py-3 text-center">{{ $p->sks_lulus }}</td>
                                <td class="tabular px-5 py-3 text-center">{{ $p->mahasiswa_count }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if ($p->kode_pddikti)
                                        <span class="tabular text-[12px] text-ink-muted">{{ $p->kode_pddikti }}</span>
                                    @else
                                        <x-chip tone="danger" title="Sinkronisasi Feeder akan ditolak tanpa id_sms">belum diisi</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.master.prodi.destroy', $p) }}"
                                        onsubmit="return confirm('Hapus program studi {{ $p->nama }}?');">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12">
                                    <x-empty-state title="Belum ada program studi"
                                        description="Mahasiswa, mata kuliah, dan kurikulum semuanya bergantung pada program studi." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Program Studi Baru">
            <form method="POST" action="{{ route('admin.master.prodi.store') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Fakultas" name="fakultas_id" required
                    :options="$daftarFakultas->pluck('nama', 'id')" />
                <x-field label="Kode" name="kode" required placeholder="IF" />
                <x-field label="Nama" name="nama" required placeholder="Informatika" />
                <x-field label="Jenjang" name="jenjang" required
                    :options="collect($jenjangPilihan)->mapWithKeys(fn ($j) => [$j->value => $j->label()])" />

                <x-field label="Kode PDDIKTI (id_sms)" name="kode_pddikti"
                    hint="Wajib sebelum sinkronisasi Feeder — salah isi berarti pelaporan masuk ke prodi lain." />

                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Gelar Pendek" name="gelar_pendek" placeholder="S.Kom." />
                    <x-field label="Akreditasi" name="akreditasi" placeholder="B" />
                </div>

                <x-field label="Gelar Panjang" name="gelar_panjang" placeholder="Sarjana Komputer" />
                <x-field label="SKS Kelulusan" name="sks_lulus" type="number" required :value="144" />
                <x-field label="Ketua Program Studi" name="kaprodi_dosen_id"
                    :options="$daftarDosen->mapWithKeys(fn ($d) => [$d->id => $d->namaLengkap()])" />

                <x-button type="submit" class="mt-1 w-full">Tambah Program Studi</x-button>
            </form>
        </x-card>
    </div>
@endsection
