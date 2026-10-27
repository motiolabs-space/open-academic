{{--
    Satu berkas kepegawaian, dipakai portal dosen dan layar staf.

    $portal  'dosen' | 'admin'
    $dosen   pemilik berkas
    $riwayat array<string, Collection> dari RiwayatKepegawaianService::riwayat()
    $sunting array{jenis, baris}|null — baris yang sedang disunting

    Empat belas riwayat sebagai tab, bukan empat belas kartu bertumpuk: orang
    datang untuk mengisi satu hal, dan halaman sepanjang empat belas formulir
    membuat yang satu itu sulit ditemukan. Tab yang dibuka ditentukan oleh
    formulir yang baru gagal divalidasi, lalu oleh baris yang disunting, lalu
    oleh #fragmen URL.
--}}
@php
    use App\Enums\JenisRiwayat;
    use App\Enums\StatusRiwayat;
    use App\Support\Sdm\FormulirRiwayat;

    $admin = $portal === 'admin';
    $awal = old('_jenis') ?? $sunting['jenis']->value ?? null;
@endphp

<div x-data="{ tab: @js($awal) || location.hash.slice(1) || @js(JenisRiwayat::Pangkat->value) }"
    class="grid gap-5 lg:grid-cols-[240px_minmax(0,1fr)]">

    <nav aria-label="{{ __('kepegawaian.judul') }}" class="rounded-card border border-line bg-surface p-2 lg:self-start">
        <ul class="space-y-0.5">
            @foreach (JenisRiwayat::cases() as $jenis)
                @php $isi = $riwayat[$jenis->value]; @endphp
                <li>
                    <a href="#{{ $jenis->value }}"
                        @click="tab = @js($jenis->value)"
                        :class="tab === @js($jenis->value) ? 'bg-navy text-canvas' : 'text-ink hover:bg-line/60'"
                        class="flex items-center justify-between gap-2 rounded-control px-3 py-2 text-[13px]">
                        <span>{{ $jenis->label() }}</span>
                        <span class="tabular text-[11px] opacity-80">
                            @if ($menunggu = $isi->where('status', StatusRiwayat::Diajukan)->count())
                                <span class="rounded-full bg-gold px-1.5 font-semibold text-navy">{{ $menunggu }}</span>
                            @endif
                            {{ $isi->count() ?: '' }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="min-w-0">
        @foreach (JenisRiwayat::cases() as $jenis)
            @php
                $isi = $riwayat[$jenis->value];
                $bidang = FormulirRiwayat::bidang($jenis);
                $barisSunting = ($sunting['jenis'] ?? null) === $jenis ? $sunting['baris'] : null;
                $nilai = FormulirRiwayat::nilaiAwal($jenis, $barisSunting);
                $bolehIsi = $admin || $jenis->diisiDosen();
            @endphp

            <section id="{{ $jenis->value }}" x-show="tab === @js($jenis->value)" x-cloak class="space-y-5">
                <x-card flush :title="$jenis->label()" :meta="__('kepegawaian.ringkasan_status', [
                    'disetujui' => $isi->where('status', StatusRiwayat::Disetujui)->count(),
                    'diajukan' => $isi->where('status', StatusRiwayat::Diajukan)->count(),
                    'ditolak' => $isi->where('status', StatusRiwayat::Ditolak)->count(),
                ])">
                    @if (in_array($jenis, [JenisRiwayat::TugasBelajar, JenisRiwayat::Mutasi, JenisRiwayat::Pensiun], true))
                        <div class="border-b border-line px-5 py-3 text-[12.5px] text-ink-muted">
                            {{ __('kepegawaian.petunjuk.'.$jenis->value) }}
                        </div>
                    @endif

                    <ul>
                        @forelse ($isi as $baris)
                            @php $ringkas = FormulirRiwayat::ringkasan($jenis, $baris); @endphp
                            <li class="flex flex-wrap items-start justify-between gap-3 border-b border-line/50 px-5 py-3 text-[13px] last:border-b-0">
                                <div class="min-w-0">
                                    <div class="font-medium">{{ $ringkas['judul'] }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $ringkas['rincian'] }}</div>

                                    @if ($baris->status === StatusRiwayat::Ditolak && $baris->alasan_tolak)
                                        <div class="mt-1 text-[11.5px] text-danger">
                                            {{ __('kepegawaian.alasan_tolak', ['alasan' => $baris->alasan_tolak]) }}
                                        </div>
                                    @elseif ($baris->status === StatusRiwayat::Disetujui && $baris->validator)
                                        <div class="mt-1 text-[11.5px] text-ink-faint">
                                            {{ __('kepegawaian.divalidasi_oleh', [
                                                'nama' => $baris->validator->nama,
                                                'tanggal' => \App\Support\Format::tanggal($baris->divalidasi_at),
                                            ]) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-chip :tone="$baris->status->tone()">{{ $baris->status->label() }}</x-chip>

                                    @if ($baris->dokumen_path)
                                        <x-button size="sm" variant="ghost"
                                            :href="route('berkas.kepegawaian', [$jenis->value, $baris->uuid])">
                                            {{ __('kepegawaian.aksi.unduh') }}
                                        </x-button>
                                    @endif

                                    @if ($admin && $baris->status === StatusRiwayat::Diajukan)
                                        @include('kepegawaian.validasi', ['jenis' => $jenis, 'baris' => $baris])
                                    @endif

                                    @can('update', $baris)
                                        @if ($jenis !== JenisRiwayat::Mutasi)
                                            <x-button size="sm" variant="outline"
                                                :href="request()->url().'?ubah='.$jenis->value.':'.$baris->uuid.'#'.$jenis->value">
                                                {{ __('kepegawaian.aksi.ubah') }}
                                            </x-button>
                                            <form method="POST"
                                                action="{{ $admin
                                                    ? route('admin.kepegawaian.destroy', [$jenis->value, $baris->uuid])
                                                    : route('dosen.kepegawaian.destroy', [$jenis->value, $baris->uuid]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-button type="submit" size="sm" variant="danger">{{ __('kepegawaian.aksi.hapus') }}</x-button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-[13px] text-ink-muted">{{ __('kepegawaian.kosong.riwayat') }}</li>
                        @endforelse
                    </ul>
                </x-card>

                @if ($bolehIsi)
                    <x-card :title="$barisSunting ? __('kepegawaian.aksi.ubah').' — '.$jenis->label() : $jenis->label()">
                        <form method="POST" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2"
                            action="{{ match (true) {
                                $barisSunting !== null && $admin => route('admin.kepegawaian.update', [$jenis->value, $barisSunting->uuid]),
                                $barisSunting !== null => route('dosen.kepegawaian.update', [$jenis->value, $barisSunting->uuid]),
                                $admin => route('admin.dosen.kepegawaian.store', [$dosen, $jenis->value]),
                                default => route('dosen.kepegawaian.store', $jenis->value),
                            } }}">
                            @csrf
                            @if ($barisSunting)
                                @method('PUT')
                            @endif
                            <input type="hidden" name="_jenis" value="{{ $jenis->value }}">

                            @foreach ($bidang as $nama => $b)
                                <x-field
                                    :label="__('kepegawaian.field.'.$nama)"
                                    :name="$nama"
                                    :type="$b['tipe'] === 'select' ? 'text' : $b['tipe']"
                                    :options="$b['pilihan'] ?? null"
                                    :required="$b['wajib']"
                                    :value="$nilai[$nama] ?? ($nama === 'negara' ? 'Indonesia' : null)"
                                    :hint="match (true) {
                                        $nama === 'tanggal_selesai' || $nama === 'tmt_selesai' => __('kepegawaian.petunjuk.tanggal_selesai'),
                                        $nama === 'is_tanggungan' => __('kepegawaian.petunjuk.tanggungan'),
                                        $nama === 'is_luar_negeri' => __('kepegawaian.petunjuk.luar_negeri'),
                                        default => null,
                                    }"
                                    @class(['sm:col-span-2' => $b['lebar'] ?? false]) />
                            @endforeach

                            <x-field :label="__('kepegawaian.field.dokumen')" name="dokumen" type="file"
                                :hint="__('kepegawaian.petunjuk.dokumen')" class="sm:col-span-2" />

                            <div class="flex items-center gap-2 sm:col-span-2">
                                <x-button type="submit" size="sm">
                                    {{ $barisSunting ? __('kepegawaian.aksi.simpan')
                                        : ($admin ? __('kepegawaian.aksi.catat') : __('kepegawaian.aksi.ajukan')) }}
                                </x-button>
                                @if ($barisSunting)
                                    <x-button size="sm" variant="ghost" :href="request()->url().'#'.$jenis->value">
                                        {{ __('kepegawaian.aksi.batal') }}
                                    </x-button>
                                @endif
                            </div>
                        </form>
                    </x-card>
                @else
                    <p class="text-[12.5px] text-ink-muted">{{ __('kepegawaian.petunjuk.diisi_staf') }}</p>
                @endif
            </section>
        @endforeach
    </div>
</div>
