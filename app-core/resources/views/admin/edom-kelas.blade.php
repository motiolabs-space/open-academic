@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Hasil EDOM Kelas')

@section('content')
    <x-card
        :title="$kelas->mataKuliah->nama.' · Kelas '.$kelas->nama"
        :meta="$dosen->nama"
        class="mb-5"
    >
        @if (! $hasil['cukup'])
            <p class="text-[13px] text-ink-muted">
                Kelas ini belum mencapai {{ $hasil['ambang'] }} responden, sehingga hasilnya
                belum dapat ditampilkan.
            </p>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                <x-stat-card
                    label="Rerata"
                    :value="$hasil['rerata'] === null ? '—' : Format::angka($hasil['rerata'], 2)"
                    meta="dari skala 1–5" feature />
                <x-stat-card label="Responden" :value="$hasil['responden']" meta="pengisi" />
            </div>

            @if ($hasil['kategori'] !== [])
                <dl class="mt-4 space-y-2">
                    @foreach ($hasil['kategori'] as $nama => $nilai)
                        <div class="flex items-center gap-3">
                            <dt class="w-52 shrink-0 text-[12.5px] text-ink-muted">{{ $nama }}</dt>
                            <dd class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="h-1.5 min-w-0 flex-1 rounded-full bg-line">
                                    <div class="h-1.5 rounded-full bg-navy" style="width: {{ round($nilai / 5 * 100) }}%"></div>
                                </div>
                                <span class="tabular w-10 shrink-0 text-right text-[12.5px] font-medium">
                                    {{ Format::angka($nilai, 2) }}
                                </span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        @endif
    </x-card>

    <x-card title="Komentar">
        @if ($kebijakanKomentar !== 'prodi')
            <p class="text-[13px] leading-relaxed text-ink-muted">
                Komentar bebas
                {{ $kebijakanKomentar === 'dosen' ? 'diteruskan langsung kepada dosen yang bersangkutan' : 'tidak ditampilkan di mana pun' }},
                sesuai pengaturan kampus. Ubah lewat <code class="text-[12px]">EDOM_KOMENTAR</code>.
            </p>
        @elseif (! $hasil['cukup'])
            <p class="text-[13px] text-ink-muted">
                Komentar mengikuti ambang yang sama dengan nilainya.
            </p>
        @elseif ($hasil['komentar'] === [])
            <p class="text-[13px] text-ink-muted">Tidak ada komentar tertulis pada kelas ini.</p>
        @else
            <ul class="space-y-2">
                @foreach ($hasil['komentar'] as $teks)
                    <li class="rounded-control border border-line bg-zebra px-3 py-2 text-[13px] leading-relaxed">
                        {{ $teks }}
                    </li>
                @endforeach
            </ul>

            {{-- Peringatan ini ditempatkan di bawah komentarnya, bukan di atas:
                 dibaca setelah orangnya tahu apa yang sedang ia pegang. --}}
            <p class="mt-3 text-[12px] leading-relaxed text-warning-ink">
                Urutan diacak dan tidak mencerminkan waktu pengisian. Sebuah kalimat dapat
                menunjuk penulisnya lewat isinya sendiri — perlakukan halaman ini sebagai
                bahan pembinaan, bukan bahan yang diteruskan apa adanya.
            </p>
        @endif
    </x-card>
@endsection
