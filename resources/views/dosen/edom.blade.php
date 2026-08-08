@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Hasil Evaluasi Dosen')

@section('content')
    @if ($periode === null)
        <x-card>
            <x-empty-state
                title="Belum ada periode EDOM"
                description="Hasil muncul setelah periode evaluasi pertama ditutup." />
        </x-card>
    @else
        <div class="mb-5">
            {{-- Ambang disebut lebih dulu, sebelum daftarnya. Kelas yang kosong di
                 bawah ini hampir selalu berarti "responden kurang", bukan "tidak
                 ada yang menilai" — dan dua hal itu terasa sangat berbeda bagi
                 orang yang membacanya. --}}
            <x-alert tone="info">
                Hasil sebuah kelas baru ditampilkan bila terkumpul minimal
                {{ $periode->min_responden }} responden. Di bawah ambang itu, rerata
                maupun jumlah pengisi sama-sama disembunyikan — jumlahnya sendiri sudah
                cukup untuk menebak siapa yang mengisi pada kelas kecil.
                @if (! $bolehKomentar)
                    Komentar bebas
                    {{ $kebijakanKomentar === 'prodi' ? 'diteruskan ke program studi' : 'disimpan sebagai catatan internal' }},
                    tidak ditampilkan di sini.
                @endif
            </x-alert>
        </div>

        <div class="space-y-5">
            @forelse ($daftar as $baris)
                @php
                    $kelas = $baris['kelas'];
                    $hasil = $baris['hasil'];
                @endphp

                <x-card
                    title="{{ $kelas->mataKuliah->nama }} · Kelas {{ $kelas->nama }}"
                    meta="{{ $kelas->mataKuliah->kode }}"
                >
                    @if (! $hasil['cukup'])
                        <p class="text-[13px] text-ink-muted">
                            Belum mencapai {{ $hasil['ambang'] }} responden. Hasil kelas ini
                            belum dapat ditampilkan.
                        </p>
                    @else
                        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Rerata</p>
                                <p class="tabular text-2xl font-semibold text-navy">
                                    {{ $hasil['rerata'] === null ? '—' : Format::angka($hasil['rerata'], 2) }}
                                    <span class="text-[13px] font-normal text-ink-faint">/ 5</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Responden</p>
                                <p class="tabular text-2xl font-semibold">{{ $hasil['responden'] }}</p>
                            </div>
                        </div>

                        @if ($hasil['kategori'] !== [])
                            <dl class="mt-4 space-y-2">
                                @foreach ($hasil['kategori'] as $nama => $nilai)
                                    <div class="flex items-center gap-3">
                                        <dt class="w-52 shrink-0 text-[12.5px] text-ink-muted">{{ $nama }}</dt>
                                        <dd class="flex min-w-0 flex-1 items-center gap-3">
                                            <div class="h-1.5 min-w-0 flex-1 rounded-full bg-line">
                                                <div
                                                    class="h-1.5 rounded-full bg-navy"
                                                    style="width: {{ round($nilai / 5 * 100) }}%"
                                                ></div>
                                            </div>
                                            <span class="tabular w-10 shrink-0 text-right text-[12.5px] font-medium">
                                                {{ Format::angka($nilai, 2) }}
                                            </span>
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        @if ($bolehKomentar && $hasil['komentar'] !== [])
                            <div class="mt-5 border-t border-line pt-4">
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                    Komentar ({{ count($hasil['komentar']) }})
                                </p>
                                <ul class="space-y-2">
                                    @foreach ($hasil['komentar'] as $teks)
                                        <li class="rounded-control border border-line bg-zebra px-3 py-2 text-[13px] leading-relaxed">
                                            {{ $teks }}
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="mt-2 text-[11.5px] text-ink-faint">
                                    Urutan diacak dan tidak mencerminkan waktu pengisian.
                                </p>
                            </div>
                        @endif
                    @endif
                </x-card>
            @empty
                <x-card>
                    <x-empty-state
                        title="Tidak ada kelas pada periode ini"
                        description="Hasil evaluasi muncul untuk kelas yang Anda ampu di {{ $periode->tahunAkademik->nama }}." />
                </x-card>
            @endforelse
        </div>
    @endif
@endsection
