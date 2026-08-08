@extends('layouts.app')

@section('title', 'Jadwal Kuliah')

@php
    use App\Models\Akademik\JadwalKuliah;
    use App\Support\Format;
@endphp

@section('content')
    <div class="mb-5 grid gap-3.5 sm:grid-cols-3">
        <x-stat-card label="Sesi per Pekan" :value="Format::bulat($perHari->flatten()->count())" />
        <x-stat-card feature label="Total SKS" :value="Format::bulat($totalSks)" meta="Dari rencana studi disetujui" />
        <x-stat-card label="Hari Ini" :value="JadwalKuliah::HARI[$hariIni] ?? '—'"
            :meta="Format::bulat($perHari[$hariIni]?->count() ?? 0).' sesi terjadwal'" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($perHari as $hari => $sesi)
            <x-card flush @class(['ring-2 ring-navy' => $hari === $hariIni])>
                <div @class([
                    'flex items-baseline justify-between border-b px-5 py-3',
                    'border-navy bg-navy text-canvas' => $hari === $hariIni,
                    'border-line' => $hari !== $hariIni,
                ])>
                    <span class="text-[14px] font-semibold">{{ JadwalKuliah::HARI[$hari] }}</span>
                    <span class="tabular text-[11.5px] {{ $hari === $hariIni ? 'text-gold' : 'text-ink-faint' }}">
                        {{ $sesi->count() }} sesi
                    </span>
                </div>

                @forelse ($sesi as $item)
                    @php
                        $berlangsung = $hari === $hariIni
                            && now()->format('H:i:s') >= $item->jam_mulai
                            && now()->format('H:i:s') <= $item->jam_selesai;
                    @endphp

                    <div class="flex gap-3 border-b border-line/50 px-5 py-3 last:border-b-0">
                        <div class="tabular w-14 flex-none text-[12px] {{ $berlangsung ? 'font-semibold text-navy' : 'text-ink-muted' }}">
                            {{ Format::jam($item->jam_mulai) }}
                        </div>

                        <div class="w-[3px] flex-none rounded-[3px] {{ $berlangsung ? 'bg-gold' : 'bg-line' }}"></div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-[13px] font-semibold">{{ $item->kelasKuliah->mataKuliah->nama }}</span>
                                @if ($berlangsung)
                                    <x-chip tone="gold">BERLANGSUNG</x-chip>
                                @endif
                                @unless ($item->ruang)
                                    <x-chip tone="info">DARING</x-chip>
                                @endunless
                            </div>
                            <div class="tabular mt-0.5 text-[11.5px] text-ink-faint">
                                {{ Format::rentangJam($item->jam_mulai, $item->jam_selesai) }} ·
                                {{ $item->ruang?->kode ?? 'Daring' }} ·
                                Kelas {{ $item->kelasKuliah->kode }}
                            </div>
                            <div class="truncate text-[11.5px] text-ink-muted">
                                {{ $item->kelasKuliah->dosenPengampu->first()?->nama ?? '—' }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-6 text-center text-[12.5px] text-ink-faint">Tidak ada kuliah.</div>
                @endforelse
            </x-card>
        @endforeach
    </div>

    @if ($perHari->flatten()->isEmpty())
        <div class="mt-5">
            <x-empty-state
                title="Jadwal belum tersedia"
                description="Jadwal kuliah muncul setelah Kartu Rencana Studi Anda disetujui Dosen Wali."
            >
                <x-button :href="route('mahasiswa.krs')">Buka Rencana Studi</x-button>
            </x-empty-state>
        </div>
    @endif
@endsection
