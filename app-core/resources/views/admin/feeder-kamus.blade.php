@extends('layouts.app')

@section('title', __('feeder.kamus.judul'))

@php
    use App\Enums\JenisTemuanKamus;
    use App\Support\Format;
@endphp

@section('content')
    <x-card class="mb-5">
        <p class="text-[13px] text-ink-muted">{{ __('feeder.kamus.penjelasan') }}</p>
        <p class="mt-1 text-[11.5px] text-ink-faint">{{ __('feeder.kamus.privasi') }}</p>

        @can('create', App\Models\Feeder\FeederDictionaryRun::class)
            <form method="POST" action="{{ route('admin.feeder.kamus.periksa') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-field
                    :label="__('feeder.kamus.form.versi')" name="versi" class="w-48"
                    :value="old('versi')" placeholder="3.1" :hint="__('feeder.kamus.form.versi_hint')"
                />
                <x-button type="submit" size="sm" :disabled="! $aktif">{{ __('feeder.kamus.form.periksa') }}</x-button>
            </form>
        @endcan
    </x-card>

    @if ($run)
        <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ([
                ['diperiksa', Format::tanggal($run->diperiksa_at).' '.Format::jam($run->diperiksa_at), ''],
                ['versi', $run->versi_feeder ?? __('feeder.kamus.versi_tidak_diketahui'), ''],
                ['gagal', Format::bulat($run->jumlah_gagal).' / '.Format::bulat($run->jumlah_aksi), $run->jumlah_gagal > 0 ? 'text-warning' : ''],
                ['selisih', Format::bulat($selisih->count()).' / '.Format::bulat($laporan->count()), $selisih->isNotEmpty() ? 'text-danger' : 'text-success'],
            ] as [$kunci, $nilai, $warna])
                <x-card>
                    <div class="tabular font-serif text-[18px] font-semibold leading-none {{ $warna }}">{{ $nilai }}</div>
                    <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ __('feeder.kamus.ringkasan.'.$kunci) }}</div>
                </x-card>
            @endforeach
        </div>

        <x-card
            :title="__('feeder.kamus.judul')"
            :meta="__('feeder.kamus.ringkasan.oleh').': '.($run->dijalankanOleh?->nama ?? __('feeder.kamus.ringkasan.perintah'))"
            flush
        >
            @foreach ($laporan as $l)
                <div class="border-b border-line/50 px-5 py-3 last:border-b-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="w-56 flex-none truncate text-[12.5px] font-medium">{{ $l->label }}</span>
                        <span class="tabular min-w-0 flex-1 truncate text-[11.5px] text-ink-faint">{{ $l->act }}</span>
                        @if ($l->cocok())
                            <x-chip tone="success">{{ __('feeder.kamus.cocok') }}</x-chip>
                        @elseif (! $l->terbaca)
                            <x-chip tone="neutral">{{ __('feeder.kamus.tidak_terbaca') }}</x-chip>
                        @else
                            <x-chip tone="danger">{{ count($l->temuan) }} {{ __('feeder.kamus.kolom.temuan') }}</x-chip>
                        @endif
                    </div>

                    @if (! $l->cocok())
                        <ul class="mt-2 space-y-1 text-[12px]">
                            @foreach ($l->temuan as $t)
                                <li class="flex flex-wrap items-center gap-2">
                                    <x-chip :tone="$t->jenis->tone()">{{ $t->jenis->label() }}</x-chip>
                                    @if ($t->jenis === JenisTemuanKamus::AksiTidakTerbaca)
                                        <span class="text-ink-muted">{{ $t->keterangan }}</span>
                                    @else
                                        @if ($t->kita)
                                            <span><span class="text-ink-faint">{{ __('feeder.kamus.kolom.dikirim') }}:</span> <code class="tabular">{{ $t->kita }}</code></span>
                                        @endif
                                        @if ($t->feeder)
                                            <span><span class="text-ink-faint">{{ __('feeder.kamus.kolom.kamus') }}:</span> <code class="tabular">{{ $t->feeder }}</code></span>
                                        @endif
                                        @if ($t->wajib)
                                            <span class="text-[11px] text-danger">{{ __('feeder.kamus.wajib') }}</span>
                                        @endif
                                        @if ($t->keterangan)
                                            <span class="text-[11px] text-ink-faint">{{ $t->keterangan }}</span>
                                        @endif
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($l->opsionalTidakDikirim !== [])
                        <div class="mt-1.5 text-[11px] text-ink-faint">
                            {{ __('feeder.kamus.opsional', ['jumlah' => count($l->opsionalTidakDikirim), 'daftar' => implode(', ', $l->opsionalTidakDikirim)]) }}
                        </div>
                    @endif
                </div>
            @endforeach
        </x-card>

        @if ($riwayat->count() > 1)
            <x-card class="mt-5" :title="__('feeder.kamus.riwayat')" flush>
                @foreach ($riwayat as $r)
                    <a href="{{ route('admin.feeder.kamus', ['run' => $r->uuid]) }}"
                        class="flex flex-wrap items-center gap-3 border-b border-line/50 px-5 py-2 text-[12px] last:border-b-0 hover:bg-highlight {{ $r->is($run) ? 'font-semibold' : '' }}">
                        <span class="tabular w-40 flex-none">{{ Format::tanggal($r->diperiksa_at) }} {{ Format::jam($r->diperiksa_at) }}</span>
                        <span class="tabular min-w-0 flex-1 text-ink-muted">{{ $r->versi_feeder ?? __('feeder.kamus.versi_tidak_diketahui') }} · {{ $r->driver }}</span>
                        <span class="tabular text-ink-faint">{{ $r->jumlah_aksi - $r->jumlah_gagal }}/{{ $r->jumlah_aksi }}</span>
                    </a>
                @endforeach
            </x-card>
        @endif
    @else
        <x-card>
            <x-empty-state :title="__('feeder.kamus.kosong.judul')" :description="__('feeder.kamus.kosong.deskripsi')" />
        </x-card>
    @endif
@endsection
