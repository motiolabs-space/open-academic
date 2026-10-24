@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('helpdesk.judul'))

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_400px]">
        <div class="space-y-5">
            <x-card>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <x-field :label="__('helpdesk.daftar.filter_status')" name="status" :options="$statusPilihan"
                        :value="$filter['status'] ?? null" class="w-52" />
                    <x-button type="submit" variant="outline">{{ __('helpdesk.daftar.terapkan') }}</x-button>
                    <x-button href="{{ route($prefix.'.helpdesk') }}" variant="ghost">{{ __('helpdesk.daftar.reset') }}</x-button>
                </form>
            </x-card>

            <x-card flush :title="__('helpdesk.daftar.judul')">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.nomor') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.judul') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.status') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('helpdesk.tiket.aktivitas_terakhir') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $t)
                                <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                    <td class="tabular px-5 py-3">
                                        <a href="{{ route($prefix.'.helpdesk.show', $t) }}" class="font-semibold text-navy hover:text-gold">
                                            {{ $t->nomor }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3">
                                        <a href="{{ route($prefix.'.helpdesk.show', $t) }}" class="font-medium hover:text-gold">{{ $t->judul }}</a>
                                        <div class="text-[11.5px] text-ink-faint">
                                            {{ $t->kategori?->nama }}
                                            @if ($t->unitKerja) · {{ $t->unitKerja->nama }} @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-chip :tone="$t->status->tone()" dot>{{ $t->status->label() }}</x-chip>
                                    </td>
                                    <td class="tabular px-5 py-3 text-ink-muted">
                                        {{ Format::tanggal($t->balasan_terakhir_at ?? $t->created_at) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-empty-state :title="__('helpdesk.daftar.kosong')"
                                            :description="__('helpdesk.daftar.kosong_deskripsi')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div>{{ $daftar->links() }}</div>
        </div>

        <div class="space-y-5">
            <x-card :title="__('helpdesk.form.buat_judul')">
                <form method="POST" action="{{ route($prefix.'.helpdesk.store') }}" enctype="multipart/form-data"
                    class="space-y-3" x-data="{ kategori: '{{ old('helpdesk_kategori_id') }}' }">
                    @csrf

                    {{-- Written out rather than through x-field: the Alpine binding
                         has to sit on the <select> itself, and the component puts
                         extra attributes on its wrapper. --}}
                    <div>
                        <label for="helpdesk_kategori_id" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                            {{ __('helpdesk.form.kategori') }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select id="helpdesk_kategori_id" name="helpdesk_kategori_id" required x-model="kategori"
                            class="w-full rounded-control border bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10 {{ $errors->has('helpdesk_kategori_id') ? 'border-danger' : 'border-line-input' }}">
                            <option value="">{{ __('helpdesk.form.kategori_pilih') }}</option>
                            @foreach ($kategori as $k)
                                <option value="{{ $k->id }}" @selected((string) old('helpdesk_kategori_id') === (string) $k->id)>{{ $k->nama }}</option>
                            @endforeach
                        </select>
                        @error('helpdesk_kategori_id')
                            <p class="mt-1 text-[11.5px] text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- The SLA promise is shown the moment a category is picked,
                         before the ticket exists: that is when it changes what
                         somebody does. --}}
                    @foreach ($kategori as $k)
                        <p class="text-[11.5px] text-ink-faint" x-show="kategori === '{{ $k->id }}'" x-cloak>
                            @if ($k->deskripsi) {{ $k->deskripsi }} @endif
                            @if ($k->slaJamEfektif() !== null)
                                {{ __('helpdesk.form.sla_info', ['jam' => $k->slaJamEfektif()]) }}
                            @else
                                {{ __('helpdesk.form.sla_tanpa') }}
                            @endif
                        </p>
                    @endforeach

                    <x-field :label="__('helpdesk.form.judul')" name="judul" required
                        :placeholder="__('helpdesk.form.judul_placeholder')" />

                    <x-field :label="__('helpdesk.form.isi')" name="isi" type="textarea" required
                        :placeholder="__('helpdesk.form.isi_placeholder')" />

                    <x-field :label="__('helpdesk.form.prioritas')" name="prioritas" :options="$prioritasPilihan"
                        value="normal" required />

                    <x-field :label="__('helpdesk.form.lampiran')" name="lampiran" type="file"
                        :hint="__('helpdesk.form.lampiran_hint', ['kb' => config('berkas.maks_kb')])" />

                    <x-button type="submit" class="w-full">{{ __('helpdesk.form.kirim') }}</x-button>
                </form>
            </x-card>

            <x-card :title="__('helpdesk.faq.panel')">
                @forelse ($faq as $f)
                    <details class="group border-b border-line/50 py-2.5 last:border-b-0">
                        <summary class="cursor-pointer text-[13px] font-medium hover:text-gold">{{ $f->pertanyaan }}</summary>
                        <div class="mt-2 whitespace-pre-line text-[12.5px] leading-relaxed text-ink-muted">{{ $f->jawaban }}</div>
                    </details>
                @empty
                    <p class="text-[12.5px] text-ink-faint">{{ __('helpdesk.faq.kosong') }}</p>
                @endforelse

                <div class="mt-3">
                    <x-button href="{{ route('bantuan') }}" variant="outline" size="sm">{{ __('helpdesk.faq.lihat_semua') }}</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
