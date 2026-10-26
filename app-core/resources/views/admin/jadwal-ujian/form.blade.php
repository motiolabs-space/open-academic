@extends('layouts.app')

@section('title', $judul)

@php
    $ruangAwal = old('ruang', $jadwal?->ruang->map(fn ($r) => ['ruang_id' => (string) $r->ruang_id, 'kursi' => (string) $r->kursi])->values()->all()
        ?: [['ruang_id' => '', 'kursi' => '']]);

    $pengawasAwal = old('pengawas', $jadwal?->pengawas->map(fn ($p) => [
        'orang' => $p->kunci(),
        'ruang_id' => (string) ($jadwal->ruang->firstWhere('id', $p->jadwal_ujian_ruang_id)?->ruang_id ?? ''),
    ])->values()->all() ?: [['orang' => '', 'ruang_id' => '']]);

    $kelasInput = 'w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy';
@endphp

@section('content')
    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($jadwal?->sudahTerbit())
        <div class="mb-5"><x-alert tone="warning">{{ __('jadwal_ujian.petunjuk.terbit_ubah') }}</x-alert></div>
    @endif

    <form method="POST"
        action="{{ $jadwal ? route('admin.jadwal-ujian.update', $jadwal) : route('admin.jadwal-ujian.store') }}"
        x-data="{ ruang: {{ Js::from($ruangAwal) }}, pengawas: {{ Js::from($pengawasAwal) }} }"
        class="space-y-5">
        @csrf
        @if ($jadwal)
            @method('PUT')
        @endif

        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($jadwal)
                    <input type="hidden" name="jenis" value="{{ $jadwal->jenis->value }}">
                    <div>
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('jadwal_ujian.field.kelas') }}</div>
                        <div class="py-2 text-[13px] font-medium">{{ $jadwal->kelasKuliah->mataKuliah->kode }} · {{ $jadwal->kelasKuliah->namaLengkap() }}</div>
                    </div>
                    <div>
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('jadwal_ujian.field.jenis') }}</div>
                        <div class="py-2 text-[13px]">{{ $jadwal->jenis->label() }}</div>
                    </div>
                @else
                    <x-field :label="__('jadwal_ujian.field.kelas')" name="kelas_kuliah_id" :options="$daftarKelas" :value="$kelasTerpilih" required />
                    <x-field :label="__('jadwal_ujian.field.jenis')" name="jenis" :options="$jenisPilihan" required />
                @endif

                <x-field :label="__('jadwal_ujian.field.tanggal')" name="tanggal" type="date" :value="$jadwal?->tanggal" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('jadwal_ujian.field.jam_mulai')" name="jam_mulai" type="time"
                        :value="$jadwal ? \App\Models\Akademik\JadwalUjian::jam($jadwal->jam_mulai) : null" required />
                    <x-field :label="__('jadwal_ujian.field.jam_selesai')" name="jam_selesai" type="time"
                        :value="$jadwal ? \App\Models\Akademik\JadwalUjian::jam($jadwal->jam_selesai) : null" required />
                </div>
            </div>
        </x-card>

        <x-card :title="__('jadwal_ujian.field.ruang')" :meta="__('jadwal_ujian.petunjuk.kursi')">
            @error('ruang.*.ruang_id') <p class="mb-2 text-[11.5px] text-danger">{{ $message }}</p> @enderror
            <div class="space-y-2">
                <template x-for="(r, i) in ruang" :key="i">
                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem_auto]">
                        <select x-bind:name="'ruang[' + i + '][ruang_id]'" x-model="r.ruang_id" class="{{ $kelasInput }}"
                            aria-label="{{ __('jadwal_ujian.field.ruang') }}">
                            <option value="">—</option>
                            @foreach ($daftarRuang as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" x-bind:name="'ruang[' + i + '][kursi]'" x-model="r.kursi"
                            class="tabular {{ $kelasInput }}" placeholder="{{ __('jadwal_ujian.field.kursi') }}"
                            aria-label="{{ __('jadwal_ujian.field.kursi') }}">
                        <button type="button" x-on:click="ruang.splice(i, 1)" x-show="ruang.length > 1"
                            class="rounded-control px-2 py-2 text-ink-faint hover:text-danger"
                            aria-label="{{ __('jadwal_ujian.aksi.lepas') }}">✕</button>
                    </div>
                </template>
            </div>
            <x-button type="button" variant="outline" size="sm" class="mt-3"
                x-on:click="ruang.push({ ruang_id: '', kursi: '' })">{{ __('jadwal_ujian.aksi.tambah_ruang') }}</x-button>
        </x-card>

        <x-card :title="__('jadwal_ujian.field.pengawas')">
            @error('pengawas.*.orang') <p class="mb-2 text-[11.5px] text-danger">{{ $message }}</p> @enderror
            <div class="space-y-2">
                <template x-for="(p, i) in pengawas" :key="i">
                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_14rem_auto]">
                        <select x-bind:name="'pengawas[' + i + '][orang]'" x-model="p.orang" class="{{ $kelasInput }}"
                            aria-label="{{ __('jadwal_ujian.field.pengawas') }}">
                            <option value="">—</option>
                            @foreach ($daftarPengawas as $kunci => $nama)
                                <option value="{{ $kunci }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                        <select x-bind:name="'pengawas[' + i + '][ruang_id]'" x-model="p.ruang_id" class="{{ $kelasInput }}"
                            aria-label="{{ __('jadwal_ujian.field.ruang_pengawas') }}">
                            <option value="">{{ __('jadwal_ujian.field.ruang_pengawas') }}: —</option>
                            @foreach ($daftarRuang as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" x-on:click="pengawas.splice(i, 1)" x-show="pengawas.length > 1"
                            class="rounded-control px-2 py-2 text-ink-faint hover:text-danger"
                            aria-label="{{ __('jadwal_ujian.aksi.lepas') }}">✕</button>
                    </div>
                </template>
            </div>
            <x-button type="button" variant="outline" size="sm" class="mt-3"
                x-on:click="pengawas.push({ orang: '', ruang_id: '' })">{{ __('jadwal_ujian.aksi.tambah_pengawas') }}</x-button>
        </x-card>

        <x-card>
            <x-field :label="__('jadwal_ujian.field.catatan')" name="catatan" type="textarea" :value="$jadwal?->catatan" />
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-[12.5px] text-ink-muted">{{ __('jadwal_ujian.petunjuk.draf') }}</p>
                <x-button type="submit">{{ __('jadwal_ujian.aksi.simpan') }}</x-button>
            </div>
        </x-card>
    </form>
@endsection
