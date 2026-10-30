@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Capaian Pembelajaran')

@section('content')
    @if (! $capaian['terhitung'] && ! $hasil['terpetakan'])
        <x-card>
            <x-empty-state
                title="Capaian pembelajaran belum dapat dihitung"
                :description="__('capaian.mahasiswa.kosong')" />
        </x-card>
    @else
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-5">
                @if ($capaian['terhitung'])
                    {{-- Dari nilai final yang tersimpan (ADR-038): angka ini tidak
                         berubah ketika layar dibuka ulang, hanya lewat koreksi nilai. --}}
                    <x-card :title="__('capaian.mahasiswa.judul_cpl')"
                        :meta="__('capaian.istilah.ambang', ['ambang' => Format::angka($capaian['ambang'], 0)])">
                        <dl class="space-y-4">
                            @foreach ($capaian['cpl'] as $baris)
                                <div>
                                    <div class="flex items-baseline justify-between gap-3">
                                        <dt class="min-w-0 text-[13px]">
                                            <span class="font-semibold">{{ $baris['cpl']->kode }}</span>
                                            <span class="text-ink-muted">{{ $baris['cpl']->deskripsi }}</span>
                                        </dt>
                                        <dd class="tabular shrink-0 text-[14px] font-semibold {{ $baris['tercapai'] ? 'text-navy' : 'text-warning-ink' }}">
                                            {{ Format::angka($baris['nilai'], 1) }}
                                        </dd>
                                    </div>

                                    <div class="mt-2 h-2 rounded-full bg-line">
                                        <div class="h-2 rounded-full {{ $baris['tercapai'] ? 'bg-navy' : 'bg-warning' }}"
                                            style="width: {{ min(100, round($baris['nilai'])) }}%"></div>
                                    </div>

                                    <p class="mt-1.5 text-[11.5px] text-ink-faint">
                                        {{ __('capaian.mahasiswa.diukur_di', ['daftar' => $baris['mata_kuliah']->implode(', ')]) }}
                                    </p>
                                </div>
                            @endforeach
                        </dl>
                    </x-card>

                    <x-card flush :title="__('capaian.mahasiswa.judul_cpmk')">
                        @foreach ($capaian['mata_kuliah'] as $mk)
                            <div class="border-b border-line px-5 py-4 last:border-b-0">
                                <div class="flex items-baseline justify-between gap-3">
                                    <h3 class="text-[13.5px] font-semibold">{{ $mk['kelas']->mataKuliah->nama }}</h3>
                                    <span class="tabular text-[11.5px] text-ink-faint">{{ $mk['kelas']->tahunAkademik->nama }}</span>
                                </div>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($mk['cpmk'] as $c)
                                        <li class="flex items-baseline justify-between gap-3 text-[12.5px]">
                                            <span class="min-w-0">
                                                <span class="font-medium">{{ $c['cpmk']->kode }}</span>
                                                <span class="text-ink-muted">{{ $c['cpmk']->rumusan }}</span>
                                                @if ($c['cakupan'] < 100)
                                                    <span class="text-ink-faint">({{ __('capaian.istilah.cakupan', ['persen' => $c['cakupan']]) }})</span>
                                                @endif
                                            </span>
                                            <span class="tabular shrink-0 font-semibold {{ $c['tercapai'] ? 'text-navy' : 'text-warning-ink' }}">
                                                {{ Format::angka($c['nilai'], 1) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </x-card>
                @endif

                @if ($hasil['terpetakan'])
                    <x-card :title="$capaian['terhitung'] ? __('capaian.mahasiswa.berjalan') : 'Penguasaan per Capaian Pembelajaran'"
                        :meta="$capaian['terhitung'] ? __('capaian.mahasiswa.berjalan_meta') : 'ambang '.Format::angka($hasil['ambang'], 0)">
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
                @endif
            </div>

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
    @endif
@endsection
