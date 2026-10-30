@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', $kuesioner->nama)

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @php $terkunci = $kuesioner->pertanyaanTerkunci(); @endphp

    {{-- ============ RINGKASAN & AKSI ============ --}}
    <x-card class="mb-5" flush>
        <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <x-chip :tone="$kuesioner->status()->tone()" dot>{{ $kuesioner->status()->label() }}</x-chip>
                    <x-chip :tone="$kuesioner->anonim ? 'info' : 'warning'">
                        {{ $kuesioner->anonim ? __('kuesioner.daftar.anonim') : __('kuesioner.daftar.beridentitas') }}
                    </x-chip>
                </div>
                <p class="mt-2 text-[12.5px] text-ink-muted">
                    {{ $kuesioner->sasaran->label() }} · {{ $kuesioner->prodi?->nama ?? __('kuesioner.daftar.semua_prodi') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-button href="{{ route('admin.kuesioner.hasil', $kuesioner) }}" variant="outline" size="sm">{{ __('kuesioner.aksi.hasil') }}</x-button>

                @if ($hasilTerbuka && $responden > 0)
                    <x-button href="{{ route('admin.kuesioner.ekspor', $kuesioner) }}" variant="outline" size="sm">{{ __('kuesioner.aksi.ekspor') }}</x-button>
                    <x-button href="{{ route('admin.kuesioner.ekspor', [$kuesioner, 'format' => 'xlsx']) }}" variant="outline" size="sm">{{ __('kuesioner.aksi.ekspor_xlsx') }}</x-button>
                @endif

                @if ($bolehKelola)
                    @if ($kuesioner->is_active)
                        <form method="POST" action="{{ route('admin.kuesioner.tutup', $kuesioner) }}" data-confirm="{{ __('kuesioner.aksi.tutup_konfirmasi') }}">
                            @csrf
                            <x-button type="submit" variant="danger" size="sm">{{ __('kuesioner.aksi.tutup') }}</x-button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.kuesioner.terbitkan', $kuesioner) }}"
                            @unless ($terkunci) data-confirm="{{ __('kuesioner.aksi.terbitkan_konfirmasi') }}" @endunless>
                            @csrf
                            <x-button type="submit" size="sm">
                                {{ $terkunci ? __('kuesioner.aksi.buka_kembali') : __('kuesioner.aksi.terbitkan') }}
                            </x-button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 divide-x divide-line border-t border-line text-center md:grid-cols-4">
            <div class="px-2 py-3">
                <div class="tabular font-serif text-[19px] font-semibold leading-none">{{ Format::bulat($responden) }}</div>
                <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ __('kuesioner.ringkasan.responden') }}</div>
            </div>
            <div class="px-2 py-3">
                <div class="tabular font-serif text-[19px] font-semibold leading-none">{{ $kuesioner->pertanyaan->count() }}</div>
                <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ __('kuesioner.pertanyaan.judul') }}</div>
            </div>
            <div class="px-2 py-3">
                <div class="tabular text-[13px] font-semibold leading-tight">
                    {{ $kuesioner->diterbitkan_at ? Format::tanggal($kuesioner->diterbitkan_at) : '—' }}
                </div>
                <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ __('kuesioner.ringkasan.diterbitkan') }}</div>
            </div>
            <div class="px-2 py-3">
                <div class="text-[13px] font-semibold leading-tight">{{ $kuesioner->pembuat?->nama ?? '—' }}</div>
                <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ __('kuesioner.ringkasan.pembuat') }}</div>
            </div>
        </div>
    </x-card>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        {{-- ============ PERTANYAAN ============ --}}
        <div class="space-y-5">
            <x-card flush :title="__('kuesioner.pertanyaan.judul')">
                @if ($terkunci)
                    <div class="border-b border-line px-5 py-3 text-[12px] text-ink-muted">{{ __('kuesioner.pertanyaan.terkunci') }}</div>
                @endif

                <ol class="divide-y divide-line/60">
                    @forelse ($kuesioner->pertanyaan as $p)
                        <li class="flex items-start gap-4 px-5 py-3.5">
                            <span class="tabular w-6 flex-none pt-0.5 text-[12px] font-semibold text-ink-faint">{{ $p->urutan }}.</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13.5px]">{{ $p->teks }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <x-chip tone="neutral">{{ $p->tipe->label() }}</x-chip>
                                    <x-chip tone="neutral">{{ $p->wajib ? __('kuesioner.pertanyaan.wajib') : __('kuesioner.pertanyaan.opsional') }}</x-chip>
                                </div>
                                @if ($p->opsi)
                                    <div class="mt-1.5 text-[12px] text-ink-muted">{{ implode(' · ', $p->opsi) }}</div>
                                @endif
                            </div>
                            @if ($bolehKelola && ! $terkunci)
                                <form method="POST" action="{{ route('admin.kuesioner.pertanyaan.hapus', $p) }}" data-confirm="{{ __('kuesioner.pertanyaan.hapus_konfirmasi') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="ghost" size="sm">{{ __('kuesioner.pertanyaan.hapus') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-[12.5px] text-ink-faint">{{ __('kuesioner.pertanyaan.kosong') }}</li>
                    @endforelse
                </ol>
            </x-card>

            @if ($bolehKelola && ! $terkunci)
                <x-card :title="__('kuesioner.pertanyaan.tambah')">
                    <form method="POST" action="{{ route('admin.kuesioner.pertanyaan', $kuesioner) }}" class="space-y-3">
                        @csrf
                        <x-field :label="__('kuesioner.pertanyaan.teks')" name="teks" type="textarea" required />
                        <x-field :label="__('kuesioner.pertanyaan.tipe')" name="tipe" :options="$tipePilihan" required value="skala" />
                        <x-field :label="__('kuesioner.pertanyaan.opsi')" name="opsi" type="textarea" :hint="__('kuesioner.pertanyaan.opsi_hint')" />
                        <x-field :label="__('kuesioner.pertanyaan.wajib')" name="wajib" type="checkbox" :value="old('wajib', true)"
                            :hint="__('kuesioner.pertanyaan.wajib')" />
                        <x-button type="submit">{{ __('kuesioner.pertanyaan.tambah') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>

        {{-- ============ DATA KUESIONER ============ --}}
        <x-card :title="__('kuesioner.form.ubah_judul')">
            @if ($bolehKelola)
                <form method="POST" action="{{ route('admin.kuesioner.perbarui', $kuesioner) }}" class="space-y-3">
                    @csrf
                    @method('PUT')

                    <x-field :label="__('kuesioner.form.kode')" name="kode" :value="$kuesioner->kode" required />
                    <x-field :label="__('kuesioner.form.nama')" name="nama" :value="$kuesioner->nama" required />
                    <x-field :label="__('kuesioner.form.deskripsi')" name="deskripsi" type="textarea" :value="$kuesioner->deskripsi" />

                    @if ($terkunci)
                        {{-- Sent unchanged so the request validates; the service
                             ignores them once the questionnaire was published. --}}
                        <input type="hidden" name="sasaran" value="{{ $kuesioner->sasaran->value }}">
                        <input type="hidden" name="prodi_id" value="{{ $kuesioner->prodi_id }}">
                        <p class="text-[11.5px] text-ink-faint">{{ __('kuesioner.form.terkunci') }}</p>
                    @else
                        <x-field :label="__('kuesioner.form.sasaran')" name="sasaran" :options="$sasaranPilihan" :value="$kuesioner->sasaran->value" required />
                        <x-field :label="__('kuesioner.form.prodi')" name="prodi_id" :options="$prodiPilihan" :value="$kuesioner->prodi_id"
                            :hint="__('kuesioner.form.prodi_hint')" />
                        <x-field :label="__('kuesioner.form.ambang')" name="min_responden" type="number" :value="$kuesioner->min_responden"
                            :hint="__('kuesioner.form.ambang_hint', ['ambang' => config('kuesioner.min_responden')])" />
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <x-field :label="__('kuesioner.form.mulai')" name="mulai" type="date" :value="$kuesioner->mulai" />
                        <x-field :label="__('kuesioner.form.selesai')" name="selesai" type="date" :value="$kuesioner->selesai" />
                    </div>

                    <p class="text-[11.5px] text-ink-faint">
                        {{ __('kuesioner.form.anonim_tetap', ['bentuk' => Str::lower($kuesioner->anonim ? __('kuesioner.daftar.anonim') : __('kuesioner.daftar.beridentitas'))]) }}
                    </p>

                    <x-button type="submit" class="w-full">{{ __('kuesioner.form.simpan') }}</x-button>
                </form>
            @else
                <dl class="space-y-2 text-[13px]">
                    <div><dt class="text-ink-muted">{{ __('kuesioner.form.kode') }}</dt><dd class="tabular">{{ $kuesioner->kode }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('kuesioner.form.deskripsi') }}</dt><dd>{{ $kuesioner->deskripsi ?? '—' }}</dd></div>
                </dl>
            @endif
        </x-card>
    </div>
@endsection
