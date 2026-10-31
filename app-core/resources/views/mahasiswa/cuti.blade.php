@php
    use App\Enums\JenisCuti;
    use App\Enums\LeaveStatus;
    use App\Enums\StudentStatus;
    use App\Support\Format;
@endphp
@extends('layouts.app')

@section('title', __('swalayan.cuti.judul'))

@section('content')
    @include('mahasiswa._swalayan-pesan')

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <x-card flush :title="__('swalayan.cuti.riwayat')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('swalayan.kolom.semester') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('swalayan.kolom.jenis_alasan') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('swalayan.kolom.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayat as $c)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-cuti="{{ $c->uuid }}">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $c->tahunAkademik->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">
                                        {{ __('swalayan.diajukan_pada', ['tanggal' => Format::tanggal($c->diajukan_at)]) }}
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <x-chip tone="info">{{ JenisCuti::tryFrom((string) $c->jenis)?->label() ?? $c->jenis }}</x-chip>
                                    <p class="mt-1.5 max-w-md text-[12.5px] leading-relaxed text-ink-muted">{{ $c->alasan }}</p>
                                    @if ($c->dokumen_path)
                                        <a href="{{ route('berkas.cuti', $c) }}" class="mt-1 inline-block text-[12px] text-navy underline">
                                            {{ __('swalayan.unduh_berkas') }}
                                        </a>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$c->status->tone()">{{ $c->status->label() }}</x-chip>
                                    @if ($c->diproses_at)
                                        <div class="tabular mt-1 text-[11px] text-ink-faint">{{ Format::tanggal($c->diproses_at) }}</div>
                                    @endif
                                    @if ($c->catatan)
                                        <p class="mt-1 max-w-xs text-[12px] {{ $c->status === LeaveStatus::Ditolak ? 'text-danger' : 'text-ink-muted' }}">
                                            {{ $c->catatan }}
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <x-empty-state :title="__('swalayan.cuti.kosong')" :description="__('swalayan.cuti.kosong_penjelasan')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="__('swalayan.cuti.ajukan')">
            @if ($mahasiswa->status !== StudentStatus::Aktif)
                <p class="text-[12.5px] text-warning-ink">
                    {{ __('swalayan.cuti.bukan_aktif', ['status' => $mahasiswa->status->label()]) }}
                </p>
            @elseif ($menunggu)
                <p class="text-[12.5px] text-ink-muted">{{ __('swalayan.cuti.masih_menunggu') }}</p>
            @elseif ($pilihanSemester->isEmpty())
                <p class="text-[12.5px] text-ink-muted">{{ __('swalayan.cuti.tidak_ada_semester') }}</p>
            @else
                <form method="POST" action="{{ route('mahasiswa.cuti.ajukan') }}" enctype="multipart/form-data"
                    class="flex flex-col gap-3.5">
                    @csrf
                    <x-field :label="__('swalayan.kolom.semester')" name="tahun_akademik_id" required
                        :value="$pilihanSemester->keys()->last()" :options="$pilihanSemester" />
                    <x-field :label="__('swalayan.kolom.jenis')" name="jenis" required :options="$jenisPilihan" />
                    <x-field :label="__('swalayan.kolom.alasan')" name="alasan" type="textarea" required />

                    @include('mahasiswa._unggah-berkas', ['nama' => 'dokumen', 'label' => __('swalayan.cuti.dokumen'), 'wajib' => false])

                    <p class="rounded-card border border-line bg-canvas px-3.5 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
                        {{ __('swalayan.cuti.aturan') }}
                    </p>

                    <x-button type="submit" class="w-full">{{ __('swalayan.cuti.tombol') }}</x-button>
                </form>
            @endif
        </x-card>
    </div>
@endsection
