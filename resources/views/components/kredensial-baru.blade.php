@props(['data'])

{{-- Shown exactly once, from a flash message, and never persisted anywhere
     readable. An operator has to hand this over now or reset it again. --}}
<div class="mb-5 rounded-card border-2 border-gold bg-gold/10 px-5 py-4">
    <div class="mb-2 flex items-center gap-2 text-[13px] font-bold text-navy">
        <span aria-hidden="true">🔑</span>
        Kata sandi untuk {{ $data['nama'] }}
    </div>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px]">
        <div>
            <span class="text-ink-muted">Masuk dengan</span>
            <span class="tabular ml-1 font-semibold">{{ $data['identitas'] }}</span>
        </div>
        <div>
            <span class="text-ink-muted">Kata sandi</span>
            <code class="tabular ml-1 select-all rounded border border-gold/50 bg-surface px-2 py-1 font-bold">{{ $data['kata_sandi'] }}</code>
        </div>
    </div>

    <p class="mt-2.5 text-[12px] leading-relaxed text-ink-muted">
        Catat sekarang — kata sandi ini tidak disimpan dan tidak dapat ditampilkan
        ulang. Bila hilang, gunakan tombol <span class="font-semibold">Reset Sandi</span>
        untuk menerbitkan yang baru.
    </p>
</div>
