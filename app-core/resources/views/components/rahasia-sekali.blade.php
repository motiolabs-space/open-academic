@props(['data'])

{{-- A token or secret, shown exactly once from a flashed session value. The
     page carrying it is sent no-store; nothing else holds the plain text. --}}
<div class="mb-5 rounded-card border-2 border-gold bg-gold/10 px-5 py-4" role="alert">
    <div class="mb-2 flex items-center gap-2 text-[13px] font-bold text-navy">
        <span aria-hidden="true">🔑</span>
        {{ $data['judul'] }}
    </div>

    <code class="tabular block select-all break-all rounded border border-gold/50 bg-surface px-3 py-2 text-[13px] font-bold">{{ $data['nilai'] }}</code>

    <p class="mt-2.5 text-[12px] leading-relaxed text-ink-muted">{{ $data['catatan'] }}</p>
</div>
