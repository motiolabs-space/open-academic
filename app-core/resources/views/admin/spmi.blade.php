@extends('layouts.app')

@section('title', 'SPMI & Audit Mutu')

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

    {{-- Rekap temuan terbuka. Yang terlambat berdiri sendiri karena itulah
         satu-satunya angka yang menuntut tindakan hari ini. --}}
    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card label="Lewat tenggat" :value="$rekap['terlambat']" feature
            meta="Menuntut tindakan hari ini" />
        <x-stat-card label="Mayor terbuka" :value="$rekap['mayor']" meta="Tenggat 30 hari" />
        <x-stat-card label="Minor terbuka" :value="$rekap['minor']" meta="Tenggat 90 hari" />
        <x-stat-card label="Observasi" :value="$rekap['observasi']" meta="Tanpa tenggat" />
        <x-stat-card label="Saran" :value="$rekap['saran']" meta="Tanpa tenggat" />
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-2">
        <label class="flex flex-col gap-1">
            <span class="text-[11px] font-semibold text-ink-muted">Tahun audit</span>
            <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2100" placeholder="Semua"
                class="w-32 rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
        </label>
        <x-button type="submit" variant="outline" class="px-4 py-2 text-xs">Tampilkan</x-button>
    </form>

    {{-- ------------------------------------------------------------------
         Temuan terbuka
    ------------------------------------------------------------------- --}}
    <x-card class="mb-5" title="Temuan terbuka">
        @if ($temuan->isEmpty())
            <x-empty-state title="Belum ada temuan terbuka"
                description="Temuan muncul di sini setelah audit berjalan dan auditor mencatatnya." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase text-ink-muted">
                            <th class="py-2 pr-3">Unit / Audit</th>
                            <th class="py-2 pr-3">Jenis</th>
                            <th class="py-2 pr-3">Uraian</th>
                            <th class="py-2 pr-3">Tenggat</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2">Tindakan</th>
                        </tr>
                    </thead>
                    {{-- Satu <tbody> per temuan, bukan satu untuk semuanya.
                         `x-data` harus membungkus kedua barisnya: lingkup Alpine
                         adalah subpohon elemen, dan baris rincian di bawah ini
                         saudara dari baris ringkasnya — bukan anaknya. --}}
                    @foreach ($temuan as $t)
                        <tbody x-data="{ buka: false }">
                            <tr class="border-b border-line/60 align-top">
                                <td class="py-2.5 pr-3">
                                    <div class="font-semibold">{{ $t->audit->unit->nama }}</div>
                                    <div class="text-[11px] text-ink-muted">{{ $t->audit->nama }}</div>
                                </td>
                                <td class="py-2.5 pr-3">
                                    <x-chip :tone="$t->tone()">{{ $t->jenisLabel() }}</x-chip>
                                </td>
                                <td class="max-w-md py-2.5 pr-3">{{ $t->uraian }}</td>
                                <td class="py-2.5 pr-3 tabular">
                                    @if ($t->tenggat)
                                        <span @class(['text-danger font-semibold' => $t->terlambat()])>
                                            {{ $t->tenggat->translatedFormat('d M Y') }}
                                        </span>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3">
                                    <x-chip :tone="$t->status->tone()">{{ $t->status->label() }}</x-chip>
                                </td>
                                <td class="py-2.5">
                                    <button type="button" @click="buka = !buka"
                                        class="text-[12px] font-semibold text-accent underline">
                                        Tindak lanjut ({{ $t->tindakLanjut->count() }})
                                    </button>
                                </td>
                            </tr>

                            <tr x-show="buka" x-cloak class="border-b border-line/60 bg-surface-muted">
                                <td colspan="6" class="px-3 py-3">
                                    @foreach ($t->tindakLanjut as $tindak)
                                        <div class="mb-2 rounded border border-line bg-canvas p-2.5">
                                            <div class="text-[12.5px]"><strong>Rencana:</strong> {{ $tindak->rencana }}</div>
                                            @if ($tindak->realisasi)
                                                <div class="text-[12.5px]"><strong>Realisasi:</strong> {{ $tindak->realisasi }}</div>
                                            @endif

                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                @if ($tindak->is_terverifikasi)
                                                    <x-chip tone="success">Terverifikasi</x-chip>
                                                @else
                                                    {{-- Tidak dapat diverifikasi oleh yang mencatatnya;
                                                         layanan menolaknya, dan layar ini tidak berpura-pura
                                                         bisa. --}}
                                                    <form method="POST"
                                                        action="{{ route('admin.spmi.tindak.verifikasi', $tindak) }}"
                                                        class="flex flex-wrap items-center gap-2">
                                                        @csrf
                                                        <input type="text" name="catatan_verifikasi"
                                                            placeholder="Catatan verifikasi"
                                                            class="rounded border border-line bg-canvas px-2 py-1 text-[12px]">
                                                        <x-button type="submit" variant="outline"
                                                            class="px-3 py-1 text-[11px]">Verifikasi</x-button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    <form method="POST" action="{{ route('admin.spmi.temuan.tindak', $t) }}"
                                        class="grid gap-2 sm:grid-cols-2">
                                        @csrf
                                        <input type="text" name="rencana" required placeholder="Rencana perbaikan"
                                            class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                                        <input type="text" name="realisasi" placeholder="Realisasi (bila sudah ada)"
                                            class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                                        <div class="flex gap-2 sm:col-span-2">
                                            <x-button type="submit" class="px-3 py-1.5 text-[11px]">Catat tindak lanjut</x-button>
                                        </div>
                                    </form>

                                    <form method="POST" action="{{ route('admin.spmi.temuan.tutup', $t) }}"
                                        class="mt-2 border-t border-line pt-2">
                                        @csrf
                                        {{-- Menutup satu arah. Temuan yang dapat dibuka lagi adalah
                                             temuan yang dapat dihaluskan menjelang asesmen lapangan. --}}
                                        <x-button type="submit" variant="outline" class="px-3 py-1.5 text-[11px]"
                                            onclick="return confirm('Tutup temuan ini? Uraiannya tidak dapat diubah lagi setelah ditutup.')">
                                            Tutup temuan
                                        </x-button>
                                        @if ($t->wajibTindakLanjut())
                                            <span class="ml-2 text-[11px] text-ink-muted">
                                                {{ $t->jenisLabel() }} hanya dapat ditutup setelah ada tindak lanjut
                                                yang diverifikasi orang lain.
                                            </span>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif
    </x-card>

    {{-- ------------------------------------------------------------------
         Audit
    ------------------------------------------------------------------- --}}
    <x-card class="mb-5" title="Audit Mutu Internal">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase text-ink-muted">
                        <th class="py-2 pr-3">Audit</th>
                        <th class="py-2 pr-3">Unit</th>
                        <th class="py-2 pr-3">Auditor</th>
                        <th class="py-2 pr-3">Tanggal</th>
                        <th class="py-2 pr-3">Temuan</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2">Tindakan</th>
                    </tr>
                </thead>
                @forelse ($audit as $a)
                    {{-- Alasan yang sama seperti tabel temuan: `x-data` membungkus
                         kedua baris, bukan hanya baris ringkasnya. --}}
                    <tbody x-data="{ catat: false }">
                        <tr class="border-b border-line/60 align-top">
                            <td class="py-2.5 pr-3 font-semibold">{{ $a->nama }}</td>
                            <td class="py-2.5 pr-3">{{ $a->unit->nama }}</td>
                            <td class="py-2.5 pr-3">
                                {{ $a->auditorDosen?->nama ?? $a->auditorStaff?->nama ?? '—' }}
                            </td>
                            <td class="py-2.5 pr-3 tabular">{{ $a->tanggal_audit->translatedFormat('d M Y') }}</td>
                            <td class="py-2.5 pr-3 tabular">{{ $a->temuan_count }}</td>
                            <td class="py-2.5 pr-3"><x-chip :tone="$a->status->tone()">{{ $a->status->label() }}</x-chip></td>
                            <td class="py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($a->status === App\Enums\StatusAudit::Direncanakan)
                                        <form method="POST" action="{{ route('admin.spmi.audit.mulai', $a) }}">
                                            @csrf
                                            <x-button type="submit" class="px-3 py-1 text-[11px]">Mulai</x-button>
                                        </form>
                                    @elseif ($a->status === App\Enums\StatusAudit::Berlangsung)
                                        <button type="button" @click="catat = !catat"
                                            class="text-[12px] font-semibold text-accent underline">Catat temuan</button>

                                        {{-- Menutup audit tidak ikut menutup temuannya: perbaikan
                                             lazim berjalan berminggu-minggu sesudah auditnya usai. --}}
                                        <form method="POST" action="{{ route('admin.spmi.audit.tutup', $a) }}">
                                            @csrf
                                            <x-button type="submit" variant="outline"
                                                class="px-3 py-1 text-[11px]">Tutup audit</x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @if ($a->status === App\Enums\StatusAudit::Berlangsung)
                            <tr x-show="catat" x-cloak class="border-b border-line/60 bg-surface-muted">
                                <td colspan="7" class="px-3 py-3">
                                    <form method="POST" action="{{ route('admin.spmi.audit.temuan', $a) }}"
                                        class="grid gap-2 sm:grid-cols-4">
                                        @csrf
                                        <select name="jenis" required
                                            class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                                            @foreach ($jenisTemuan as $kunci => $def)
                                                <option value="{{ $kunci }}">{{ $def['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <select name="standar_mutu_id"
                                            class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                                            <option value="">Standar (opsional)</option>
                                            @foreach ($standar as $s)
                                                <option value="{{ $s->id }}">{{ $s->kode }} — {{ $s->nama }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="uraian" required placeholder="Uraian temuan"
                                            class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px] sm:col-span-2">
                                        <input type="text" name="akar_masalah" placeholder="Akar masalah (opsional)"
                                            class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px] sm:col-span-3">
                                        <x-button type="submit" class="px-3 py-1.5 text-[11px]">Catat</x-button>
                                    </form>
                                    <p class="mt-2 text-[11px] text-ink-muted">
                                        Tenggat diambil dari beratnya temuan, bukan dari formulir ini —
                                        mayor {{ $jenisTemuan['mayor']['tenggat_hari'] }} hari,
                                        minor {{ $jenisTemuan['minor']['tenggat_hari'] }} hari.
                                    </p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="7" class="py-6">
                                <x-empty-state title="Belum ada audit"
                                    description="Rencanakan audit di bawah untuk memulai siklus AMI." />
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        <form method="POST" action="{{ route('admin.spmi.audit') }}"
            class="mt-4 grid gap-2 border-t border-line pt-4 sm:grid-cols-3">
            @csrf
            <input type="text" name="nama" required placeholder="Nama audit, mis. AMI 2026"
                class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
            <select name="unit_kerja_id" required class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                <option value="">Unit yang diaudit</option>
                @foreach ($unitAktif as $u)
                    <option value="{{ $u->id }}">{{ $u->kode }} — {{ $u->nama }}</option>
                @endforeach
            </select>
            <input type="number" name="tahun" required value="{{ $tahun ?? now()->year }}" min="2000" max="2100"
                class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">

            <select name="auditor_staff_id" class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                <option value="">Auditor dari staf</option>
                @foreach ($calonAuditorStaf as $s)
                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                @endforeach
            </select>
            <select name="auditor_dosen_id" class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                <option value="">Auditor dari dosen</option>
                @foreach ($calonAuditorDosen as $d)
                    <option value="{{ $d->id }}">{{ $d->nama }}</option>
                @endforeach
            </select>
            <input type="date" name="tanggal_audit" required
                class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">

            <div class="sm:col-span-3">
                <x-button type="submit" class="px-4 py-2 text-xs">Rencanakan audit</x-button>
                <span class="ml-2 text-[11px] text-ink-muted">
                    Pilih satu auditor saja. Auditor tidak dapat mengaudit unitnya sendiri.
                </span>
            </div>
        </form>
    </x-card>

    {{-- ------------------------------------------------------------------
         Standar mutu
    ------------------------------------------------------------------- --}}
    <x-card title="Standar mutu">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase text-ink-muted">
                        <th class="py-2 pr-3">Kode</th>
                        <th class="py-2 pr-3">Nama</th>
                        <th class="py-2 pr-3">Pernyataan</th>
                        <th class="py-2 pr-3">Siklus</th>
                        <th class="py-2">Penanggung jawab</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($standar as $s)
                        <tr class="border-b border-line/60 align-top">
                            <td class="py-2.5 pr-3 font-semibold tabular">{{ $s->kode }}</td>
                            <td class="py-2.5 pr-3">
                                {{ $s->nama }}
                                @if ($s->melampaui_sndikti)
                                    <x-chip tone="info" class="ml-1">Melampaui SN-Dikti</x-chip>
                                @endif
                            </td>
                            <td class="max-w-md py-2.5 pr-3 text-ink-muted">{{ $s->pernyataan }}</td>
                            <td class="py-2.5 pr-3">{{ $s->siklusLabel() }}</td>
                            <td class="py-2.5">{{ $s->unitPenanggungJawab?->nama ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6">
                                <x-empty-state title="Belum ada standar mutu"
                                    description="Standar adalah yang diaudit. Tambahkan di bawah." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.spmi.standar') }}"
            class="mt-4 grid gap-2 border-t border-line pt-4 sm:grid-cols-3">
            @csrf
            <input type="text" name="kode" required placeholder="Kode, mis. SM-01"
                class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
            <input type="text" name="nama" required placeholder="Nama standar"
                class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
            <select name="siklus" required class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                @foreach ($siklusPpepp as $kunci => $label)
                    <option value="{{ $kunci }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Pernyataan berdiri sendiri, bukan bagian dari nama: sebuah standar
                 dirujuk dengan namanya dan diaudit dengan pernyataannya. --}}
            <textarea name="pernyataan" required rows="2" placeholder="Pernyataan standar — siapa harus melakukan apa, kapan"
                class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px] sm:col-span-3"></textarea>

            <input type="text" name="kategori" placeholder="Kategori (pendidikan, penelitian, …)"
                class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
            <select name="unit_penanggung_jawab_id" class="rounded border border-line bg-canvas px-2 py-1.5 text-[12px]">
                <option value="">Unit penanggung jawab (opsional)</option>
                @foreach ($unitAktif as $u)
                    <option value="{{ $u->id }}">{{ $u->kode }} — {{ $u->nama }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-[12px]">
                <input type="checkbox" name="melampaui_sndikti" value="1" class="rounded border-line">
                Melampaui SN-Dikti
            </label>

            <div class="sm:col-span-3">
                <x-button type="submit" class="px-4 py-2 text-xs">Tambah standar</x-button>
            </div>
        </form>
    </x-card>
@endsection
