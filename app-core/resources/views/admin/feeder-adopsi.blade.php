@extends('layouts.app')

@section('title', __('feeder.adopsi.judul'))

@php
    use App\Enums\StatusAdopsiFeeder;
    use App\Support\Format;
@endphp

@section('content')
    <x-card class="mb-5">
        <p class="text-[13px] text-ink-muted">{{ __('feeder.adopsi.penjelasan') }}</p>
        <p class="mt-1 text-[11.5px] text-ink-faint">{{ __('feeder.adopsi.privasi') }}</p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <form method="GET" action="{{ route('admin.feeder.adopsi') }}" class="flex flex-wrap items-end gap-3">
                <x-field
                    :label="__('feeder.adopsi.form.prodi')" name="prodi" required class="min-w-[240px]"
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->uuid => $p->nama])->all()"
                    :value="$prodi?->uuid"
                />
                <x-field
                    :label="__('feeder.adopsi.form.periode')" name="periode" required class="w-36"
                    :value="$periode" placeholder="20241" :hint="__('feeder.adopsi.form.periode_hint')"
                />
                <x-button type="submit" variant="outline" size="sm">{{ __('feeder.adopsi.form.tampilkan') }}</x-button>
            </form>

            @if ($prodi && $periode)
                <form method="POST" action="{{ route('admin.feeder.adopsi.jalankan') }}">
                    @csrf
                    <input type="hidden" name="prodi" value="{{ $prodi->uuid }}">
                    <input type="hidden" name="periode" value="{{ $periode }}">
                    <x-button type="submit" size="sm" :disabled="! $aktif">{{ __('feeder.adopsi.form.jalankan') }}</x-button>
                </form>
            @endif
        </div>
    </x-card>

    @if ($laporan->isNotEmpty())
        <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-5">
            @foreach ([
                ['diadopsi', ($jumlah[StatusAdopsiFeeder::Diadopsi->value] ?? 0), 'text-success'],
                ['ambigu', ($jumlah[StatusAdopsiFeeder::Ambigu->value] ?? 0), ($jumlah[StatusAdopsiFeeder::Ambigu->value] ?? 0) > 0 ? 'text-danger' : ''],
                ['hanya_feeder', ($jumlah[StatusAdopsiFeeder::HanyaFeeder->value] ?? 0), ''],
                ['hanya_lokal', ($jumlah[StatusAdopsiFeeder::HanyaLokal->value] ?? 0), ''],
                ['diputuskan', ($jumlah[StatusAdopsiFeeder::Ditautkan->value] ?? 0) + ($jumlah[StatusAdopsiFeeder::Dipisahkan->value] ?? 0), 'text-ink-muted'],
            ] as [$kunci, $nilai, $warna])
                <x-card>
                    <div class="tabular font-serif text-[20px] font-semibold leading-none {{ $warna }}">{{ Format::bulat($nilai) }}</div>
                    <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ __('feeder.adopsi.ringkasan.'.$kunci) }}</div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-card :title="$prodi ? $prodi->nama.' · '.$periode : __('feeder.adopsi.judul')" :meta="__('feeder.adopsi.meta', ['jumlah' => $laporan->count()])" flush>
        @forelse ($laporan as $a)
            <div class="flex flex-wrap items-start gap-3 border-b border-line/50 px-5 py-3 last:border-b-0">
                <div class="w-36 flex-none">
                    <x-chip :tone="$a->status->tone()">{{ $a->status->label() }}</x-chip>
                </div>

                <div class="w-44 flex-none">
                    @if ($a->mahasiswa)
                        <div class="tabular truncate text-[12.5px] font-medium">{{ $a->mahasiswa->nim }}</div>
                        <div class="truncate text-[11px] text-ink-faint">{{ $a->mahasiswa->nama }}</div>
                    @else
                        <div class="text-[12px] text-ink-faint">—</div>
                    @endif
                </div>

                <div class="min-w-0 flex-1 text-[12.5px]">
                    @if ($a->alasan)
                        <div class="text-danger">{{ $a->alasan->label() }}</div>
                    @endif

                    @foreach ($a->kandidat ?? [] as $k)
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="tabular font-medium">{{ __('feeder.adopsi.kandidat.nim', ['nim' => $k['nim'] ?? '—']) }}</span>
                            <span class="text-ink-muted">{{ $k['nama'] ?? '—' }}</span>
                            @if (! empty($k['tanggal_lahir']))
                                <span class="tabular text-[11px] text-ink-faint">{{ __('feeder.adopsi.kandidat.lahir', ['tanggal' => Format::tanggal($k['tanggal_lahir'])]) }}</span>
                            @endif
                            @if ($a->mahasiswa)
                                <span class="text-[11px] {{ ($k['nik_cocok'] ?? null) === false ? 'text-danger' : 'text-ink-faint' }}">
                                    {{ match ($k['nik_cocok'] ?? null) {
                                        true => __('feeder.adopsi.kandidat.nik_cocok'),
                                        false => __('feeder.adopsi.kandidat.nik_beda'),
                                        default => __('feeder.adopsi.kandidat.nik_tidak_ada'),
                                    } }}
                                </span>
                            @endif

                            @if ($a->status === StatusAdopsiFeeder::Ambigu && $a->mahasiswa)
                                @can('update', $a)
                                    <form method="POST" action="{{ route('admin.feeder.adopsi.tautkan', $a) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="id_registrasi_mahasiswa" value="{{ $k['id_registrasi_mahasiswa'] }}">
                                        <input type="text" name="catatan" required minlength="10" maxlength="1000"
                                            aria-label="{{ __('feeder.adopsi.catatan') }}"
                                            placeholder="{{ __('feeder.adopsi.catatan_placeholder') }}"
                                            class="w-72 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]" />
                                        <x-button type="submit" size="sm" variant="outline">{{ __('feeder.adopsi.tombol.tautkan') }}</x-button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    @endforeach

                    @if ($a->status === StatusAdopsiFeeder::Diadopsi && $a->mahasiswa && $a->nama_feeder
                        && Str::lower(trim($a->nama_feeder)) !== Str::lower(trim($a->mahasiswa->nama)))
                        <div class="mt-1 text-[11px] text-warning">{{ __('feeder.adopsi.nama_berbeda', ['nama' => $a->nama_feeder]) }}</div>
                    @endif

                    @if ($a->status->keputusanManual())
                        <div class="mt-1 text-[11px] text-ink-faint">
                            {{ __('feeder.adopsi.keputusan', [
                                'nama' => $a->diputuskanOleh?->nama ?? '—',
                                'tanggal' => Format::tanggal($a->diputuskan_at),
                                'catatan' => $a->catatan,
                            ]) }}
                        </div>
                    @endif

                    @if ($a->status === StatusAdopsiFeeder::Ambigu && $a->mahasiswa)
                        @can('update', $a)
                            <form method="POST" action="{{ route('admin.feeder.adopsi.pisahkan', $a) }}" class="mt-2 flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="text" name="catatan" required minlength="10" maxlength="1000"
                                    aria-label="{{ __('feeder.adopsi.catatan') }}"
                                    placeholder="{{ __('feeder.adopsi.catatan_placeholder') }}"
                                    class="w-72 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]" />
                                <x-button type="submit" size="sm" variant="ghost">{{ __('feeder.adopsi.tombol.pisahkan') }}</x-button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-10">
                <x-empty-state
                    :title="__('feeder.adopsi.kosong.judul')"
                    :description="__('feeder.adopsi.kosong.deskripsi')"
                />
            </div>
        @endforelse
    </x-card>
@endsection
