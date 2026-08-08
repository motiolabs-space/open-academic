@extends('layouts.app')

@section('title', 'Mahasiswa Bimbingan')

@php use App\Support\Format; @endphp

@section('content')
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($daftar as $baris)
            @php $m = $baris['mahasiswa']; @endphp

            <x-card flush @class(['ring-1 ring-danger-line' => $baris['peringatan'] !== []])>
                <div class="flex flex-wrap items-start gap-3 border-b border-line px-5 py-4">
                    <span class="grid h-10 w-10 flex-none place-items-center rounded-full bg-navy font-serif text-[13px] font-semibold text-gold">
                        {{ Str::upper(Str::substr($m->nama, 0, 1)) }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="text-[14.5px] font-semibold">{{ $m->nama }}</div>
                        <div class="tabular text-xs text-ink-muted">
                            {{ $m->nim }} · {{ $m->prodi->nama }}
                        </div>
                    </div>

                    <x-chip :tone="$m->status->tone()" dot>{{ $m->status->label() }}</x-chip>
                </div>

                <div class="grid grid-cols-3 divide-x divide-line border-b border-line text-center">
                    <div class="px-2 py-3">
                        <div class="tabular font-serif text-[19px] font-semibold leading-none">
                            {{ $baris['ips'] === null ? '—' : Format::angka($baris['ips']) }}
                        </div>
                        <div class="mt-1 text-[10px] uppercase tracking-[0.08em] text-ink-faint">IPS</div>
                    </div>

                    <div class="px-2 py-3">
                        <div class="tabular font-serif text-[19px] font-semibold leading-none">
                            {{ $baris['ipk'] === null ? '—' : Format::angka($baris['ipk']) }}
                        </div>
                        <div class="mt-1 text-[10px] uppercase tracking-[0.08em] text-ink-faint">IPK</div>
                    </div>

                    <div class="px-2 py-3">
                        @if ($baris['tren'] === null)
                            <div class="font-serif text-[19px] leading-none text-ink-faint">—</div>
                        @else
                            <div class="tabular font-serif text-[19px] font-semibold leading-none {{ $baris['tren'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $baris['tren'] >= 0 ? '▲' : '▼' }} {{ Format::angka(abs($baris['tren'])) }}
                            </div>
                        @endif
                        <div class="mt-1 text-[10px] uppercase tracking-[0.08em] text-ink-faint">Tren</div>
                    </div>
                </div>

                {{-- Riwayat IPS sebagai bar sederhana: bentuk kurvanya lebih
                     cepat terbaca daripada deretan angka. --}}
                @if ($baris['riwayat']->isNotEmpty())
                    <div class="flex items-end gap-1.5 border-b border-line px-5 py-4" style="height: 72px">
                        @foreach ($baris['riwayat'] as $semester)
                            @php $tinggi = max(6, round((float) $semester->ips / $ipsMaksimum * 44)); @endphp

                            <div class="flex flex-1 flex-col items-center gap-1">
                                <div
                                    class="w-full rounded-t-sm {{ (float) $semester->ips < 2 ? 'bg-danger/70' : 'bg-navy' }}"
                                    style="height: {{ $tinggi }}px"
                                    title="{{ $semester->tahunAkademik->nama }} · IPS {{ Format::angka($semester->ips) }}"
                                ></div>
                                <span class="tabular text-[9px] text-ink-faint">{{ $semester->semester_ke }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($baris['peringatan'])
                    <div class="border-b border-line bg-danger-bg/50 px-5 py-2.5">
                        @foreach ($baris['peringatan'] as $pesan)
                            <div class="text-[12px] text-danger">⚠ {{ $pesan }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-[12px] text-ink-muted">KRS semester ini</span>

                    @if ($baris['krs'])
                        <x-chip :tone="$baris['krs']->status->tone()">
                            {{ $baris['krs']->status->label() }} · {{ $baris['krs']->total_sks }} SKS
                        </x-chip>
                    @else
                        <x-chip tone="neutral">Belum diisi</x-chip>
                    @endif
                </div>
            </x-card>
        @empty
            <div class="md:col-span-2">
                <x-empty-state
                    title="Belum ada mahasiswa bimbingan"
                    description="Perwalian ditetapkan oleh bagian akademik. Hubungi BAAK bila Anda seharusnya menjadi Dosen Wali."
                />
            </div>
        @endforelse
    </div>
@endsection
