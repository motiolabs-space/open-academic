@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Surat & Dokumen')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-field label="Cari" name="cari" :value="$filter['cari'] ?? null"
                placeholder="Nomor, nama, atau NIM" />
            <x-field label="Jenis" name="jenis" :options="$jenisPilihan" :value="$filter['jenis'] ?? null" />
            <x-field label="Status" name="status" :options="$statusPilihan" :value="$filter['status'] ?? null" />
            <div class="flex items-end gap-2">
                <x-button type="submit">Terapkan</x-button>
                <x-button href="{{ route('admin.surat') }}" variant="outline">Reset</x-button>
            </div>
        </form>
    </x-card>

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Pemohon</th>
                        <th class="px-5 py-3 font-semibold">Jenis & Keperluan</th>
                        <th class="px-5 py-3 font-semibold">Nomor</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $s)
                        <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $s->mahasiswa->nama }}</div>
                                <div class="tabular text-[11.5px] text-ink-faint">
                                    {{ $s->mahasiswa->nim }} · {{ $s->mahasiswa->prodi->nama }}
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div>{{ $s->jenis->label() }}</div>
                                @if ($s->keperluan)
                                    <p class="mt-0.5 max-w-xs text-[12px] text-ink-muted">{{ $s->keperluan }}</p>
                                @endif
                                <div class="tabular mt-0.5 text-[11px] text-ink-faint">
                                    Diajukan {{ Format::tanggal($s->diajukan_at) }}
                                </div>
                            </td>
                            <td class="tabular px-5 py-3">
                                {{ $s->nomor ?? '—' }}
                                @if ($s->penerbit)
                                    <div class="text-[11px] text-ink-faint">{{ $s->penerbit->nama }}</div>
                                @elseif ($s->status->dapatDiunduh())
                                    {{-- Jalur swalayan: kolom penerbit sengaja kosong,
                                         bukan diisi siapa pun yang kebetulan sedang masuk. --}}
                                    <div class="text-[11px] text-ink-faint">Terbit otomatis</div>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-chip :tone="$s->status->tone()">{{ $s->status->label() }}</x-chip>
                                @if ($s->alasan)
                                    <p class="mt-1 max-w-xs text-[12px] text-ink-muted">{{ $s->alasan }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    @if ($s->status === \App\Enums\StatusSurat::Diajukan)
                                        <form method="POST" action="{{ route('admin.surat.terbitkan', $s) }}">
                                            @csrf
                                            <x-button type="submit" size="sm">Terbitkan</x-button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.surat.tolak', $s) }}"
                                            class="flex items-end gap-1.5">
                                            @csrf
                                            <x-field label="Alasan" name="alasan" class="w-44" required />
                                            <x-button type="submit" variant="outline" size="sm">Tolak</x-button>
                                        </form>
                                    @endif

                                    @if ($s->status->dapatDiunduh())
                                        <x-button href="{{ route('admin.surat.unduh', $s) }}" variant="outline" size="sm">
                                            PDF
                                        </x-button>

                                        <form method="POST" action="{{ route('admin.surat.cabut', $s) }}"
                                            class="flex items-end gap-1.5">
                                            @csrf
                                            <x-field label="Alasan cabut" name="alasan" class="w-44" required />
                                            <x-button type="submit" variant="outline" size="sm">Cabut</x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state
                                    title="Belum ada permohonan surat"
                                    description="Permohonan mahasiswa akan muncul di sini untuk diputuskan." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $daftar->links() }}</div>

    <x-card title="Catatan" class="mt-5">
        <p class="text-[13px] leading-relaxed text-ink-muted">
            <strong>Surat Keterangan Aktif Kuliah terbit sendiri</strong> saat mahasiswa
            memintanya, selama statusnya aktif. Tidak ada keputusan yang diambil kampus di
            sana — sistem hanya membacakan kolom status.
        </p>
        <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
            <strong>Mencabut surat tidak menghapusnya.</strong> Nomornya tetap dapat
            diverifikasi dan akan tampil sebagai dicabut. Seseorang di luar sana memegang
            kertasnya; jawaban "tidak ditemukan" akan terbaca sebagai pemalsuan, bukan
            sebagai pencabutan.
        </p>
    </x-card>
@endsection
