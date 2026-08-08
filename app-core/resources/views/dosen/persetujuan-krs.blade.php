@extends('layouts.app')

@section('title', 'Persetujuan KRS')

@php use App\Support\Format; @endphp

@section('content')
    <div class="grid gap-5 lg:grid-cols-[1.7fr_1fr]">
        <div class="flex flex-col gap-4">
            @forelse ($antrean as $item)
                @php $krs = $item['krs']; @endphp

                <x-card flush x-data="{ tolak: false }">
                    <div class="flex flex-wrap items-start gap-3 border-b border-line px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <div class="text-[15px] font-semibold">{{ $krs->mahasiswa->nama }}</div>
                            <div class="tabular mt-0.5 text-xs text-ink-muted">
                                {{ $krs->mahasiswa->nim }} · {{ $krs->mahasiswa->prodi->namaLengkap() }} ·
                                Semester {{ $krs->semester_ke }}
                            </div>
                        </div>

                        <div class="tabular text-right">
                            <div class="font-serif text-[22px] font-semibold leading-none">
                                {{ $krs->total_sks }}<span class="text-[14px] text-ink-faint">/{{ $krs->batas_sks }}</span>
                            </div>
                            <div class="text-[11px] text-ink-faint">
                                SKS @if ($krs->ips_acuan) · IPS {{ Format::angka($krs->ips_acuan) }} @endif
                            </div>
                        </div>
                    </div>

                    @if ($item['peringatan'])
                        <div class="border-b border-line bg-warning-bg/60 px-5 py-2.5">
                            @foreach ($item['peringatan'] as $pesan)
                                <div class="text-[12.5px] text-warning">⚠ {{ $pesan }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="divide-y divide-line/50">
                        @foreach ($krs->detail as $detail)
                            <div class="flex items-center gap-3 px-5 py-2.5 text-[13px]">
                                <span class="tabular w-20 flex-none text-[11.5px] text-ink-faint">
                                    {{ $detail->kelasKuliah->mataKuliah->kode }}
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{ $detail->kelasKuliah->mataKuliah->nama }}</span>
                                <span class="tabular flex-none text-[11.5px] text-ink-muted">
                                    {{ $detail->kelasKuliah->jadwal->first()?->rentangWaktu() ?? '—' }}
                                </span>
                                <span class="tabular w-12 flex-none text-right text-[11.5px]">{{ $detail->sks }} SKS</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-line px-5 py-4">
                        <form method="POST" action="{{ route('dosen.persetujuan-krs.putuskan', $krs) }}">
                            @csrf

                            {{-- Catatan wajib saat menolak; disembunyikan sampai
                                 dosen memilih menolak agar jalur setuju tetap satu klik. --}}
                            <div x-show="tolak" x-cloak class="mb-3">
                                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                    Catatan untuk mahasiswa
                                </label>
                                <textarea
                                    name="catatan"
                                    rows="2"
                                    placeholder="Contoh: kurangi beban SKS, dahulukan mata kuliah semester 3."
                                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10"
                                ></textarea>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <template x-if="! tolak">
                                    <div class="flex flex-wrap gap-2">
                                        <x-button type="submit" name="disetujui" value="1">Setujui</x-button>
                                        <x-button variant="danger" type="button" @click="tolak = true">Tolak</x-button>
                                    </div>
                                </template>

                                <template x-if="tolak">
                                    <div class="flex flex-wrap gap-2">
                                        <x-button variant="danger" type="submit" name="disetujui" value="0">
                                            Kirim Penolakan
                                        </x-button>
                                        <x-button variant="ghost" type="button" @click="tolak = false">Batal</x-button>
                                    </div>
                                </template>
                            </div>
                        </form>

                        @error('catatan')
                            <p class="mt-2 text-[12px] text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </x-card>
            @empty
                <x-empty-state
                    title="Antrean persetujuan kosong"
                    description="Tidak ada rencana studi mahasiswa bimbingan Anda yang menunggu keputusan saat ini."
                />
            @endforelse
        </div>

        <x-card title="Keputusan Terakhir" flush>
            @forelse ($riwayat as $krs)
                <div class="flex items-center gap-3 border-b border-line/60 px-5 py-3 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[13px] font-semibold">{{ $krs->mahasiswa->nama }}</div>
                        <div class="tabular text-[11.5px] text-ink-faint">
                            {{ $krs->mahasiswa->nim }} · {{ $krs->total_sks }} SKS ·
                            {{ Format::tanggal($krs->disetujui_at ?? $krs->updated_at) }}
                        </div>
                    </div>

                    <x-chip :tone="$krs->status->tone()">{{ $krs->status->label() }}</x-chip>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-[13px] text-ink-faint">
                    Belum ada keputusan pada semester ini.
                </div>
            @endforelse
        </x-card>
    </div>
@endsection
