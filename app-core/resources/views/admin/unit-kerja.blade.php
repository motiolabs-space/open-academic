@extends('layouts.app')

@section('title', 'Unit Kerja')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($belumTerpetakan->isNotEmpty())
        {{-- Sisa yang tidak berhasil dipetakan backfill. Dibiarkan terlihat,
             bukan disembunyikan: staf tanpa unit tidak muncul di laporan
             mana pun, dan itu justru kegagalan yang dulu disebabkan kolom
             teks bebas. --}}
        <x-alert tone="warning" class="mb-5">
            <strong>{{ $belumTerpetakan->count() }} staf belum punya unit kerja.</strong>
            Mereka tidak akan terhitung pada rekap unit mana pun sampai ditempatkan.
        </x-alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">

        {{-- ============ POHON ============ --}}
        <x-card title="Struktur Organisasi" :meta="$pohon->count().' unit'" flush>
            <table class="w-full text-[12.5px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-wide text-ink-faint">
                        <th class="py-2 pl-5">Unit</th>
                        <th>Kepala</th>
                        <th class="text-right">Staf</th>
                        <th class="pr-5 text-right">+ Bawahan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pohon as $unit)
                        <tr class="border-b border-line/60 {{ $unit->is_active ? '' : 'opacity-50' }}">
                            <td class="py-2 pl-5">
                                <div class="font-semibold">{{ $unit->nama }}</div>
                                <div class="text-[11px] text-ink-faint">
                                    {{ $rekap[$unit->id]['jalur'] }}
                                </div>
                                <x-chip tone="neutral">{{ $unit->jenis->label() }}</x-chip>
                                @unless ($unit->is_active)
                                    <x-chip tone="danger">nonaktif</x-chip>
                                @endunless
                            </td>
                            <td>{{ $unit->kepala()?->nama ?? '—' }}</td>
                            <td class="tabular text-right">{{ $unit->staf_count }}</td>
                            <td class="tabular pr-5 text-right font-semibold">
                                {{ $rekap[$unit->id]['total'] }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-ink-muted">Belum ada unit kerja.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <p class="border-t border-line px-5 py-3 text-[11.5px] leading-relaxed text-ink-faint">
                Kolom <strong>+ Bawahan</strong> adalah jumlah staf termasuk seluruh unit di
                bawahnya — angka yang sebenarnya ditanyakan seorang kepala biro, bukan
                "berapa yang tercatat persis di level saya".
            </p>
        </x-card>

        {{-- ============ TAMBAH ============ --}}
        <x-card title="Tambah Unit">
            <form method="POST" action="{{ route('admin.unit-kerja.simpan') }}" class="grid gap-3 sm:grid-cols-2">
                @csrf

                <x-field label="Kode" name="kode" required />
                <x-field label="Nama" name="nama" required />

                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-ink-muted">Jenis</span>
                    <select name="jenis" required
                        class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                        @foreach ($jenisOptions as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-ink-muted">Induk</span>
                    <select name="parent_id"
                        class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                        <option value="">— unit puncak —</option>
                        @foreach ($pohon as $u)
                            <option value="{{ $u->id }}">{{ $rekap[$u->id]['jalur'] }}</option>
                        @endforeach
                    </select>
                </label>

                {{-- Dua pilihan kepala, dan hanya satu boleh terisi: dekan itu
                     dosen, kepala biro itu staf. Memaksakan satu tabel berarti
                     mengarang baris palsu di tabel yang lain. --}}
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-ink-muted">Kepala (staf)</span>
                    <select name="kepala_staff_id"
                        class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                        <option value="">—</option>
                        @foreach ($calonStaf as $s)
                            <option value="{{ $s->id }}">{{ $s->nama }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-ink-muted">Kepala (dosen)</span>
                    <select name="kepala_dosen_id"
                        class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                        <option value="">—</option>
                        @foreach ($calonDosen as $d)
                            <option value="{{ $d->id }}">{{ $d->nama }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="sm:col-span-2">
                    <x-button type="submit" class="px-4 py-2 text-xs">Tambah Unit</x-button>
                </div>
            </form>

            @if ($belumTerpetakan->isNotEmpty())
                <div class="mt-5 border-t border-line pt-4">
                    <h3 class="mb-2 text-[13px] font-semibold">Tempatkan Staf</h3>

                    @foreach ($belumTerpetakan->take(10) as $staf)
                        <form method="POST" action="{{ route('admin.unit-kerja.pindah-staf', $staf) }}"
                            class="mb-2 flex items-end gap-2">
                            @csrf
                            <span class="flex-1 text-[12.5px]">
                                {{ $staf->nama }}
                                @if ($staf->unit)
                                    <span class="text-ink-faint">(dulu: {{ $staf->unit }})</span>
                                @endif
                            </span>
                            <select name="unit_kerja_id" required
                                class="rounded border border-line bg-canvas px-2 py-1 text-[12px]">
                                @foreach ($pohon->where('is_active', true) as $u)
                                    <option value="{{ $u->id }}">{{ $u->nama }}</option>
                                @endforeach
                            </select>
                            <x-button type="submit" variant="outline" class="px-3 py-1 text-xs">Simpan</x-button>
                        </form>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
@endsection
