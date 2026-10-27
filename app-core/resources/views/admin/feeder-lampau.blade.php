@extends('layouts.app')

@section('title', __('feeder.lampau.judul'))

@php
    use App\Enums\MasalahKlaimLampau;
    use App\Enums\StatusLampauFeeder;
    use App\Support\Format;

    $dipilih = collect(old('baris', []));
@endphp

@section('aksi')
    <x-button variant="outline" href="{{ route('admin.feeder.adopsi', array_filter(['prodi' => $prodi?->uuid, 'periode' => $periode])) }}">
        {{ __('feeder.adopsi.tautan_konsol') }}
    </x-button>
@endsection

@section('content')
    <x-card class="mb-5">
        <p class="text-[13px] text-ink-muted">{{ __('feeder.lampau.penjelasan') }}</p>
        <p class="mt-1 text-[11.5px] text-ink-faint">{{ __('feeder.lampau.privasi') }}</p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <form method="GET" action="{{ route('admin.feeder.lampau') }}" class="flex flex-wrap items-end gap-3">
                <x-field
                    :label="__('feeder.lampau.form.prodi')" name="prodi" required class="min-w-[240px]"
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->uuid => $p->nama])->all()"
                    :value="$prodi?->uuid"
                />
                <x-field
                    :label="__('feeder.lampau.form.periode')" name="periode" required class="w-36"
                    :value="$periode" placeholder="20191" :hint="__('feeder.lampau.form.periode_hint')"
                />
                <x-button type="submit" variant="outline" size="sm">{{ __('feeder.lampau.form.tampilkan') }}</x-button>
            </form>

            @if ($prodi && $periode)
                @can('create', App\Models\Feeder\FeederPastStudent::class)
                    <form method="POST" action="{{ route('admin.feeder.lampau.baca') }}">
                        @csrf
                        <input type="hidden" name="prodi" value="{{ $prodi->uuid }}">
                        <input type="hidden" name="periode" value="{{ $periode }}">
                        <x-button type="submit" size="sm" :disabled="! $aktif">{{ __('feeder.lampau.form.baca') }}</x-button>
                    </form>
                @endcan
            @endif
        </div>
    </x-card>

    @if (session('galat_klaim'))
        <x-card class="mb-5 border-danger/40">
            <div class="text-[12.5px] font-semibold text-danger">{{ __('feeder.lampau.galat_judul') }}</div>
            <ul class="mt-2 space-y-1 text-[12.5px]">
                @foreach (session('galat_klaim') as $g)
                    <li>
                        <span class="tabular font-medium">{{ $g['nim'] ?? '—' }}</span>
                        <span class="text-ink-muted">{{ $g['nama'] ?? '' }}</span>
                        — {{ $g['pesan'] }}
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if (session('ditolak_klaim'))
        <x-card class="mb-5 border-warning/40">
            <div class="text-[12.5px] font-semibold text-warning">{{ __('feeder.lampau.ditolak_judul') }}</div>
            <ul class="mt-2 space-y-1 text-[12.5px]">
                @foreach (session('ditolak_klaim') as $g)
                    <li><span class="tabular font-medium">{{ $g['nim'] ?? '—' }}</span> — {{ $g['pesan'] }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($laporan->isNotEmpty())
        <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ([
                ['cocok', $jumlah[StatusLampauFeeder::Cocok->value] ?? 0, 'text-success'],
                ['ambigu', $jumlah[StatusLampauFeeder::Ambigu->value] ?? 0, ($jumlah[StatusLampauFeeder::Ambigu->value] ?? 0) > 0 ? 'text-danger' : ''],
                ['tanpa_lokal', $jumlah[StatusLampauFeeder::TanpaLokal->value] ?? 0, ''],
                ['diklaim', $jumlah[StatusLampauFeeder::Diklaim->value] ?? 0, 'text-ink-muted'],
            ] as [$kunci, $nilai, $warna])
                <x-card>
                    <div class="tabular font-serif text-[20px] font-semibold leading-none {{ $warna }}">{{ Format::bulat($nilai) }}</div>
                    <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ __('feeder.lampau.ringkasan.'.$kunci) }}</div>
                </x-card>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.feeder.lampau.klaim') }}">
        @csrf
        @if ($prodi && $periode)
            <input type="hidden" name="prodi" value="{{ $prodi->uuid }}">
            <input type="hidden" name="periode" value="{{ $periode }}">
        @endif

        <x-card :title="$prodi ? $prodi->nama.' · '.$periode : __('feeder.lampau.judul')" :meta="__('feeder.lampau.meta', ['jumlah' => $laporan->count()])" flush>
            @forelse ($laporan as $b)
                @php
                    $masalah = $b->status === StatusLampauFeeder::Diklaim
                        ? null
                        : MasalahKlaimLampau::untuk($b->status, $b->kunci_klaim !== null, false);
                    $bolehDipilih = $b->status !== StatusLampauFeeder::Diklaim
                        && ($masalah === null || $masalah === MasalahKlaimLampau::AmbiguTanpaAlasan);
                    $coba = $percobaan->get($b->feeder_registrasi_id);
                @endphp

                <div class="flex flex-wrap items-start gap-3 border-b border-line/50 px-5 py-3 last:border-b-0">
                    <div class="w-6 flex-none pt-0.5">
                        @if ($bolehDipilih)
                            @can('klaim', App\Models\Feeder\FeederPastStudent::class)
                                <input type="checkbox" name="baris[]" value="{{ $b->uuid }}" class="accent-navy"
                                    aria-label="{{ __('feeder.lampau.form.pilih', ['nim' => $b->nim_feeder ?? '—']) }}"
                                    @checked($dipilih->contains($b->uuid))>
                            @endcan
                        @endif
                    </div>

                    <div class="w-32 flex-none">
                        <x-chip :tone="$b->status->tone()">{{ $b->status->label() }}</x-chip>
                    </div>

                    <div class="min-w-0 flex-1 text-[12.5px]">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="tabular font-medium">{{ __('feeder.lampau.kandidat.nim', ['nim' => $b->nim_feeder ?? '—']) }}</span>
                            <span class="text-ink-muted">{{ $b->nama_feeder ?? '—' }}</span>
                            @if ($b->tanggal_lahir_feeder)
                                <span class="tabular text-[11px] text-ink-faint">{{ __('feeder.lampau.kandidat.lahir', ['tanggal' => Format::tanggal($b->tanggal_lahir_feeder)]) }}</span>
                            @endif
                            @if ($b->mahasiswa)
                                <span class="text-[11px] {{ $b->nik_cocok === false ? 'text-danger' : 'text-ink-faint' }}">
                                    {{ match ($b->nik_cocok) {
                                        true => __('feeder.lampau.kandidat.nik_cocok'),
                                        false => __('feeder.lampau.kandidat.nik_beda'),
                                        default => __('feeder.lampau.kandidat.nik_tidak_ada'),
                                    } }}
                                </span>
                            @endif
                        </div>

                        @if ($b->alasan)
                            <div class="mt-1 text-danger">{{ $b->alasan->label() }}</div>
                        @endif

                        @if ($masalah && $masalah !== MasalahKlaimLampau::AmbiguTanpaAlasan)
                            <div class="mt-1 text-[11.5px] text-ink-faint">{{ $masalah->label() }}</div>
                        @endif

                        @if ($b->status === StatusLampauFeeder::Diklaim)
                            <div class="mt-1 text-[11px] text-ink-faint">
                                {{ __('feeder.lampau.diklaim_oleh', ['nama' => $b->diklaimOleh?->nama ?? '—', 'tanggal' => Format::tanggal($b->diklaim_at)]) }}
                                @if ($b->catatan)
                                    · {{ __('feeder.lampau.diklaim_catatan', ['catatan' => $b->catatan]) }}
                                @endif
                            </div>
                            <div class="mt-1 text-[11px] {{ $b->mahasiswa?->feeder_registrasi_id === $b->feeder_registrasi_id ? 'text-success' : 'text-warning' }}">
                                {{ $b->mahasiswa?->feeder_registrasi_id === $b->feeder_registrasi_id ? __('feeder.lampau.tertaut') : __('feeder.lampau.belum_tertaut') }}
                            </div>
                        @elseif ($coba && $coba['log']->status === App\Enums\FeederSyncStatus::Failed)
                            <div class="mt-1 text-[11px] text-warning">
                                {{ __('feeder.lampau.percobaan', ['ke' => $coba['percobaan'], 'pesan' => $coba['log']->error_message]) }}
                            </div>
                        @endif
                    </div>

                    <div class="w-44 flex-none text-right">
                        @if ($b->mahasiswa)
                            <div class="tabular truncate text-[12.5px] font-medium">{{ $b->mahasiswa->nim }}</div>
                            <div class="truncate text-[11px] text-ink-faint">{{ $b->mahasiswa->nama }}</div>
                        @else
                            <div class="text-[12px] text-ink-faint">—</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-10">
                    <x-empty-state
                        :title="__('feeder.lampau.kosong.judul')"
                        :description="__('feeder.lampau.kosong.deskripsi')"
                    />
                </div>
            @endforelse
        </x-card>

        @if ($laporan->contains(fn ($b) => $b->status === StatusLampauFeeder::Cocok || $b->status === StatusLampauFeeder::Ambigu))
            @can('klaim', App\Models\Feeder\FeederPastStudent::class)
                <x-card class="mt-5">
                    <div class="flex flex-wrap items-end gap-3">
                        <x-field
                            :label="__('feeder.lampau.form.catatan')" name="catatan" class="min-w-[320px] flex-1"
                            :placeholder="__('feeder.lampau.form.catatan_placeholder')" :hint="__('feeder.lampau.form.catatan_hint')"
                        />
                        <x-button type="submit" size="sm" :disabled="! $aktif">{{ __('feeder.lampau.form.klaim') }}</x-button>
                    </div>
                </x-card>
            @endcan
        @endif
    </form>
@endsection
