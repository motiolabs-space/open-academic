@php
    use App\Models\Sdm\Staff;
    use App\Support\Format;

    /** @var \App\Models\Helpdesk\HelpdeskTiket $tiket */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Helpdesk\HelpdeskBalasan> $percakapan */
@endphp

{{-- The opening message, then every reply in order. Internal notes are only
     in $percakapan on the staff screen; the applicant's controller filters
     them out in SQL, so this template never has to know. --}}
<div class="space-y-3">
    <article class="rounded-card border border-line bg-surface px-5 py-4">
        <header class="flex flex-wrap items-baseline justify-between gap-2 text-[11.5px] text-ink-faint">
            <span>
                <strong class="text-ink">{{ $tiket->pemohon?->nama ?? '—' }}</strong>
                · {{ __('helpdesk.percakapan.pemohon') }} · {{ __('helpdesk.percakapan.pesan_awal') }}
            </span>
            <span class="tabular">{{ Format::tanggal($tiket->created_at) }} {{ Format::jam($tiket->created_at) }}</span>
        </header>
        <div class="mt-2 whitespace-pre-line text-[13.5px] leading-relaxed">{{ $tiket->isi }}</div>
        @if ($tiket->adaLampiran())
            <div class="mt-3">
                <x-button href="{{ route('berkas.helpdesk.tiket', $tiket) }}" variant="outline" size="sm">
                    ⇩ {{ $tiket->lampiran_nama ?? __('helpdesk.tiket.lampiran') }}
                </x-button>
            </div>
        @endif
    </article>

    @forelse ($percakapan as $b)
        @php
            // Staff replying to their own ticket write as the applicant.
            $staf = $b->penulis instanceof Staff && ! $tiket->milik($b->penulis);
        @endphp
        <article @class([
            'rounded-card border px-5 py-4',
            'border-warning-line bg-warning-bg/40' => $b->is_internal,
            'border-line bg-surface' => ! $b->is_internal && ! $staf,
            'border-navy/20 bg-navy/[0.03]' => ! $b->is_internal && $staf,
        ])>
            <header class="flex flex-wrap items-baseline justify-between gap-2 text-[11.5px] text-ink-faint">
                <span>
                    <strong class="text-ink">{{ $b->penulis?->nama ?? '—' }}</strong>
                    · {{ $staf ? __('helpdesk.percakapan.petugas') : __('helpdesk.percakapan.pemohon') }}
                    @if ($b->is_internal)
                        · <x-chip tone="warning">{{ __('helpdesk.percakapan.internal') }}</x-chip>
                    @endif
                </span>
                <span class="tabular">{{ Format::tanggal($b->created_at) }} {{ Format::jam($b->created_at) }}</span>
            </header>
            <div class="mt-2 whitespace-pre-line text-[13.5px] leading-relaxed">{{ $b->isi }}</div>
            @if ($b->adaLampiran())
                <div class="mt-3">
                    <x-button href="{{ route('berkas.helpdesk.balasan', $b) }}" variant="outline" size="sm">
                        ⇩ {{ $b->lampiran_nama ?? __('helpdesk.tiket.lampiran') }}
                    </x-button>
                </div>
            @endif
        </article>
    @empty
        <p class="px-1 text-[12.5px] text-ink-faint">{{ __('helpdesk.percakapan.belum_ada') }}</p>
    @endforelse
</div>
