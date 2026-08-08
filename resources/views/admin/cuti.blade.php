@extends('layouts.app')

@section('title', 'Cuti Mahasiswa')

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

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-5">
            <x-card>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <x-field label="Status" name="status" :value="$filter['status'] ?? ''"
                        :options="collect($statusPilihan)->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
                    <x-button type="submit">Terapkan</x-button>
                    @if (array_filter($filter))
                        <x-button variant="ghost" :href="route('admin.cuti')">Reset</x-button>
                    @endif
                </form>
            </x-card>

            <x-card flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[880px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                                <th class="px-5 py-3 font-semibold">Semester</th>
                                <th class="px-5 py-3 font-semibold">Jenis & Alasan</th>
                                <th class="px-5 py-3 text-center font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $c)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $c->mahasiswa->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">
                                            {{ $c->mahasiswa->nim }} · {{ $c->mahasiswa->prodi->nama }}
                                        </div>
                                    </td>
                                    <td class="tabular px-5 py-3 text-ink-muted">{{ $c->tahunAkademik->kode }}</td>
                                    <td class="px-5 py-3">
                                        <x-chip tone="info">{{ ucfirst($c->jenis) }}</x-chip>
                                        <p class="mt-1.5 max-w-md text-[12.5px] leading-relaxed text-ink-muted">
                                            {{ $c->alasan }}
                                        </p>
                                        @if ($c->catatan)
                                            <p class="mt-1 text-[11.5px] italic text-ink-faint">
                                                Catatan: {{ $c->catatan }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <x-chip :tone="match ($c->status->value) {
                                            'disetujui' => 'success',
                                            'ditolak' => 'danger',
                                            'dibatalkan' => 'neutral',
                                            default => 'warning',
                                        }">{{ $c->status->label() }}</x-chip>

                                        @if ($c->pemroses)
                                            <div class="mt-1 text-[11px] text-ink-faint">oleh {{ $c->pemroses->nama }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-col items-end gap-1.5">
                                            @if ($c->status->value === 'diajukan')
                                                <form method="POST" action="{{ route('admin.cuti.setujui', $c) }}"
                                                    onsubmit="return confirm('Setujui cuti {{ $c->mahasiswa->nama }}? Statusnya berubah menjadi Cuti dan itulah yang dilaporkan ke PDDIKTI.');">
                                                    @csrf
                                                    <x-button type="submit" size="sm">Setujui</x-button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.cuti.tolak', $c) }}"
                                                    class="flex items-center gap-1.5">
                                                    @csrf
                                                    <input type="text" name="catatan" required placeholder="Alasan penolakan"
                                                        class="w-40 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                    <x-button type="submit" variant="danger" size="sm">Tolak</x-button>
                                                </form>
                                            @elseif ($c->status->value === 'disetujui')
                                                <form method="POST" action="{{ route('admin.cuti.aktifkan', $c) }}"
                                                    onsubmit="return confirm('Akhiri cuti dan aktifkan kembali {{ $c->mahasiswa->nama }}?');">
                                                    @csrf
                                                    <x-button type="submit" variant="outline" size="sm">Aktifkan Kembali</x-button>
                                                </form>
                                            @else
                                                <span class="text-[11.5px] text-ink-faint">selesai</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-12">
                                        <x-empty-state title="Tidak ada pengajuan cuti"
                                            description="Pengajuan dari portal mahasiswa akan muncul di sini untuk diputuskan." />
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
        </div>

        <x-card title="Ajukan atas Nama Mahasiswa">
            <form method="POST" action="{{ route('admin.cuti.ajukan') }}" enctype="multipart/form-data"
                class="flex flex-col gap-3.5">
                @csrf
                <x-field label="UUID Mahasiswa" name="mahasiswa_uuid" required
                    hint="Salin dari layar Data Mahasiswa." />
                <x-field label="Semester" name="tahun_akademik_id" required
                    :value="$termAktif?->id" :options="$daftarTerm->pluck('nama', 'id')" />
                <x-field label="Jenis" name="jenis" required
                    :options="['akademik' => 'Cuti Akademik', 'sakit' => 'Sakit', 'lainnya' => 'Lainnya']" />
                <x-field label="Alasan" name="alasan" type="textarea" required />

                <div>
                    <label for="dokumen-cuti"
                        class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        Dokumen Pendukung
                    </label>
                    <input type="file" id="dokumen-cuti" name="dokumen" accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-[12.5px]">
                    <p class="mt-1 text-[11.5px] text-ink-faint">
                        PDF atau gambar, maksimal {{ number_format((int) config('berkas.maks_kb') / 1024, 0) }} MB.
                        Surat keterangan sakit disimpan di penyimpanan privat dan hanya dapat
                        dibuka BAAK serta mahasiswa yang bersangkutan.
                    </p>
                </div>

                <p class="rounded-card border border-line bg-canvas px-3.5 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
                    Cuti tidak dapat diajukan bila mahasiswa masih memegang KRS aktif pada
                    semester yang sama — seseorang tidak dapat sekaligus mengambil kelas
                    dan sedang cuti.
                </p>

                <x-button type="submit" class="w-full">Catat Pengajuan</x-button>
            </form>
        </x-card>
    </div>
@endsection
