{{--
    Persetujuan notifikasi instalasi (ADR-043), dipakai bersama oleh langkah
    terakhir wizard dan oleh Pengaturan — supaya kalimat yang disetujui di
    kedua tempat itu selalu kalimat yang sama.

    Kotak centangnya tidak pernah tercentang kecuali oleh pilihan kampus
    sendiri: $setuju datang dari old() atau dari Setting, bukan dari bawaan.

    Variabel: $diizinkan, $tujuan, $urlAplikasi, $setuju, $sertakanUrl.
--}}

<div x-data="{ setuju: @js($setuju) }" class="flex flex-col gap-3.5 text-[12.5px] leading-relaxed">
    <p class="text-ink-muted">{{ __('instalasi.telemetri.pengantar', ['tujuan' => $tujuan]) }}</p>

    <div>
        <p class="mb-1.5 font-semibold">{{ __('instalasi.telemetri.yang_dikirim') }}</p>
        <ul class="list-disc space-y-0.5 pl-5 text-ink-muted">
            @foreach (__('instalasi.telemetri.daftar') as $kunci => $keterangan)
                <li><code class="rounded bg-line/50 px-1 text-[11.5px]">{{ $kunci }}</code> — {{ $keterangan }}</li>
            @endforeach
        </ul>
    </div>

    <p class="text-ink-muted">{{ __('instalasi.telemetri.tidak_dikirim') }}</p>

    @if ($diizinkan)
        <label class="flex items-start gap-2.5 rounded-control border border-line px-3.5 py-3">
            <input type="hidden" name="telemetri_setuju" value="0">
            <input type="checkbox" name="telemetri_setuju" value="1" class="mt-0.5 accent-navy"
                x-model="setuju" @checked($setuju)>
            <span class="font-semibold">{{ __('instalasi.telemetri.setuju', ['tujuan' => $tujuan]) }}</span>
        </label>

        <label class="flex items-start gap-2.5 pl-1" :class="setuju ? '' : 'opacity-50'">
            <input type="hidden" name="telemetri_sertakan_url" value="0">
            <input type="checkbox" name="telemetri_sertakan_url" value="1" class="mt-0.5 accent-navy"
                :disabled="!setuju" @checked($sertakanUrl)>
            <span>
                {{ __('instalasi.telemetri.sertakan_url', ['url' => $urlAplikasi]) }}
                <span class="block text-[11.5px] text-ink-faint">{{ __('instalasi.telemetri.sertakan_url_hint') }}</span>
            </span>
        </label>
    @else
        <x-alert tone="info">{{ __('instalasi.telemetri.dimatikan_env') }}</x-alert>
    @endif

    <p class="text-[11.5px] text-ink-faint">{{ __('instalasi.telemetri.matikan_kapan_saja') }}</p>
</div>
