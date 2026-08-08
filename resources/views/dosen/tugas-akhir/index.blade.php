@extends('layouts.app')

@section('title', 'Tugas Akhir')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    <div class="space-y-5">
        <x-card title="Bimbingan Berjalan" :meta="$dibimbing->count().' mahasiswa'">
            @forelse ($dibimbing as $ta)
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line/50 py-3 last:border-b-0">
                    <div class="min-w-0">
                        <p class="font-medium">{{ $ta->mahasiswa->nama }}
                            <span class="tabular ml-1 text-[11.5px] text-ink-faint">{{ $ta->mahasiswa->nim }}</span>
                        </p>
                        <p class="mt-0.5 max-w-lg text-[12px] text-ink-muted">{{ $ta->judul }}</p>
                        <p class="mt-1 text-[11.5px] text-ink-faint">
                            {{ $ta->bimbingan_disetujui_count }} bimbingan disetujui
                            @if ($minBimbingan > 0) dari {{ $minBimbingan }} yang diperlukan untuk sidang @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($ta->menunggu_persetujuan_count > 0)
                            {{-- Log yang menunggu tanda tangan. Tanpa penanda ini, satu-satunya
                                 cara mahasiswa mengetahuinya adalah bertanya langsung. --}}
                            <x-chip tone="warning">{{ $ta->menunggu_persetujuan_count }} menunggu persetujuan</x-chip>
                        @endif
                        <x-button href="{{ route('dosen.tugas-akhir.show', $ta) }}" variant="outline" size="sm">
                            Buka
                        </x-button>
                    </div>
                </div>
            @empty
                <x-empty-state title="Belum ada bimbingan"
                    description="Tugas akhir yang program studi tetapkan kepada Anda akan muncul di sini." />
            @endforelse
        </x-card>

        <x-card title="Ujian yang Anda Uji" :meta="$menguji->count().' terjadwal'">
            @forelse ($menguji as $kursi)
                @php $u = $kursi->ujian; @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line/50 py-3 last:border-b-0">
                    <div class="min-w-0">
                        <p class="font-medium">{{ $u->tugasAkhir->mahasiswa->nama }}</p>
                        <p class="mt-0.5 max-w-lg text-[12px] text-ink-muted">{{ $u->tugasAkhir->judul }}</p>
                        <p class="tabular mt-1 text-[11.5px] text-ink-faint">
                            {{ $u->jenis->label() }} · {{ $u->tanggal->translatedFormat('j M Y') }},
                            {{ substr((string) $u->jam_mulai, 0, 5) }}
                            @if ($u->ruang) · {{ $u->ruang->kode }} @endif
                            · {{ $kursi->peran->label() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($kursi->nilai !== null)
                            <x-chip tone="success">Nilai {{ $kursi->nilai }}</x-chip>
                        @else
                            <x-chip tone="warning">Belum dinilai</x-chip>
                        @endif
                        <x-button href="{{ route('dosen.tugas-akhir.show', $u->tugasAkhir) }}" variant="outline" size="sm">
                            Buka
                        </x-button>
                    </div>
                </div>
            @empty
                <x-empty-state title="Tidak ada ujian terjadwal"
                    description="Undangan menguji akan muncul di sini beserta tanggal dan ruangnya." />
            @endforelse
        </x-card>
    </div>
@endsection
