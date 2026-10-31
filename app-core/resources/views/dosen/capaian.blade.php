@extends('layouts.app')

@section('title', __('capaian.nav.kelas'))

@section('content')
    @if ($daftar->isEmpty())
        <x-card>
            <x-empty-state :title="__('capaian.rekap.tanpa_kelas')" :description="__('capaian.rekap.kelas_belum')" />
        </x-card>
    @else
        <x-card flush>
            <table class="w-full text-[13px]">
                <tbody>
                    @foreach ($daftar as $k)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $k->mataKuliah->nama }}</div>
                                <div class="tabular text-[11.5px] text-ink-faint">{{ $k->mataKuliah->kode }} · Kelas {{ $k->kode }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <x-chip :tone="$k->status_nilai === 'final' ? 'success' : 'neutral'">
                                    {{ $k->status_nilai === 'final' ? __('capaian.rekap.status_final') : __('capaian.rekap.status_belum') }}
                                </x-chip>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-button size="sm" variant="outline" :href="route('dosen.capaian.kelas', $k)">{{ __('capaian.rekap.lihat_kelas') }}</x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endif
@endsection
