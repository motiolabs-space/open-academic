@extends('layouts.app')

@section('title', __('dosen_wali.judul'))

@section('content')
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="min-w-[220px]">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('dosen_wali.filter.prodi') }}</span>
                <select name="prodi" required class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                    <option value="">—</option>
                    @foreach ($daftarProdi as $p)
                        <option value="{{ $p->id }}" @selected($prodi?->id === $p->id)>{{ $p->namaLengkap() }}</option>
                    @endforeach
                </select>
            </label>

            @if ($prodi)
                <label>
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('dosen_wali.filter.angkatan') }}</span>
                    <select name="angkatan" class="rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                        <option value="">{{ __('dosen_wali.filter.semua_angkatan') }}</option>
                        @foreach ($daftarAngkatan as $a)
                            <option value="{{ $a }}" @selected((int) $angkatan === (int) $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="flex items-center gap-2 py-2 text-[13px]">
                <input type="checkbox" name="tanpa_wali" value="1" class="accent-navy" @checked($tanpaWali)>
                {{ __('dosen_wali.filter.tanpa_wali') }}
            </label>

            <x-button type="submit">{{ __('dosen_wali.filter.terapkan') }}</x-button>
        </form>
    </x-card>

    @if (! $prodi)
        <x-card>
            <x-empty-state :title="__('dosen_wali.pilih_prodi_judul')" :description="__('dosen_wali.pilih_prodi')" />
        </x-card>
    @else
        <x-alert tone="info" class="mb-5">{{ __('dosen_wali.aturan') }}</x-alert>

        @if ($calon->isEmpty())
            <x-alert tone="warning" class="mb-5">{{ __('dosen_wali.tanpa_calon') }}</x-alert>
        @endif

        <div class="grid gap-5 xl:grid-cols-[1fr_320px]">
            <x-card :title="__('dosen_wali.massal.judul')" :meta="$konteks" flush>
                <form method="POST" action="{{ route('admin.dosen-wali.massal') }}"
                    data-confirm="{{ __('dosen_wali.massal.konfirmasi') }}">
                    @csrf

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">{{ __('dosen_wali.kolom.nim') }}</th>
                                    <th class="px-5 py-3 font-semibold">{{ __('dosen_wali.kolom.nama') }}</th>
                                    <th class="px-5 py-3 text-center font-semibold">{{ __('dosen_wali.kolom.angkatan') }}</th>
                                    <th class="px-5 py-3 font-semibold">{{ __('dosen_wali.kolom.wali_saat_ini') }}</th>
                                    <th class="px-5 py-3 font-semibold">{{ __('dosen_wali.kolom.wali_baru') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($mahasiswa as $m)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top" data-mahasiswa="{{ $m->uuid }}">
                                        <td class="tabular px-5 py-3">{{ $m->nim }}</td>
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $m->nama }}</div>
                                            @if ($m->status !== \App\Enums\StudentStatus::Aktif)
                                                <x-chip :tone="$m->status->tone()" class="mt-1">{{ $m->status->label() }}</x-chip>
                                            @endif
                                        </td>
                                        <td class="tabular px-5 py-3 text-center">{{ $m->angkatan }}</td>
                                        <td class="px-5 py-3">
                                            @if ($m->dosenWali)
                                                {{ $m->dosenWali->namaLengkap() }}
                                            @else
                                                <span class="text-ink-faint">{{ __('dosen_wali.belum_ada') }}</span>
                                            @endif
                                            @if ($m->krs_menunggu > 0)
                                                <div class="mt-1 text-[11.5px] text-warning">
                                                    {{ __('dosen_wali.krs_menunggu', ['jumlah' => $m->krs_menunggu]) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <select name="penetapan[{{ $m->uuid }}]"
                                                aria-label="{{ __('dosen_wali.kolom.wali_baru') }} {{ $m->nama }}"
                                                class="w-full rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px]">
                                                <option value="">{{ __('dosen_wali.tidak_berubah') }}</option>
                                                @foreach ($calon as $d)
                                                    <option value="{{ $d->uuid }}">{{ $d->namaLengkap() }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-12">
                                            <x-empty-state :title="__('dosen_wali.kosong')" description="" />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($mahasiswa->isNotEmpty() && $calon->isNotEmpty())
                        <div class="flex flex-wrap items-end gap-3 border-t border-line px-5 py-4">
                            <label class="min-w-[240px] flex-1">
                                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('dosen_wali.alasan') }}</span>
                                <input type="text" name="alasan" maxlength="500" placeholder="{{ __('dosen_wali.alasan_placeholder') }}"
                                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px]">
                            </label>
                            <x-button type="submit">{{ __('dosen_wali.massal.tombol') }}</x-button>
                        </div>
                    @endif
                </form>

                @if ($mahasiswa->hasPages())
                    <div class="border-t border-line px-5 py-3">{{ $mahasiswa->links() }}</div>
                @endif
            </x-card>

            <x-card :title="__('dosen_wali.merata.judul')">
                <p class="mb-4 text-[12.5px] leading-relaxed text-ink-muted">{{ __('dosen_wali.merata.penjelasan') }}</p>

                <form method="POST" action="{{ route('admin.dosen-wali.merata') }}"
                    data-confirm="{{ __('dosen_wali.merata.konfirmasi') }}">
                    @csrf
                    <input type="hidden" name="prodi" value="{{ $prodi->id }}">
                    <input type="hidden" name="angkatan" value="{{ $angkatan }}">
                    <input type="hidden" name="hanya_tanpa_wali" value="{{ $tanpaWali ? 1 : 0 }}">

                    <fieldset class="mb-4">
                        <legend class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('dosen_wali.merata.dosen') }}</legend>
                        @forelse ($calon as $d)
                            <label class="flex items-center justify-between gap-2 py-1.5 text-[13px]">
                                <span class="flex items-center gap-2">
                                    <input type="checkbox" name="dosen[]" value="{{ $d->uuid }}" class="accent-navy">
                                    {{ $d->namaLengkap() }}
                                </span>
                                <span class="tabular text-[11.5px] text-ink-faint" title="{{ __('dosen_wali.kolom.bimbingan') }}">{{ $d->jumlah_bimbingan }}</span>
                            </label>
                        @empty
                            <p class="text-[12.5px] text-ink-faint">{{ __('dosen_wali.tanpa_calon') }}</p>
                        @endforelse
                    </fieldset>

                    <x-field :label="__('dosen_wali.alasan')" name="alasan" :placeholder="__('dosen_wali.alasan_placeholder')" class="mb-4" />

                    <x-button type="submit" class="w-full" :disabled="$calon->isEmpty()">{{ __('dosen_wali.merata.tombol') }}</x-button>
                </form>
            </x-card>
        </div>
    @endif
@endsection
