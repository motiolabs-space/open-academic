@extends('layouts.app')

@section('title', __('capaian.kelola.judul'))

@section('content')
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1 text-[12px] font-medium text-ink-muted">
                {{ __('capaian.kelola.filter_prodi') }}
                <select name="prodi" class="min-w-[220px] rounded-control border border-line-input bg-surface px-2.5 py-2 text-[13px] text-ink outline-none focus:border-navy">
                    @foreach ($daftarProdi as $p)
                        <option value="{{ $p->uuid }}" @selected($prodi?->is($p))>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </label>

            @if ($daftarKurikulum->isNotEmpty())
                <label class="flex flex-col gap-1 text-[12px] font-medium text-ink-muted">
                    {{ __('capaian.kelola.filter_kurikulum') }}
                    <select name="kurikulum" class="min-w-[220px] rounded-control border border-line-input bg-surface px-2.5 py-2 text-[13px] text-ink outline-none focus:border-navy">
                        @foreach ($daftarKurikulum as $k)
                            <option value="{{ $k->uuid }}" @selected($kurikulum?->is($k))>{{ $k->nama }}{{ $k->is_active ? ' ★' : '' }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <x-button type="submit" variant="outline">{{ __('capaian.kelola.tampilkan') }}</x-button>
        </form>
    </x-card>

    @if ($kurikulum === null)
        <x-card>
            <x-empty-state :title="__('capaian.kelola.tanpa_kurikulum')" :description="__('capaian.kelola.pengantar')" />
        </x-card>
    @else
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('capaian.kelola.kolom_mk') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ __('capaian.kelola.kolom_smt') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('capaian.kelola.kolom_status') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ __('capaian.kelola.kolom_cpmk') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('capaian.kelola.kolom_draf') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $baris)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-2.5">
                                    <div class="font-medium">{{ $baris['mata_kuliah']->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $baris['mata_kuliah']->kode }} · {{ $baris['mata_kuliah']->sks }} SKS</div>
                                </td>
                                <td class="tabular px-3 py-2.5 text-center">{{ $baris['semester'] }}</td>
                                <td class="px-3 py-2.5">
                                    @if ($baris['berlaku'])
                                        <x-chip tone="success">{{ __('capaian.istilah.versi', ['versi' => $baris['berlaku']->versi]) }}</x-chip>
                                    @else
                                        <span class="text-ink-faint">{{ __('capaian.kelola.belum') }}</span>
                                    @endif
                                </td>
                                <td class="tabular px-3 py-2.5 text-center">{{ $baris['berlaku']?->cpmk_count ?? '—' }}</td>
                                <td class="px-3 py-2.5">
                                    @if ($baris['draf'])
                                        <x-chip>{{ __('capaian.istilah.versi', ['versi' => $baris['draf']->versi]) }}</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-right">
                                    <x-button size="sm" variant="outline" :href="route('admin.cpmk.edit', [$kurikulum, $baris['mata_kuliah']])">{{ __('capaian.kelola.buka') }}</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
