@extends('layouts.app')

@section('title', 'Wisuda')

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-5">
            {{-- Periode --}}
            <x-card title="Periode Wisuda" flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Periode</th>
                                <th class="px-5 py-3 font-semibold">Tanggal</th>
                                <th class="px-5 py-3 text-center font-semibold">Peserta</th>
                                <th class="px-5 py-3 text-center font-semibold">Pendaftaran</th>
                                <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftarPeriode as $p)
                                <tr @class([
                                    'border-b border-line/50 last:border-b-0',
                                    'bg-highlight' => $periode?->id === $p->id,
                                    'odd:bg-zebra' => $periode?->id !== $p->id,
                                ])>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('admin.wisuda', ['periode' => $p->id]) }}"
                                            class="font-medium text-navy hover:underline">{{ $p->nama }}</a>
                                        @if ($p->lokasi)
                                            <div class="text-[11.5px] text-ink-faint">{{ $p->lokasi }}</div>
                                        @endif
                                    </td>
                                    <td class="tabular px-5 py-3 text-ink-muted">
                                        {{ $p->tanggal->translatedFormat('j F Y') }}
                                    </td>
                                    <td class="tabular px-5 py-3 text-center">
                                        {{ $p->peserta_count }}@if ($p->kuota) / {{ $p->kuota }} @endif
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        @if ($p->is_pendaftaran_dibuka)
                                            <x-chip tone="success" dot>Dibuka</x-chip>
                                        @else
                                            <x-chip tone="neutral">Ditutup</x-chip>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($p->is_pendaftaran_dibuka)
                                            <form method="POST" action="{{ route('admin.wisuda.tutup', $p) }}">
                                                @csrf
                                                <x-button type="submit" variant="outline" size="sm">Tutup</x-button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.wisuda.buka', $p) }}">
                                                @csrf
                                                <x-button type="submit" size="sm">Buka</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-12">
                                        <x-empty-state title="Belum ada periode wisuda"
                                            description="Lulusan yang kelulusannya sudah ditetapkan menunggu di sini sampai ada periode untuk mereka." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- Peserta --}}
            @if ($periode)
                <x-card :title="'Peserta — '.$periode->nama" :meta="$peserta->count().' orang'" flush>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 text-center font-semibold">Urut</th>
                                    <th class="px-5 py-3 font-semibold">Lulusan</th>
                                    <th class="px-5 py-3 font-semibold">Program Studi</th>
                                    <th class="px-5 py-3 text-center font-semibold">IPK</th>
                                    <th class="px-5 py-3 font-semibold">Nomor Ijazah</th>
                                    <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($peserta as $ps)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="tabular px-5 py-3 text-center font-semibold">{{ $ps->nomor_urut }}</td>
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $ps->yudisium->mahasiswa->nama }}</div>
                                            <div class="tabular text-[11.5px] text-ink-faint">
                                                {{ $ps->yudisium->mahasiswa->nim }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-ink-muted">
                                            {{ $ps->yudisium->mahasiswa->prodi->namaLengkap() }}
                                        </td>
                                        <td class="tabular px-5 py-3 text-center">
                                            {{ number_format((float) $ps->yudisium->ipk, 2, ',', '.') }}
                                            @if ($ps->yudisium->predikat)
                                                <div class="text-[11px] text-ink-faint">{{ $ps->yudisium->predikat }}</div>
                                            @endif
                                        </td>
                                        <td class="tabular px-5 py-3">
                                            @if ($ps->nomor_ijazah)
                                                {{ $ps->nomor_ijazah }}
                                            @else
                                                <span class="text-ink-faint">belum terbit</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            @if ($ps->nomor_ijazah)
                                                <span class="text-[11.5px] text-ink-faint">terkunci</span>
                                            @else
                                                <form method="POST" action="{{ route('admin.wisuda.peserta.batal', $ps) }}"
                                                    onsubmit="return confirm('Keluarkan {{ $ps->yudisium->mahasiswa->nama }} dari daftar peserta?');">
                                                    @csrf @method('DELETE')
                                                    <x-button type="submit" variant="danger" size="sm">Keluarkan</x-button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-10">
                                            <x-empty-state title="Belum ada peserta"
                                                description="Daftarkan lulusan dari antrean di sebelah." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            {{-- Antrean --}}
            @if ($menunggu->isNotEmpty())
                <x-card title="Menunggu Periode Wisuda" :meta="$menunggu->count().' lulusan'" flush>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Lulusan</th>
                                    <th class="px-5 py-3 font-semibold">Program Studi</th>
                                    <th class="px-5 py-3 font-semibold">Tanggal Lulus</th>
                                    <th class="px-5 py-3 text-right font-semibold">Daftarkan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($menunggu as $y)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $y->mahasiswa->nama }}</div>
                                            <div class="tabular text-[11.5px] text-ink-faint">{{ $y->mahasiswa->nim }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-ink-muted">{{ $y->mahasiswa->prodi->nama }}</td>
                                        <td class="tabular px-5 py-3 text-ink-muted">
                                            {{ $y->tanggal_lulus?->translatedFormat('j M Y') ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            @if ($periode?->is_pendaftaran_dibuka)
                                                <form method="POST" action="{{ route('admin.wisuda.daftarkan', $periode) }}">
                                                    @csrf
                                                    <input type="hidden" name="yudisium_id" value="{{ $y->id }}">
                                                    <x-button type="submit" size="sm">Daftarkan</x-button>
                                                </form>
                                            @else
                                                <span class="text-[11.5px] text-ink-faint">pendaftaran ditutup</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-5">
            <x-card title="Periode Baru">
                <form method="POST" action="{{ route('admin.wisuda.periode.store') }}" class="flex flex-col gap-3.5">
                    @csrf
                    <x-field label="Nama" name="nama" required placeholder="Wisuda Periode I 2026" />
                    <x-field label="Tanggal" name="tanggal" type="date" required />
                    <x-field label="Lokasi" name="lokasi" placeholder="Auditorium Utama" />
                    <x-field label="Kuota" name="kuota" type="number"
                        hint="Kosongkan untuk tanpa batas." />
                    <x-button type="submit" class="w-full">Buat Periode</x-button>
                </form>
            </x-card>

            @if ($periode)
                <x-card title="Pendaftaran Massal">
                    @if ($periode->is_pendaftaran_dibuka)
                        <p class="mb-3.5 text-[13px] leading-relaxed text-ink-muted">
                            Mendaftarkan seluruh lulusan yang kelulusannya sudah ditetapkan dan
                            belum masuk periode mana pun, urut tanggal lulus.
                        </p>
                        <form method="POST" action="{{ route('admin.wisuda.daftarkan-massal', $periode) }}">
                            @csrf
                            <x-button type="submit" class="w-full">Daftarkan {{ $menunggu->count() }} Lulusan</x-button>
                        </form>
                    @else
                        <p class="text-[13px] text-ink-muted">Pendaftaran periode ini sedang ditutup.</p>
                    @endif
                </x-card>

                <x-card title="Terbitkan Nomor Ijazah">
                    <form method="POST" action="{{ route('admin.wisuda.ijazah', $periode) }}"
                        onsubmit="return confirm('Terbitkan nomor ijazah? Nomor yang sudah terbit tidak akan diubah.');">
                        @csrf
                        <x-field label="Pola Nomor" name="pola" required :value="$polaIjazah"
                            hint="Penanda: {tahun}, {prodi}, {urut}." />
                        <x-button type="submit" variant="gold" class="mt-3.5 w-full">Terbitkan</x-button>
                    </form>

                    <p class="mt-4 border-t border-line pt-3 text-[11.5px] leading-relaxed text-ink-muted">
                        Nomor ijazah tercetak pada dokumen yang dipegang seumur hidup dan dikutip
                        di setiap lamaran kerja. Karena itu diterbitkan sekali, tidak pernah
                        dipakai ulang, dan tidak pernah diterbitkan ulang diam-diam.
                    </p>
                </x-card>
            @endif
        </div>
    </div>
@endsection
