@extends('layouts.app')

@section('title', 'Padanan, Konsentrasi & Paket')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($semua->isNotEmpty())
        <div class="mb-5 flex flex-wrap gap-2">
            @foreach ($semua as $k)
                <x-button href="{{ route('admin.kurikulum-lanjutan', ['kurikulum' => $k->uuid]) }}"
                    :variant="$kurikulum?->is($k) ? 'primary' : 'outline'" size="sm">
                    {{ $k->prodi->kode }} · {{ $k->nama }}
                </x-button>
            @endforeach
        </div>
    @endif

    @if ($kurikulum === null)
        <x-card>
            <x-empty-state title="Belum ada kurikulum"
                description="Buat kurikulum lebih dulu di Master Akademik." />
        </x-card>
    @else
        <div class="space-y-5">
            {{-- Padanan --}}
            <x-card title="Padanan Mata Kuliah" :meta="$padanan->count().' padanan'">
                <p class="mb-4 text-[13px] leading-relaxed text-ink-muted">
                    Mahasiswa yang sudah lulus mata kuliah asal terhitung sudah memenuhi mata
                    kuliah penggantinya — sebagai prasyarat, di KRS, dan di syarat kelulusan.
                    <strong>Arahnya satu.</strong> Mata kuliah pengganti biasanya mencakup lebih
                    banyak, dan menerimanya mundur akan meloloskan mahasiswa sekarang dari
                    prasyarat yang silabus lama tidak pernah ajarkan.
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-4 py-3 font-semibold">Sudah lulus</th>
                                <th class="px-4 py-3 font-semibold">Diakui sebagai</th>
                                <th class="px-4 py-3 font-semibold">Catatan</th>
                                <th class="px-4 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($padanan as $p)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="px-4 py-2">
                                        <span class="tabular font-medium">{{ $p->asal_kode }}</span>
                                        <div class="text-[11.5px] text-ink-faint">{{ $p->asal_nama }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="tabular font-medium">{{ $p->tujuan_kode }}</span>
                                        <div class="text-[11.5px] text-ink-faint">{{ $p->tujuan_nama }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-[12px] text-ink-muted">{{ $p->catatan ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right">
                                        @if ($bolehKelola)
                                            <form method="POST" action="{{ route('admin.kurikulum-lanjutan.padanan.hapus') }}">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="mata_kuliah_id" value="{{ $p->mata_kuliah_id }}">
                                                <input type="hidden" name="diakui_sebagai_id" value="{{ $p->diakui_sebagai_id }}">
                                                <x-button type="submit" variant="ghost" size="sm">Hapus</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4">
                                    <x-empty-state title="Belum ada padanan"
                                        description="Diperlukan begitu kurikulum diganti, agar mahasiswa angkatan lama tidak disuruh mengulang." />
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($bolehKelola)
                    <form method="POST" action="{{ route('admin.kurikulum-lanjutan.padanan') }}"
                        class="mt-4 grid gap-3 border-t border-line pt-4 lg:grid-cols-4">
                        @csrf
                        <x-field label="Sudah lulus" name="mata_kuliah_id" :options="$mataKuliahPilihan" required />
                        <x-field label="Diakui sebagai" name="diakui_sebagai_id" :options="$mataKuliahPilihan" required />
                        <x-field label="Catatan" name="catatan" />
                        <div class="flex items-end">
                            <x-button type="submit" size="sm" variant="outline">Tambah padanan</x-button>
                        </div>
                    </form>
                @endif
            </x-card>

            <div class="grid gap-5 xl:grid-cols-2">
                {{-- Konsentrasi --}}
                <x-card title="Konsentrasi" :meta="$konsentrasi->count().' jalur'">
                    <ul class="space-y-2">
                        @forelse ($konsentrasi as $k)
                            <li class="border-b border-line/50 pb-2 last:border-b-0">
                                <div class="flex items-baseline justify-between gap-3 text-[13px]">
                                    <span><span class="font-semibold">{{ $k->kode }}</span> {{ $k->nama }}</span>
                                    <span class="tabular shrink-0 text-[11.5px] text-ink-faint">
                                        {{ $k->mahasiswa_count }} mahasiswa
                                    </span>
                                </div>
                                @if ($k->sks_wajib > 0)
                                    <div class="text-[11.5px] text-ink-faint">{{ $k->sks_wajib }} SKS wajib jalur</div>
                                @endif
                            </li>
                        @empty
                            <li class="text-[13px] text-ink-muted">Belum ada konsentrasi pada kurikulum ini.</li>
                        @endforelse
                    </ul>

                    @if ($bolehKelola)
                        <form method="POST" action="{{ route('admin.kurikulum-lanjutan.konsentrasi', $kurikulum) }}"
                            class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                            @csrf
                            <x-field label="Kode" name="kode" required />
                            <x-field label="Nama" name="nama" required />
                            <x-field label="SKS wajib jalur" name="sks_wajib" type="number" />
                            <div class="flex items-end">
                                <x-button type="submit" size="sm" variant="outline">Tambah</x-button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.kurikulum-lanjutan.petakan', $kurikulum) }}"
                            class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                            @csrf
                            <x-field label="Mata kuliah" name="mata_kuliah_id" :options="$mataKuliahPilihan" required
                                class="sm:col-span-2" />
                            <x-field label="Milik konsentrasi" name="konsentrasi_id" :options="$konsentrasiPilihan"
                                hint="Kosongkan untuk menjadikannya mata kuliah bersama." />
                            <div class="flex items-end">
                                <x-button type="submit" size="sm" variant="outline">Petakan</x-button>
                            </div>
                        </form>
                    @endif
                </x-card>

                {{-- Paket --}}
                <x-card title="Paket Kuliah" :meta="$paket->count().' paket'">
                    <p class="mb-3 text-[12.5px] leading-relaxed text-ink-muted">
                        Untuk prodi yang KRS-nya ditetapkan, bukan dipilih. Aturan yang berlaku
                        sama persis — yang berubah hanya siapa yang memilih.
                    </p>

                    <ul class="space-y-2">
                        @forelse ($paket as $p)
                            <li class="border-b border-line/50 pb-2 last:border-b-0 text-[13px]">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="font-medium">Semester {{ $p->semester_ke }} — {{ $p->nama }}</span>
                                    <span class="tabular shrink-0 text-[11.5px] text-ink-faint">
                                        {{ $p->mataKuliah->count() }} MK · {{ $p->totalSks() }} SKS
                                    </span>
                                </div>
                                <div class="text-[11.5px] text-ink-faint">
                                    {{ $p->konsentrasi?->nama ?? 'Semua konsentrasi' }}
                                </div>
                            </li>
                        @empty
                            <li class="text-[13px] text-ink-muted">Belum ada paket pada kurikulum ini.</li>
                        @endforelse
                    </ul>

                    @if ($bolehKelola)
                        <form method="POST" action="{{ route('admin.kurikulum-lanjutan.paket', $kurikulum) }}"
                            class="mt-4 space-y-3 border-t border-line pt-4">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field label="Nama paket" name="nama" required />
                                <x-field label="Semester ke" name="semester_ke" type="number" required />
                            </div>
                            <x-field label="Konsentrasi" name="konsentrasi_id" :options="$konsentrasiPilihan"
                                hint="Kosongkan bila paket berlaku untuk semua." />

                            <div>
                                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                    Mata kuliah
                                </span>
                                <div class="max-h-56 space-y-1 overflow-y-auto rounded-control border border-line-input p-2">
                                    @foreach ($mataKuliahPilihan as $id => $label)
                                        <label class="flex cursor-pointer items-center gap-2 text-[12.5px]">
                                            <input type="checkbox" name="mata_kuliah[]" value="{{ $id }}" class="accent-navy">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <x-button type="submit" size="sm" variant="outline">Simpan paket</x-button>
                        </form>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
@endsection
