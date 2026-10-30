@extends('layouts.app')

@section('title', __('registrasi_ulang.judul'))

@php
    use App\Support\Format;

    $baru = old('jenis') !== 'aktif_kembali';
@endphp

@section('content')
    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <x-card :title="__('registrasi_ulang.form.judul')">
            <p class="mb-4 text-[13px] text-ink-muted">{{ __('registrasi_ulang.penjelasan') }}</p>

            @if ($jenisBoleh === [])
                <x-alert tone="warning">{{ __('registrasi_ulang.tidak_ada_jenis', ['status' => $mahasiswa->status->label()]) }}</x-alert>
            @else
                <form method="POST" action="{{ route('admin.mahasiswa.registrasi-ulang.store', $mahasiswa) }}" class="space-y-4"
                    data-confirm="{{ __('registrasi_ulang.form.konfirmasi') }}">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field :label="__('registrasi_ulang.form.atribut.jenis')" name="jenis" required :options="$jenisBoleh"
                            :value="array_key_first($jenisBoleh)" />
                        <x-field :label="__('registrasi_ulang.form.atribut.term')" name="term" required
                            :options="$daftarTerm->mapWithKeys(fn ($t) => [$t->uuid => $t->nama.' ('.$t->kode.')'])->all()"
                            :value="$termAktif?->uuid" />
                        <x-field :label="__('registrasi_ulang.form.atribut.tanggal')" name="tanggal" type="date" required
                            :value="now()->toDateString()" />
                    </div>

                    <fieldset class="rounded-card border border-line p-4">
                        <legend class="px-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('registrasi_ulang.form.registrasi_baru') }}</legend>
                        <p class="mb-3 text-[12px] text-ink-faint">{{ __('registrasi_ulang.form.registrasi_baru_hint') }}</p>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-field :label="__('registrasi_ulang.form.atribut.prodi')" name="prodi"
                                :options="['' => '—'] + $daftarProdi->mapWithKeys(fn ($p) => [$p->uuid => $p->nama])->all()" />
                            <x-field :label="__('registrasi_ulang.form.atribut.nim')" name="nim"
                                :hint="__('registrasi_ulang.form.nim_hint')" />
                            <x-field :label="__('registrasi_ulang.form.atribut.email')" name="email" type="email"
                                :hint="__('registrasi_ulang.form.email_hint')" />
                        </div>
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field :label="__('registrasi_ulang.form.atribut.nomor_sk')" name="nomor_sk" />
                        <x-field :label="__('registrasi_ulang.form.atribut.tanggal_sk')" name="tanggal_sk" type="date" />
                    </div>

                    <x-field :label="__('registrasi_ulang.form.atribut.alasan')" name="alasan" type="textarea" required
                        :hint="__('registrasi_ulang.form.alasan_hint')" />

                    <x-button type="submit">{{ __('registrasi_ulang.form.simpan') }}</x-button>
                </form>
            @endif
        </x-card>

        <div class="space-y-5">
            <x-card :title="__('registrasi_ulang.feeder.judul')">
                <ul class="space-y-2.5 text-[12.5px] text-ink-muted">
                    @foreach (['pindah_prodi', 'masuk_kembali', 'aktif_kembali'] as $j)
                        <li><span class="font-semibold text-ink">{{ __('registrasi_ulang.jenis.'.$j) }}.</span> {{ __('registrasi_ulang.feeder.'.$j) }}</li>
                    @endforeach
                </ul>
            </x-card>

            @include('admin.partials.riwayat-registrasi', ['mahasiswa' => $mahasiswa])
        </div>
    </div>
@endsection
