@extends('layouts.app')

@section('title', 'Dasbor Institusi')

@php
    use App\Enums\StudentStatus;
    use App\Support\Format;

    $persenTerbayar = $tagihanTotal > 0 ? round($tagihanTerbayar / $tagihanTotal * 100) : 0;
@endphp

@section('content')
    <div class="mb-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card
            label="Mahasiswa Aktif"
            :value="Format::bulat($mahasiswaAktif)"
            :meta="'dari '.Format::bulat($mahasiswaTotal).' total terdaftar'"
        />

        <x-stat-card
            feature
            label="Penerimaan Semester Ini"
            :value="Format::rupiah($tagihanTerbayar)"
            :meta="$persenTerbayar.'% dari '.Format::rupiah($tagihanTotal)"
        />

        <x-stat-card
            label="Kelas Berjalan"
            :value="Format::bulat($kelasBerjalan)"
            :meta="Format::bulat($dosenAktif).' dosen aktif · rasio '.$rasioDosenMahasiswa"
        />

        <x-stat-card
            label="KRS Menunggu Persetujuan"
            :value="Format::bulat($krsMenunggu)"
            :meta="$penunggak.' mahasiswa belum lunas'"
        />
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Mahasiswa per Program Studi" flush>
            <div class="divide-y divide-line/60">
                @foreach ($perProdi as $prodi)
                    @php $persen = $mahasiswaTotal > 0 ? round($prodi->jumlah_total / $mahasiswaTotal * 100) : 0; @endphp

                    <div class="px-5 py-3.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[13.5px] font-semibold">{{ $prodi->namaLengkap() }}</span>
                            <span class="tabular text-[13px] text-ink-muted">
                                {{ Format::bulat($prodi->jumlah_aktif) }} aktif
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-line">
                            <div class="h-full rounded-full bg-navy" style="width: {{ $persen }}%"></div>
                        </div>
                        <div class="tabular mt-1.5 text-[11.5px] text-ink-faint">
                            Akreditasi {{ $prodi->akreditasi ?? '—' }} · {{ Format::bulat($prodi->jumlah_total) }} total
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Funnel Penerimaan Mahasiswa Baru" flush>
            <div class="flex flex-col gap-3 px-5 py-4">
                @php $puncak = max(array_column($funnelPmb, 'jumlah')) ?: 1; @endphp

                @foreach ($funnelPmb as $i => $tahap)
                    <div>
                        <div class="flex items-baseline justify-between text-[13px]">
                            <span class="font-semibold">{{ $tahap['label'] }}</span>
                            <span class="tabular text-ink-muted">{{ Format::bulat($tahap['jumlah']) }}</span>
                        </div>
                        <div class="mt-1.5 h-7 overflow-hidden rounded-md bg-line/50">
                            <div
                                class="h-full rounded-md {{ $i === count($funnelPmb) - 1 ? 'bg-gold' : 'bg-navy-soft' }}"
                                style="width: {{ round($tahap['jumlah'] / $puncak * 100) }}%"
                            ></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Status Mahasiswa" flush>
            <div class="flex flex-wrap gap-2 px-5 py-4">
                @foreach ($perStatus as $kode => $jumlah)
                    @php $status = StudentStatus::from($kode); @endphp

                    <x-chip :tone="$status->tone()" dot>
                        {{ $status->label() }} · {{ Format::bulat($jumlah) }}
                    </x-chip>
                @endforeach
            </div>
        </x-card>

        <x-card title="Integrasi" flush>
            <div class="divide-y divide-line/60">
                <div class="flex items-center gap-3 px-5 py-3.5">
                    {{-- Calm heartbeat: institutional reliability, not a flashing alarm. --}}
                    <span class="animate-heartbeat text-[10px] {{ $feederGagal > 0 ? 'text-danger' : 'text-success' }}" aria-hidden="true">●</span>

                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-semibold">Neo Feeder PDDIKTI</div>
                        <div class="tabular text-[11.5px] text-ink-faint">
                            @if ($feederTerakhir)
                                Sinkron terakhir {{ Format::tanggal($feederTerakhir->created_at) }} ·
                                {{ $feederGagal }} entri gagal
                            @else
                                Belum pernah disinkronkan · mode {{ config('feeder.driver') }}
                            @endif
                        </div>
                    </div>

                    <x-chip :tone="config('feeder.enabled') ? 'success' : 'neutral'">
                        {{ config('feeder.enabled') ? 'Aktif' : 'Nonaktif' }}
                    </x-chip>
                </div>

                @foreach ($bridgeConsumers as $consumer)
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-navy font-serif text-[13px] font-semibold text-gold">
                            {{ Str::upper(Str::substr($consumer->nama, 0, 1)) }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] font-semibold">{{ $consumer->nama }}</div>
                            <div class="text-[11.5px] text-ink-faint">
                                {{ count($consumer->scopes) }} scope · Campus Bridge
                            </div>
                        </div>

                        <x-chip tone="info">Terhubung</x-chip>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
@endsection
