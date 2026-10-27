@php use App\Enums\UnsurAngkaKredit; @endphp
@extends('layouts.app')

@section('title', __('angka_kredit.judul'))

@section('content')
    @include('kepegawaian.pesan')

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        @can('update', $dosen)
            <form method="POST" action="{{ route('admin.angka-kredit.nuptk', $dosen) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                @method('PUT')
                <x-field label="NUPTK" name="nuptk" :value="$dosen->nuptk" class="w-56"
                    :hint="$dosen->nidn ? 'NIDN '.$dosen->nidn : null" />
                <x-button type="submit" size="sm" variant="outline">{{ __('kepegawaian.aksi.simpan') }}</x-button>
            </form>
        @else
            <div class="tabular text-[13px] text-ink-muted">
                {{ collect([$dosen->nuptk ? 'NUPTK '.$dosen->nuptk : null, $dosen->nidn ? 'NIDN '.$dosen->nidn : null])
                    ->filter()->implode(' · ') }}
            </div>
        @endcan
        @can('susun', App\Models\Sdm\AngkaKreditDosen::class)
            <form method="POST" action="{{ route('admin.angka-kredit.susun', $dosen) }}">
                @csrf
                <x-button type="submit" size="sm" variant="outline">{{ __('angka_kredit.aksi.susun') }}</x-button>
            </form>
        @endcan
    </div>

    <div class="space-y-5">
        @include('angka-kredit.rekap')
        @include('angka-kredit.buku', ['admin' => true])

        @can('create', App\Models\Sdm\AngkaKreditDosen::class)
            <x-card :title="__('angka_kredit.catat_manual')">
                <form method="POST" action="{{ route('admin.angka-kredit.store', $dosen) }}"
                    x-data="{ sumber: @js(old('sumber', 'konversi_manual')) }" class="grid gap-3 sm:grid-cols-3">
                    @csrf
                    <div class="min-w-0">
                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted" for="ak-sumber">
                            {{ __('angka_kredit.field.sumber') }}
                        </label>
                        <select id="ak-sumber" name="sumber" x-model="sumber"
                            class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                            <option value="konversi_manual">{{ __('angka_kredit.sumber.konversi_manual') }}</option>
                            <option value="penyesuaian">{{ __('angka_kredit.sumber.penyesuaian') }}</option>
                        </select>
                    </div>
                    <x-field :label="__('angka_kredit.field.unsur')" name="unsur" :options="UnsurAngkaKredit::options()" required />

                    <template x-if="sumber === 'konversi_manual'">
                        <div class="contents">
                            <x-field :label="__('angka_kredit.field.semester_kode')" name="semester_kode"
                                :hint="__('angka_kredit.petunjuk.semester_kode')" />
                            <x-field :label="__('angka_kredit.field.sks')" name="sks" type="number"
                                :hint="__('angka_kredit.petunjuk.sks')" />
                        </div>
                    </template>
                    <template x-if="sumber === 'penyesuaian'">
                        <div class="contents">
                            <x-field :label="__('angka_kredit.field.nilai')" name="nilai" type="number"
                                :hint="__('angka_kredit.petunjuk.nilai')" />
                            <x-field :label="__('angka_kredit.field.tanggal')" name="tanggal" type="date" />
                        </div>
                    </template>

                    <x-field :label="__('angka_kredit.field.keterangan')" name="keterangan" class="sm:col-span-3" />
                    <div class="sm:col-span-3">
                        <x-button type="submit" size="sm">{{ __('angka_kredit.aksi.catat') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan
    </div>
@endsection
