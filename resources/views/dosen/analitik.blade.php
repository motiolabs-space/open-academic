@extends('layouts.app')

@section('title', 'Analitik Kelas')

@section('content')
    <x-card flush title="Kelas Diampu">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Mata Kuliah</th>
                        <th class="px-5 py-3 font-semibold">Kelas</th>
                        <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $k)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $k->mataKuliah->nama }}</div>
                                <div class="tabular text-[11.5px] text-ink-faint">{{ $k->mataKuliah->kode }}</div>
                            </td>
                            <td class="px-5 py-3">{{ $k->nama }}</td>
                            <td class="px-5 py-3 text-right">
                                <x-button href="{{ route('dosen.analitik.kelas', $k) }}" variant="outline" size="sm">
                                    Lihat analitik
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">
                            <x-empty-state title="Tidak ada kelas pada semester ini"
                                description="Analitik muncul untuk kelas yang Anda ampu." />
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
