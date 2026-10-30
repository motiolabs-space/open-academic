@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('helpdesk.judul'))

@section('aksi')
    @can('manage', \App\Models\Helpdesk\HelpdeskTiket::class)
        <x-button href="{{ route('admin.helpdesk.pengaturan') }}" variant="outline" size="sm">
            ⚙ {{ __('helpdesk.antrean.pengaturan') }}
        </x-button>
    @endcan
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-field :label="__('helpdesk.antrean.cari')" name="cari" :value="$filter['cari'] ?? null"
                :placeholder="__('helpdesk.antrean.cari_placeholder')" />
            <x-field :label="__('helpdesk.antrean.status')" name="status" :options="$statusPilihan" :value="$filter['status'] ?? null" />
            @if ($semuaUnit)
                <x-field :label="__('helpdesk.antrean.unit')" name="unit" :options="$unitPilihan" :value="$filter['unit'] ?? null" />
            @endif
            <x-field :label="__('helpdesk.antrean.kategori')" name="kategori" :options="$kategoriPilihan" :value="$filter['kategori'] ?? null" />
            <div class="flex flex-col justify-end gap-1 text-[12.5px]">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="lewat_sla" value="1" class="accent-navy" @checked(!empty($filter['lewat_sla']))>
                    {{ __('helpdesk.antrean.hanya_lewat_sla') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="semua" value="1" class="accent-navy" @checked(!empty($filter['semua']))>
                    {{ __('helpdesk.antrean.termasuk_tertutup') }}
                </label>
            </div>
            <div class="flex items-end gap-2 lg:col-span-5">
                <x-button type="submit">{{ __('helpdesk.daftar.terapkan') }}</x-button>
                <x-button href="{{ route('admin.helpdesk') }}" variant="outline">{{ __('helpdesk.daftar.reset') }}</x-button>
                <span class="ml-auto text-[12px] text-ink-faint">
                    {{ $semuaUnit ? __('helpdesk.antrean.lingkup_semua') : __('helpdesk.antrean.lingkup_unit') }}
                </span>
            </div>
        </form>
    </x-card>

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1040px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.nomor') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.judul') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.pemohon') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.unit') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.status') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.tenggat_sla') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('helpdesk.tiket.tindakan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $t)
                        <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                            <td class="tabular px-5 py-3 font-semibold">{{ $t->nomor }}</td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $t->judul }}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11.5px] text-ink-faint">
                                    <x-chip :tone="$t->prioritas->tone()">{{ $t->prioritas->label() }}</x-chip>
                                    <span>{{ $t->kategori?->nama }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div>{{ $t->pemohon?->nama ?? '—' }}</div>
                                <div class="tabular text-[11.5px] text-ink-faint">
                                    {{ $t->pemohon?->nim ?? $t->pemohon?->nidn ?? $t->pemohon?->nip ?? '' }}
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                {{ $t->unitKerja?->nama ?? __('helpdesk.tiket.belum_disposisi') }}
                                <div class="text-[11.5px] text-ink-faint">{{ $t->petugas?->nama ?? __('helpdesk.tiket.belum_ada_petugas') }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <x-chip :tone="$t->status->tone()" dot>{{ $t->status->label() }}</x-chip>
                                @if ($t->lewatSla())
                                    <x-chip tone="danger" class="mt-1">{{ __('helpdesk.tiket.lewat_sla') }}</x-chip>
                                @endif
                            </td>
                            <td @class(['tabular px-5 py-3', 'text-danger' => $t->lewatSla(), 'text-ink-muted' => ! $t->lewatSla()])>
                                {{ $t->tenggat_sla_at ? Format::tanggal($t->tenggat_sla_at).' '.Format::jam($t->tenggat_sla_at) : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-button href="{{ route('admin.helpdesk.show', $t) }}" variant="outline" size="sm">
                                    {{ __('helpdesk.tiket.buka') }}
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state :title="__('helpdesk.antrean.kosong')"
                                    :description="__('helpdesk.antrean.kosong_deskripsi')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
