@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('swalayan.beasiswa.judul'))

@section('content')
    @include('mahasiswa._swalayan-pesan')

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            <x-card :title="__('swalayan.beasiswa.terbuka')">
                @forelse ($terbuka as $baris)
                    @php $p = $baris['periode']; @endphp
                    <div class="border-b border-line/50 py-4 first:pt-0 last:border-b-0 last:pb-0" data-periode="{{ $p->uuid }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[14px] font-medium">{{ $p->nama }}</p>
                                <p class="text-[12px] text-ink-muted">{{ $p->beasiswa->nama }} — {{ $p->beasiswa->cakupan() }}</p>
                            </div>
                            <x-chip tone="info">{{ __('swalayan.beasiswa.dibuka_sampai', ['tanggal' => Format::tanggal($p->dibuka_sampai)]) }}</x-chip>
                        </div>

                        <p class="tabular mt-1 text-[12px] text-ink-faint">
                            {{ __('swalayan.beasiswa.mulai_semester', ['semester' => $p->tahunAkademik->nama]) }}
                            @if ($p->ipk_minimum !== null)
                                · {{ __('swalayan.beasiswa.ipk_minimum', ['ipk' => Format::angka((float) $p->ipk_minimum, 2)]) }}
                            @endif
                        </p>

                        @if ($p->syarat)
                            <p class="mt-2 whitespace-pre-line text-[12.5px] leading-relaxed text-ink-muted">{{ $p->syarat }}</p>
                        @endif

                        @if ($baris['halangan'] !== null)
                            <p class="mt-2 text-[12px] text-warning-ink" data-halangan>{{ $baris['halangan'] }}</p>
                        @else
                            <form method="POST" action="{{ route('mahasiswa.beasiswa.ajukan', $p) }}" enctype="multipart/form-data"
                                class="mt-3 space-y-3 rounded-card border border-line bg-canvas p-3.5">
                                @csrf
                                <x-field :label="__('swalayan.beasiswa.motivasi')" name="motivasi" type="textarea" required
                                    :hint="__('swalayan.beasiswa.motivasi_petunjuk')" />
                                @foreach ($p->daftarBerkas() as $i => $label)
                                    @include('mahasiswa._unggah-berkas', ['nama' => "berkas[{$i}]", 'label' => $label, 'wajib' => true])
                                @endforeach
                                <p class="text-[11.5px] text-ink-faint">{{ __('swalayan.beasiswa.aturan') }}</p>
                                <x-button type="submit" size="sm">{{ __('swalayan.beasiswa.tombol') }}</x-button>
                            </form>
                        @endif
                    </div>
                @empty
                    <x-empty-state :title="__('swalayan.beasiswa.tidak_ada_terbuka')" :description="__('swalayan.beasiswa.tidak_ada_terbuka_penjelasan')" />
                @endforelse
            </x-card>

            <x-card flush :title="__('swalayan.beasiswa.ajuan_saya')">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-[13px]">
                        <tbody>
                            @forelse ($ajuan as $a)
                                <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-ajuan="{{ $a->uuid }}">
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $a->periode->nama }}</div>
                                        <div class="text-[11.5px] text-ink-faint">{{ $a->periode->beasiswa->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">{{ __('swalayan.diajukan_pada', ['tanggal' => Format::tanggal($a->diajukan_at)]) }}</div>
                                        @foreach ($a->berkas ?? [] as $i => $b)
                                            <a href="{{ route('berkas.beasiswa', [$a, $i]) }}" class="mt-0.5 block text-[12px] text-navy underline">{{ $b['label'] }}</a>
                                        @endforeach
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-chip :tone="$a->status->tone()">{{ $a->status->label() }}</x-chip>
                                        @if ($a->diputus_at)
                                            <div class="tabular mt-1 text-[11px] text-ink-faint">{{ Format::tanggal($a->diputus_at) }}</div>
                                        @endif
                                        @if ($a->catatan_keputusan)
                                            <p class="mt-1 max-w-xs text-[12px] text-ink-muted">{{ $a->catatan_keputusan }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="px-5 py-6 text-[12.5px] text-ink-muted">{{ __('swalayan.beasiswa.ajuan_kosong') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <x-card :title="__('swalayan.beasiswa.penerimaan')">
            @forelse ($penerima as $pn)
                <div class="border-b border-line/50 py-3 first:pt-0 last:border-b-0 last:pb-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[13px] font-medium">{{ $pn->beasiswa->nama }}</p>
                        <x-chip :tone="$pn->status->tone()">{{ $pn->status->label() }}</x-chip>
                    </div>
                    <p class="text-[12px] text-ink-muted">{{ $pn->beasiswa->cakupan() }}</p>
                    <p class="tabular text-[11.5px] text-ink-faint">
                        {{ $pn->mulai->nama }}@if ($pn->selesai) – {{ $pn->selesai->nama }}@endif
                    </p>
                </div>
            @empty
                <p class="text-[12.5px] text-ink-muted">{{ __('swalayan.beasiswa.penerimaan_kosong') }}</p>
            @endforelse
        </x-card>
    </div>
@endsection
