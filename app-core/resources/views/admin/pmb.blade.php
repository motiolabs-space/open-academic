@extends('layouts.app')

@section('title', 'Penerimaan Mahasiswa Baru')

@section('content')
    @if (session('kata_sandi_baru'))
        <x-kredensial-baru :data="session('kata_sandi_baru')" />
    @endif

    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    {{-- Gelombang --}}
    <div class="mb-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <x-card title="Gelombang Pendaftaran" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Kode</th>
                            <th class="px-5 py-3 font-semibold">Gelombang</th>
                            <th class="px-5 py-3 font-semibold">Periode</th>
                            <th class="px-5 py-3 text-center font-semibold">Pendaftar</th>
                            <th class="px-5 py-3 font-semibold">Sebaran Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarGelombang as $g)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-5 py-3 font-semibold">{{ $g->kode }}</td>
                                <td class="px-5 py-3">
                                    {{ $g->nama }}
                                    <div class="text-[11.5px] text-ink-faint">
                                        {{ ucfirst($g->jalur) }} · {{ $g->tahunAkademik->nama }}
                                        @if ($g->kuota) · kuota {{ $g->kuota }} @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-ink-muted">
                                    {{ $g->tanggal_mulai->translatedFormat('j M Y') }} –
                                    {{ $g->tanggal_selesai->translatedFormat('j M Y') }}
                                    @if ($g->sedangBerjalan())
                                        <x-chip tone="success" class="ml-1">dibuka</x-chip>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3 text-center font-semibold">{{ $g->pendaftar_count }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (($rekap[$g->id] ?? collect()) as $baris)
                                            {{-- Sudah berupa enum: kolomnya ikut cast model meski
                                                 diambil lewat selectRaw. --}}
                                            <x-chip tone="neutral">
                                                {{ $baris->status->label() }}: {{ $baris->jumlah }}
                                            </x-chip>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10">
                                    <x-empty-state title="Belum ada gelombang"
                                        description="Pendaftar hanya dapat masuk melalui gelombang yang dibuka." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Buka Gelombang">
            <form method="POST" action="{{ route('admin.pmb.gelombang.store') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Tahun Akademik" name="tahun_akademik_id" required
                    :options="$daftarTerm->pluck('nama', 'id')" />
                <x-field label="Kode" name="kode" required placeholder="PMB-2026-1" />
                <x-field label="Nama" name="nama" required placeholder="Gelombang I 2026" />
                <x-field label="Jalur" name="jalur" required
                    :options="['reguler' => 'Reguler', 'prestasi' => 'Prestasi', 'rpl' => 'RPL', 'transfer' => 'Transfer']" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Mulai" name="tanggal_mulai" type="date" required />
                    <x-field label="Selesai" name="tanggal_selesai" type="date" required />
                </div>
                <x-field label="Biaya Pendaftaran" name="biaya_pendaftaran" type="number" required :value="0" />
                <x-field label="Kuota" name="kuota" type="number" />
                <x-button type="submit" class="mt-1 w-full">Buka Gelombang</x-button>
            </form>
        </x-card>
    </div>

    {{-- Pendaftar --}}
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field label="Cari" name="cari" type="search" :value="$filter['cari'] ?? ''"
                placeholder="Nama, nomor, atau surel…" class="min-w-[220px] flex-1" />
            <x-field label="Gelombang" name="gelombang" :value="$filter['gelombang'] ?? ''"
                :options="$daftarGelombang->pluck('nama', 'id')" />
            <x-field label="Status" name="status" :value="$filter['status'] ?? ''"
                :options="collect($statusPilihan)->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
            <x-button type="submit">Terapkan</x-button>
            @if (array_filter($filter))
                <x-button variant="ghost" :href="route('admin.pmb')">Reset</x-button>
            @endif
        </form>
    </x-card>

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">No. Daftar</th>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-5 py-3 font-semibold">Pilihan</th>
                        <th class="px-5 py-3 text-center font-semibold">Status</th>
                        <th class="px-5 py-3 text-center font-semibold">Siap PDDIKTI</th>
                        <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendaftar as $p)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                            <td class="tabular px-5 py-3">{{ $p->nomor_pendaftaran }}</td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $p->nama }}</div>
                                <div class="text-[11.5px] text-ink-faint">{{ $p->email }}</div>
                                @if ($p->mahasiswa)
                                    <div class="tabular mt-1 text-[11.5px] font-semibold text-navy">
                                        NIM {{ $p->mahasiswa->nim }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-ink-muted">
                                <div>1. {{ $p->prodiPilihan1->nama }}</div>
                                @if ($p->prodiDiterima)
                                    <div class="mt-1 font-semibold text-ink">
                                        Diterima: {{ $p->prodiDiterima->nama }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                <x-chip :tone="match ($p->status->value) {
                                    'mahasiswa' => 'success',
                                    'lulus', 'daftar_ulang' => 'info',
                                    'tidak_lulus', 'batal' => 'danger',
                                    default => 'neutral',
                                }">{{ $p->status->label() }}</x-chip>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if ($p->siapSinkronFeeder())
                                    <x-chip tone="success">Lengkap</x-chip>
                                @else
                                    <x-chip tone="warning" title="NIK atau data kelahiran belum lengkap">Kurang data</x-chip>
                                @endif

                                <details class="mt-2 text-left">
                                    <summary class="cursor-pointer text-[11.5px] text-navy">
                                        Berkas ({{ $p->berkas->count() }})
                                    </summary>

                                    <ul class="mt-1.5 flex flex-col gap-1">
                                        @foreach ($p->berkas as $b)
                                            <li class="flex items-center gap-1.5 text-[11.5px]">
                                                <a href="{{ route('berkas.pmb', $b) }}"
                                                    class="text-navy hover:underline">
                                                    {{ $jenisBerkas[$b->jenis] ?? $b->jenis }}
                                                </a>
                                                @if ($b->is_verified)
                                                    <span class="text-success" title="Terverifikasi" aria-hidden="true">✓</span>
                                                @endif
                                                <form method="POST" action="{{ route('admin.pmb.berkas.hapus', $b) }}"
                                                    onsubmit="return confirm('Hapus berkas ini?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-danger" title="Hapus">×</button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <form method="POST" action="{{ route('admin.pmb.berkas.unggah', $p) }}"
                                        enctype="multipart/form-data" class="mt-2 flex flex-col gap-1.5">
                                        @csrf
                                        <select name="jenis" required
                                            class="rounded-control border border-line-input bg-surface px-2 py-1 text-[11.5px]">
                                            @foreach ($jenisBerkas as $kode => $label)
                                                <option value="{{ $kode }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="file" name="berkas" required
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            class="text-[11px]">
                                        <x-button type="submit" size="sm">Unggah</x-button>
                                    </form>
                                </details>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col items-end gap-1.5">
                                    @if ($p->status->value === 'mahasiswa')
                                        <span class="text-[11.5px] text-ink-faint">sudah terdaftar</span>
                                    @elseif (in_array($p->status->value, ['lulus', 'daftar_ulang'], true))
                                        <form method="POST" action="{{ route('admin.pmb.daftar-ulang', $p) }}"
                                            class="flex items-center gap-1.5"
                                            onsubmit="return confirm('Daftarkan {{ $p->nama }} sebagai mahasiswa? NIM akan diterbitkan dan tidak dapat ditarik kembali.');">
                                            @csrf
                                            <select name="tahun_akademik_id"
                                                class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                @foreach ($daftarTerm as $t)
                                                    <option value="{{ $t->id }}" @selected($termAktif?->id === $t->id)>{{ $t->kode }}</option>
                                                @endforeach
                                            </select>
                                            <x-button type="submit" size="sm">Daftar Ulang</x-button>
                                        </form>
                                    @else
                                        <details>
                                            <summary class="cursor-pointer text-[11.5px] text-navy">Putuskan seleksi</summary>
                                            <form method="POST" action="{{ route('admin.pmb.luluskan', $p) }}"
                                                class="mt-2 flex items-center gap-1.5">
                                                @csrf
                                                <select name="prodi_diterima_id" required
                                                    class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                    <option value="{{ $p->prodi_pilihan_1_id }}">{{ $p->prodiPilihan1->nama }}</option>
                                                    @if ($p->prodiPilihan2)
                                                        <option value="{{ $p->prodi_pilihan_2_id }}">{{ $p->prodiPilihan2->nama }}</option>
                                                    @endif
                                                </select>
                                                <input type="number" name="nilai_seleksi" step="0.01" min="0" max="100"
                                                    placeholder="Nilai"
                                                    class="w-20 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <x-button type="submit" size="sm">Luluskan</x-button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.pmb.tidak-luluskan', $p) }}" class="mt-1.5">
                                                @csrf
                                                <x-button type="submit" variant="danger" size="sm">Tidak Lulus</x-button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12">
                                <x-empty-state title="Tidak ada pendaftar"
                                    description="Ubah filter, atau buka gelombang pendaftaran lebih dulu." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pendaftar->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $pendaftar->links() }}</div>
        @endif
    </x-card>
@endsection
