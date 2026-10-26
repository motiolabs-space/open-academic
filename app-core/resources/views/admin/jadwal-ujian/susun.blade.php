@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', $judul)

@php
    $kelasInput = 'w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy';
    $sesiAwal = old('sesi', $parameter['sesi']);
    $ruangTerpilih = array_map('intval', (array) old('ruang_ids', $parameter['ruang_ids']));
@endphp

@section('content')
    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    <x-card class="mb-5" :title="__('jadwal_ujian.kartu.parameter')">
        <p class="mb-4 text-[12.5px] text-ink-muted">{{ __('jadwal_ujian.petunjuk.susun') }}</p>

        <form method="GET" action="{{ route('admin.jadwal-ujian.pratinjau') }}" class="space-y-4"
            x-data="{ sesi: {{ Js::from($sesiAwal) }} }">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field :label="__('jadwal_ujian.field.term')" name="tahun_akademik_id" :options="$daftarTerm" :value="$parameter['tahun_akademik_id']" required />
                <x-field :label="__('jadwal_ujian.field.jenis')" name="jenis" :options="$jenisPilihan" :value="$parameter['jenis']" required />
                <x-field :label="__('jadwal_ujian.field.prodi')" name="prodi_id" :options="$daftarProdi" :value="$parameter['prodi_id']" />
                <x-field :label="__('jadwal_ujian.field.tanggal_mulai')" name="tanggal_mulai" type="date" :value="$parameter['tanggal_mulai']" required />
                <x-field :label="__('jadwal_ujian.field.tanggal_selesai')" name="tanggal_selesai" type="date" :value="$parameter['tanggal_selesai']" required />
                <div class="space-y-1">
                    <x-field :label="__('jadwal_ujian.field.sertakan_sabtu')" name="sertakan_sabtu" type="checkbox"
                        :value="$parameter['sertakan_sabtu']" :hint="__('jadwal_ujian.field.sertakan_sabtu')" />
                    <x-field :label="__('jadwal_ujian.kolom.pengawas')" name="pengampu_mengawas" type="checkbox"
                        :value="$parameter['pengampu_mengawas']" :hint="__('jadwal_ujian.field.pengampu_mengawas')" />
                </div>
            </div>

            <div>
                <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('jadwal_ujian.field.sesi') }}</div>
                @error('sesi.*.selesai') <p class="mb-2 text-[11.5px] text-danger">{{ $message }}</p> @enderror
                <div class="flex flex-wrap gap-2">
                    <template x-for="(s, i) in sesi" :key="i">
                        <div class="flex items-center gap-1 rounded-control border border-line px-2 py-1">
                            <input type="time" x-bind:name="'sesi[' + i + '][mulai]'" x-model="s.mulai" class="tabular {{ $kelasInput }} w-28"
                                aria-label="{{ __('jadwal_ujian.field.jam_mulai') }}">
                            <span class="text-ink-faint">–</span>
                            <input type="time" x-bind:name="'sesi[' + i + '][selesai]'" x-model="s.selesai" class="tabular {{ $kelasInput }} w-28"
                                aria-label="{{ __('jadwal_ujian.field.jam_selesai') }}">
                            <button type="button" x-on:click="sesi.splice(i, 1)" x-show="sesi.length > 1"
                                class="px-1 text-ink-faint hover:text-danger" aria-label="{{ __('jadwal_ujian.aksi.lepas') }}">✕</button>
                        </div>
                    </template>
                    <x-button type="button" variant="outline" size="sm"
                        x-on:click="sesi.push({ mulai: '', selesai: '' })">{{ __('jadwal_ujian.aksi.tambah_sesi') }}</x-button>
                </div>
            </div>

            <fieldset>
                <legend class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('jadwal_ujian.field.ruang_dipakai') }}</legend>
                <p class="mb-2 text-[11.5px] text-ink-faint">{{ __('jadwal_ujian.petunjuk.ruang_dipakai') }}</p>
                <div class="grid gap-1 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($daftarRuang as $r)
                        <label class="flex items-center gap-2 text-[13px]">
                            <input type="checkbox" name="ruang_ids[]" value="{{ $r->id }}" class="accent-navy" @checked(in_array($r->id, $ruangTerpilih, true))>
                            <span>{{ $r->kode }} <span class="tabular text-ink-faint">({{ $r->kapasitas }})</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="flex justify-end"><x-button type="submit">{{ __('jadwal_ujian.aksi.pratinjau') }}</x-button></div>
        </form>
    </x-card>

    @if ($rencana)
        <x-card flush class="mb-5" :title="__('jadwal_ujian.kartu.usulan')" :meta="Format::bulat($rencana->usulan->count())">
            @if ($rencana->usulan->isEmpty())
                <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('jadwal_ujian.keterangan.usulan_kosong') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.kelas') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.waktu') }}</th>
                                <th class="px-5 py-3 text-center font-semibold">{{ __('jadwal_ujian.kolom.peserta') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.ruang') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.pengawas') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rencana->usulan as $u)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $u->kelas->mataKuliah->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">{{ $u->kelas->mataKuliah->kode }} · {{ $u->kelas->namaLengkap() }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3">
                                        {{ Format::tanggalHari($u->tanggal) }}
                                        <div class="text-[11.5px] text-ink-faint">{{ Format::rentangJam($u->jamMulai, $u->jamSelesai) }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3 text-center">{{ Format::bulat($u->jumlahPeserta) }}</td>
                                    <td class="tabular px-5 py-3">
                                        {{ collect($u->ruang)->map(fn ($r) => $r['ruang']->kode.' ('.$r['kursi'].')')->implode(', ') }}
                                    </td>
                                    <td class="px-5 py-3 text-[12.5px]">
                                        {{ collect($u->pengawas)->map->namaLengkap()->implode(', ') ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('admin.jadwal-ujian.susun.simpan') }}"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3">
                    @csrf
                    @foreach (\Illuminate\Support\Arr::dot($parameter) as $kunci => $nilai)
                        @continue(is_array($nilai) || $nilai === null)
                        @php
                            $segmen = explode('.', $kunci);
                            $nama = array_shift($segmen).implode('', array_map(fn ($s) => '['.$s.']', $segmen));
                        @endphp
                        <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
                    @endforeach
                    <p class="text-[12.5px] text-ink-muted">{{ __('jadwal_ujian.petunjuk.draf') }}</p>
                    <x-button type="submit">{{ __('jadwal_ujian.aksi.simpan_draf') }}</x-button>
                </form>
            @endif
        </x-card>

        @if ($rencana->tidakTerjadwal->isNotEmpty())
            <x-card flush :title="__('jadwal_ujian.kartu.tidak_terjadwal')" :meta="Format::bulat($rencana->tidakTerjadwal->count())">
                <ul class="divide-y divide-line/60 text-[13px]">
                    @foreach ($rencana->tidakTerjadwal as $t)
                        <li class="px-5 py-3">
                            <span class="font-medium">{{ $t['kelas']->mataKuliah->kode }} · {{ $t['kelas']->namaLengkap() }}</span>
                            <div class="text-[12px] text-warning">{{ $t['alasan'] }}</div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endif
@endsection
