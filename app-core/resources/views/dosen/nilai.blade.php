@extends('layouts.app')

@section('title', 'Input Nilai')

@section('content')
    @unless ($periodeDibuka)
        <x-alert tone="warning" class="mb-5">
            Periode pengisian nilai untuk semester ini sedang tertutup. Anda tetap dapat
            melihat lembar nilai, tetapi perubahan tidak dapat disimpan.
        </x-alert>
    @endunless

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Kode</th>
                        <th class="px-5 py-3 font-semibold">Mata Kuliah</th>
                        <th class="px-5 py-3 text-center font-semibold">Kelas</th>
                        <th class="px-5 py-3 text-center font-semibold">Peserta</th>
                        <th class="px-5 py-3 text-center font-semibold">Status Nilai</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $kelas)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra hover:bg-highlight">
                            <td class="tabular px-5 py-3">{{ $kelas->mataKuliah->kode }}</td>
                            <td class="px-5 py-3 font-medium">{{ $kelas->mataKuliah->nama }}</td>
                            <td class="px-5 py-3 text-center">{{ $kelas->kode }}</td>
                            <td class="tabular px-5 py-3 text-center">{{ $kelas->jumlah_peserta }}</td>
                            <td class="px-5 py-3 text-center">
                                <x-chip :tone="$kelas->status_nilai === 'final' ? 'success' : ($kelas->status_nilai === 'sebagian' ? 'warning' : 'neutral')">
                                    {{ ['belum' => 'Belum diisi', 'sebagian' => 'Sebagian', 'final' => 'Final'][$kelas->status_nilai] }}
                                </x-chip>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-button variant="outline" :href="route('dosen.nilai.kelas', $kelas)" class="px-4 py-2 text-xs">
                                    {{ $kelas->status_nilai === 'final' ? 'Lihat' : 'Isi Nilai' }}
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12">
                                <x-empty-state
                                    title="Belum ada kelas diampu"
                                    description="Penugasan mengajar pada semester aktif belum ditetapkan."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
