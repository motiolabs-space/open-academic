@extends('layouts.app')

@section('title', 'Dasbor Mahasiswa')

@php
    use App\Enums\InvoiceStatus;
    use App\Support\Format;
@endphp

@section('content')
    @if ($tagihan && ! $tagihan->memenuhiSyaratKrs())
        <x-alert tone="warning" class="mb-5">
            <strong>Tagihan semester ini belum memenuhi pembayaran minimum.</strong>
            Sisa {{ Format::rupiah($tagihan->sisa()) }} — jatuh tempo {{ Format::tanggal($tagihan->jatuh_tempo) }}.
            Pengisian KRS terkunci hingga pembayaran minimum terpenuhi.

            <x-slot:action>
                <x-button variant="gold" :href="route('mahasiswa.tagihan')">Bayar Sekarang</x-button>
            </x-slot:action>
        </x-alert>
    @endif

    <div class="mb-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card
            label="IPS Terakhir"
            :value="Format::angka($statusSebelumnya?->ips ?? 0)"
            :meta="$statusSebelumnya?->tahunAkademik?->nama ?? 'Belum ada riwayat'"
        />

        <x-stat-card
            feature
            label="IPK Kumulatif"
            :value="Format::angka($statusSebelumnya?->ipk ?? 0)"
            :meta="Format::bulat($statusSebelumnya?->sks_kumulatif ?? 0).'/'.$mahasiswa->prodi->sks_lulus.' SKS'"
        />

        <x-stat-card
            label="Status Semester"
            :meta="'Semester '.($statusTerm?->semester_ke ?? '—').' · '.$mahasiswa->prodi->nama"
        >
            <x-chip :tone="$mahasiswa->status->tone()" dot>{{ $mahasiswa->status->label() }}</x-chip>
        </x-stat-card>

        <x-stat-card
            :label="'KRS '.($term?->nama ?? '')"
            :meta="$krs ? Format::bulat($krs->total_sks).' dari '.$krs->batas_sks.' SKS' : 'Batas SKS mengikuti IPS terakhir'"
        >
            @if ($krs)
                <x-chip :tone="$krs->status->tone()">{{ $krs->status->label() }}</x-chip>
            @else
                <x-chip tone="neutral">Belum diisi</x-chip>
            @endif
        </x-stat-card>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
        <x-card title="Jadwal Hari Ini" :meta="Format::tanggalHari(now())" flush>
            @forelse ($jadwalHariIni as $jadwal)
                @php
                    $sedangBerlangsung = now()->format('H:i:s') >= $jadwal->jam_mulai
                        && now()->format('H:i:s') <= $jadwal->jam_selesai;
                @endphp

                <div class="flex items-center gap-4 border-b border-line/60 px-5 py-3.5 last:border-b-0">
                    <div class="tabular w-[88px] flex-none text-[12.5px] {{ $sedangBerlangsung ? 'font-semibold text-navy' : 'text-ink-muted' }}">
                        {{ Format::jam($jadwal->jam_mulai) }}
                    </div>

                    <div class="w-[3px] self-stretch rounded-[3px] {{ $sedangBerlangsung ? 'bg-gold' : 'bg-line' }}"></div>

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[13.5px] font-semibold">
                            {{ $jadwal->kelasKuliah->mataKuliah->nama }}
                        </div>
                        <div class="truncate text-xs text-ink-muted">
                            Kelas {{ $jadwal->kelasKuliah->kode }} ·
                            {{ $jadwal->ruang?->namaLengkap() ?? 'Daring' }} ·
                            {{ $jadwal->kelasKuliah->dosenPengampu->first()?->nama ?? '—' }}
                        </div>
                    </div>

                    @if ($sedangBerlangsung)
                        <x-chip tone="gold">BERLANGSUNG</x-chip>
                    @endif
                </div>
            @empty
                <div class="px-5 py-10">
                    <x-empty-state
                        title="Tidak ada kuliah hari ini"
                        description="Jadwal muncul di sini setelah Kartu Rencana Studi Anda disetujui Dosen Wali."
                    />
                </div>
            @endforelse
        </x-card>

        <x-card title="Pengumuman" flush>
            @forelse ($pengumuman as $item)
                <div class="border-b border-line/60 px-5 py-3.5 last:border-b-0">
                    <div class="text-[13px] font-semibold leading-snug">
                        @if ($item->is_pinned)
                            <span class="text-gold" aria-hidden="true">◆</span>
                        @endif
                        {{ $item->judul }}
                    </div>
                    <div class="tabular mt-1 text-[11.5px] text-ink-faint">
                        {{ Format::tanggal($item->published_at) }}
                    </div>
                    <p class="mt-1.5 text-xs leading-relaxed text-ink-muted">{{ $item->ringkasan }}</p>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-[13px] text-ink-faint">Belum ada pengumuman.</div>
            @endforelse
        </x-card>
    </div>
@endsection
