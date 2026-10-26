@extends('layouts.pendaftar')

@section('title', __('pmb.dasbor.judul'))
@section('judul_halaman', __('pmb.dasbor.judul'))

@section('content')
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex flex-col gap-5">
            @forelse ($pendaftaran as $p)
                @php $terlihat = $p->statusTerlihat(); @endphp
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="tabular text-[12px] font-semibold text-ink-muted">{{ $p->nomor_pendaftaran }}</div>
                            <div class="mt-0.5 font-semibold">{{ $p->gelombang->nama }}</div>
                            <div class="text-[12.5px] text-ink-muted">1. {{ $p->prodiPilihan1->nama }}</div>
                        </div>
                        <x-chip :tone="$terlihat->tone()">{{ $terlihat->label() }}</x-chip>
                    </div>
                    <div class="mt-3">
                        <x-button :href="route('pmb.portal.show', $p)" size="sm">{{ __('pmb.dasbor.buka') }}</x-button>
                    </div>
                </x-card>
            @empty
                <x-empty-state :title="__('pmb.dasbor.kosong')" />
            @endforelse

            <x-card :title="__('pmb.dasbor.mulai')">
                @if ($daftarGelombang->isEmpty())
                    <p class="text-[13px] text-ink-muted">{{ __('pmb.dasbor.tidak_ada_gelombang') }}</p>
                @else
                    <form method="POST" action="{{ route('pmb.portal.store') }}" class="flex flex-col gap-4"
                        x-data="{ gelombang: @js(old('gelombang', $daftarGelombang->first()->uuid)), tawaran: @js($daftarGelombang->mapWithKeys(fn ($g) => [$g->uuid => $g->prodiDitawarkan->map(fn ($t) => ['id' => $t->prodi_id, 'nama' => trim($t->prodi->jenjang?->label().' '.$t->prodi->nama)])->values()])) }">
                        @csrf

                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.dasbor.pilih_gelombang') }}</span>
                            <select name="gelombang" x-model="gelombang" required
                                class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px]">
                                @foreach ($daftarGelombang as $g)
                                    <option value="{{ $g->uuid }}">
                                        {{ $g->nama }} — {{ $g->biaya_pendaftaran > 0 ? \App\Support\Format::rupiah($g->biaya_pendaftaran) : __('pmb.gratis') }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.dasbor.pilihan_1') }}</span>
                            <select name="prodi_pilihan_1_id" required
                                class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px]">
                                <template x-for="p in tawaran[gelombang] ?? []" :key="p.id">
                                    <option :value="p.id" x-text="p.nama"></option>
                                </template>
                            </select>
                            @error('prodi_pilihan_1_id')<p class="mt-1 text-[11.5px] text-danger">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.dasbor.pilihan_2') }}</span>
                            <select name="prodi_pilihan_2_id"
                                class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px]">
                                <option value="">—</option>
                                <template x-for="p in tawaran[gelombang] ?? []" :key="p.id">
                                    <option :value="p.id" x-text="p.nama"></option>
                                </template>
                            </select>
                            @error('prodi_pilihan_2_id')<p class="mt-1 text-[11.5px] text-danger">{{ $message }}</p>@enderror
                        </label>

                        <x-button type="submit">{{ __('pmb.dasbor.mulai') }}</x-button>
                    </form>
                @endif
            </x-card>
        </div>

        <x-card :title="__('pmb.dasbor.notifikasi')">
            @forelse ($notifikasi as $n)
                <div class="border-b border-line/60 py-2.5 first:pt-0 last:border-b-0 last:pb-0">
                    <div class="text-[13px] font-semibold">{{ $n->data['judul'] ?? '' }}</div>
                    <div class="text-[12.5px] text-ink-muted">{{ $n->data['ringkasan'] ?? '' }}</div>
                    <div class="tabular mt-0.5 text-[11px] text-ink-faint">{{ \App\Support\Format::tanggal($n->created_at) }}</div>
                </div>
            @empty
                <p class="text-[13px] text-ink-muted">—</p>
            @endforelse
        </x-card>
    </div>
@endsection
