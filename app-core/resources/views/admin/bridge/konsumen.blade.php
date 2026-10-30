@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', $konsumen->nama)

@section('content')
    @if (session('rahasia_sekali'))
        <x-rahasia-sekali :data="session('rahasia_sekali')" />
    @endif

    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @unless ($konsumen->is_active)
        <div class="mb-5"><x-alert tone="warning">{{ __('bridge.konsumen.nonaktif') }}</x-alert></div>
    @endunless

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            {{-- ============ TOKEN ============ --}}
            <x-card flush :title="__('bridge.token.judul')">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('bridge.token.nama') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('bridge.token.dibuat') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('bridge.token.terakhir') }}</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($token as $t)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-2.5 font-medium">{{ $t->name }}</td>
                                    <td class="tabular px-5 py-2.5 text-ink-muted">{{ Format::tanggal($t->created_at) }}</td>
                                    <td class="tabular px-5 py-2.5 text-ink-muted">
                                        {{ $t->last_used_at ? Format::tanggal($t->last_used_at).' '.Format::jam($t->last_used_at) : __('bridge.token.belum_dipakai') }}
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        @if ($bolehKelola)
                                            <form method="POST" action="{{ route('admin.bridge.konsumen.token.cabut', [$konsumen, $t->id]) }}"
                                                data-confirm="{{ __('bridge.token.cabut_konfirmasi') }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-button type="submit" variant="danger" size="sm">{{ __('bridge.token.cabut') }}</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-8 text-center text-[12.5px] text-ink-faint">{{ __('bridge.token.kosong') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($bolehKelola && $konsumen->is_active)
                    <form method="POST" action="{{ route('admin.bridge.konsumen.token', $konsumen) }}"
                        class="flex flex-wrap items-end gap-3 border-t border-line px-5 py-4">
                        @csrf
                        <x-field :label="__('bridge.token.nama')" name="nama_token" :hint="__('bridge.token.nama_hint')" class="w-64" />
                        <x-button type="submit">{{ __('bridge.token.terbitkan') }}</x-button>
                    </form>
                @elseif (! $bolehKelola)
                    <p class="border-t border-line px-5 py-3 text-[12px] text-ink-faint">{{ __('bridge.token.hanya_kredensial') }}</p>
                @endif
            </x-card>

            {{-- ============ WEBHOOK ============ --}}
            <x-card :title="__('bridge.webhook.judul')">
                <div class="mb-4 text-[12.5px]">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('bridge.webhook.rahasia') }}</div>
                    <p class="mt-1 text-ink-muted">{{ $adaRahasia ? __('bridge.webhook.rahasia_ada') : __('bridge.webhook.rahasia_belum') }}</p>

                    @if ($bolehKelola)
                        <form method="POST" action="{{ route('admin.bridge.konsumen.rahasia', $konsumen) }}" class="mt-2"
                            @if ($adaRahasia) data-confirm="{{ __('bridge.webhook.rotasi_konfirmasi') }}" @endif>
                            @csrf
                            <x-button type="submit" variant="outline" size="sm">{{ __('bridge.webhook.rotasi') }}</x-button>
                        </form>
                    @endif
                </div>

                @if ($bolehKelola)
                    <form method="POST" action="{{ route('admin.bridge.konsumen.webhook', $konsumen) }}" class="space-y-3 border-t border-line pt-4">
                        @csrf
                        @method('PUT')
                        <x-field :label="__('bridge.webhook.url')" name="webhook_url" type="url" :value="$konsumen->webhook_url"
                            :hint="__('bridge.webhook.url_hint')" />

                        <fieldset>
                            <legend class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('bridge.webhook.events') }}</legend>
                            <div class="grid gap-1.5 sm:grid-cols-2">
                                @foreach ($eventTersedia as $event)
                                    <label class="flex items-center gap-2 text-[12.5px]">
                                        <input type="checkbox" name="webhook_events[]" value="{{ $event }}" class="accent-navy"
                                            @checked(in_array($event, old('webhook_events', $konsumen->webhook_events ?? []), true))>
                                        <span class="tabular">{{ $event }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <x-button type="submit">{{ __('bridge.webhook.simpan') }}</x-button>
                    </form>
                @else
                    <p class="tabular text-[12.5px] text-ink-muted">{{ $konsumen->webhook_url ?? '—' }}</p>
                    <p class="tabular mt-1 text-[11.5px] text-ink-faint">{{ implode(' · ', $konsumen->webhook_events ?? []) }}</p>
                @endif
            </x-card>

            {{-- ============ RIWAYAT ============ --}}
            <x-card flush :title="__('bridge.riwayat.judul')">
                <ul class="divide-y divide-line/60">
                    @forelse ($riwayat as $log)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-2.5 text-[12.5px]">
                            <span>
                                <x-chip tone="info">{{ $log->event }}</x-chip>
                                {{ $log->description }}
                                <span class="text-ink-faint">· {{ $log->causer_name ?? '—' }}</span>
                            </span>
                            <span class="tabular text-ink-faint">{{ Format::tanggal($log->created_at) }} {{ Format::jam($log->created_at) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-[12.5px] text-ink-faint">{{ __('bridge.riwayat.kosong') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        {{-- ============ DATA APLIKASI ============ --}}
        <x-card :title="__('bridge.konsumen.data')">
            @if ($bolehKelola)
                <form method="POST" action="{{ route('admin.bridge.konsumen.perbarui', $konsumen) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    @include('admin.bridge.partials.form-konsumen', ['konsumen' => $konsumen])
                    <x-button type="submit" class="w-full">{{ __('bridge.daftar.perbarui') }}</x-button>
                </form>
            @else
                <dl class="space-y-2 text-[13px]">
                    <div><dt class="text-ink-muted">{{ __('bridge.daftar.slug') }}</dt><dd class="tabular">{{ $konsumen->slug }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('bridge.daftar.scopes') }}</dt><dd class="tabular">{{ implode(', ', $konsumen->scopes ?? []) }}</dd></div>
                </dl>
            @endif
        </x-card>
    </div>
@endsection
