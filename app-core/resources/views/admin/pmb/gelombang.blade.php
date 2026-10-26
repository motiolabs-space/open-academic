@extends('layouts.app')

@php use App\Support\Format; @endphp

@section('title', $gelombang->nama)

@section('content')
    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if (session('galat_impor'))
        <div class="mb-5">
            <x-alert tone="danger">
                <ul class="list-disc pl-4">
                    @foreach (session('galat_impor') as $baris)
                        <li>{{ $baris }}</li>
                    @endforeach
                </ul>
            </x-alert>
        </div>
    @endif

    <div class="mb-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        {{-- Prodi ditawarkan --}}
        <x-card :title="__('pmb.admin.tawaran')" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('pmb.admin.prodi') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pmb.admin.biaya_formulir') }}</th>
                            <th class="px-5 py-3 text-center font-semibold">{{ __('pmb.admin.pendaftar') }}</th>
                            <th class="px-5 py-3 text-center font-semibold">{{ __('pmb.admin.daya_tampung') }}</th>
                            <th class="px-5 py-3 text-center font-semibold">{{ __('pmb.admin.ambang_lulus') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gelombang->prodiDitawarkan as $t)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    {{ $t->prodi->jenjang?->label() }} {{ $t->prodi->nama }}
                                    @if ($t->dokumen_wajib)
                                        <div class="text-[11.5px] text-ink-faint">
                                            + {{ collect($t->dokumen_wajib)->map(fn ($j) => $jenisBerkas[$j] ?? $j)->implode(', ') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::rupiah($gelombang->biayaUntuk($t)) }}</td>
                                <td class="tabular px-5 py-3 text-center">{{ $rekap['pendaftar'][$t->prodi_id] ?? 0 }}</td>
                                <td class="tabular px-5 py-3 text-center">
                                    {{ $rekap['terisi'][$t->prodi_id] ?? 0 }} / {{ $t->daya_tampung ?? '∞' }}
                                </td>
                                <td class="tabular px-5 py-3 text-center">{{ $t->ambang_lulus !== null ? Format::angka($t->ambang_lulus) : '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.pmb.gelombang.prodi.hapus', [$gelombang, $t]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="ghost" size="sm">×</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-ink-muted">{{ __('pmb.admin.tawaran_kosong') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('admin.pmb.gelombang.prodi', $gelombang) }}"
                class="grid gap-3 border-t border-line px-5 py-4 sm:grid-cols-2">
                @csrf
                <x-field :label="__('pmb.admin.prodi')" name="prodi_id" required
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => trim($p->jenjang?->label().' '.$p->nama)])" />
                <x-field :label="__('pmb.admin.daya_tampung')" name="daya_tampung" type="number" />
                <x-field :label="__('pmb.admin.biaya_formulir')" name="biaya_formulir" type="number" :hint="__('pmb.admin.biaya_formulir_petunjuk')" />
                <x-field :label="__('pmb.admin.ambang_lulus')" name="ambang_lulus" type="number" :hint="__('pmb.admin.ambang_petunjuk')" />
                <fieldset class="sm:col-span-2">
                    <legend class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.admin.dokumen_tambahan') }}</legend>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        @foreach ($jenisBerkas as $kode => $label)
                            <label class="flex items-center gap-1.5 text-[12.5px]">
                                <input type="checkbox" name="dokumen_wajib[]" value="{{ $kode }}" class="accent-navy"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="flex items-end"><x-button type="submit" size="sm">{{ __('pmb.admin.tawaran_simpan') }}</x-button></div>
            </form>
        </x-card>

        {{-- Pengaturan daring --}}
        <x-card :title="__('pmb.admin.pengaturan')">
            <form method="POST" action="{{ route('admin.pmb.gelombang.update', $gelombang) }}" class="flex flex-col gap-3.5">
                @csrf
                @method('PUT')
                <x-field :label="__('pmb.admin.is_daring')" name="is_daring" type="checkbox" :value="$gelombang->is_daring" :hint="__('pmb.admin.is_daring')" />
                <p class="-mt-2 text-[11.5px] text-ink-faint">{{ __('pmb.admin.is_daring_petunjuk') }}</p>
                <fieldset>
                    <legend class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.admin.dokumen_wajib') }}</legend>
                    <div class="flex flex-col gap-1">
                        @foreach ($jenisBerkas as $kode => $label)
                            <label class="flex items-center gap-1.5 text-[12.5px]">
                                <input type="checkbox" name="dokumen_wajib[]" value="{{ $kode }}" class="accent-navy"
                                    @checked(in_array($kode, (array) $gelombang->dokumen_wajib, true))> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-field :label="__('pmb.admin.keterangan')" name="keterangan" type="textarea" :value="$gelombang->keterangan" />
                <x-button type="submit" class="w-full">{{ __('pmb.admin.simpan') }}</x-button>
            </form>
        </x-card>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        {{-- Jadwal seleksi --}}
        <x-card :title="__('pmb.jadwal.judul')" flush>
            <ul>
                @forelse ($gelombang->jadwalSeleksi as $j)
                    <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line/60 px-5 py-3 last:border-b-0">
                        <div>
                            <div class="text-[13px] font-semibold">{{ $j->nama }}</div>
                            <div class="tabular text-[12px] text-ink-muted">
                                {{ Format::tanggalHari($j->mulai) }}, {{ Format::rentangJam($j->mulai, $j->selesai) }}
                                @if ($j->lokasi) · {{ $j->lokasi }} @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.pmb.jadwal.hapus', [$gelombang, $j]) }}">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" size="sm">×</x-button>
                        </form>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-[13px] text-ink-muted">{{ __('pmb.jadwal.kosong') }}</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('admin.pmb.jadwal.store', $gelombang) }}"
                class="grid gap-3 border-t border-line px-5 py-4 sm:grid-cols-2">
                @csrf
                <x-field :label="__('pmb.jadwal.nama')" name="nama" required />
                <x-field :label="__('pmb.jadwal.lokasi')" name="lokasi" />
                <x-field :label="__('pmb.jadwal.mulai')" name="mulai" type="datetime-local" required />
                <x-field :label="__('pmb.jadwal.selesai')" name="selesai" type="datetime-local" />
                <div class="sm:col-span-2"><x-button type="submit" size="sm">{{ __('pmb.jadwal.tambah') }}</x-button></div>
            </form>
        </x-card>

        <div class="flex flex-col gap-5">
            {{-- Impor nilai --}}
            <x-card :title="__('pmb.admin.impor')">
                <p class="mb-3 text-[12px] text-ink-muted">{{ __('pmb.admin.impor_petunjuk') }}</p>
                <p class="mb-3 text-[12px] {{ $otomatis ? 'text-warning' : 'text-ink-faint' }}">
                    {{ $otomatis ? __('pmb.admin.kelulusan_otomatis_aktif') : __('pmb.admin.kelulusan_otomatis_mati') }}
                </p>
                <form method="POST" action="{{ route('admin.pmb.nilai.impor', $gelombang) }}" enctype="multipart/form-data"
                    class="flex flex-col gap-3">
                    @csrf
                    <input type="file" name="berkas" required accept=".csv,text/csv,text/plain" class="text-[12px]">
                    <x-button type="submit" size="sm">{{ __('pmb.admin.impor_tombol') }}</x-button>
                </form>
            </x-card>

            {{-- Pengumuman --}}
            <x-card :title="__('pmb.pengumuman.judul')">
                <p class="mb-3 text-[13px]">
                    @if ($gelombang->diumumkan_at)
                        <x-chip tone="success">{{ __('pmb.pengumuman.sudah', ['waktu' => Format::tanggal($gelombang->diumumkan_at)]) }}</x-chip>
                    @else
                        <x-chip tone="neutral">{{ __('pmb.pengumuman.belum') }}</x-chip>
                    @endif
                </p>
                @if ($belumDiputuskan > 0)
                    <p class="mb-3 text-[12px] text-ink-muted">{{ __('pmb.pengumuman.belum_diputuskan', ['jumlah' => $belumDiputuskan]) }}</p>
                @endif
                <form method="POST" action="{{ route('admin.pmb.umumkan', $gelombang) }}"
                    onsubmit="return confirm(@js(__('pmb.pengumuman.umumkan_konfirmasi')));">
                    @csrf
                    <x-button type="submit" variant="gold" size="sm">{{ __('pmb.pengumuman.umumkan') }}</x-button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
