@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Capaian Pembelajaran')

@section('content')
    @unless ($hasil['terpetakan'])
        <x-card>
            <x-empty-state
                title="Capaian pembelajaran belum dapat dihitung"
                description="Angka ini muncul setelah dosen menyatakan komponen nilai mana yang mengukur capaian pembelajaran yang mana. Nilai Anda tetap tercatat seperti biasa di KHS." />
        </x-card>
    @else
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <x-card title="Penguasaan per Capaian Pembelajaran"
                :meta="'ambang '.Format::angka($hasil['ambang'], 0)">
                {{-- Diurutkan terlemah lebih dulu: yang paling berguna dibaca
                     mahasiswa bukan yang sudah dikuasainya. --}}
                <dl class="space-y-4">
                    @foreach ($hasil['cpl'] as $baris)
                        <div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="min-w-0 text-[13px]">
                                    <span class="font-semibold">{{ $baris['cpl']->kode }}</span>
                                    <span class="text-ink-muted">{{ $baris['cpl']->deskripsi }}</span>
                                </dt>
                                <dd class="tabular shrink-0 text-[14px] font-semibold {{ $baris['tercapai'] ? 'text-navy' : 'text-warning-ink' }}">
                                    {{ $baris['nilai'] === null ? '—' : Format::angka($baris['nilai'], 1) }}
                                </dd>
                            </div>

                            <div class="mt-2 h-2 rounded-full bg-line">
                                <div class="h-2 rounded-full {{ $baris['tercapai'] ? 'bg-navy' : 'bg-warning' }}"
                                    style="width: {{ min(100, round($baris['nilai'] ?? 0)) }}%"></div>
                            </div>

                            <p class="mt-1.5 text-[11.5px] text-ink-faint">
                                Diukur di {{ $baris['mata_kuliah']->implode(', ') }}
                                ({{ $baris['jumlah_pengukuran'] }} pengukuran)
                            </p>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <div class="space-y-5">
                <x-card title="Cara Membacanya">
                    <p class="text-[13px] leading-relaxed text-ink-muted">
                        Angka ini bukan nilai baru. Ia adalah rerata terbobot dari komponen
                        penilaian yang memang mengukur capaian tersebut — sehingga nilai 70 di
                        satu mata kuliah dan 50 di mata kuliah lain dapat dibaca sebagai satu
                        gambaran.
                    </p>
                    <p class="mt-3 text-[13px] leading-relaxed text-ink-muted">
                        Yang di bawah ambang bukan berarti tidak lulus. Ia menunjukkan bidang
                        yang layak Anda bicarakan dengan dosen wali.
                    </p>
                </x-card>

                <x-card title="Bukan Ramalan">
                    {{-- Dikatakan kepada mahasiswa dengan kalimat yang sama seperti
                         kepada dosen. Angka yang disajikan sebagai perkiraan akan
                         dipercaya seperti perkiraan. --}}
                    <p class="text-[13px] leading-relaxed text-ink-muted">
                        Tidak ada perhitungan di sini yang memperkirakan hasil Anda ke depan.
                        Yang ditampilkan adalah apa yang sudah diukur, sampai hari ini.
                    </p>
                </x-card>
            </div>
        </div>
    @endunless
@endsection
