@extends('layouts.app')

@section('title', 'Borang LKPS')

@section('aksi')
    <x-button
        variant="outline"
        :href="route('admin.lkps.ekspor', ['prodi' => $prodi->uuid, 'semester' => $term->kode])"
    >Unduh CSV</x-button>
@endsection

@section('content')
    {{-- ============ PEMILIH ============ --}}
    <x-card class="mb-5">
        <form method="GET" action="{{ route('admin.lkps') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-[11px] uppercase tracking-[0.08em] text-ink-muted">Program Studi</label>
                <select name="prodi" class="rounded border border-line bg-surface px-3 py-2 text-[13px]">
                    @foreach ($daftarProdi as $satu)
                        <option value="{{ $satu->uuid }}" @selected($satu->id === $prodi->id)>
                            {{ $satu->kode }} · {{ $satu->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[11px] uppercase tracking-[0.08em] text-ink-muted">Semester</label>
                <select name="semester" class="rounded border border-line bg-surface px-3 py-2 text-[13px]">
                    @foreach ($semesterPilihan as $kode => $nama)
                        <option value="{{ $kode }}" @selected($kode === $term->kode)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            <x-button type="submit" class="px-5 py-2 text-xs">Tampilkan</x-button>
        </form>
    </x-card>

    {{--
        Definisinya di layar, bukan hanya di dokumen. Orang yang membaca
        angkanya adalah orang yang perlu tahu aturan mana yang menghasilkannya,
        dan ia tidak akan membuka berkas untuk mencari tahu.
    --}}
    <x-alert tone="warning" class="mb-5">
        <strong>Definisi masih sementara.</strong> Angka di bawah dihitung memakai aturan berikut,
        dan aturannya belum tentu disepakati kampus — lihat <code>docs/LKPS-DEFINISI.md</code>.
        <ul class="mt-2 list-disc space-y-0.5 pl-5 text-[12.5px]">
            @foreach ($definisiSementara as $satu)
                <li>{{ $satu }}</li>
            @endforeach
        </ul>
    </x-alert>

    {{-- ============ TABEL ============ --}}
    @foreach ($tabel as $satu)
        <x-card
            class="mb-5"
            :title="trim(($satu['nomor'] ?? '').' '.$satu['judul'])"
            :meta="$satu['nomor'] ? null : 'nomor tabel belum diisi'"
            flush
        >
            @if (! $satu['terisi'])
                {{--
                    Alasannya menggantikan barisnya. Sel kosong di borang
                    akreditasi terbaca sebagai nol, dan nol di tabel penelitian
                    adalah pernyataan tentang prodinya.
                --}}
                <div class="px-5 py-4">
                    <x-chip tone="neutral">Tidak diisi</x-chip>
                    <p class="mt-2 text-[12.5px] leading-relaxed text-ink-muted">{{ $satu['alasan'] }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                @foreach ($satu['kolom'] as $kolom)
                                    <th class="px-5 py-3 font-semibold">{{ $kolom }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($satu['baris'] as $baris)
                                <tr class="border-b border-line/50 last:border-b-0">
                                    @foreach ($baris as $sel)
                                        <td class="tabular px-5 py-2.5">{{ $sel }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($satu['catatan'])
                    <div class="border-t border-line px-5 py-3 text-[12px] text-ink-faint">
                        {{ $satu['catatan'] }}
                    </div>
                @endif
            @endif
        </x-card>
    @endforeach
@endsection
