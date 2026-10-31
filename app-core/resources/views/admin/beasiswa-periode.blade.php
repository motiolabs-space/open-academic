@php
    use App\Enums\StatusAjuanBeasiswa;
    use App\Support\Format;
@endphp
@extends('layouts.app')

@section('title', $periode->nama)

@section('content')
    @include('mahasiswa._swalayan-pesan')

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <x-card flush :title="__('swalayan.beasiswa.admin.ajuan')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-[13px]">
                    <tbody>
                        @forelse ($daftar as $a)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-ajuan="{{ $a->uuid }}">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $a->mahasiswa->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $a->mahasiswa->nim }} · {{ $a->mahasiswa->prodi->nama }}</div>
                                    <div class="tabular mt-0.5 text-[11.5px] text-ink-faint">
                                        {{ __('swalayan.diajukan_pada', ['tanggal' => Format::tanggal($a->diajukan_at)]) }}
                                        @if ($a->ipk_saat_ajuan !== null)
                                            · {{ __('swalayan.beasiswa.ipk_saat_ajuan', ['ipk' => Format::angka((float) $a->ipk_saat_ajuan, 2)]) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="max-w-md whitespace-pre-line text-[12.5px] leading-relaxed text-ink-muted">{{ $a->motivasi }}</p>
                                    @foreach ($a->berkas ?? [] as $i => $b)
                                        <a href="{{ route('berkas.beasiswa', [$a, $i]) }}" class="mt-1 block text-[12px] text-navy underline">{{ $b['label'] }}</a>
                                    @endforeach
                                </td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$a->status->tone()">{{ $a->status->label() }}</x-chip>
                                    @if ($a->pemutus)
                                        <div class="mt-1 text-[11px] text-ink-faint">{{ $a->pemutus->nama }} · {{ Format::tanggal($a->diputus_at) }}</div>
                                    @endif
                                    @if ($a->catatan_keputusan)
                                        <p class="mt-1 max-w-xs text-[12px] text-ink-muted">{{ $a->catatan_keputusan }}</p>
                                    @endif
                                    @if ($a->penerima)
                                        <div class="mt-1 text-[11px] text-success-ink">{{ __('swalayan.beasiswa.admin.penerima_tercatat') }}</div>
                                    @endif
                                </td>
                                <td class="w-[260px] px-5 py-3">
                                    @if ($bolehPutus && $a->status === StatusAjuanBeasiswa::Diajukan)
                                        <form method="POST" action="{{ route('admin.beasiswa.ajuan.putuskan', $a) }}" class="space-y-2"
                                            data-confirm="{{ __('swalayan.beasiswa.admin.tetapkan_konfirmasi', ['nama' => $a->mahasiswa->nama]) }}">
                                            @csrf
                                            <input type="hidden" name="tetapkan" value="1">
                                            <x-field :label="__('swalayan.beasiswa.admin.semester_selesai')" name="tahun_akademik_selesai_id"
                                                :options="$daftarTerm->mapWithKeys(fn ($t) => [$t->id => $t->nama])" />
                                            <x-field :label="__('swalayan.beasiswa.admin.nomor_sk')" name="nomor_sk" />
                                            <x-field :label="__('swalayan.beasiswa.admin.catatan')" name="catatan" />
                                            <x-button type="submit" size="sm" class="w-full">{{ __('swalayan.beasiswa.admin.tetapkan') }}</x-button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.beasiswa.ajuan.putuskan', $a) }}" class="mt-3 flex items-center gap-1.5">
                                            @csrf
                                            <input type="hidden" name="tetapkan" value="0">
                                            <input type="text" name="catatan" required placeholder="{{ __('swalayan.beasiswa.admin.alasan_tolak') }}"
                                                class="w-full rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                            <x-button type="submit" variant="danger" size="sm">{{ __('swalayan.beasiswa.admin.tolak') }}</x-button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-12">
                                    <x-empty-state :title="__('swalayan.beasiswa.admin.ajuan_kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="space-y-5">
            <x-card :title="__('swalayan.beasiswa.periode_ajuan')">
                <dl class="space-y-2 text-[12.5px]">
                    <div>
                        <dt class="text-ink-faint">{{ __('swalayan.beasiswa.admin.skema') }}</dt>
                        <dd class="font-medium">{{ $periode->beasiswa->nama }} — {{ $periode->beasiswa->cakupan() }}</dd>
                    </div>
                    <div>
                        <dd class="tabular">{{ Format::rentangTanggal($periode->dibuka_mulai, $periode->dibuka_sampai) }}</dd>
                        <dd>{{ __('swalayan.beasiswa.mulai_semester', ['semester' => $periode->tahunAkademik->nama]) }}</dd>
                    </div>
                    @if ($periode->ipk_minimum !== null)
                        <dd class="tabular">{{ __('swalayan.beasiswa.ipk_minimum', ['ipk' => Format::angka((float) $periode->ipk_minimum, 2)]) }}</dd>
                    @endif
                    <dd class="tabular">
                        {{ $kuotaTersisa === null ? __('swalayan.beasiswa.admin.kuota_bebas') : __('swalayan.beasiswa.admin.kuota', ['sisa' => $kuotaTersisa]) }}
                    </dd>
                    @if ($periode->syarat)
                        <div>
                            <dt class="text-ink-faint">{{ __('swalayan.beasiswa.syarat') }}</dt>
                            <dd class="whitespace-pre-line text-ink-muted">{{ $periode->syarat }}</dd>
                        </div>
                    @endif
                    @if ($periode->daftarBerkas() !== [])
                        <div>
                            <dt class="text-ink-faint">{{ __('swalayan.beasiswa.berkas_diminta') }}</dt>
                            <dd>
                                <ul class="list-disc pl-4 text-ink-muted">
                                    @foreach ($periode->daftarBerkas() as $label)
                                        <li>{{ $label }}</li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($bolehPutus && $periode->dibuka())
                    <form method="POST" action="{{ route('admin.beasiswa.periode.tutup', $periode) }}" class="mt-4"
                        data-confirm="{{ __('swalayan.beasiswa.admin.tutup_konfirmasi') }}">
                        @csrf
                        <x-button type="submit" variant="outline" size="sm" class="w-full">{{ __('swalayan.beasiswa.admin.tutup') }}</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection
