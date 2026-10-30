@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('kuesioner.judul'))

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
            <x-card flush :title="__('kuesioner.daftar.judul')">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('kuesioner.daftar.nama') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('kuesioner.daftar.sasaran') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('kuesioner.daftar.periode') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ __('kuesioner.daftar.responden') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('kuesioner.daftar.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $k)
                                <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('admin.kuesioner.show', $k) }}" class="font-medium text-navy hover:text-gold">{{ $k->nama }}</a>
                                        <div class="tabular text-[11.5px] text-ink-faint">
                                            {{ $k->kode }} · {{ $k->pertanyaan_count }} {{ Str::lower(__('kuesioner.pertanyaan.judul')) }}
                                            · {{ $k->anonim ? __('kuesioner.daftar.anonim') : __('kuesioner.daftar.beridentitas') }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        {{ $k->sasaran->label() }}
                                        <div class="text-[11.5px] text-ink-faint">{{ $k->prodi?->nama ?? __('kuesioner.daftar.semua_prodi') }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3 text-ink-muted">
                                        @if ($k->mulai || $k->selesai)
                                            {{ $k->mulai ? Format::tanggal($k->mulai) : '…' }} – {{ $k->selesai ? Format::tanggal($k->selesai) : '…' }}
                                        @else
                                            {{ __('kuesioner.daftar.tanpa_batas') }}
                                        @endif
                                    </td>
                                    <td class="tabular px-5 py-3 text-right">{{ Format::bulat($k->partisipasi_count) }}</td>
                                    <td class="px-5 py-3">
                                        <x-chip :tone="$k->status()->tone()" dot>{{ $k->status()->label() }}</x-chip>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-empty-state :title="__('kuesioner.daftar.kosong')" :description="__('kuesioner.daftar.kosong_deskripsi')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div>{{ $daftar->links() }}</div>
        </div>

        @if ($bolehKelola)
            <x-card :title="__('kuesioner.form.buat_judul')">
                <form method="POST" action="{{ route('admin.kuesioner.store') }}" class="space-y-3">
                    @csrf

                    <x-field :label="__('kuesioner.form.kode')" name="kode" required :hint="__('kuesioner.form.kode_hint')" />
                    <x-field :label="__('kuesioner.form.nama')" name="nama" required />
                    <x-field :label="__('kuesioner.form.deskripsi')" name="deskripsi" type="textarea" />
                    <x-field :label="__('kuesioner.form.sasaran')" name="sasaran" :options="$sasaranPilihan" required value="mahasiswa" />
                    <x-field :label="__('kuesioner.form.prodi')" name="prodi_id" :options="$prodiPilihan" :hint="__('kuesioner.form.prodi_hint')" />

                    <div class="grid grid-cols-2 gap-3">
                        <x-field :label="__('kuesioner.form.mulai')" name="mulai" type="date" />
                        <x-field :label="__('kuesioner.form.selesai')" name="selesai" type="date" />
                    </div>

                    <x-field :label="__('kuesioner.form.anonim')" name="anonim" type="checkbox" :value="old('anonim', true)"
                        :hint="__('kuesioner.form.anonim_hint')" />
                    <p class="text-[11.5px] leading-relaxed text-ink-faint">{{ __('kuesioner.form.anonim_peringatan') }}</p>

                    <x-field :label="__('kuesioner.form.ambang')" name="min_responden" type="number" :value="$ambangBawaan"
                        :hint="__('kuesioner.form.ambang_hint', ['ambang' => $ambangBawaan])" />

                    <x-button type="submit" class="w-full">{{ __('kuesioner.form.buat') }}</x-button>
                </form>
            </x-card>
        @endif
    </div>
@endsection
