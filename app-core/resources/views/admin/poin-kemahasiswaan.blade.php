@extends('layouts.app')

@section('title', 'Poin Kemahasiswaan')

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

    {{-- ============ CARI MAHASISWA ============ --}}
    <x-card class="mb-5" title="Catatan Seorang Mahasiswa">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <label class="flex flex-col gap-1">
                <span class="text-[11px] font-semibold text-ink-muted">NIM</span>
                <input type="text" name="nim" value="{{ request('nim') }}" required
                    class="tabular rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
            </label>
            <x-button type="submit" variant="outline" class="px-4 py-2 text-xs">Tampilkan</x-button>
        </form>

        @if ($mahasiswa && $rekap)
            <div class="mt-4 border-t border-line pt-4">
                <div class="text-[13.5px] font-semibold">{{ $mahasiswa->nama }}</div>
                <div class="tabular text-xs text-ink-muted">
                    {{ $mahasiswa->nim }} · {{ $mahasiswa->prodi?->nama }}
                </div>

                {{-- Dua angka berdampingan, tidak pernah dijumlahkan.
                     Menjumlahkannya berarti membiarkan prestasi menebus
                     pelanggaran — dan tidak ada bagian kemahasiswaan yang
                     bermaksud begitu. --}}
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="rounded border border-line p-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-faint">
                            Prestasi & kegiatan
                        </div>
                        <div class="tabular mt-1 text-[20px] font-bold text-navy">
                            {{ $rekap['prestasi'] }}
                            @if ($rekap['minimum'] > 0)
                                <span class="text-[13px] font-normal text-ink-muted">
                                    / {{ $rekap['minimum'] }}
                                </span>
                            @endif
                        </div>
                        @if ($rekap['minimum'] > 0)
                            <x-chip :tone="$rekap['memenuhi'] ? 'success' : 'warning'">
                                {{ $rekap['memenuhi'] ? 'Memenuhi syarat lulus' : 'Belum memenuhi' }}
                            </x-chip>
                        @else
                            <div class="text-[11.5px] text-ink-faint">Kampus tidak menetapkan minimum</div>
                        @endif
                    </div>

                    <div class="rounded border border-line p-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-faint">
                            Pelanggaran
                        </div>
                        <div class="tabular mt-1 text-[20px] font-bold text-danger">{{ $rekap['pelanggaran'] }}</div>
                        @if ($rekap['temuan'])
                            {{-- Temuan, bukan sanksi. Sanksi adalah keputusan
                                 orang, dengan alasan tertulis. --}}
                            <x-chip tone="danger">{{ $rekap['temuan'] }}</x-chip>
                        @else
                            <div class="text-[11.5px] text-ink-faint">Belum melewati ambang mana pun</div>
                        @endif
                    </div>
                </div>

                <table class="mt-4 w-full text-[12.5px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-wide text-ink-faint">
                            <th class="py-1.5">Tanggal</th>
                            <th>Kategori</th>
                            <th>Judul</th>
                            <th class="text-right">Poin</th>
                            <th>Status</th>
                            @if ($bolehSimkatmawa)
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayat as $baris)
                            <tr class="border-b border-line/60">
                                <td class="tabular py-1.5">{{ $baris->tanggal->translatedFormat('d/m/Y') }}</td>
                                <td>
                                    <x-chip :tone="$baris->jenis->tone()">{{ $baris->kategori->nama }}</x-chip>
                                </td>
                                <td>{{ $baris->judul }}</td>
                                <td class="tabular text-right">{{ $baris->poin }}</td>
                                <td>
                                    @if ($baris->is_verified)
                                        <span class="text-success">Terverifikasi</span>
                                    @elseif ($baris->ditolak())
                                        <span class="text-danger" title="{{ $baris->alasan_tolak }}">Ditolak</span>
                                    @else
                                        <span class="text-ink-faint">Menunggu</span>
                                    @endif
                                </td>
                                @if ($bolehSimkatmawa)
                                    <td class="text-right">
                                        @if ($baris->jenis === \App\Enums\JenisPoin::Prestasi)
                                            <a href="{{ route('admin.poin-kemahasiswaan.simkatmawa', $baris) }}"
                                                class="text-[11.5px] font-semibold text-navy underline">{{ __('simkatmawa.lengkapi.judul') }}</a>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-3 text-center text-ink-muted">Belum ada catatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="grid gap-5 lg:grid-cols-2">

        {{-- ============ ANTREAN VERIFIKASI ============ --}}
        <x-card title="Menunggu Verifikasi" :meta="$antrean->count().' catatan'" flush>
            <div class="divide-y divide-line/60">
                @forelse ($antrean as $baris)
                    <div class="px-5 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-chip :tone="$baris->jenis->tone()">{{ $baris->kategori->nama }}</x-chip>
                            <span class="text-[13px] font-semibold">{{ $baris->mahasiswa->nama }}</span>
                            <span class="tabular text-[11px] text-ink-faint">{{ $baris->mahasiswa->nim }}</span>
                            <span class="tabular ml-auto text-[12px] font-semibold">{{ $baris->poin }} poin</span>
                        </div>

                        <div class="mt-1 text-[12.5px]">{{ $baris->judul }}</div>

                        @if ($baris->dilaporkan_by_mahasiswa_id)
                            {{-- Dilaporkan dari portal mahasiswa (ADR-040 §3): bukti dan
                                 rincian SIMKATMAWA berasal dari mahasiswa, periksa sebelum
                                 memverifikasi. --}}
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-[11.5px]" data-dilaporkan-mahasiswa>
                                <x-chip tone="info">{{ __('swalayan.prestasi.admin.dilaporkan') }}</x-chip>
                                <span class="tabular text-ink-faint">{{ \App\Support\Format::tanggal($baris->tanggal) }}</span>
                                @if ($baris->bukti_path)
                                    <a href="{{ route('berkas.poin', $baris) }}" class="font-semibold text-navy underline">{{ __('swalayan.prestasi.admin.bukti') }}</a>
                                @endif
                                @if ($baris->jenis === \App\Enums\JenisPoin::Prestasi)
                                    <a href="{{ route('admin.poin-kemahasiswaan.simkatmawa', $baris) }}" class="font-semibold text-navy underline">{{ __('simkatmawa.lengkapi.judul') }}</a>
                                @endif
                            </div>
                            @if ($baris->keterangan)
                                <p class="mt-1 text-[12px] text-ink-muted">{{ $baris->keterangan }}</p>
                            @endif
                        @endif

                        <div class="mt-2 flex flex-wrap items-end gap-2">
                            <form method="POST" action="{{ route('admin.poin-kemahasiswaan.verifikasi', $baris) }}">
                                @csrf
                                <x-button type="submit" class="px-3 py-1.5 text-xs">Verifikasi</x-button>
                            </form>

                            <form method="POST" action="{{ route('admin.poin-kemahasiswaan.tolak', $baris) }}"
                                class="flex flex-1 items-end gap-2">
                                @csrf
                                <input type="text" name="alasan" required maxlength="500" placeholder="Alasan penolakan"
                                    class="min-w-[160px] flex-1 rounded border border-line bg-canvas px-2.5 py-1.5 text-[12px]">
                                <x-button type="submit" variant="outline" class="px-3 py-1.5 text-xs">Tolak</x-button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-muted">
                        Tidak ada catatan yang menunggu verifikasi.
                    </div>
                @endforelse
            </div>
        </x-card>

        <div class="flex flex-col gap-5">

            {{-- ============ CATAT ============ --}}
            <x-card title="Catat Poin">
                <form method="POST" action="{{ route('admin.poin-kemahasiswaan.catat') }}"
                    class="grid gap-3 sm:grid-cols-2">
                    @csrf

                    <x-field label="NIM" name="nim" required :value="request('nim')" />

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-ink-muted">Kategori</span>
                        <select name="poin_kategori_id" required
                            class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                            @foreach ($kategori->where('is_active', true) as $k)
                                <option value="{{ $k->id }}">
                                    [{{ $k->jenis->label() }}] {{ $k->nama }} — {{ $k->poin }} poin
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <x-field label="Tanggal" name="tanggal" type="date" required />
                    <x-field label="Judul" name="judul" required />

                    <div class="sm:col-span-2">
                        <x-field label="Bukti (path berkas)" name="bukti_path"
                            hint="Wajib untuk kategori yang mensyaratkannya." />
                    </div>

                    <div class="sm:col-span-2">
                        <x-button type="submit" class="px-4 py-2 text-xs">Simpan</x-button>
                    </div>
                </form>
            </x-card>

            {{-- ============ KATALOG ============ --}}
            <x-card title="Katalog Poin" :meta="$kategori->count().' kategori'">
                <p class="mb-3 text-[12px] leading-relaxed text-ink-muted">
                    Katalog ada di basis data, bukan di config — daftarnya panjang, berbeda tiap
                    kampus, dan direvisi tiap tahun oleh yang mengelolanya. Nilai poin
                    <strong>disalin</strong> ke tiap catatan saat dibuat, jadi perubahan harga di
                    sini tidak menulis ulang catatan lama.
                </p>

                <form method="POST" action="{{ route('admin.poin-kemahasiswaan.kategori') }}"
                    class="grid gap-2.5 sm:grid-cols-2">
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
                        <span class="text-[11px] font-semibold text-ink-muted">Tingkat</span>
                        <select name="tingkat"
                            class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                            <option value="">—</option>
                            @foreach ($tingkatOptions as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <x-field label="Poin" name="poin" type="number" required />

                    <x-field :label="__('simkatmawa.katalog.label')" name="simkatmawa_kategori" :options="$simkatmawaOptions" />

                    <label class="flex items-center gap-2 self-end pb-1.5">
                        <input type="checkbox" name="wajib_bukti" value="1" checked class="rounded border-line">
                        <span class="text-[12px]">Wajib bukti</span>
                    </label>

                    <div class="sm:col-span-2">
                        <x-button type="submit" variant="outline" class="px-4 py-2 text-xs">
                            Tambah Kategori
                        </x-button>
                    </div>
                </form>

                @if ($bolehSimkatmawa)
                    {{-- Tabel SIMKATMAWA per kategori prestasi (ADR-037 §1). Katalog
                         lama tidak punya isian ini, jadi ditetapkan di sini. --}}
                    <div class="mt-4 divide-y divide-line/60 border-t border-line pt-2">
                        @foreach ($kategori->where('jenis', \App\Enums\JenisPoin::Prestasi)->where('is_active', true) as $k)
                            <form method="POST" action="{{ route('admin.poin-kemahasiswaan.kategori.simkatmawa', $k) }}"
                                class="flex flex-wrap items-center gap-2 py-2">
                                @csrf
                                <span class="min-w-[160px] flex-1 text-[12.5px]">
                                    <span class="tabular text-ink-faint">{{ $k->kode }}</span> {{ $k->nama }}
                                </span>
                                <select name="simkatmawa_kategori" aria-label="{{ __('simkatmawa.katalog.label') }}"
                                    class="rounded border border-line bg-canvas px-2 py-1 text-[12px]">
                                    <option value="">{{ __('simkatmawa.katalog.tidak_dilaporkan') }}</option>
                                    @foreach ($simkatmawaOptions as $nilai => $label)
                                        <option value="{{ $nilai }}" @selected($k->simkatmawa_kategori?->value === $nilai)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-button type="submit" size="sm" variant="outline">{{ __('simkatmawa.katalog.simpan') }}</x-button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
