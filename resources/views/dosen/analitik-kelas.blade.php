@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Analitik Kelas')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            {{-- Kehadiran --}}
            <x-card title="Kehadiran" :meta="$kehadiran['jumlah_peserta'].' peserta'">
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-stat-card label="Rerata kehadiran"
                        :value="$kehadiran['rerata'] === null ? '—' : Format::angka($kehadiran['rerata'], 1).'%'"
                        :meta="'Ambang UAS '.Format::angka($kehadiran['ambang'], 0).'%'"
                        :feature="true" />
                    <x-stat-card label="Di bawah ambang" :value="$kehadiran['di_bawah_ambang']->count()"
                        meta="terancam tidak boleh UAS" />
                    <x-stat-card label="Pertemuan terlaksana"
                        :value="$keterlaksanaan['terlaksana'].'/'.$keterlaksanaan['rencana']"
                        :meta="$keterlaksanaan['berjurnal'].' berjurnal'" />
                </div>

                <dl class="mt-4 space-y-2">
                    @foreach ($kehadiran['sebaran'] as $rentang => $jumlah)
                        <div class="flex items-center gap-3">
                            <dt class="w-20 shrink-0 text-[12.5px] text-ink-muted">{{ $rentang }}%</dt>
                            <dd class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="h-1.5 min-w-0 flex-1 rounded-full bg-line">
                                    <div class="h-1.5 rounded-full {{ $rentang === '<50' ? 'bg-danger' : 'bg-navy' }}"
                                        style="width: {{ $kehadiran['jumlah_peserta'] > 0 ? round($jumlah / $kehadiran['jumlah_peserta'] * 100) : 0 }}%"></div>
                                </div>
                                <span class="tabular w-8 shrink-0 text-right text-[12.5px] font-medium">{{ $jumlah }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            {{-- Penilaian --}}
            <x-card flush title="Penilaian per Komponen"
                :meta="$penilaian['sudah_final'] > 0 ? $penilaian['sudah_final'].' nilai final' : 'belum final'">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Komponen</th>
                                <th class="px-5 py-3 text-right font-semibold">Bobot</th>
                                <th class="px-5 py-3 text-right font-semibold">Terisi</th>
                                <th class="px-5 py-3 text-right font-semibold">Rerata</th>
                                <th class="px-5 py-3 text-right font-semibold">Rentang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($penilaian['per_komponen'] as $k)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-3">
                                        <span class="font-medium">{{ $k['nama'] }}</span>
                                        @if (($penilaian['komponen_terlemah']['nama'] ?? null) === $k['nama'])
                                            {{-- Satu baris yang paling berguna di layar ini, dan yang
                                                 selama ini diturunkan dosen dengan mata dari tabel
                                                 lima baris setiap kali. --}}
                                            <x-chip tone="warning">terlemah</x-chip>
                                        @endif
                                    </td>
                                    <td class="tabular px-5 py-3 text-right">{{ $k['bobot'] }}%</td>
                                    <td class="tabular px-5 py-3 text-right">{{ $k['terisi'] }}</td>
                                    <td class="tabular px-5 py-3 text-right font-medium">{{ Format::angka($k['rerata'], 1) }}</td>
                                    <td class="tabular px-5 py-3 text-right text-[11.5px] text-ink-faint">
                                        {{ Format::angka($k['terendah'], 0) }}–{{ Format::angka($k['tertinggi'], 0) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">
                                    <x-empty-state title="Belum ada nilai komponen"
                                        description="Analitik penilaian muncul setelah ada isian nilai." />
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- Penguasaan CPL --}}
            <x-card title="Penguasaan Capaian Pembelajaran"
                :meta="$penguasaan['terpetakan'] ? 'ambang '.Format::angka($penguasaan['ambang'], 0) : null">
                @unless ($penguasaan['terpetakan'])
                    {{-- Bukan nol. Nol akan terbaca "mahasiswa tidak menguasai apa
                         pun", padahal artinya "belum ada yang menyatakan ujian ini
                         mengukur apa". --}}
                    <x-empty-state
                        title="Komponen nilai belum dipetakan ke CPL"
                        description="Penguasaan baru dapat dihitung setelah tiap komponen dinyatakan mengukur CPL yang mana, dan seberapa besar porsinya." />
                @else
                    <dl class="space-y-3">
                        @foreach ($penguasaan['cpl'] as $baris)
                            <div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="text-[13px] font-medium">
                                        {{ $baris['cpl']->kode }}
                                        <span class="font-normal text-ink-muted">{{ Str::limit($baris['cpl']->deskripsi, 70) }}</span>
                                    </dt>
                                    <dd class="tabular shrink-0 text-[13px] font-semibold {{ $baris['tercapai'] ? '' : 'text-warning-ink' }}">
                                        {{ $baris['nilai'] === null ? '—' : Format::angka($baris['nilai'], 1) }}
                                    </dd>
                                </div>
                                <div class="mt-1.5 h-1.5 rounded-full bg-line">
                                    <div class="h-1.5 rounded-full {{ $baris['tercapai'] ? 'bg-navy' : 'bg-warning' }}"
                                        style="width: {{ min(100, round(($baris['nilai'] ?? 0))) }}%"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-ink-faint">
                                    {{ $baris['jumlah_pengukuran'] }} pengukuran ·
                                    {{ $baris['mahasiswa_dinilai'] }} mahasiswa
                                </p>
                            </div>
                        @endforeach
                    </dl>
                @endunless

                @if ($cplTanpaPengukur->isNotEmpty())
                    {{-- Celah yang persis dicari saat visitasi. --}}
                    <div class="mt-4 border-t border-line pt-3">
                        <p class="text-[12.5px] leading-relaxed text-warning-ink">
                            RPS membebankan
                            <strong>{{ $cplTanpaPengukur->pluck('kode')->implode(', ') }}</strong>
                            pada mata kuliah ini, tetapi tidak ada komponen nilai yang mengukurnya.
                            CPL itu akan tampak diajarkan dan tidak pernah dapat dilaporkan.
                        </p>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card title="Perlu Perhatian" :meta="$perhatian->count().' mahasiswa'">
                @if ($perhatian->isEmpty())
                    <p class="text-[13px] text-ink-muted">
                        Tidak ada mahasiswa yang melanggar ambang mana pun saat ini.
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($perhatian as $p)
                            <li class="border-b border-line/50 pb-3 last:border-b-0">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-[13px] font-medium">{{ $p['mahasiswa']->nama }}</span>
                                    <span class="tabular shrink-0 text-[11.5px] text-ink-faint">
                                        {{ $p['mahasiswa']->nim }}
                                    </span>
                                </div>
                                {{-- Alasan tertulis, bukan skor. Indeks risiko berperingkat
                                     mengundang pembacanya memperlakukan kombinasi aritmetik
                                     dua angka tak sejenis sebagai ramalan. --}}
                                <ul class="mt-1 space-y-1">
                                    @foreach ($p['alasan'] as $alasan)
                                        <li class="text-[12px] leading-relaxed text-warning-ink">{{ $alasan }}</li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Angka Ini Bukan Ramalan">
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    Tidak ada model yang memperkirakan apakah seorang mahasiswa akan lulus.
                    Yang ditampilkan adalah <strong>cacahan</strong> — persentase, rerata,
                    ketercapaian — dan <strong>aturan</strong> yang menyala saat sebuah angka
                    melewati ambang yang ditetapkan kampus.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-muted">
                    Tiap peringatan disertai aturannya, supaya Anda dapat berselisih dengan
                    ambangnya — bukan dengan mahasiswanya.
                </p>
            </x-card>

            @if ($rps !== null)
                <x-card title="RPS" :meta="'versi '.$rps->versi">
                    <dl class="space-y-2 text-[13px]">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Status</dt>
                            <dd><x-chip :tone="$rps->status->tone()">{{ $rps->status->label() }}</x-chip></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">CPL dibebankan</dt>
                            <dd class="tabular text-right">{{ $rps->cpl->count() }}</dd>
                        </div>
                    </dl>
                    <div class="mt-3">
                        <x-button href="{{ route('dosen.rps.jurnal', $kelas) }}" variant="outline" size="sm">
                            Jurnal perkuliahan
                        </x-button>
                    </div>
                </x-card>
            @else
                <x-card title="RPS">
                    <x-empty-state
                        title="Belum ada RPS berlaku"
                        description="Penguasaan CPL tetap dapat dihitung dari pemetaan komponen, tetapi keterlaksanaan tidak dapat diukur tanpa rencana." />
                </x-card>
            @endif
        </div>
    </div>
@endsection
