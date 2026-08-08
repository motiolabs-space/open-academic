@extends('layouts.app')

@section('title', 'Log Aktivitas')

@section('content')
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field label="Cari" name="cari" type="search" :value="$filter['cari'] ?? ''"
                placeholder="Deskripsi, pelaku, atau objek…" class="min-w-[220px] flex-1" />
            <x-field label="Peristiwa" name="event" :value="$filter['event'] ?? ''" :options="$daftarEvent" />
            <x-field label="Objek" name="subjek" :value="$filter['subjek'] ?? ''" :options="$daftarSubjek" />
            <x-field label="Dari" name="dari" type="date" :value="$filter['dari'] ?? ''" />
            <x-field label="Sampai" name="sampai" type="date" :value="$filter['sampai'] ?? ''" />
            <x-button type="submit">Terapkan</x-button>
            @if (array_filter($filter))
                <x-button variant="ghost" :href="route('admin.log')">Reset</x-button>
            @endif
        </form>
    </x-card>

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Waktu</th>
                        <th class="px-5 py-3 font-semibold">Pelaku</th>
                        <th class="px-5 py-3 font-semibold">Peristiwa</th>
                        <th class="px-5 py-3 font-semibold">Objek</th>
                        <th class="px-5 py-3 font-semibold">Perubahan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $log)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                            <td class="tabular whitespace-nowrap px-5 py-3 text-ink-muted">
                                {{ $log->created_at->translatedFormat('j M Y') }}
                                <div class="text-[11px] text-ink-faint">{{ $log->created_at->format('H:i:s') }}</div>
                            </td>
                            <td class="px-5 py-3">
                                {{ $log->causer_name ?? 'Sistem' }}
                                @if ($log->ip_address)
                                    <div class="tabular text-[11px] text-ink-faint">{{ $log->ip_address }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-chip tone="info">{{ $log->event }}</x-chip>
                                @if ($log->description)
                                    <p class="mt-1.5 max-w-md text-[12.5px] leading-relaxed">{{ $log->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-ink-muted">
                                <div class="text-[11.5px] font-semibold">{{ class_basename($log->subject_type) }}</div>
                                <div>{{ $log->subject_label ?? '#'.$log->subject_id }}</div>
                            </td>
                            <td class="px-5 py-3">
                                @if (filled($log->changes))
                                    <details>
                                        <summary class="cursor-pointer text-[11.5px] text-navy">
                                            {{ count($log->changes) }} kolom berubah
                                        </summary>
                                        <dl class="mt-1.5 flex flex-col gap-1">
                                            @foreach ($log->changes as $kolom => $ubah)
                                                <div class="text-[11.5px]">
                                                    <dt class="font-semibold">{{ $kolom }}</dt>
                                                    <dd class="text-ink-muted">
                                                        <span class="line-through">{{ $ubah['lama'] ?? $ubah['old'] ?? '—' }}</span>
                                                        <span aria-hidden="true">→</span>
                                                        <span class="font-semibold text-ink">{{ $ubah['baru'] ?? $ubah['new'] ?? '—' }}</span>
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </details>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12">
                                <x-empty-state title="Belum ada catatan aktivitas"
                                    description="Jejak audit ditulis lewat antrean. Bila kosong padahal ada perubahan data, periksa apakah worker antrean menyala." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($daftar->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $daftar->links() }}</div>
        @endif
    </x-card>
@endsection
