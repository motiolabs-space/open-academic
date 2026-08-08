@extends('layouts.app')

@section('title', $ta->mahasiswa->nama.' — Tugas Akhir')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if (session('peringatan'))
        <div class="mb-5">
            <x-alert tone="warning">
                <p class="font-semibold">Dijadwalkan, dengan catatan:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach (session('peringatan') as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
            </x-alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            {{-- Judul & keputusan --}}
            <x-card title="Judul">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-serif text-lg">{{ $ta->judul }}</p>
                        <p class="mt-1 text-[12px] text-ink-muted">
                            {{ $ta->mahasiswa->nama }} · {{ $ta->mahasiswa->nim }} ·
                            {{ $ta->mahasiswa->prodi->nama }}
                        </p>
                    </div>
                    <x-chip :tone="$ta->status->tone()">{{ $ta->status->label() }}</x-chip>
                </div>

                @if ($ta->bidang_kajian)
                    <p class="mt-3 text-[12px]"><span class="text-ink-muted">Bidang kajian:</span>
                        {{ $ta->bidang_kajian }}</p>
                @endif

                @if ($ta->abstrak)
                    <p class="mt-3 whitespace-pre-line text-[13px] leading-relaxed">{{ $ta->abstrak }}</p>
                @endif

                @if ($ta->catatan)
                    <div class="mt-3"><x-alert tone="warning">{{ $ta->catatan }}</x-alert></div>
                @endif

                @if ($ta->status === \App\Enums\TugasAkhirStatus::Diajukan)
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                        <form method="POST" action="{{ route('admin.tugas-akhir.setujui', $ta) }}">
                            @csrf
                            <x-button type="submit">Setujui judul</x-button>
                        </form>

                        <form method="POST" action="{{ route('admin.tugas-akhir.tolak', $ta) }}"
                            class="flex flex-1 flex-wrap items-end gap-2">
                            @csrf
                            <x-field label="Alasan penolakan" name="alasan" class="flex-1"
                                hint="Dibaca mahasiswa — tanpa ini judul yang sama diajukan lagi." required />
                            <x-button type="submit" variant="outline">Tolak</x-button>
                        </form>
                    </div>
                @endif
            </x-card>

            {{-- Log bimbingan --}}
            <x-card title="Log Bimbingan"
                meta="{{ $ta->jumlahBimbinganDisetujui() }} disetujui dari {{ $ta->bimbingan->count() }} tercatat">
                @if ($minBimbingan > 0)
                    <p class="mb-3 text-[12px] text-ink-muted">
                        Sidang memerlukan {{ $minBimbingan }} bimbingan yang sudah disetujui pembimbing.
                        Log yang belum disetujui tidak dihitung.
                    </p>
                @endif

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
                            <p class="mt-0.5 text-[11.5px] text-ink-faint">{{ $b->dosen->namaLengkap() }}</p>
                        </div>
                        <x-chip :tone="$b->disetujui ? 'success' : 'warning'">
                            {{ $b->disetujui ? 'Disetujui' : 'Menunggu' }}
                        </x-chip>
                    </div>
                @empty
                    <p class="py-6 text-center text-[13px] text-ink-muted">Belum ada catatan bimbingan.</p>
                @endforelse
            </x-card>

            {{-- Ujian --}}
            <x-card title="Ujian">
                @forelse ($ta->ujian as $u)
                    <div class="border-b border-line/50 py-3 last:border-b-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-medium">{{ $u->jenis->label() }}</span>
                                <span class="tabular ml-2 text-[12px] text-ink-muted">
                                    {{ $u->tanggal->translatedFormat('j M Y') }},
                                    {{ substr((string) $u->jam_mulai, 0, 5) }}–{{ substr((string) $u->jam_selesai, 0, 5) }}
                                    @if ($u->ruang) · {{ $u->ruang->kode }} @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <x-chip :tone="$u->status->tone()">{{ $u->status->label() }}</x-chip>
                                @if ($u->hasil)
                                    <x-chip :tone="$u->hasil->tone()">{{ $u->hasil->label() }}</x-chip>
                                @endif
                            </div>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[12px]">
                            @foreach ($u->penguji as $p)
                                <span>
                                    <span class="text-ink-faint">{{ $p->peran->label() }}:</span>
                                    {{ $p->dosen->namaLengkap() }}
                                    @if ($p->nilai !== null)
                                        <span class="tabular font-medium">({{ $p->nilai }})</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>

                        @if ($u->batas_revisi)
                            <p class="mt-1.5 text-[12px] text-warning-ink">
                                Batas revisi: {{ $u->batas_revisi->translatedFormat('j M Y') }}
                            </p>
                        @endif

                        @if ($u->status === \App\Enums\StatusUjian::Dijadwalkan)
                            <form method="POST" action="{{ route('admin.tugas-akhir.ujian.hasil', $u) }}"
                                class="mt-3 grid gap-2 rounded-control bg-zebra p-3 sm:grid-cols-4">
                                @csrf
                                <x-field label="Hasil" name="hasil" :options="$hasilUjian" required />
                                <x-field label="Nilai" name="nilai" type="number"
                                    :placeholder="$u->rerataPenguji() !== null ? 'Rerata: '.$u->rerataPenguji() : 'Belum ada nilai penguji'"
                                    hint="Kosongkan untuk memakai rerata penguji." />
                                <x-field label="Batas revisi" name="batas_revisi" type="date"
                                    hint="Wajib bila lulus dengan revisi." />
                                <div class="flex items-end">
                                    <x-button type="submit" size="sm">Catat hasil</x-button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-[13px] text-ink-muted">Belum ada ujian dijadwalkan.</p>
                @endforelse

                @if ($ta->status === \App\Enums\TugasAkhirStatus::Dibimbing)
                    <form method="POST" action="{{ route('admin.tugas-akhir.ujian', $ta) }}"
                        class="mt-4 space-y-3 border-t border-line pt-4"
                        x-data="{ kursi: [{ dosen_id: '', peran: 'ketua' }] }">
                        @csrf
                        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                            Jadwalkan ujian
                        </p>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <x-field label="Jenis" name="jenis" :options="$jenisUjian" required />
                            <x-field label="Tanggal" name="tanggal" type="date" required />
                            <x-field label="Mulai" name="jam_mulai" type="time" required />
                            <x-field label="Selesai" name="jam_selesai" type="time" required />
                            <x-field label="Ruang" name="ruang_id"
                                :options="$daftarRuang->mapWithKeys(fn ($r) => [$r->id => $r->kode.' — '.$r->nama])" />
                        </div>

                        <div class="space-y-2">
                            <template x-for="(k, i) in kursi" :key="i">
                                <div class="flex items-end gap-2">
                                    <div class="min-w-0 flex-1">
                                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                            Penguji
                                        </label>
                                        <select :name="`penguji[${i}][dosen_id]`" required
                                            class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px]">
                                            <option value="">—</option>
                                            @foreach ($daftarDosen as $d)
                                                <option value="{{ $d->id }}">{{ $d->namaLengkap() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="w-44">
                                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                            Peran
                                        </label>
                                        <select :name="`penguji[${i}][peran]`" required
                                            class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px]">
                                            @foreach ($peranPenguji as $nilai => $label)
                                                <option value="{{ $nilai }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <x-button type="button" variant="outline" size="sm"
                                        x-on:click="kursi.splice(i, 1)" x-show="kursi.length > 1">Hapus</x-button>
                                </div>
                            </template>

                            <x-button type="button" variant="outline" size="sm"
                                x-on:click="kursi.push({ dosen_id: '', peran: 'anggota' })">
                                Tambah penguji
                            </x-button>
                        </div>

                        <p class="text-[11.5px] text-ink-faint">
                            Sidang akhir memerlukan sekurangnya satu penguji yang bukan pembimbing karya ini.
                        </p>

                        <x-button type="submit">Jadwalkan</x-button>
                    </form>
                @endif
            </x-card>
        </div>

        {{-- Panel kanan --}}
        <div class="space-y-5">
            <x-card title="Pembimbing">
                @forelse ($ta->pembimbing as $p)
                    <div class="flex items-center justify-between gap-2 border-b border-line/50 py-2.5 last:border-b-0">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-medium">{{ $p->dosen->namaLengkap() }}</p>
                            <p class="text-[11.5px] text-ink-faint">{{ $p->peran->label() }}</p>
                        </div>
                        @if ($ta->status !== \App\Enums\TugasAkhirStatus::Selesai)
                            <form method="POST" action="{{ route('admin.tugas-akhir.pembimbing.lepas', $p) }}">
                                @csrf @method('DELETE')
                                <x-button type="submit" variant="outline" size="sm">Lepas</x-button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="py-4 text-center text-[13px] text-ink-muted">Belum ada pembimbing.</p>
                @endforelse

                @if (in_array($ta->status, [\App\Enums\TugasAkhirStatus::Disetujui, \App\Enums\TugasAkhirStatus::Dibimbing], true))
                    <form method="POST" action="{{ route('admin.tugas-akhir.pembimbing', $ta) }}"
                        class="mt-3 space-y-3 border-t border-line pt-3">
                        @csrf
                        <div>
                            <label for="dosen_id" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                Dosen <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <select id="dosen_id" name="dosen_id" required
                                class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px]">
                                <option value="">—</option>
                                @foreach ($daftarDosen as $d)
                                    @php $beban = $bebanPembimbing[$d->id] ?? 0; @endphp
                                    <option value="{{ $d->id }}" @disabled($kuotaPembimbing > 0 && $beban >= $kuotaPembimbing)>
                                        {{ $d->namaLengkap() }}
                                        ({{ $beban }}@if ($kuotaPembimbing > 0)/{{ $kuotaPembimbing }}@endif)
                                    </option>
                                @endforeach
                            </select>
                            {{-- Beban ditampilkan sebelum memilih, bukan sesudah ditolak: yang
                                 mengalokasikan perlu tahu siapa yang sudah penuh saat memutuskan. --}}
                            <p class="mt-1 text-[11.5px] text-ink-faint">
                                Angka dalam kurung adalah bimbingan yang sedang berjalan.
                            </p>
                        </div>

                        <x-field label="Peran" name="peran" :options="$peranPembimbing" required />
                        <x-button type="submit" class="w-full">Tetapkan pembimbing</x-button>
                    </form>
                @endif
            </x-card>

            <x-card title="Ringkasan">
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Diajukan</dt>
                        <dd class="tabular">{{ $ta->tanggal_pengajuan->translatedFormat('j M Y') }}</dd>
                    </div>
                    @if ($ta->tanggal_disetujui)
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Disetujui</dt>
                            <dd class="tabular">{{ $ta->tanggal_disetujui->translatedFormat('j M Y') }}</dd>
                        </div>
                    @endif
                    @if ($ta->batas_selesai)
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Batas selesai</dt>
                            <dd class="tabular {{ $ta->terlambat() ? 'text-danger font-semibold' : '' }}">
                                {{ $ta->batas_selesai->translatedFormat('j M Y') }}
                            </dd>
                        </div>
                    @endif
                    @if ($ta->nilai_akhir !== null)
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Nilai akhir</dt>
                            <dd class="tabular font-semibold">{{ $ta->nilai_akhir }} ({{ $ta->nilai_huruf }})</dd>
                        </div>
                    @endif
                </dl>

                @if ($ta->status->aktif())
                    <div class="mt-4 space-y-2 border-t border-line pt-4">
                        @if ($ta->sidangLulus() !== null)
                            <form method="POST" action="{{ route('admin.tugas-akhir.selesai', $ta) }}">
                                @csrf
                                <x-button type="submit" class="w-full">Nyatakan selesai</x-button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.tugas-akhir.batal', $ta) }}" class="space-y-2">
                            @csrf
                            <x-field label="Alasan pembatalan" name="alasan" required />
                            <x-button type="submit" variant="outline" class="w-full">Batalkan tugas akhir</x-button>
                        </form>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
