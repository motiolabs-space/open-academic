@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('sso_klien.judul'))

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

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <x-card flush :title="__('sso_klien.daftar.judul')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('sso_klien.daftar.nama') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('sso_klien.daftar.redirect') }}</th>
                            <th class="px-5 py-3 text-right font-semibold" title="{{ __('sso_klien.daftar.pengguna_hint') }}">{{ __('sso_klien.daftar.pengguna') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('sso_klien.daftar.status') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $baris)
                            @php
                                $k = $baris['klien'];
                                $atur = $k->pengaturan;
                                $lewatEnv = in_array((string) $k->getKey(), $firstPartyEnv, true);
                            @endphp
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $k->name }}</div>
                                    <div class="tabular select-all text-[11px] text-ink-faint">{{ $k->getKey() }}</div>
                                    @if ($atur?->rahasia_dirotasi_at)
                                        <div class="text-[11px] text-ink-faint">{{ __('sso_klien.daftar.rotasi_terakhir', ['tanggal' => Format::tanggal($atur->rahasia_dirotasi_at)]) }}</div>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3 text-[11.5px] text-ink-muted">
                                    @foreach ((array) $k->redirect_uris as $uri)
                                        <div class="break-all">{{ $uri }}</div>
                                    @endforeach
                                </td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($baris['pengguna']) }}</td>
                                <td class="px-5 py-3">
                                    @if ($k->revoked)
                                        <x-chip tone="neutral">{{ __('sso_klien.daftar.dicabut') }}</x-chip>
                                        @if ($atur?->dicabut_at)
                                            <div class="tabular mt-1 text-[11px] text-ink-faint">{{ __('sso_klien.daftar.dicabut_pada', ['tanggal' => Format::tanggal($atur->dicabut_at)]) }}</div>
                                        @endif
                                    @else
                                        <x-chip tone="success" dot>{{ __('sso_klien.daftar.aktif') }}</x-chip>
                                        @if ($k->pihakPertama())
                                            <div class="mt-1"><x-chip tone="warning">{{ __('sso_klien.daftar.pihak_pertama') }}</x-chip></div>
                                            @if ($lewatEnv)
                                                <div class="text-[11px] text-ink-faint">{{ __('sso_klien.daftar.dari_env') }}</div>
                                            @endif
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @unless ($k->revoked)
                                        <div class="flex flex-col items-end gap-1.5">
                                            <form method="POST" action="{{ route('admin.sso-klien.rotasi', $k) }}" data-confirm="{{ __('sso_klien.aksi.rotasi_konfirmasi') }}">
                                                @csrf
                                                <x-button type="submit" variant="outline" size="sm">{{ __('sso_klien.aksi.rotasi') }}</x-button>
                                            </form>

                                            @unless ($lewatEnv)
                                                <form method="POST" action="{{ route('admin.sso-klien.pihak-pertama', $k) }}"
                                                    @unless ($atur?->is_first_party) data-confirm="{{ __('sso_klien.aksi.jadikan_pihak_pertama_konfirmasi') }}" @endunless>
                                                    @csrf
                                                    <input type="hidden" name="pihak_pertama" value="{{ $atur?->is_first_party ? 0 : 1 }}">
                                                    <x-button type="submit" variant="ghost" size="sm">
                                                        {{ $atur?->is_first_party ? __('sso_klien.aksi.minta_persetujuan') : __('sso_klien.aksi.jadikan_pihak_pertama') }}
                                                    </x-button>
                                                </form>
                                            @endunless

                                            <form method="POST" action="{{ route('admin.sso-klien.cabut', $k) }}" data-confirm="{{ __('sso_klien.aksi.cabut_konfirmasi') }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-button type="submit" variant="danger" size="sm">{{ __('sso_klien.aksi.cabut') }}</x-button>
                                            </form>
                                        </div>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state :title="__('sso_klien.daftar.kosong')" :description="__('sso_klien.daftar.kosong_deskripsi')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="__('sso_klien.form.judul')">
            <form method="POST" action="{{ route('admin.sso-klien.store') }}" class="space-y-3">
                @csrf
                <x-field :label="__('sso_klien.form.nama')" name="nama" required :hint="__('sso_klien.form.nama_hint')" />
                <x-field :label="__('sso_klien.form.redirect')" name="redirect_uris" type="textarea" required
                    placeholder="https://campus.example.ac.id/auth/callback" :hint="__('sso_klien.form.redirect_hint')" />
                <x-field :label="__('sso_klien.form.pihak_pertama')" name="pihak_pertama" type="checkbox" :value="old('pihak_pertama', false)"
                    :hint="__('sso_klien.form.pihak_pertama_hint')" />
                <p class="text-[11.5px] leading-relaxed text-ink-faint">{{ __('sso_klien.form.pihak_pertama_peringatan') }}</p>
                <x-button type="submit" class="w-full">{{ __('sso_klien.form.simpan') }}</x-button>
            </form>
        </x-card>
    </div>
@endsection
