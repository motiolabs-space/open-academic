{{-- Riwayat versi proposal dan telaahnya, terbaru di atas. Dipakai tiga portal;
     relasi proposal.telaah.dosen, .staff, dan proposal.calonPembimbing harus
     sudah dimuat pemanggilnya. --}}
@php use App\Support\Format; @endphp

@if ($daftarProposal->isEmpty())
    <p class="text-[13px] text-ink-muted">{{ __('tugas_akhir_proposal.keterangan.belum_ada') }}</p>
@else
    <ol class="space-y-4">
        @foreach ($daftarProposal as $p)
            <li class="border-b border-line/50 pb-4 last:border-b-0 last:pb-0">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('tugas_akhir_proposal.keterangan.versi', ['versi' => $p->versi]) }}</span>
                        <p class="font-medium">{{ $p->judul }}</p>
                    </div>
                    <x-chip :tone="$p->status->tone()">{{ $p->status->label() }}</x-chip>
                </div>
                <p class="mt-1 text-[11.5px] text-ink-faint">
                    {{ __('tugas_akhir_proposal.keterangan.diunggah', ['tanggal' => Format::tanggal($p->created_at)]) }}
                    @if ($p->calonPembimbing)
                        · {{ __('tugas_akhir_proposal.keterangan.calon', ['nama' => $p->calonPembimbing->namaLengkap()]) }}
                    @endif
                    · <a href="{{ route('berkas.proposal-ta', $p) }}" class="text-navy hover:underline">{{ $p->berkas_nama ?? __('tugas_akhir_proposal.keterangan.unduh') }}</a>
                </p>
                @if ($p->ringkasan)
                    <p class="mt-1 whitespace-pre-line text-[12.5px] leading-relaxed">{{ $p->ringkasan }}</p>
                @endif
                @foreach ($p->telaah as $t)
                    <div class="mt-2 rounded-control border border-line bg-zebra px-3 py-2 text-[12.5px]">
                        <span class="font-medium">{{ $t->keputusan->label() }}</span>
                        <span class="text-ink-faint">· {{ __('tugas_akhir_proposal.keterangan.oleh', ['nama' => $t->namaPenelaah()]) }} · {{ Format::tanggal($t->created_at) }}</span>
                        @if ($t->catatan)
                            <p class="mt-1 whitespace-pre-line">{{ $t->catatan }}</p>
                        @endif
                    </div>
                @endforeach
            </li>
        @endforeach
    </ol>
@endif
