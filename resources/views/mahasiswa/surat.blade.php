@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Surat & Dokumen')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <x-card flush title="Riwayat Permohonan">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Jenis</th>
                            <th class="px-5 py-3 font-semibold">Nomor</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $s)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $s->jenis->label() }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">
                                        {{ Format::tanggal($s->diajukan_at) }}
                                    </div>
                                    @if ($s->keperluan)
                                        <p class="mt-0.5 max-w-xs text-[12px] text-ink-muted">{{ $s->keperluan }}</p>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3">{{ $s->nomor ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$s->status->tone()">{{ $s->status->label() }}</x-chip>

                                    @if ($s->berlaku_sampai)
                                        <div class="tabular mt-1 text-[11px] {{ $s->kedaluwarsa() ? 'text-danger' : 'text-ink-faint' }}">
                                            {{ $s->kedaluwarsa() ? 'Kedaluwarsa' : 'Berlaku s.d.' }}
                                            {{ Format::tanggal($s->berlaku_sampai) }}
                                        </div>
                                    @endif

                                    @if ($s->alasan)
                                        <p class="mt-1 max-w-xs text-[12px] text-danger">{{ $s->alasan }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($s->status->dapatDiunduh())
                                        <x-button href="{{ route('mahasiswa.surat.unduh', $s) }}" variant="outline" size="sm">
                                            Unduh PDF
                                        </x-button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state
                                        title="Belum ada permohonan"
                                        description="Ajukan surat yang Anda perlukan lewat panel di samping." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="space-y-5">
            <x-card title="Ajukan Surat">
                @foreach ($tersedia as $baris)
                    @php $j = $baris['jenis']; @endphp
                    <div class="border-b border-line/50 py-3 last:border-b-0">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[13px] font-medium">{{ $j->label() }}</p>
                                @if ($j->swalayan())
                                    {{-- Ditandai terbit langsung, karena itulah perubahan
                                         yang paling terasa: tidak perlu antre untuk
                                         sesuatu yang jawabannya sudah ada di sistem. --}}
                                    <p class="mt-0.5 text-[11.5px] text-success-ink">Terbit langsung</p>
                                @else
                                    <p class="mt-0.5 text-[11.5px] text-ink-faint">Menunggu persetujuan bagian akademik</p>
                                @endif
                            </div>
                        </div>

                        @if ($baris['halangan'] !== null)
                            <p class="mt-1.5 text-[12px] text-warning-ink">
                                Belum dapat diajukan: {{ $baris['halangan'] }}
                            </p>
                        @else
                            <form method="POST" action="{{ route('mahasiswa.surat.ajukan') }}" class="mt-2 space-y-2">
                                @csrf
                                <input type="hidden" name="jenis" value="{{ $j->value }}">

                                @if ($j->perluKeperluan())
                                    <x-field label="Keperluan" name="keperluan" required
                                        hint="Kalimat ini tercetak sebagai isi suratnya." />
                                @endif

                                <x-button type="submit" size="sm" variant="outline">
                                    {{ $j->swalayan() ? 'Terbitkan sekarang' : 'Ajukan' }}
                                </x-button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </x-card>

            <x-card title="Keaslian Dokumen">
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    Setiap surat yang terbit memuat kode QR dan alamat verifikasi. Pihak yang
                    menerima dokumen Anda dapat memeriksanya sendiri tanpa perlu menghubungi
                    kampus.
                </p>
                <div class="mt-3">
                    <x-button href="{{ route('verifikasi.formulir') }}" variant="outline" size="sm">
                        Halaman verifikasi
                    </x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
