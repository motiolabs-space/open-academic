@extends('layouts.app')

@section('title', 'Tahun Akademik')

@section('content')
    @include('admin.master.partials.tabs')

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Kode</th>
                            <th class="px-5 py-3 font-semibold">Semester</th>
                            <th class="px-5 py-3 font-semibold">Rentang</th>
                            <th class="px-5 py-3 font-semibold">Masa KRS</th>
                            <th class="px-5 py-3 text-center font-semibold">Kelas</th>
                            <th class="px-5 py-3 text-center font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $term)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-5 py-3 font-semibold">{{ $term->kode }}</td>
                                <td class="px-5 py-3">{{ $term->nama }}</td>
                                <td class="px-5 py-3 text-ink-muted">
                                    {{ $term->tanggal_mulai->translatedFormat('j M Y') }} –
                                    {{ $term->tanggal_selesai->translatedFormat('j M Y') }}
                                </td>
                                <td class="px-5 py-3 text-ink-muted">
                                    @if ($term->krs_mulai)
                                        {{ $term->krs_mulai->translatedFormat('j M') }} –
                                        {{ ($term->krs_perubahan_selesai ?? $term->krs_selesai)?->translatedFormat('j M') }}
                                        @if ($term->krsDibuka())
                                            <x-chip tone="success" class="ml-1">dibuka</x-chip>
                                        @endif
                                    @else
                                        <span class="text-ink-faint">belum diatur</span>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3 text-center">{{ $term->kelas_kuliah_count }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if ($term->is_active)
                                        <x-chip tone="success" dot>Berjalan</x-chip>
                                    @elseif ($term->is_locked)
                                        <x-chip tone="neutral">Terkunci</x-chip>
                                    @else
                                        <x-chip tone="warning">Tidak aktif</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        @unless ($term->is_active || $term->is_locked)
                                            <form method="POST" action="{{ route('admin.master.term.aktifkan', $term) }}"
                                                onsubmit="return confirm('Jadikan {{ $term->nama }} semester berjalan? Semester aktif saat ini akan dinonaktifkan.');">
                                                @csrf
                                                <x-button type="submit" size="sm">Aktifkan</x-button>
                                            </form>
                                        @endunless

                                        @if ($term->is_locked)
                                            <form method="POST" action="{{ route('admin.master.term.buka-kunci', $term) }}"
                                                x-data
                                                @submit="$el.alasan.value = window.prompt('Alasan membuka kunci semester ini?') ?? ''; if (! $el.alasan.value) $event.preventDefault();">
                                                @csrf
                                                <input type="hidden" name="alasan">
                                                <x-button type="submit" variant="outline" size="sm">Buka Kunci</x-button>
                                            </form>
                                        @elseif (! $term->is_active)
                                            <form method="POST" action="{{ route('admin.master.term.kunci', $term) }}"
                                                onsubmit="return confirm('Kunci {{ $term->nama }}? Tidak akan ada lagi perubahan yang diizinkan.');">
                                                @csrf
                                                <x-button type="submit" variant="outline" size="sm">Kunci</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12">
                                    <x-empty-state
                                        title="Belum ada tahun akademik"
                                        description="Portal akan menolak setiap permintaan sampai satu semester ditetapkan aktif. Buat semester pertama di formulir sebelah."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Semester Baru">
            <form method="POST" action="{{ route('admin.master.term.store') }}" class="flex flex-col gap-3.5">
                @csrf

                <x-field label="Tahun Mulai" name="tahun_mulai" type="number" required
                    :value="now()->year" hint="Kode PDDIKTI dibentuk otomatis dari tahun dan semester." />

                <x-field label="Semester" name="semester" required
                    :options="collect($semesterPilihan)->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />

                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Mulai" name="tanggal_mulai" type="date" required />
                    <x-field label="Selesai" name="tanggal_selesai" type="date" required />
                </div>

                <div class="mt-1 border-t border-line pt-3">
                    <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        Gerbang Kalender
                    </p>
                    <div class="flex flex-col gap-3">
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="KRS Dibuka" name="krs_mulai" type="date" />
                            <x-field label="KRS Ditutup" name="krs_selesai" type="date" />
                        </div>
                        <x-field label="Perubahan KRS Ditutup" name="krs_perubahan_selesai" type="date" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="Nilai Dibuka" name="nilai_mulai" type="date" />
                            <x-field label="Nilai Ditutup" name="nilai_selesai" type="date" />
                        </div>
                    </div>
                </div>

                <x-button type="submit" class="mt-1 w-full">Buat Semester</x-button>
            </form>
        </x-card>
    </div>
@endsection
