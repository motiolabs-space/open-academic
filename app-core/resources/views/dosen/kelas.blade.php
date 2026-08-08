@extends('layouts.app')

@section('title', 'Kelas Diampu')

@section('content')
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($daftar as $baris)
            @php $kelas = $baris['kelas']; @endphp

            <x-card flush>
                <div class="border-b border-line px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="tabular text-[11.5px] font-semibold text-ink-faint">
                                {{ $kelas->mataKuliah->kode }}
                            </div>
                            <h2 class="mt-0.5 text-[15px] font-semibold">{{ $kelas->mataKuliah->nama }}</h2>
                            <div class="tabular mt-1 text-xs text-ink-muted">
                                Kelas {{ $kelas->kode }} · {{ $kelas->sks }} SKS ·
                                {{ $baris['kelas']->jumlah_peserta }}/{{ $kelas->kuota }} mahasiswa
                            </div>
                        </div>

                        @if ($kelas->kelasKolaboratif())
                            <x-chip tone="gold">IKU 7</x-chip>
                        @endif
                    </div>

                    <div class="tabular mt-2 text-xs text-ink-faint">
                        {{ $kelas->jadwal->first()?->rentangWaktu() ?? 'Jadwal belum diatur' }} ·
                        {{ $kelas->jadwal->first()?->ruang?->namaLengkap() ?? 'Daring' }}
                    </div>
                </div>

                <div class="grid grid-cols-3 divide-x divide-line border-b border-line text-center">
                    <div class="px-3 py-3">
                        <div class="tabular font-serif text-[20px] font-semibold leading-none">
                            {{ $baris['terlaksana'] }}<span class="text-[13px] text-ink-faint">/{{ $baris['total_pertemuan'] }}</span>
                        </div>
                        <div class="mt-1 text-[10.5px] uppercase tracking-[0.08em] text-ink-faint">Pertemuan</div>
                    </div>

                    <div class="px-3 py-3">
                        <div class="tabular font-serif text-[20px] font-semibold leading-none {{ $baris['rawan_absensi'] > 0 ? 'text-danger' : '' }}">
                            {{ $baris['rawan_absensi'] }}
                        </div>
                        <div class="mt-1 text-[10.5px] uppercase tracking-[0.08em] text-ink-faint">Rawan absensi</div>
                    </div>

                    <div class="grid place-items-center px-3 py-3">
                        <x-chip :tone="$kelas->status_nilai === 'final' ? 'success' : ($kelas->status_nilai === 'sebagian' ? 'warning' : 'neutral')">
                            {{ ['belum' => 'Nilai belum diisi', 'sebagian' => 'Nilai sebagian', 'final' => 'Nilai final'][$kelas->status_nilai] }}
                        </x-chip>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 px-5 py-4">
                    <x-button variant="outline" :href="route('dosen.nilai.kelas', $kelas)" class="px-4 py-2 text-xs">
                        Input Nilai
                    </x-button>
                    <x-button variant="outline" :href="route('dosen.presensi.kelas', $kelas)" class="px-4 py-2 text-xs">
                        Presensi
                    </x-button>
                </div>
            </x-card>
        @empty
            <div class="md:col-span-2">
                <x-empty-state
                    title="Belum ada kelas diampu"
                    description="Kelas akan muncul di sini setelah bagian akademik menetapkan penugasan mengajar pada semester aktif."
                />
            </div>
        @endforelse
    </div>
@endsection
