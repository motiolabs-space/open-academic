@extends('layouts.app')

@section('title', 'Jadwal & Kelas')

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($belumSiap['dosen'] > 0 || $belumSiap['jadwal'] > 0)
        <div class="mb-5">
            <x-alert tone="warning">
                Semester ini belum siap dijalankan:
                @if ($belumSiap['dosen'] > 0)
                    <strong>{{ $belumSiap['dosen'] }} kelas tanpa dosen</strong>
                @endif
                @if ($belumSiap['dosen'] > 0 && $belumSiap['jadwal'] > 0) dan @endif
                @if ($belumSiap['jadwal'] > 0)
                    <strong>{{ $belumSiap['jadwal'] }} kelas tanpa jadwal</strong>
                @endif.
                Keduanya tidak menimbulkan galat apa pun sampai masa KRS dibuka.
            </x-alert>
        </div>
    @endif

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field label="Cari Mata Kuliah" name="cari" type="search" :value="$filter['cari'] ?? ''"
                placeholder="Kode atau nama…" class="min-w-[200px] flex-1" />
            <x-field label="Semester" name="term" :value="$filter['term'] ?? $term?->id"
                :options="$daftarTerm->pluck('nama', 'id')" />
            <x-field label="Program Studi" name="prodi" :value="$filter['prodi'] ?? ''"
                :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />

            <label class="flex items-center gap-2 pb-2.5 text-[13px]">
                <input type="checkbox" name="tanpa_dosen" value="1" class="accent-navy"
                    @checked($filter['tanpa_dosen'] ?? false)>
                Tanpa dosen
            </label>
            <label class="flex items-center gap-2 pb-2.5 text-[13px]">
                <input type="checkbox" name="tanpa_jadwal" value="1" class="accent-navy"
                    @checked($filter['tanpa_jadwal'] ?? false)>
                Tanpa jadwal
            </label>

            <x-button type="submit">Terapkan</x-button>
            @if (array_filter($filter))
                <x-button variant="ghost" :href="route('admin.kelas')">Reset</x-button>
            @endif
        </form>
    </x-card>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Kelas</th>
                            <th class="px-5 py-3 font-semibold">Dosen Pengampu</th>
                            <th class="px-5 py-3 font-semibold">Jadwal</th>
                            <th class="px-5 py-3 text-center font-semibold">Kuota</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $k)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $k->mataKuliah->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">
                                        {{ $k->mataKuliah->kode }} · Kelas {{ $k->kode }} · {{ $k->sks }} SKS
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if ($k->mode !== 'tatap_muka')
                                            <x-chip tone="info">{{ str_replace('_', ' ', $k->mode) }}</x-chip>
                                        @endif
                                        @if ($k->is_case_method)
                                            <x-chip tone="gold">case method</x-chip>
                                        @endif
                                        @if ($k->is_team_based_project)
                                            <x-chip tone="gold">TBP</x-chip>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-3">
                                    @forelse ($k->dosen as $d)
                                        <form method="POST" class="mb-1 inline-block"
                                            action="{{ route('admin.kelas.dosen.lepas', [$k, $d]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-full border border-line bg-line/40 px-2.5 py-1 text-[11.5px] hover:border-danger-line hover:text-danger"
                                                title="Lepas {{ $d->nama }}">
                                                {{ $d->nama }}
                                                @if ($d->pivot->peran !== 'pengampu')
                                                    <span class="text-ink-faint">({{ $d->pivot->peran }})</span>
                                                @endif
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </form>
                                    @empty
                                        <x-chip tone="danger">belum ada dosen</x-chip>
                                    @endforelse

                                    <details class="mt-1.5">
                                        <summary class="cursor-pointer text-[11.5px] text-navy">+ tugaskan</summary>
                                        <form method="POST" class="mt-2 flex flex-wrap gap-1.5"
                                            action="{{ route('admin.kelas.dosen.tugaskan', $k) }}">
                                            @csrf
                                            <select name="dosen_id" required
                                                class="min-w-0 flex-1 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                @foreach ($daftarDosen as $opsi)
                                                    <option value="{{ $opsi->id }}">
                                                        {{ $opsi->namaLengkap() }}@if ($opsi->is_praktisi) — praktisi @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            <select name="peran"
                                                class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <option value="pengampu">Pengampu</option>
                                                <option value="pendamping">Pendamping</option>
                                                <option value="praktisi">Praktisi (IKU 4)</option>
                                            </select>
                                            <x-button type="submit" size="sm">Simpan</x-button>
                                        </form>
                                    </details>
                                </td>

                                <td class="px-5 py-3">
                                    @forelse ($k->jadwal as $j)
                                        <form method="POST" class="mb-1"
                                            action="{{ route('admin.kelas.jadwal.hapus', [$k, $j]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="tabular inline-flex items-center gap-1 rounded border border-line bg-line/40 px-2 py-1 text-[11.5px] hover:border-danger-line hover:text-danger"
                                                title="Hapus slot ini">
                                                {{ $hariPilihan[$j->hari] ?? '?' }}
                                                {{ \Illuminate\Support\Str::substr($j->jam_mulai, 0, 5) }}–{{ \Illuminate\Support\Str::substr($j->jam_selesai, 0, 5) }}
                                                @if ($j->ruang) · {{ $j->ruang->kode }} @else · <span class="text-warning">tanpa ruang</span> @endif
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </form>
                                    @empty
                                        <x-chip tone="danger">belum dijadwalkan</x-chip>
                                    @endforelse

                                    <details class="mt-1.5">
                                        <summary class="cursor-pointer text-[11.5px] text-navy">+ jadwalkan</summary>
                                        <form method="POST" class="mt-2 flex flex-wrap gap-1.5"
                                            action="{{ route('admin.kelas.jadwal', $k) }}">
                                            @csrf
                                            <select name="hari" required
                                                class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                @foreach ($hariPilihan as $nomor => $nama)
                                                    <option value="{{ $nomor }}">{{ $nama }}</option>
                                                @endforeach
                                            </select>
                                            <input type="time" name="jam_mulai" required value="08:00"
                                                class="tabular rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                            <input type="time" name="jam_selesai" required value="09:40"
                                                class="tabular rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                            <select name="ruang_id"
                                                class="min-w-0 flex-1 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <option value="">Tanpa ruang</option>
                                                @foreach ($daftarRuang as $r)
                                                    <option value="{{ $r->id }}">{{ $r->kode }} ({{ $r->kapasitas }})</option>
                                                @endforeach
                                            </select>
                                            <x-button type="submit" size="sm">Simpan</x-button>
                                        </form>
                                    </details>
                                </td>

                                <td class="px-5 py-3 text-center">
                                    <div class="tabular font-semibold">{{ $k->terisi }} / {{ $k->kuota }}</div>
                                    @if ($k->terdaftar > 0)
                                        <div class="text-[11px] text-ink-faint">{{ $k->terdaftar }} di KRS</div>
                                    @endif
                                </td>

                                <td class="px-5 py-3">
                                    <div class="flex flex-col items-end gap-1.5">
                                        <form method="POST" action="{{ route('admin.kelas.perbarui', $k) }}"
                                            class="flex items-center gap-1.5">
                                            @csrf @method('PUT')
                                            <input type="number" name="kuota" min="1" max="500" value="{{ $k->kuota }}"
                                                class="tabular w-16 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                            <input type="hidden" name="mode" value="{{ $k->mode }}">
                                            <x-button type="submit" variant="outline" size="sm">Ubah</x-button>
                                        </form>

                                        @if ($k->terdaftar === 0)
                                            <form method="POST" action="{{ route('admin.kelas.tutup', $k) }}"
                                                onsubmit="return confirm('Hapus kelas {{ $k->mataKuliah->kode }} {{ $k->kode }}?');">
                                                @csrf @method('DELETE')
                                                <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12">
                                    <x-empty-state title="Belum ada kelas dibuka"
                                        description="Tanpa kelas, katalog KRS kosong dan mahasiswa tidak punya apa pun untuk diambil." />
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

        <x-card title="Buka Kelas Baru">
            <form method="POST" action="{{ route('admin.kelas.buka') }}" class="flex flex-col gap-3.5">
                @csrf

                <x-field label="Semester" name="tahun_akademik_id" required
                    :value="$term?->id" :options="$daftarTerm->pluck('nama', 'id')" />

                <x-field label="Mata Kuliah" name="mata_kuliah_id" required
                    :options="$daftarMk->mapWithKeys(fn ($m) => [$m->id => $m->kode.' — '.$m->nama.' ('.$m->sks.' SKS)'])" />

                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Jumlah Kelas" name="jumlah_kelas" type="number" required :value="1"
                        hint="Kode A, B, C…" />
                    <x-field label="Kuota per Kelas" name="kuota" type="number" required :value="40" />
                </div>

                <x-field label="Mode" name="mode" required
                    :options="['tatap_muka' => 'Tatap Muka', 'daring' => 'Daring', 'hybrid' => 'Hybrid']" />

                <div class="flex flex-col gap-1.5 rounded-card border border-line bg-canvas px-3.5 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Metode IKU 7</p>
                    <label class="flex items-center gap-2 text-[13px]">
                        <input type="hidden" name="is_case_method" value="0">
                        <input type="checkbox" name="is_case_method" value="1" class="accent-navy">
                        Case method
                    </label>
                    <label class="flex items-center gap-2 text-[13px]">
                        <input type="hidden" name="is_team_based_project" value="0">
                        <input type="checkbox" name="is_team_based_project" value="1" class="accent-navy">
                        Team-based project
                    </label>
                </div>

                <x-button type="submit" class="w-full">Buka Kelas</x-button>
            </form>

            <p class="mt-4 border-t border-line pt-3 text-[11.5px] leading-relaxed text-ink-muted">
                Penjadwalan menolak bentrok ruang dan dosen — dua hal yang mustahil secara
                fisik. Kelas sekohor yang beririsan hanya diperingatkan, karena mata kuliah
                pilihan memang lazim bertabrakan.
            </p>
        </x-card>
    </div>
@endsection
