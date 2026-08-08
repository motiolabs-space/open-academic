@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Penilaian BKD')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="space-y-5">
        @forelse ($daftar as $l)
            <x-card
                :title="$l->dosen->namaLengkap()"
                :meta="$l->tahunAkademik->nama"
                x-data="{ buka: false }"
            >
                <div class="grid gap-3 sm:grid-cols-4">
                    <x-stat-card label="Pendidikan" :value="Format::angka($l->sks_pendidikan / 100, 2)" meta="SKS" />
                    <x-stat-card label="Penelitian" :value="Format::angka($l->sks_penelitian / 100, 2)" meta="SKS" />
                    <x-stat-card label="Pengabdian" :value="Format::angka($l->sks_pengabdian / 100, 2)" meta="SKS" />
                    <x-stat-card label="Total" :value="Format::angka($l->sksTotal(), 2)" meta="SKS"
                        :feature="true" />
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-chip :tone="$l->status->tone()">{{ $l->status->label() }}</x-chip>

                    @if ($l->kesimpulan)
                        <x-chip :tone="$l->kesimpulan->tone()">{{ $l->kesimpulan->label() }}</x-chip>
                    @endif

                    <x-button href="{{ route('dosen.bkd.unduh', $l) }}" variant="outline" size="sm">
                        Unduh lembar
                    </x-button>

                    @if ($l->status->menungguAsesor())
                        <x-button type="button" size="sm" variant="outline" @click="buka = !buka"
                            x-text="buka ? 'Tutup' : 'Nilai'"></x-button>
                    @endif
                </div>

                @if ($l->catatan_asesor)
                    <p class="mt-3 rounded-control border border-line bg-zebra px-3 py-2 text-[12.5px] leading-relaxed">
                        {{ $l->catatan_asesor }}
                    </p>
                @endif

                @if ($l->status->menungguAsesor())
                    <div x-show="buka" x-cloak class="mt-4 grid gap-4 border-t border-line pt-4 lg:grid-cols-2">
                        <form method="POST" action="{{ route('dosen.bkd.nilai', $l) }}" class="space-y-3">
                            @csrf
                            <x-field label="Kesimpulan" name="kesimpulan" :options="$kesimpulanPilihan" required />
                            <x-field label="Catatan" name="catatan" type="textarea"
                                hint="Wajib bila kesimpulannya bukan &quot;memenuhi&quot; — tanpa alasan, yang dinilai tidak tahu apa yang harus diperbaiki." />
                            <x-button type="submit" size="sm">Simpan penilaian</x-button>
                        </form>

                        <form method="POST" action="{{ route('dosen.bkd.kembalikan', $l) }}" class="space-y-3">
                            @csrf
                            {{-- Dikembalikan, bukan langsung dinyatakan tidak memenuhi:
                                 baris yang salah unsur seharusnya diperbaiki, bukan
                                 menggugurkan satu semester. --}}
                            <x-field label="Kembalikan untuk diperbaiki" name="alasan" type="textarea" required
                                hint="Laporan akan dapat disunting kembali oleh dosen yang bersangkutan." />
                            <x-button type="submit" size="sm" variant="outline">Kembalikan</x-button>
                        </form>
                    </div>
                @endif
            </x-card>
        @empty
            <x-card>
                <x-empty-state
                    title="Tidak ada laporan untuk dinilai"
                    description="Laporan muncul di sini setelah bagian kepegawaian menetapkan Anda sebagai asesornya." />
            </x-card>
        @endforelse
    </div>
@endsection
