@extends('layouts.pendaftar')

@section('title', __('pmb.judul_beranda'))

{{-- Satu-satunya halaman yang memang ingin ditemukan mesin pencari. --}}
@section('robots', 'index, follow')
@section('deskripsi', __('pmb.pengantar_beranda'))

@section('judul_halaman', __('pmb.judul_beranda'))
@section('subjudul_halaman', __('pmb.pengantar_beranda'))

@section('content')
    <div class="mb-6 flex flex-wrap gap-2">
        <x-button :href="route('pmb.daftar')">{{ __('pmb.mulai_daftar') }}</x-button>
        <x-button :href="route('pmb.masuk')" variant="outline">{{ __('pmb.sudah_punya_akun') }}</x-button>
    </div>

    <div class="flex flex-col gap-4">
        @forelse ($daftarGelombang as $g)
            <x-card :title="$g->nama" :meta="$g->jalur?->label().' · '.$g->tahunAkademik->nama">
                <dl class="grid gap-3 text-[13px] sm:grid-cols-2">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.formulir.gelombang') }}</dt>
                        <dd class="tabular">{{ __('pmb.periode', ['mulai' => \App\Support\Format::tanggal($g->tanggal_mulai), 'selesai' => \App\Support\Format::tanggal($g->tanggal_selesai)]) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.biaya') }}</dt>
                        <dd class="tabular">{{ $g->biaya_pendaftaran > 0 ? \App\Support\Format::rupiah($g->biaya_pendaftaran) : __('pmb.gratis') }}</dd>
                    </div>
                </dl>

                @if ($g->keterangan)
                    <p class="mt-3 whitespace-pre-line text-[13px] text-ink-muted">{{ $g->keterangan }}</p>
                @endif

                <h3 class="mb-2 mt-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.prodi_ditawarkan') }}</h3>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($g->prodiDitawarkan as $t)
                        <li>
                            <x-chip tone="neutral">
                                {{ $t->prodi->jenjang?->label() }} {{ $t->prodi->nama }}
                                @if ($t->daya_tampung)
                                    <span class="tabular font-normal">· {{ __('pmb.daya_tampung', ['jumlah' => $t->daya_tampung]) }}</span>
                                @endif
                            </x-chip>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @empty
            <x-empty-state :title="__('pmb.tidak_ada_gelombang')" />
        @endforelse
    </div>
@endsection
