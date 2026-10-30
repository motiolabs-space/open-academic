@php
    use App\Support\Format;

    /** @var \App\Models\Helpdesk\HelpdeskTiket $tiket */
@endphp

{{-- The facts of a ticket, in a definition list. Shared by the applicant's
     and the staff screens so the two never show different facts. --}}
<dl class="grid gap-x-6 gap-y-3 text-[13px] sm:grid-cols-2">
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('helpdesk.tiket.status') }}</dt>
        <dd class="mt-1 flex flex-wrap items-center gap-1.5">
            <x-chip :tone="$tiket->status->tone()" dot>{{ $tiket->status->label() }}</x-chip>
            @if ($tiket->lewatSla())
                <x-chip tone="danger">{{ __('helpdesk.tiket.lewat_sla') }}</x-chip>
            @endif
        </dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('helpdesk.tiket.prioritas') }}</dt>
        <dd class="mt-1"><x-chip :tone="$tiket->prioritas->tone()">{{ $tiket->prioritas->label() }}</x-chip></dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('helpdesk.tiket.kategori') }}</dt>
        <dd class="mt-1">{{ $tiket->kategori?->nama ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('helpdesk.tiket.unit') }}</dt>
        <dd class="mt-1">
            {{ $tiket->unitKerja?->nama ?? __('helpdesk.tiket.belum_disposisi') }}
            <span class="block text-[11.5px] text-ink-faint">
                {{ $tiket->petugas?->nama ?? __('helpdesk.tiket.belum_ada_petugas') }}
            </span>
        </dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('helpdesk.tiket.dibuat') }}</dt>
        <dd class="tabular mt-1">
            {{ Format::tanggal($tiket->created_at) }} {{ Format::jam($tiket->created_at) }}
            {{-- Typed in by an officer on the applicant's behalf: said on both
                 sides, so "I never filed that" has an answer. --}}
            @if ($tiket->dicatat_oleh_staff_id)
                <span class="block text-[11.5px] text-ink-faint">{{ __('helpdesk.staf.dicatat_oleh', ['nama' => $tiket->pencatat?->nama ?? '—']) }}</span>
            @endif
        </dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('helpdesk.tiket.tenggat_sla') }}</dt>
        <dd @class(['tabular mt-1', 'text-danger' => $tiket->lewatSla()])>
            @if ($tiket->tenggat_sla_at)
                {{ Format::tanggal($tiket->tenggat_sla_at) }} {{ Format::jam($tiket->tenggat_sla_at) }}
            @else
                {{ __('helpdesk.tiket.tanpa_sla') }}
            @endif
        </dd>
    </div>
</dl>
