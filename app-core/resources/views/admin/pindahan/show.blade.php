@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', $mahasiswa->nama)

@section('aksi')
    <x-button href="{{ route('admin.konversi', ['mahasiswa' => $mahasiswa->uuid]) }}" variant="outline" size="sm">
        ⇄ {{ __('pindahan.aksi.putuskan') }}
    </x-button>
@endsection

@section('content')
    @if (session('kata_sandi_baru'))
        <x-kredensial-baru :data="session('kata_sandi_baru')" />
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
        <div class="space-y-5">
            <x-card :title="$mahasiswa->jenis_daftar?->label() ?? __('pindahan.kolom.jenis_daftar')"
                :meta="($mahasiswa->pt_asal ?? '—').' · '.($mahasiswa->prodi_asal ?? '—')">
                {{-- Batas ditampilkan sebelum transkrip dimasukkan: penilai
                     memetakan satu transkrip utuh, dan angka inilah yang
                     membatasi seluruh isinya. --}}
                <div class="grid gap-3 sm:grid-cols-4">
                    <x-stat-card :label="__('pindahan.statistik.diakui')" :value="$ringkasan->diakui" :meta="__('pindahan.statistik.sks')" />
                    <x-stat-card :label="__('pindahan.statistik.batas')" :value="$ringkasan->batas" :meta="__('pindahan.statistik.sks')" />
                    <x-stat-card :label="__('pindahan.statistik.sisa')" :value="$ringkasan->sisa" :meta="__('pindahan.statistik.sks')"
                        :feature="$ringkasan->sisa > 0" />
                    <x-stat-card :label="__('pindahan.statistik.menunggu')" :value="$ringkasan->menunggu" :meta="__('pindahan.statistik.sks')" />
                </div>

                @if ($ringkasan->usulanMelebihiSisa())
                    <div class="mt-3">
                        <x-alert tone="warning">
                            {{ __('pindahan.keterangan.usulan_melebihi', ['menunggu' => $ringkasan->menunggu, 'sisa' => $ringkasan->sisa]) }}
                        </x-alert>
                    </div>
                @endif

                <dl class="mt-4 grid gap-4 border-t border-line pt-4 text-[13px] sm:grid-cols-3">
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pindahan.statistik.semester_awal') }}</dt>
                        <dd class="tabular text-[20px] font-semibold">{{ $ringkasan->semesterAwal }}</dd>
                        <p class="text-[11.5px] text-ink-faint">
                            {{ __('pindahan.keterangan.semester_awal', ['per' => config('academic.pindahan.sks_per_semester')]) }}
                        </p>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pindahan.statistik.batas_krs') }}</dt>
                        <dd class="tabular text-[20px] font-semibold">
                            {{ $ringkasan->batasKrsAwal }}
                            <span class="text-[12px] font-normal text-ink-muted">{{ __('pindahan.statistik.sks') }}</span>
                        </dd>
                        <p class="text-[11.5px] text-ink-faint">
                            {{ $ringkasan->ipsKonversi === null
                                ? __('pindahan.keterangan.batas_krs_bawaan')
                                : __('pindahan.keterangan.batas_krs_ips', ['ips' => Format::angka($ringkasan->ipsKonversi)]) }}
                        </p>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pindahan.statistik.semester_asal') }}</dt>
                        <dd class="tabular text-[20px] font-semibold">{{ $ringkasan->semesterDiAsal }}</dd>
                        <p class="text-[11.5px] text-ink-faint">
                            {{ config('academic.pindahan.masa_studi_dari_asal')
                                ? __('pindahan.keterangan.masa_studi_dihitung')
                                : __('pindahan.keterangan.masa_studi_tidak') }}
                        </p>
                    </div>
                </dl>
            </x-card>

            @can('update', $mahasiswa)
                <x-card :title="__('pindahan.kartu.transkrip')">
                    @if ($mataKuliah->isEmpty())
                        <p class="text-[13px] text-ink-muted">{{ __('pindahan.keterangan.mata_kuliah_kosong') }}</p>
                    @else
                        <p class="mb-3 text-[12.5px] text-ink-muted">{{ __('pindahan.keterangan.baris_transkrip') }}</p>

                        <form method="POST" action="{{ route('admin.pindahan.transkrip', $mahasiswa) }}"
                            enctype="multipart/form-data" class="space-y-3"
                            x-data="{ baris: {{ Js::from(old('baris', [['mata_kuliah_id' => '', 'asal_nama' => '', 'asal_sks' => '', 'asal_nilai' => '']])) }} }">
                            @csrf

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[760px] text-[13px]">
                                    <thead>
                                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                            <th class="py-2 pr-2 font-semibold">{{ __('pindahan.kolom.nama_asal') }}</th>
                                            <th class="w-20 py-2 pr-2 font-semibold">{{ __('pindahan.kolom.sks_asal') }}</th>
                                            <th class="w-20 py-2 pr-2 font-semibold">{{ __('pindahan.kolom.nilai_asal') }}</th>
                                            <th class="py-2 pr-2 font-semibold">{{ __('pindahan.kolom.matkul_tujuan') }}</th>
                                            <th class="w-10 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(b, i) in baris" :key="i">
                                            <tr class="border-b border-line/50 align-top last:border-b-0">
                                                <td class="py-2 pr-2">
                                                    <input type="text" x-bind:name="'baris[' + i + '][asal_nama]'" x-model="b.asal_nama"
                                                        class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy"
                                                        aria-label="{{ __('pindahan.kolom.nama_asal') }}">
                                                </td>
                                                <td class="py-2 pr-2">
                                                    <input type="number" min="1" max="60" x-bind:name="'baris[' + i + '][asal_sks]'" x-model="b.asal_sks"
                                                        class="tabular w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy"
                                                        aria-label="{{ __('pindahan.kolom.sks_asal') }}">
                                                </td>
                                                <td class="py-2 pr-2">
                                                    <input type="text" maxlength="8" x-bind:name="'baris[' + i + '][asal_nilai]'" x-model="b.asal_nilai"
                                                        class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy"
                                                        aria-label="{{ __('pindahan.kolom.nilai_asal') }}">
                                                </td>
                                                <td class="py-2 pr-2">
                                                    <select x-bind:name="'baris[' + i + '][mata_kuliah_id]'" x-model="b.mata_kuliah_id"
                                                        class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy"
                                                        aria-label="{{ __('pindahan.kolom.matkul_tujuan') }}">
                                                        <option value="">—</option>
                                                        @foreach ($mataKuliah as $mk)
                                                            <option value="{{ $mk->id }}">{{ $mk->kode }} — {{ $mk->nama }} ({{ $mk->sks }} SKS)</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="py-2 text-right">
                                                    <button type="button" x-on:click="baris.splice(i, 1)" x-show="baris.length > 1"
                                                        class="rounded-control px-2 py-2 text-ink-faint hover:text-danger"
                                                        aria-label="{{ __('pindahan.aksi.hapus_baris') }}">✕</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <x-button type="button" variant="outline" size="sm"
                                x-on:click="baris.push({ mata_kuliah_id: '', asal_nama: '', asal_sks: '', asal_nilai: '' })">
                                {{ __('pindahan.aksi.tambah_baris') }}
                            </x-button>

                            <div class="grid gap-3 border-t border-line pt-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <x-field :label="__('pindahan.kolom.bukti')" name="berkas" type="file" :hint="__('pindahan.petunjuk.bukti')" />
                                <div class="flex items-end">
                                    <x-button type="submit">{{ __('pindahan.aksi.ajukan_transkrip') }}</x-button>
                                </div>
                            </div>
                        </form>
                    @endif
                </x-card>
            @endcan

            <x-card flush :title="__('pindahan.kartu.konversi')">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.nama_asal') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.matkul_tujuan') }}</th>
                                <th class="px-5 py-3 text-center font-semibold">{{ __('pindahan.kolom.sks_diakui') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($konversi as $k)
                                <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-3">
                                        {{ $k->asal_nama }}
                                        <div class="text-[11.5px] text-ink-faint">
                                            @if ($k->asal_sks) {{ $k->asal_sks }} SKS @endif
                                            @if ($k->asal_nilai) · {{ $k->asal_nilai }} @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $k->mataKuliah->kode }}</div>
                                        <div class="text-[12px] text-ink-muted">{{ $k->mataKuliah->nama }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3 text-center">
                                        {{ $k->sks_diakui }}
                                        @if ($k->nilai_huruf)
                                            <div class="text-[11px] text-ink-faint">{{ $k->nilai_huruf }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3"><x-chip :tone="$k->status->tone()">{{ $k->status->label() }}</x-chip></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-4 text-[13px] text-ink-muted">{{ __('pindahan.keterangan.konversi_kosong') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card :title="__('pindahan.kartu.kesiapan_feeder')">
                @if ($ringkasan->isuFeeder === [])
                    <p class="text-[13px] text-success">✓ {{ __('pindahan.keterangan.siap_feeder') }}</p>
                @else
                    <ul class="space-y-2 text-[12.5px]">
                        @foreach ($ringkasan->isuFeeder as $isu)
                            <li class="flex gap-2">
                                <x-chip :tone="$isu['severity'] === 'error' ? 'danger' : 'warning'">{{ $isu['severity'] === 'error' ? '✕' : '!' }}</x-chip>
                                <span class="text-ink-muted">{{ $isu['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @can('update', $mahasiswa)
                <x-card :title="__('pindahan.kartu.ubah_asal')">
                    @if (filled($mahasiswa->feeder_registrasi_id))
                        <p class="text-[13px] text-ink-muted">{{ __('pindahan.keterangan.terkunci_feeder') }}</p>
                    @else
                        <form method="POST" action="{{ route('admin.pindahan.update', $mahasiswa) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <x-field :label="__('pindahan.kolom.jenis_daftar')" name="jenis_daftar"
                                :options="\App\Enums\JenisDaftar::optionsIntake()" :value="$mahasiswa->jenis_daftar?->value" required />
                            <x-field :label="__('pindahan.kolom.pt_asal')" name="pt_asal" :value="$mahasiswa->pt_asal" />
                            <x-field :label="__('pindahan.kolom.prodi_asal')" name="prodi_asal" :value="$mahasiswa->prodi_asal" />
                            <x-field :label="__('pindahan.kolom.term_masuk_asal')" name="term_masuk_asal"
                                :value="$mahasiswa->term_masuk_asal" placeholder="20231" />
                            <x-button type="submit" variant="outline" class="w-full">{{ __('pindahan.aksi.simpan_asal') }}</x-button>
                        </form>
                    @endif
                </x-card>
            @endcan
        </div>
    </div>
@endsection
