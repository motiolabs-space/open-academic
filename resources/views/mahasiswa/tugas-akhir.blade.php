@extends('layouts.app')

@section('title', $judul)

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($ta === null || in_array($ta->status, [\App\Enums\TugasAkhirStatus::Ditolak, \App\Enums\TugasAkhirStatus::Dibatalkan], true))
        @if ($ta?->catatan)
            <div class="mb-5">
                <x-alert tone="warning">
                    <p class="font-semibold">{{ $ta->status->label() }}: {{ $ta->judul }}</p>
                    <p class="mt-1">{{ $ta->catatan }}</p>
                </x-alert>
            </div>
        @endif

        <x-card title="Ajukan Judul" class="max-w-2xl">
            @if ($minSks > 0 && $sksSaatIni < $minSks)
                <x-alert tone="warning">
                    Pengajuan {{ strtolower($judul) }} memerlukan minimal {{ $minSks }} SKS.
                    Saat ini tercatat {{ $sksSaatIni }} SKS.
                </x-alert>
            @else
                <form method="POST" action="{{ route('mahasiswa.tugas-akhir.ajukan') }}" class="space-y-3">
                    @csrf
                    <x-field label="Judul" name="judul" required
                        hint="Judul ini yang nanti tercetak pada ijazah bila karya Anda lulus sidang." />
                    <x-field label="Bidang kajian" name="bidang_kajian" />
                    <x-field label="Ringkasan rencana" name="abstrak" type="textarea" />
                    <x-button type="submit">Ajukan judul</x-button>
                </form>
            @endif
        </x-card>
    @else
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-5">
                <x-card title="Judul">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="font-serif text-lg">{{ $ta->judul }}</p>
                        <x-chip :tone="$ta->status->tone()">{{ $ta->status->label() }}</x-chip>
                    </div>

                    @if ($ta->status === \App\Enums\TugasAkhirStatus::Diajukan)
                        <p class="mt-3 text-[13px] text-ink-muted">
                            Judul sedang menunggu keputusan program studi.
                        </p>
                    @elseif ($ta->status === \App\Enums\TugasAkhirStatus::Disetujui)
                        <p class="mt-3 text-[13px] text-ink-muted">
                            Judul sudah disetujui. Bimbingan dapat dimulai setelah program studi
                            menetapkan pembimbing.
                        </p>
                    @endif

                    @if ($ta->menungguRevisi())
                        <div class="mt-3">
                            <x-alert tone="warning">
                                Anda lulus sidang dengan revisi. Karya belum dinyatakan selesai sampai
                                revisi diterima pembimbing.
                            </x-alert>
                        </div>
                    @endif
                </x-card>

                @if ($ta->status === \App\Enums\TugasAkhirStatus::Dibimbing)
                    <x-card title="Catat Bimbingan">
                        <form method="POST" action="{{ route('mahasiswa.tugas-akhir.bimbingan') }}"
                            class="grid gap-3 sm:grid-cols-2">
                            @csrf
                            <x-field label="Pembimbing" name="dosen_id" required
                                :options="$pilihanPembimbing->mapWithKeys(fn ($d) => [$d->id => $d->namaLengkap()])" />
                            <x-field label="Tanggal" name="tanggal" type="date" required />
                            <x-field label="Topik" name="topik" class="sm:col-span-2" required />
                            <x-field label="Uraian" name="uraian" type="textarea" class="sm:col-span-2" />
                            <div class="sm:col-span-2">
                                <x-button type="submit">Catat bimbingan</x-button>
                                <p class="mt-2 text-[11.5px] text-ink-faint">
                                    Catatan baru dihitung setelah pembimbing menyetujuinya.
                                </p>
                            </div>
                        </form>
                    </x-card>
                @endif

                <x-card title="Riwayat Bimbingan"
                    meta="{{ $ta->jumlahBimbinganDisetujui() }} disetujui@if ($minBimbingan > 0) dari {{ $minBimbingan }} untuk sidang @endif">
                    @forelse ($ta->bimbingan as $b)
                        <div class="flex items-start gap-3 border-b border-line/50 py-2.5 last:border-b-0">
                            <div class="tabular w-24 shrink-0 text-[11.5px] text-ink-faint">
                                {{ $b->tanggal->translatedFormat('j M Y') }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium">{{ $b->topik }}</p>
                                @if ($b->uraian)
                                    <p class="mt-0.5 text-[12px] text-ink-muted">{{ $b->uraian }}</p>
                                @endif
                                @if ($b->catatan_dosen)
                                    <p class="mt-1 rounded-control bg-zebra px-2.5 py-1.5 text-[12px]">
                                        <span class="text-ink-faint">{{ $b->dosen->namaLengkap() }}:</span>
                                        {{ $b->catatan_dosen }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <x-chip :tone="$b->disetujui ? 'success' : 'warning'">
                                    {{ $b->disetujui ? 'Disetujui' : 'Menunggu' }}
                                </x-chip>
                                @unless ($b->disetujui)
                                    <form method="POST" action="{{ route('mahasiswa.tugas-akhir.bimbingan.hapus', $b) }}">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="outline" size="sm">Hapus</x-button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-[13px] text-ink-muted">Belum ada catatan bimbingan.</p>
                    @endforelse
                </x-card>
            </div>

            <div class="space-y-5">
                <x-card title="Pembimbing">
                    @forelse ($ta->pembimbing as $p)
                        <div class="border-b border-line/50 py-2 last:border-b-0">
                            <p class="text-[13px]">{{ $p->dosen->namaLengkap() }}</p>
                            <p class="text-[11.5px] text-ink-faint">{{ $p->peran->label() }}</p>
                        </div>
                    @empty
                        <p class="py-4 text-center text-[13px] text-ink-muted">Belum ditetapkan.</p>
                    @endforelse
                </x-card>

                <x-card title="Jadwal Ujian">
                    @forelse ($ta->ujian as $u)
                        <div class="border-b border-line/50 py-2.5 last:border-b-0">
                            <p class="text-[13px] font-medium">{{ $u->jenis->label() }}</p>
                            <p class="tabular mt-0.5 text-[12px] text-ink-muted">
                                {{ $u->tanggal->translatedFormat('j M Y') }},
                                {{ substr((string) $u->jam_mulai, 0, 5) }}–{{ substr((string) $u->jam_selesai, 0, 5) }}
                                @if ($u->ruang) · {{ $u->ruang->kode }} @endif
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                <x-chip :tone="$u->status->tone()">{{ $u->status->label() }}</x-chip>
                                @if ($u->hasil)
                                    <x-chip :tone="$u->hasil->tone()">{{ $u->hasil->label() }}</x-chip>
                                @endif
                            </div>
                            @if ($u->batas_revisi)
                                <p class="mt-1.5 text-[12px] text-warning-ink">
                                    Batas revisi: {{ $u->batas_revisi->translatedFormat('j M Y') }}
                                </p>
                            @endif
                            <div class="mt-2 space-y-0.5 text-[11.5px] text-ink-faint">
                                @foreach ($u->penguji as $p)
                                    <p>{{ $p->peran->label() }}: {{ $p->dosen->namaLengkap() }}</p>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-[13px] text-ink-muted">Belum ada ujian dijadwalkan.</p>
                    @endforelse
                </x-card>

                @if ($ta->batas_selesai)
                    <x-card title="Batas Waktu">
                        <p class="tabular text-[13px] {{ $ta->terlambat() ? 'font-semibold text-danger' : '' }}">
                            {{ $ta->batas_selesai->translatedFormat('j F Y') }}
                        </p>
                        @if ($ta->terlambat())
                            <p class="mt-1 text-[12px] text-ink-muted">
                                Sudah lewat batas. Hubungi program studi untuk membicarakan kelanjutannya.
                            </p>
                        @endif
                    </x-card>
                @endif
            </div>
        </div>
    @endif
@endsection
