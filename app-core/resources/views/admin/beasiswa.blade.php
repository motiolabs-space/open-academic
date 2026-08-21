@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Beasiswa & Keringanan')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_400px]">
        <div class="space-y-5">
            <x-card flush title="Skema Beasiswa">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Skema</th>
                                <th class="px-5 py-3 font-semibold">Cakupan</th>
                                <th class="px-5 py-3 text-center font-semibold">Kuota</th>
                                <th class="px-5 py-3 text-right font-semibold">Tetapkan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($skema as $s)
                                <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $s->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">{{ $s->kode }}</div>
                                        <div class="mt-1"><x-chip :tone="$s->jenis->value === 'internal' ? 'info' : 'success'">
                                            {{ $s->jenis->label() }}
                                        </x-chip></div>
                                        @if ($s->penyandang)
                                            <div class="mt-0.5 text-[11.5px] text-ink-muted">{{ $s->penyandang }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        {{ $s->cakupan() }}
                                        @if (filled($s->komponen))
                                            <div class="mt-0.5 text-[11.5px] text-ink-faint">
                                                Komponen: {{ implode(', ', $s->komponen) }}
                                            </div>
                                        @else
                                            <div class="mt-0.5 text-[11.5px] text-ink-faint">Seluruh komponen</div>
                                        @endif
                                    </td>
                                    <td class="tabular px-5 py-3 text-center">
                                        {{-- Kuota tampil saat memilih, bukan setelah ditolak:
                                             skema yang sudah penuh adalah fakta yang dibutuhkan
                                             sebelum keputusan diambil. --}}
                                        {{ $s->penerima_aktif_count }}@if ($s->kuota)/{{ $s->kuota }}@endif
                                        @if ($s->kuota && $s->penerima_aktif_count >= $s->kuota)
                                            <div class="mt-1"><x-chip tone="danger">Penuh</x-chip></div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <form method="POST" action="{{ route('admin.beasiswa.tetapkan', $s) }}"
                                            class="space-y-2">
                                            @csrf
                                            <x-field label="NIM / nama" name="mahasiswa_id" type="number"
                                                hint="ID mahasiswa" required />
                                            <x-field label="Mulai" name="tahun_akademik_mulai_id" required
                                                :options="$daftarTerm->mapWithKeys(fn ($t) => [$t->id => $t->nama])" />
                                            <x-field label="Selesai (opsional)" name="tahun_akademik_selesai_id"
                                                :options="$daftarTerm->mapWithKeys(fn ($t) => [$t->id => $t->nama])" />
                                            <x-field label="Nomor SK" name="nomor_sk" />
                                            <x-button type="submit" size="sm" class="w-full">Tetapkan</x-button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-empty-state title="Belum ada skema beasiswa"
                                            description="Buat skema lebih dulu lewat panel di samping." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card flush title="Penerima">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                                <th class="px-5 py-3 font-semibold">Skema</th>
                                <th class="px-5 py-3 font-semibold">Periode</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($penerima as $p)
                                <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $p->mahasiswa->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">
                                            {{ $p->mahasiswa->nim }} · {{ $p->mahasiswa->prodi->nama }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        {{ $p->beasiswa->nama }}
                                        <div class="text-[11.5px] text-ink-faint">{{ $p->beasiswa->cakupan() }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3">
                                        {{ $p->mulai->nama }}
                                        {{ $p->selesai ? '– '.$p->selesai->nama : '– seterusnya' }}
                                        @if ($p->nomor_sk)
                                            <div class="text-[11px] text-ink-faint">SK {{ $p->nomor_sk }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-chip :tone="$p->status->tone()">{{ $p->status->label() }}</x-chip>
                                        @if ($p->catatan)
                                            <p class="mt-1 max-w-xs text-[12px] text-ink-muted">{{ $p->catatan }}</p>
                                        @endif
                                        @if ($p->pemutus)
                                            <div class="text-[11px] text-ink-faint">
                                                {{ $p->pemutus->nama }} · {{ Format::tanggal($p->diputus_at) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($p->status === \App\Enums\StatusPenerima::Aktif)
                                            <form method="POST" action="{{ route('admin.beasiswa.cabut', $p) }}"
                                                class="flex items-end justify-end gap-1.5">
                                                @csrf
                                                <x-field label="Alasan" name="alasan" class="w-40" required />
                                                <x-button type="submit" variant="outline" size="sm">Cabut</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-empty-state title="Belum ada penerima" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div>{{ $penerima->links() }}</div>
        </div>

        <div class="space-y-5">
            <x-card title="Skema Baru">
                <form method="POST" action="{{ route('admin.beasiswa.skema') }}" class="space-y-3">
                    @csrf
                    <x-field label="Kode" name="kode" required placeholder="BS-PRESTASI" />
                    <x-field label="Nama" name="nama" required />
                    <x-field label="Jenis" name="jenis" :options="$jenisPilihan" required />
                    <x-field label="Penyandang dana" name="penyandang"
                        hint="Wajib untuk beasiswa eksternal — pihak yang menanggung potongannya." />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Persentase" name="persen" type="number" hint="1–100" />
                        <x-field label="atau Nominal" name="nominal" type="number" hint="Per semester" />
                    </div>

                    <x-field label="Komponen dicakup" name="komponen"
                        hint="Dipisah koma, mis. UKT. Kosong berarti seluruh komponen." />

                    <x-field label="Kuota" name="kuota" type="number" hint="Kosong berarti tanpa batas" />
                    <x-field label="Keterangan" name="keterangan" type="textarea" />

                    <x-button type="submit" class="w-full">Simpan skema</x-button>
                </form>
            </x-card>

            <x-card title="Catatan">
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    Potongan adalah <strong>baris bernilai negatif pada tagihan</strong>, bukan angka
                    yang dikurangkan di layar. Total tagihan tetap sama dengan jumlah barisnya,
                    sehingga gerbang KRS, daftar periksa kelulusan, dan pengingat tunggakan membaca
                    angka yang sama.
                </p>
                <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
                    <strong>Pencabutan bersifat ke depan.</strong> Tagihan yang sudah dipotong tidak
                    dibongkar — membalik semester yang lalu akan memunculkan kembali utang atas
                    tagihan yang sudah dianggap selesai. Batalkan barisnya satu per satu bila memang
                    harus.
                </p>
                <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
                    Menagih penyandang dana beasiswa eksternal adalah piutang di sistem keuangan,
                    bukan di sini. Yang dijamin modul ini: setiap potongan selalu dapat ditelusuri
                    ke pihak yang menanggungnya.
                </p>
            </x-card>
        </div>
    </div>

    {{-- ============ LAPORAN KIP KULIAH ============ --}}
    <x-card class="mt-5" title="Laporan Semester KIP Kuliah" :meta="$term?->nama">
        @if (! $kipkSiap)
            {{--
                Dinyatakan, bukan disembunyikan. Tombol yang hilang tanpa
                keterangan terbaca sebagai fitur yang tidak ada.
            --}}
            <x-chip tone="neutral">Belum dapat dibuat</x-chip>
            <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
                Aplikasi ini tidak tahu skema beasiswa mana yang KIP Kuliah — tabel
                <code>beasiswa</code> hanya membedakan internal dan eksternal, dan setiap kampus
                memberi kodenya sendiri. Isi <code>kipk.beasiswa_kode</code> pada
                <code>config/kipk.php</code> dengan kode skemanya.
            </p>
        @else
            <div class="flex flex-wrap items-center gap-5">
                @foreach ([
                    ['Penerima', $kipkRingkas['penerima'], ''],
                    ['Tanpa status semester ini', $kipkRingkas['tanpa_status'], $kipkRingkas['tanpa_status'] > 0 ? 'text-danger' : ''],
                    ['Nilai belum final', $kipkRingkas['belum_final'], $kipkRingkas['belum_final'] > 0 ? 'text-warning' : ''],
                ] as [$label, $nilai, $warna])
                    <div>
                        <div class="tabular font-serif text-[24px] font-semibold leading-none {{ $warna }}">
                            {{ $nilai }}
                        </div>
                        <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ $label }}</div>
                    </div>
                @endforeach

                <x-button
                    variant="outline"
                    class="ml-auto"
                    :href="route('admin.beasiswa.kipk', ['semester' => $term?->kode])"
                >Unduh CSV</x-button>
            </div>

            @foreach ($kipkSkema as $kode => $nama)
                @if ($nama === null)
                    <p class="mt-3 text-[12.5px] text-danger">
                        Kode skema <code>{{ $kode }}</code> tidak ada pada tabel beasiswa — periksa
                        <code>config/kipk.php</code>.
                    </p>
                @endif
            @endforeach

            @if ($kipkRingkas['tanpa_status'] > 0)
                <p class="mt-3 text-[12.5px] leading-relaxed text-ink-muted">
                    {{ $kipkRingkas['tanpa_status'] }} penerima tidak punya baris status pada semester
                    ini. Mereka tetap masuk berkas dengan keterangannya — periksa keaktifannya sebelum
                    dilaporkan, karena penerima yang berhenti kuliah tanpa tercatat adalah penerima
                    yang dananya terus berjalan.
                </p>
            @endif
        @endif
    </x-card>
@endsection
