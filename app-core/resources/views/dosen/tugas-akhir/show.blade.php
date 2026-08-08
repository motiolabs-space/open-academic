@extends('layouts.app')

@section('title', $ta->mahasiswa->nama.' — Tugas Akhir')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            <x-card title="Log Bimbingan"
                meta="{{ $ta->jumlahBimbinganDisetujui() }} disetujui dari {{ $ta->bimbingan->count() }} tercatat">
                @if ($minBimbingan > 0)
                    <p class="mb-3 text-[12px] text-ink-muted">
                        Sidang memerlukan {{ $minBimbingan }} bimbingan yang Anda setujui. Log yang belum
                        disetujui tidak dihitung — tanda tangan inilah yang membedakan karya yang dibimbing
                        dari karya yang sekadar dialokasikan.
                    </p>
                @endif

                @forelse ($ta->bimbingan as $b)
                    <div class="border-b border-line/50 py-3 last:border-b-0">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[13px] font-medium">{{ $b->topik }}</p>
                                <p class="tabular mt-0.5 text-[11.5px] text-ink-faint">
                                    {{ $b->tanggal->translatedFormat('j M Y') }} · {{ $b->dosen->namaLengkap() }}
                                </p>
                            </div>
                            <x-chip :tone="$b->disetujui ? 'success' : 'warning'">
                                {{ $b->disetujui ? 'Disetujui' : 'Menunggu' }}
                            </x-chip>
                        </div>

                        @if ($b->uraian)
                            <p class="mt-1.5 whitespace-pre-line text-[12px] text-ink-muted">{{ $b->uraian }}</p>
                        @endif

                        @if ($b->catatan_dosen)
                            <p class="mt-1.5 rounded-control bg-zebra px-3 py-2 text-[12px]">
                                <span class="text-ink-faint">Catatan Anda:</span> {{ $b->catatan_dosen }}
                            </p>
                        @endif

                        {{-- Hanya dosen yang tercatat pada baris ini yang melihat tombolnya;
                             service menolak sisanya, dan rutenya menjawab 403. --}}
                        @if ($b->dosen_id === $dosen->id)
                            @if (! $b->disetujui)
                                <form method="POST" action="{{ route('dosen.tugas-akhir.bimbingan.setujui', $b) }}"
                                    class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf
                                    <x-field label="Catatan (opsional)" name="catatan" class="flex-1" />
                                    <x-button type="submit" size="sm">Setujui</x-button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('dosen.tugas-akhir.bimbingan.cabut', $b) }}"
                                    class="mt-2">
                                    @csrf
                                    <x-button type="submit" variant="outline" size="sm">Cabut persetujuan</x-button>
                                </form>
                            @endif
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-[13px] text-ink-muted">Belum ada catatan bimbingan.</p>
                @endforelse
            </x-card>

            <x-card title="Ujian">
                @forelse ($ta->ujian as $u)
                    @php $kursiSaya = $u->penguji->firstWhere('dosen_id', $dosen->id); @endphp
                    <div class="border-b border-line/50 py-3 last:border-b-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-medium">{{ $u->jenis->label() }}</span>
                                <span class="tabular ml-2 text-[12px] text-ink-muted">
                                    {{ $u->tanggal->translatedFormat('j M Y') }},
                                    {{ substr((string) $u->jam_mulai, 0, 5) }}
                                    @if ($u->ruang) · {{ $u->ruang->kode }} @endif
                                </span>
                            </div>
                            <x-chip :tone="$u->status->tone()">{{ $u->status->label() }}</x-chip>
                        </div>

                        @if ($kursiSaya && $u->status === \App\Enums\StatusUjian::Dijadwalkan)
                            <form method="POST" action="{{ route('dosen.tugas-akhir.penguji.nilai', $kursiSaya) }}"
                                class="mt-3 grid gap-2 rounded-control bg-zebra p-3 sm:grid-cols-[120px_1fr_auto]">
                                @csrf
                                <x-field label="Nilai Anda" name="nilai" type="number" :value="$kursiSaya->nilai" required />
                                <x-field label="Catatan" name="catatan" :value="$kursiSaya->catatan" />
                                <div class="flex items-end"><x-button type="submit" size="sm">Simpan</x-button></div>
                            </form>
                        @elseif ($kursiSaya?->nilai !== null)
                            <p class="tabular mt-2 text-[12px]">Nilai Anda: <span class="font-semibold">{{ $kursiSaya->nilai }}</span></p>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-[13px] text-ink-muted">Belum ada ujian dijadwalkan.</p>
                @endforelse
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card title="Karya">
                <p class="font-serif">{{ $ta->judul }}</p>
                <p class="mt-2 text-[12px] text-ink-muted">
                    {{ $ta->mahasiswa->nama }} · {{ $ta->mahasiswa->nim }}<br>
                    {{ $ta->mahasiswa->prodi->nama }}
                </p>
                <div class="mt-3"><x-chip :tone="$ta->status->tone()">{{ $ta->status->label() }}</x-chip></div>

                @if ($ta->abstrak)
                    <p class="mt-3 whitespace-pre-line text-[12.5px] leading-relaxed">{{ $ta->abstrak }}</p>
                @endif
            </x-card>

            <x-card title="Pembimbing">
                @foreach ($ta->pembimbing as $p)
                    <div class="border-b border-line/50 py-2 last:border-b-0">
                        <p class="text-[13px]">{{ $p->dosen->namaLengkap() }}</p>
                        <p class="text-[11.5px] text-ink-faint">{{ $p->peran->label() }}</p>
                    </div>
                @endforeach
            </x-card>
        </div>
    </div>
@endsection
