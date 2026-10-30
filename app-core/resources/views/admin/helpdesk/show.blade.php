@php use App\Enums\StatusTiket; @endphp
@extends('layouts.app')

@section('title', $tiket->nomor)

@section('aksi')
    <x-button href="{{ route('admin.helpdesk') }}" variant="outline" size="sm">← {{ __('helpdesk.antrean.judul') }}</x-button>
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            @include('helpdesk.partials.percakapan', ['tiket' => $tiket, 'percakapan' => $percakapan])

            @if ($tiket->status === StatusTiket::Ditutup)
                <x-alert tone="info">{{ __('helpdesk.tangani.ditutup') }}</x-alert>
            @elseif ($bolehBalas || $bolehTangani)
                <x-card :title="__('helpdesk.form.balas_judul')">
                    <form method="POST" action="{{ route('admin.helpdesk.balas', $tiket) }}"
                        enctype="multipart/form-data" class="space-y-3" x-data="{ internal: false }">
                        @csrf
                        <x-field :label="__('helpdesk.form.balas')" name="isi" type="textarea" required
                            :placeholder="__('helpdesk.form.balasan_placeholder')" />
                        <x-field :label="__('helpdesk.form.lampiran')" name="lampiran" type="file"
                            :hint="__('helpdesk.form.lampiran_hint', ['kb' => config('berkas.maks_kb')])" />

                        @if ($bolehTangani)
                            <label class="flex items-center gap-2 text-[13px]">
                                <input type="hidden" name="internal" value="0">
                                <input type="checkbox" name="internal" value="1" class="accent-navy" x-model="internal">
                                {{ __('helpdesk.form.internal') }}
                            </label>
                        @endif

                        <x-button type="submit">
                            <span x-show="! internal">{{ __('helpdesk.form.kirim_balasan') }}</span>
                            <span x-show="internal" x-cloak>{{ __('helpdesk.form.simpan_catatan') }}</span>
                        </x-button>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            <x-card :title="$tiket->nomor">
                @include('helpdesk.partials.ringkasan', ['tiket' => $tiket])

                <div class="mt-4 border-t border-line pt-3 text-[13px]">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('helpdesk.tiket.pemohon') }}</div>
                    <div class="mt-1 font-medium">{{ $tiket->pemohon?->nama ?? '—' }}</div>
                    <div class="tabular text-[11.5px] text-ink-faint">
                        {{ $tiket->pemohon?->nim ?? $tiket->pemohon?->nidn ?? $tiket->pemohon?->nip ?? '' }}
                        @if ($tiket->pemohon?->email) · {{ $tiket->pemohon->email }} @endif
                    </div>
                </div>
            </x-card>

            @if (! $bolehTangani)
                <x-alert tone="info">{{ __('helpdesk.tangani.tanpa_akses') }}</x-alert>
            @elseif ($tiket->status !== StatusTiket::Ditutup)
                @if ($tiket->status->dapatKe(StatusTiket::Didisposisi))
                    <x-card :title="__('helpdesk.tangani.disposisi_judul')">
                        <p class="mb-3 text-[12.5px] text-ink-muted">{{ __('helpdesk.tangani.disposisi_hint') }}</p>

                        {{-- Officers narrow to the chosen unit without a round trip.
                             Leaving the unit blank lets it follow the officer. --}}
                        <form method="POST" action="{{ route('admin.helpdesk.disposisi', $tiket) }}" class="space-y-3"
                            x-data="{
                                unit: @js((string) old('unit_kerja_id', $tiket->unit_kerja_id)),
                                petugas: @js((string) old('petugas_staff_id', $tiket->petugas_staff_id)),
                                perUnit: @js($petugasPerUnit),
                                daftar() {
                                    if (this.unit === '') {
                                        return Object.values(this.perUnit).reduce((a, b) => Object.assign(a, b), {});
                                    }
                                    return this.perUnit[this.unit] ?? {};
                                },
                            }">
                            @csrf

                            <div>
                                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                    {{ __('helpdesk.tangani.unit') }}
                                </label>
                                <select name="unit_kerja_id" x-model="unit" @change="petugas = ''"
                                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10">
                                    <option value="">{{ __('helpdesk.tangani.unit_pilih') }}</option>
                                    @foreach ($unitPilihan as $id => $nama)
                                        <option value="{{ $id }}">{{ $nama }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                    {{ __('helpdesk.tangani.petugas') }}
                                </label>
                                <select name="petugas_staff_id" x-model="petugas"
                                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10">
                                    <option value="">{{ __('helpdesk.tangani.petugas_pilih') }}</option>
                                    <template x-for="[id, nama] in Object.entries(daftar())" :key="id">
                                        <option :value="id" x-text="nama" :selected="String(id) === String(petugas)"></option>
                                    </template>
                                </select>
                            </div>

                            <x-field :label="__('helpdesk.tangani.prioritas')" name="prioritas" :options="$prioritasPilihan"
                                :value="$tiket->prioritas->value" />

                            <x-field :label="__('helpdesk.tangani.catatan')" name="catatan" type="textarea" />

                            <x-button type="submit" class="w-full">{{ __('helpdesk.tangani.disposisikan') }}</x-button>
                        </form>
                    </x-card>
                @endif

                @if ($statusManual !== [])
                    <x-card :title="__('helpdesk.tangani.status_judul')">
                        <p class="mb-3 text-[12.5px] text-ink-muted">{{ __('helpdesk.tangani.status_hint') }}</p>
                        <form method="POST" action="{{ route('admin.helpdesk.status', $tiket) }}" class="space-y-3">
                            @csrf
                            <x-field :label="__('helpdesk.tangani.status')" name="status" required
                                :options="collect($statusManual)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
                            <x-field :label="__('helpdesk.tangani.status_catatan')" name="catatan" type="textarea"
                                :hint="__('helpdesk.tangani.status_catatan_hint')" />
                            <x-button type="submit" variant="outline" class="w-full">{{ __('helpdesk.tangani.ubah') }}</x-button>
                        </form>
                    </x-card>
                @endif
            @endif
        </div>
    </div>
@endsection
