@php
    use App\Enums\UnsurBkd;
    use App\Support\Format;

    $sks = fn (int $ratus): string => Format::angka($ratus / 100, 2);
@endphp
@extends('layouts.app')

@section('title', 'Beban Kerja Dosen')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @unless ($wajib)
        {{-- Dikatakan lebih dulu supaya tidak ada yang mengira dirinya terlambat.
             BKD adalah syarat tunjangan sertifikasi; yang belum bersertifikat
             pendidik tidak terikat kewajiban itu. --}}
        <div class="mb-5">
            <x-alert tone="info">
                Anda belum tercatat memegang Sertifikat Pendidik, sehingga belum wajib
                melaporkan BKD. Lembar ini tetap dapat Anda isi dan ajukan bila kampus
                memintanya.
            </x-alert>
        </div>
    @endunless

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            @foreach (UnsurBkd::cases() as $unsur)
                @php $isi = $baris->get($unsur->value, collect()); @endphp

                <x-card flush
                    :title="$unsur->label()"
                    :meta="$sks($ringkas[$unsur->value] ?? 0).' SKS'"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Kegiatan</th>
                                    <th class="px-5 py-3 font-semibold">Sumber</th>
                                    <th class="px-5 py-3 text-right font-semibold">SKS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($isi as $b)
                                    <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $b['kegiatan'] }}</div>
                                            @if ($b['rincian'])
                                                <div class="text-[11.5px] text-ink-faint">{{ $b['rincian'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            {{-- Pembedaan yang paling dicari asesor: baris
                                                 tarikan sistem dapat dicek ke daftar kelas
                                                 dalam hitungan detik, baris laporan sendiri
                                                 harus dibuka buktinya. --}}
                                            <x-chip :tone="$b['otomatis'] ? 'info' : 'neutral'">
                                                {{ $b['otomatis'] ? 'Rekaman sistem' : 'Dilaporkan sendiri' }}
                                            </x-chip>
                                        </td>
                                        <td class="tabular px-5 py-3 text-right font-medium">{{ $sks($b['sksRatus']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">
                                            <x-empty-state
                                                title="Belum ada kegiatan"
                                                :description="$unsur->terhitungOtomatis()
                                                    ? 'Belum ada kelas, bimbingan, pengujian, maupun perwalian tercatat pada semester ini.'
                                                    : 'Catat kegiatan Anda lewat menu Portofolio agar muncul di sini.'" />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="space-y-5">
            <x-card title="Ringkasan" :meta="$laporan->status->label()">
                <div class="grid gap-3">
                    <x-stat-card
                        label="Total beban"
                        :value="$sks($ringkas['total'] ?? 0)"
                        :meta="'Rentang kampus '.$sks($batas['minimum_ratus']).'–'.$sks($batas['maksimum_ratus']).' SKS'"
                        feature />
                </div>

                <dl class="mt-4 space-y-2 text-[13px]">
                    @foreach (UnsurBkd::cases() as $unsur)
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ $unsur->label() }}</dt>
                            <dd class="tabular text-right font-medium">{{ $sks($ringkas[$unsur->value] ?? 0) }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($peringatan !== [])
                    {{-- Ditampilkan, tetapi tidak menghalangi pengajuan. Semester
                         yang memang kurang harus dilaporkan apa adanya —
                         menolaknya hanya menghasilkan semester yang tidak
                         terlaporkan sama sekali. --}}
                    <div class="mt-4 space-y-2">
                        @foreach ($peringatan as $pesan)
                            <p class="text-[12px] leading-relaxed text-warning-ink">{{ $pesan }}</p>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card title="Pengajuan">
                @if ($laporan->status->dapatDisunting())
                    <p class="text-[13px] leading-relaxed text-ink-muted">
                        Saat diajukan, seluruh rincian di samping <strong>dibekukan</strong> pada
                        keadaan hari ini. Perubahan kelas, bimbingan, atau pengujian setelah itu
                        tidak akan mengubah laporan yang sudah dinilai.
                    </p>

                    @if ($laporan->catatan_asesor)
                        <p class="mt-3 rounded-control border border-line bg-zebra px-3 py-2 text-[12.5px] leading-relaxed">
                            <span class="font-semibold">Catatan asesor:</span>
                            {{ $laporan->catatan_asesor }}
                        </p>
                    @endif

                    <form method="POST" action="{{ route('dosen.bkd.ajukan') }}" class="mt-3">
                        @csrf
                        <x-button type="submit" size="sm">
                            {{ $laporan->status->value === 'dikembalikan' ? 'Ajukan ulang' : 'Ajukan laporan' }}
                        </x-button>
                    </form>
                @else
                    <dl class="space-y-2 text-[13px]">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Status</dt>
                            <dd><x-chip :tone="$laporan->status->tone()">{{ $laporan->status->label() }}</x-chip></dd>
                        </div>
                        @if ($laporan->diajukan_at)
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-muted">Diajukan</dt>
                                <dd class="tabular text-right">{{ Format::tanggal($laporan->diajukan_at) }}</dd>
                            </div>
                        @endif
                        @if ($laporan->kesimpulan)
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-muted">Kesimpulan</dt>
                                <dd><x-chip :tone="$laporan->kesimpulan->tone()">{{ $laporan->kesimpulan->label() }}</x-chip></dd>
                            </div>
                        @endif
                    </dl>

                    @if ($laporan->catatan_asesor)
                        <p class="mt-3 rounded-control border border-line bg-zebra px-3 py-2 text-[12.5px] leading-relaxed">
                            {{ $laporan->catatan_asesor }}
                        </p>
                    @endif

                    <div class="mt-3">
                        <x-button href="{{ route('dosen.bkd.unduh', $laporan) }}" variant="outline" size="sm">
                            Unduh lembar BKD
                        </x-button>
                    </div>
                @endif
            </x-card>

            <x-card title="Dari Mana Angkanya">
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    Unsur Pendidikan &amp; Pengajaran ditarik dari rekaman kelas, bimbingan
                    tugas akhir, pengujian sidang, dan perwalian pada semester ini — tidak
                    perlu Anda ketik ulang.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-muted">
                    Tiga unsur lainnya tidak pernah melewati sistem akademik, jadi harus
                    Anda catat sendiri beserta buktinya.
                </p>
                <div class="mt-3">
                    <x-button href="{{ route('dosen.portofolio') }}" variant="outline" size="sm">
                        Catat kegiatan
                    </x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
