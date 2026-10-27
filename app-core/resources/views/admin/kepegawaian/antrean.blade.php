@php
    use App\Enums\JenisRiwayat;
    use App\Support\Format;
    use App\Support\Sdm\FormulirRiwayat;
@endphp
@extends('layouts.app')

@section('title', __('kepegawaian.judul_antrean'))

@section('content')
    @include('kepegawaian.pesan')

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <x-field :label="__('kepegawaian.kolom.jenis')" name="jenis" :options="JenisRiwayat::options()"
            :value="$jenis?->value" class="w-64" />
        <x-button type="submit" size="sm" variant="outline">{{ __('kepegawaian.aksi.tampilkan') }}</x-button>
    </form>

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">{{ __('kepegawaian.kolom.dosen') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('kepegawaian.kolom.jenis') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('kepegawaian.kolom.isian') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('kepegawaian.kolom.diajukan') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('kepegawaian.kolom.aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($antrean as $baris)
                        @php
                            $j = JenisRiwayat::dariModel($baris);
                            $ringkas = FormulirRiwayat::ringkasan($j, $baris);
                        @endphp
                        <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                            <td class="px-5 py-3">
                                <a class="font-medium text-navy hover:underline"
                                    href="{{ route('admin.dosen.kepegawaian', $baris->dosen).'#'.$j->value }}">
                                    {{ $baris->dosen->namaLengkap() }}
                                </a>
                                <div class="text-[11.5px] text-ink-faint">{{ $baris->dosen->prodi?->nama }}</div>
                            </td>
                            <td class="px-5 py-3">{{ $j->label() }}</td>
                            <td class="px-5 py-3">
                                <div>{{ $ringkas['judul'] }}</div>
                                <div class="tabular text-[11.5px] text-ink-faint">{{ $ringkas['rincian'] }}</div>
                            </td>
                            <td class="tabular px-5 py-3 text-ink-muted">{{ Format::tanggal($baris->created_at) }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($baris->dokumen_path)
                                        <x-button size="sm" variant="ghost"
                                            :href="route('berkas.kepegawaian', [$j->value, $baris->uuid])">
                                            {{ __('kepegawaian.aksi.unduh') }}
                                        </x-button>
                                    @endif
                                    @include('kepegawaian.validasi', ['jenis' => $j, 'baris' => $baris])
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-5">
                                <x-empty-state :title="__('kepegawaian.kosong.antrean')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
