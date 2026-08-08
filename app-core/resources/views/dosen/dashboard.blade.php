@extends('layouts.app')

@section('title', 'Dasbor Dosen')

@php use App\Support\Format; @endphp

@section('content')
    <div class="mb-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card
            label="Kelas Diampu"
            :value="Format::bulat($kelas->count())"
            :meta="$term?->nama"
        />

        <x-stat-card
            feature
            label="Menunggu Persetujuan KRS"
            :value="Format::bulat($antreanKrs->count())"
            meta="Sebagai Dosen Wali"
        />

        <x-stat-card
            label="Mahasiswa Diajar"
            :value="Format::bulat($totalMahasiswa)"
            :meta="Format::bulat($jumlahBimbingan).' mahasiswa bimbingan'"
        />

        <x-stat-card
            label="Nilai Belum Final"
            :value="Format::bulat($kelasBelumFinal)"
            :meta="$term?->penilaianDibuka() ? 'Periode input nilai dibuka' : 'Periode input nilai tertutup'"
        />
    </div>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <x-card title="Kelas Diampu" :meta="$term?->nama" flush>
            @forelse ($kelas as $item)
                @php
                    $terlaksana = $item->pertemuan->where('is_terlaksana', true)->count();
                    $total = $item->pertemuan->count();
                @endphp

                <div class="flex flex-wrap items-center gap-4 border-b border-line/60 px-5 py-3.5 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-[13.5px] font-semibold">{{ $item->mataKuliah->nama }}</span>

                            @if ($item->kelasKolaboratif())
                                <x-chip tone="gold">IKU 7</x-chip>
                            @endif
                        </div>
                        <div class="tabular mt-0.5 text-xs text-ink-muted">
                            Kelas {{ $item->kode }} · {{ $item->sks }} SKS ·
                            {{ $item->terisi }}/{{ $item->kuota }} mahasiswa ·
                            {{ $item->jadwal->first()?->rentangWaktu() ?? 'Jadwal belum diatur' }}
                        </div>
                    </div>

                    <div class="tabular text-right text-xs text-ink-faint">
                        <div>Pertemuan {{ $terlaksana }}/{{ $total }}</div>
                        <div class="mt-1">
                            <x-chip :tone="$item->status_nilai === 'final' ? 'success' : 'neutral'">
                                {{ $item->status_nilai === 'final' ? 'Nilai final' : 'Nilai belum final' }}
                            </x-chip>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-10">
                    <x-empty-state
                        title="Belum ada kelas diampu"
                        description="Kelas muncul setelah BAAK menetapkan penugasan mengajar pada semester aktif."
                    />
                </div>
            @endforelse
        </x-card>

        <div class="flex flex-col gap-5">
            <x-card title="Antrean Persetujuan KRS" :meta="$antreanKrs->count().' mahasiswa'" flush>
                @forelse ($antreanKrs->take(6) as $krs)
                    <div class="flex items-center gap-3 border-b border-line/60 px-5 py-3 last:border-b-0">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[13px] font-semibold">{{ $krs->mahasiswa->nama }}</div>
                            <div class="tabular text-[11.5px] text-ink-faint">
                                {{ $krs->mahasiswa->nim }} · {{ $krs->total_sks }}/{{ $krs->batas_sks }} SKS
                                @if ($krs->ips_acuan)
                                    · IPS {{ Format::angka($krs->ips_acuan) }}
                                @endif
                            </div>
                        </div>

                        @if ($krs->total_sks > $krs->batas_sks)
                            <x-chip tone="danger">Lebih SKS</x-chip>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-faint">
                        Tidak ada KRS yang menunggu persetujuan.
                    </div>
                @endforelse
            </x-card>

            <x-card title="Mengajar Hari Ini" :meta="Format::tanggalHari(now())" flush>
                @forelse ($jadwalHariIni as $row)
                    <div class="flex items-center gap-4 border-b border-line/60 px-5 py-3 last:border-b-0">
                        <div class="tabular w-[70px] flex-none text-[12.5px] text-ink-muted">
                            {{ Format::jam($row['jadwal']->jam_mulai) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[13px] font-semibold">{{ $row['kelas']->mataKuliah->nama }}</div>
                            <div class="truncate text-[11.5px] text-ink-faint">
                                Kelas {{ $row['kelas']->kode }} · {{ $row['jadwal']->ruang?->namaLengkap() ?? 'Daring' }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-faint">Tidak ada jadwal mengajar hari ini.</div>
                @endforelse
            </x-card>
        </div>
    </div>
@endsection
