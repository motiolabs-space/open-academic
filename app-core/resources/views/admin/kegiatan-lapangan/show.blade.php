@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', $kegiatan->nama)

@section('aksi')
    @can('update', $kegiatan)
        <x-button href="{{ route('admin.kegiatan-lapangan.edit', $kegiatan) }}" variant="outline" size="sm">{{ __('kegiatan_lapangan.aksi.ubah') }}</x-button>
    @endcan
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if (session('ditolak'))
        <div class="mb-5">
            <x-alert tone="warning">
                <p class="font-semibold">{{ __('kegiatan_lapangan.keterangan.dilewati') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach (session('ditolak') as $nim => $alasan)
                        <li><span class="tabular">{{ $nim }}</span> — {{ $alasan }}</li>
                    @endforeach
                </ul>
            </x-alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('kegiatan_lapangan.kartu.ringkasan_peserta')" :value="Format::bulat($ringkasan['peserta'])" />
        <x-stat-card :label="__('kegiatan_lapangan.kartu.ringkasan_kelompok')" :value="Format::bulat($ringkasan['kelompok'])" />
        <x-stat-card :label="__('kegiatan_lapangan.kartu.ringkasan_dinilai')" :value="Format::bulat($ringkasan['dinilai'])" />
        <x-stat-card :label="__('kegiatan_lapangan.kartu.ringkasan_konversi')" :value="Format::bulat($ringkasan['dikonversi'])" />
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0 space-y-5">
            {{-- Kelompok, masing-masing dengan anggota dan nilainya. --}}
            @forelse ($kegiatan->kelompok as $kelompok)
                <x-card flush :title="$kelompok->nama" :meta="$kelompok->lokasi?->wilayah() ?? __('kegiatan_lapangan.keterangan.tanpa_lokasi')">
                    <div class="border-b border-line px-5 py-3 text-[12.5px]">
                        <span class="text-ink-muted">{{ __('kegiatan_lapangan.field.dosen_pembimbing_id') }}:</span>
                        {{ $kelompok->pembimbing?->namaLengkap() ?? '—' }}
                        <span class="mx-2 text-ink-faint">·</span>
                        <span class="text-ink-muted">{{ __('kegiatan_lapangan.field.pembimbing_mitra_nama') }}:</span>
                        {{ $kelompok->pembimbing_mitra_nama ?? '—' }}
                        @if ($kelompok->pembimbing_mitra_jabatan)
                            <span class="text-ink-faint">({{ $kelompok->pembimbing_mitra_jabatan }})</span>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.kegiatan-lapangan.kelompok.nilai', $kelompok) }}">
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-[13px]">
                                <thead>
                                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                        <th class="px-5 py-2.5 font-semibold">{{ __('kegiatan_lapangan.kolom.mahasiswa') }}</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.logbook') }}</th>
                                        <th class="px-3 py-2.5 font-semibold">{{ __('kegiatan_lapangan.kolom.nilai_pembimbing') }}</th>
                                        <th class="px-3 py-2.5 font-semibold">{{ __('kegiatan_lapangan.kolom.nilai_mitra') }}</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.nilai_akhir') }}</th>
                                        <th class="px-5 py-2.5"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($kelompok->anggota->sortBy(fn ($a) => $a->mahasiswa->nim) as $a)
                                        @php $terkunci = $a->krsKonversi !== null; @endphp
                                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                            <td class="px-5 py-2.5">
                                                <div class="font-medium">
                                                    {{ $a->mahasiswa->nama }}
                                                    @if ($a->is_ketua_kelompok)
                                                        <x-chip tone="info">{{ __('kegiatan_lapangan.keterangan.ketua') }}</x-chip>
                                                    @endif
                                                </div>
                                                <div class="tabular text-[11.5px] text-ink-faint">{{ $a->mahasiswa->nim }} · {{ $a->mahasiswa->prodi->nama }} · {{ $a->mahasiswa->jenis_kelamin?->value ?? '—' }}</div>
                                            </td>
                                            <td class="tabular px-3 py-2.5 text-right">{{ Format::bulat($a->logbook_count) }}</td>
                                            <td class="px-3 py-2.5">
                                                <input type="number" step="0.01" min="0" max="100" name="nilai[{{ $a->uuid }}][pembimbing]" value="{{ $a->nilai_pembimbing }}" @disabled($terkunci)
                                                    class="tabular w-20 rounded-control border border-line-input bg-surface px-2 py-1 text-[13px]" aria-label="{{ __('kegiatan_lapangan.kolom.nilai_pembimbing') }} {{ $a->mahasiswa->nim }}">
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <input type="number" step="0.01" min="0" max="100" name="nilai[{{ $a->uuid }}][mitra]" value="{{ $a->nilai_mitra }}" @disabled($terkunci)
                                                    class="tabular w-20 rounded-control border border-line-input bg-surface px-2 py-1 text-[13px]" aria-label="{{ __('kegiatan_lapangan.kolom.nilai_mitra') }} {{ $a->mahasiswa->nim }}">
                                            </td>
                                            <td class="tabular px-3 py-2.5 text-right">
                                                {{ $a->nilai_akhir !== null ? Format::angka((float) $a->nilai_akhir) : '—' }}
                                                @if ($terkunci)
                                                    <div><x-chip tone="success">{{ __('kegiatan_lapangan.keterangan.dikonversi') }}</x-chip></div>
                                                @endif
                                            </td>
                                            <td class="px-5 py-2.5 text-right">
                                                @unless ($terkunci)
                                                    <details class="text-left">
                                                        <summary class="cursor-pointer text-[12px] text-navy">{{ __('kegiatan_lapangan.aksi.pindah') }}</summary>
                                                        <div class="mt-2 flex gap-1">
                                                            <select form="pindah-{{ $a->uuid }}" name="kelompok" class="rounded-control border border-line-input bg-surface px-2 py-1 text-[12px]" aria-label="{{ __('kegiatan_lapangan.aksi.pindah') }}">
                                                                <option value="">{{ __('kegiatan_lapangan.keterangan.tanpa_kelompok') }}</option>
                                                                @foreach ($pilihanKelompok as $uuid => $nama)
                                                                    <option value="{{ $uuid }}" @selected($uuid === $kelompok->uuid)>{{ $nama }}</option>
                                                                @endforeach
                                                            </select>
                                                            <x-button size="sm" variant="outline" type="submit" form="pindah-{{ $a->uuid }}">{{ __('kegiatan_lapangan.aksi.simpan') }}</x-button>
                                                        </div>
                                                    </details>
                                                @endunless
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($kelompok->anggota->isNotEmpty())
                            <div class="flex justify-end border-t border-line px-5 py-3">
                                <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.simpan_nilai') }}</x-button>
                            </div>
                        @endif
                    </form>

                    @foreach ($kelompok->anggota as $a)
                        <form id="pindah-{{ $a->uuid }}" method="POST" action="{{ route('admin.kegiatan-lapangan.peserta.pindah', $a) }}" class="hidden">@csrf</form>
                    @endforeach

                    <details class="border-t border-line px-5 py-3">
                        <summary class="cursor-pointer text-[12.5px] font-medium text-navy">{{ __('kegiatan_lapangan.aksi.atur_kelompok') }}</summary>
                        <form method="POST" action="{{ route('admin.kegiatan-lapangan.kelompok.atur', $kelompok) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <x-field :label="__('kegiatan_lapangan.field.lokasi_id')" name="lokasi_id" :options="$daftarLokasi" :value="$kelompok->lokasi_id" />
                            <x-field :label="__('kegiatan_lapangan.field.dosen_pembimbing_id')" name="dosen_pembimbing_id" :options="$daftarDosen" :value="$kelompok->dosen_pembimbing_id" />
                            <x-field :label="__('kegiatan_lapangan.field.pembimbing_mitra_nama')" name="pembimbing_mitra_nama" :value="$kelompok->pembimbing_mitra_nama" />
                            <x-field :label="__('kegiatan_lapangan.field.pembimbing_mitra_jabatan')" name="pembimbing_mitra_jabatan" :value="$kelompok->pembimbing_mitra_jabatan" />
                            <x-field :label="__('kegiatan_lapangan.field.pembimbing_mitra_telepon')" name="pembimbing_mitra_telepon" :value="$kelompok->pembimbing_mitra_telepon" />
                            <x-field :label="__('kegiatan_lapangan.field.ketua_aktivitas_id')" name="ketua_aktivitas_id"
                                :options="$kelompok->anggota->mapWithKeys(fn ($a) => [$a->id => $a->mahasiswa->nim.' · '.$a->mahasiswa->nama])"
                                :value="$kelompok->anggota->firstWhere('is_ketua_kelompok', true)?->id" />
                            <div class="flex gap-2 sm:col-span-2">
                                <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.simpan') }}</x-button>
                            </div>
                        </form>
                        @if ($kelompok->anggota->isEmpty())
                            <form method="POST" action="{{ route('admin.kegiatan-lapangan.kelompok.hapus', $kelompok) }}" class="mt-2">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" size="sm" variant="outline">{{ __('kegiatan_lapangan.aksi.hapus_kelompok') }}</x-button>
                            </form>
                        @endif
                    </details>
                </x-card>
            @empty
                <x-empty-state :title="__('kegiatan_lapangan.keterangan.belum_ada_kelompok')" :description="__('kegiatan_lapangan.keterangan.belum_ada_kelompok_deskripsi')" />
            @endforelse

            {{-- Peserta yang belum masuk kelompok. --}}
            <x-card flush :title="__('kegiatan_lapangan.kartu.peserta_lepas')" :meta="Format::bulat($lepas->count())">
                @if ($lepas->isEmpty())
                    <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('kegiatan_lapangan.keterangan.semua_berkelompok') }}</p>
                @else
                    <ul class="divide-y divide-line/60 text-[13px]">
                        @foreach ($lepas as $a)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5">
                                <div>
                                    <span class="font-medium">{{ $a->mahasiswa->nama }}</span>
                                    <span class="tabular text-[11.5px] text-ink-faint">{{ $a->mahasiswa->nim }} · {{ $a->mahasiswa->prodi->nama }}</span>
                                </div>
                                <div class="flex gap-1">
                                    @if ($pilihanKelompok->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.kegiatan-lapangan.peserta.pindah', $a) }}" class="flex gap-1">
                                            @csrf
                                            <select name="kelompok" class="rounded-control border border-line-input bg-surface px-2 py-1 text-[12px]" aria-label="{{ __('kegiatan_lapangan.aksi.pindah') }}">
                                                @foreach ($pilihanKelompok as $uuid => $nama)
                                                    <option value="{{ $uuid }}">{{ $nama }}</option>
                                                @endforeach
                                            </select>
                                            <x-button size="sm" variant="outline" type="submit">{{ __('kegiatan_lapangan.aksi.masukkan') }}</x-button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.kegiatan-lapangan.peserta.keluar', $a) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button size="sm" variant="outline" type="submit">{{ __('kegiatan_lapangan.aksi.keluarkan') }}</x-button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex gap-2 border-t border-line px-5 py-3">
                        <form method="POST" action="{{ route('admin.kegiatan-lapangan.bentuk', $kegiatan) }}">
                            @csrf
                            <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.bentuk') }}</x-button>
                        </form>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card :title="__('kegiatan_lapangan.kartu.aturan')">
                <dl class="space-y-1.5 text-[12.5px]">
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.field.tanggal_mulai') }}</dt><dd class="tabular">{{ Format::tanggal($kegiatan->tanggal_mulai) }} – {{ Format::tanggal($kegiatan->tanggal_selesai) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.field.nomor_sk') }}</dt><dd>{{ $kegiatan->nomor_sk ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.field.min_sks') }}</dt><dd class="tabular">{{ Format::bulat($kegiatan->min_sks) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.field.ukuran_kelompok') }}</dt><dd class="tabular">{{ Format::bulat($kegiatan->ukuran_kelompok) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.field.campur_prodi') }}</dt><dd>{{ $kegiatan->campur_prodi ? __('kegiatan_lapangan.keterangan.ya') : __('kegiatan_lapangan.keterangan.tidak') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.field.campur_jenis_kelamin') }}</dt><dd>{{ $kegiatan->campur_jenis_kelamin ? __('kegiatan_lapangan.keterangan.ya') : __('kegiatan_lapangan.keterangan.tidak') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.field.bobot_mitra') }}</dt><dd class="tabular">{{ Format::bulat($kegiatan->bobot_mitra) }}%</dd></div>
                </dl>
            </x-card>

            <x-card :title="__('kegiatan_lapangan.kartu.tambah_peserta')">
                <form method="POST" action="{{ route('admin.kegiatan-lapangan.peserta', $kegiatan) }}" class="space-y-3">
                    @csrf
                    <x-field :label="__('kegiatan_lapangan.field.nim')" name="nim" type="textarea" required :placeholder="__('kegiatan_lapangan.petunjuk.nim')" />
                    <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.daftarkan') }}</x-button>
                </form>
            </x-card>

            <x-card flush :title="__('kegiatan_lapangan.kartu.lokasi')">
                @if ($kegiatan->lokasi->isNotEmpty())
                    <ul class="divide-y divide-line/60 text-[13px]">
                        @foreach ($kegiatan->lokasi as $l)
                            <li class="flex items-start justify-between gap-2 px-5 py-2.5">
                                <div class="min-w-0">
                                    <div class="font-medium">{{ $l->nama }} <span class="text-[11.5px] text-ink-faint">· {{ $l->jenis->label() }}</span></div>
                                    <div class="text-[11.5px] text-ink-faint">{{ $l->wilayah() }}</div>
                                    <div class="tabular text-[11.5px] text-ink-muted">{{ __('kegiatan_lapangan.keterangan.kuota', ['terisi' => $l->kelompok_count, 'kuota' => $l->kuota_kelompok]) }}</div>
                                </div>
                                @if ($l->kelompok_count === 0)
                                    <form method="POST" action="{{ route('admin.kegiatan-lapangan.lokasi.hapus', $l) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button size="sm" variant="outline" type="submit">{{ __('kegiatan_lapangan.aksi.hapus') }}</x-button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.kegiatan-lapangan.lokasi', $kegiatan) }}" class="grid gap-3 border-t border-line px-5 py-4">
                    @csrf
                    <x-field :label="__('kegiatan_lapangan.field.jenis_lokasi')" name="jenis" :options="$jenisLokasi" required />
                    <x-field :label="__('kegiatan_lapangan.field.nama_lokasi')" name="nama" required />
                    <x-field :label="__('kegiatan_lapangan.field.kecamatan')" name="kecamatan" />
                    <x-field :label="__('kegiatan_lapangan.field.kabupaten')" name="kabupaten" />
                    <x-field :label="__('kegiatan_lapangan.field.provinsi')" name="provinsi" />
                    <x-field :label="__('kegiatan_lapangan.field.kuota_kelompok')" name="kuota_kelompok" type="number" value="1" required />
                    <div><x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.tambah_lokasi') }}</x-button></div>
                </form>
            </x-card>

            <x-card :title="__('kegiatan_lapangan.kartu.kelompok_manual')">
                <form method="POST" action="{{ route('admin.kegiatan-lapangan.kelompok', $kegiatan) }}" class="space-y-3">
                    @csrf
                    <x-field :label="__('kegiatan_lapangan.field.lokasi_id')" name="lokasi_id" :options="$daftarLokasi" />
                    <x-field :label="__('kegiatan_lapangan.field.dosen_pembimbing_id')" name="dosen_pembimbing_id" :options="$daftarDosen" />
                    <x-button type="submit" size="sm" variant="outline">{{ __('kegiatan_lapangan.aksi.buat_kelompok') }}</x-button>
                </form>
            </x-card>

            @can('konversi', $kegiatan)
                <x-card :title="__('kegiatan_lapangan.kartu.konversi')" :meta="__('kegiatan_lapangan.petunjuk.konversi_meta')">
                    <p class="mb-3 text-[12.5px] leading-relaxed text-ink-muted">{{ __('kegiatan_lapangan.petunjuk.konversi') }}</p>
                    <form method="POST" action="{{ route('admin.kegiatan-lapangan.konversi', $kegiatan) }}" class="space-y-3">
                        @csrf
                        <x-field :label="__('kegiatan_lapangan.field.kelas_kuliah_id')" name="kelas_kuliah_id" :options="$pilihanKelas" required />
                        <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.konversi') }}</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
@endsection
