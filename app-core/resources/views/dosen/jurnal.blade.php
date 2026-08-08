@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Jurnal Perkuliahan')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <x-stat-card label="Terlaksana" :value="$keterlaksanaan['terlaksana'].'/'.$keterlaksanaan['rencana']"
            :meta="Format::angka($keterlaksanaan['persen_terlaksana'], 0).'%'" />
        <x-stat-card label="Berjurnal" :value="$keterlaksanaan['berjurnal'].'/'.$keterlaksanaan['rencana']"
            :meta="Format::angka($keterlaksanaan['persen_berjurnal'], 0).'%'" />
        <x-stat-card label="Rencana belum tersampaikan"
            :value="$keterlaksanaan['pertemuan_belum_tersampaikan']->count()"
            :meta="$keterlaksanaan['ada_rps'] ? 'dari RPS berlaku' : 'RPS belum ada'" />
    </div>

    <div class="space-y-4">
        @foreach ($pertemuan as $p)
            <x-card x-data="{ buka: {{ $p->jurnal_diisi_at === null ? 'false' : 'false' }} }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold">Pertemuan {{ $p->pertemuan_ke }}</span>
                            @if ($p->jurnal_diisi_at)
                                <x-chip tone="success">Berjurnal</x-chip>
                            @elseif ($p->is_terlaksana)
                                <x-chip tone="warning">Terlaksana, belum berjurnal</x-chip>
                            @else
                                <x-chip tone="neutral">Belum terlaksana</x-chip>
                            @endif
                        </div>
                        <div class="tabular mt-0.5 text-[11.5px] text-ink-faint">
                            {{ Format::tanggal($p->tanggal) }}
                            @if ($p->jumlah_peserta !== null)
                                · {{ $p->jumlah_hadir }}/{{ $p->jumlah_peserta }} hadir
                            @endif
                        </div>

                        @if ($p->materi)
                            <p class="mt-2 max-w-2xl text-[12.5px] leading-relaxed">{{ $p->materi }}</p>
                        @endif
                    </div>

                    <x-button type="button" variant="outline" size="sm" @click="buka = !buka"
                        x-text="buka ? 'Tutup' : '{{ $p->jurnal_diisi_at ? 'Perbarui' : 'Isi jurnal' }}'"></x-button>
                </div>

                <div x-show="buka" x-cloak class="mt-4 border-t border-line pt-4">
                    <form method="POST" action="{{ route('dosen.rps.jurnal.simpan', $p) }}" class="space-y-3">
                        @csrf

                        <x-field label="Materi yang diajarkan" name="materi" type="textarea" required
                            :value="$p->materi" />

                        @if ($rencanaPilihan !== [])
                            <x-field label="Merealisasikan rencana pekan" name="rps_pertemuan_id"
                                :options="$rencanaPilihan" :value="$p->rps_pertemuan_id"
                                hint="Boleh berbeda dari nomor pertemuan — libur dan penggabungan materi itu biasa, dan justru itu yang perlu terlihat." />
                        @endif

                        <x-field label="Catatan" name="catatan" type="textarea" :value="$p->catatan" />

                        <div class="flex items-center gap-3">
                            <x-button type="submit" size="sm">Simpan jurnal</x-button>
                            <span class="text-[11.5px] text-ink-faint">
                                Cacah kehadiran ikut dibekukan pada keadaan saat ini.
                            </span>
                        </div>
                    </form>
                </div>
            </x-card>
        @endforeach
    </div>
@endsection
