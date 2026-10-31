{{--
    Matriks komponen × sub-CPMK/CPMK untuk satu kelas (ADR-038).

    Kolom yang kosong adalah temuan yang dicari layar ini: sub-CPMK yang
    dijanjikan RPS tetapi tidak diukur komponen mana pun. Peringatannya tampil
    selagi dosen masih dapat menutupnya — bukan saat visitasi.
--}}
@php
    $terkunci = $kelas->status_nilai === 'final';
    $sasaran = $cakupan['sasaran'];
@endphp

<x-card class="mb-5" :title="__('capaian.pemetaan.judul')"
    :meta="$cakupan['versi'] ? __('capaian.pemetaan.meta', ['mk' => $kelas->mataKuliah->kode, 'versi' => $cakupan['versi']->versi]) : null">
    @if ($cakupan['versi'] === null)
        <p class="text-[12.5px] leading-relaxed text-ink-muted">{{ __('capaian.pemetaan.tanpa_versi') }}</p>
        @if (Route::has('dosen.cpmk'))
            <x-button size="sm" variant="outline" class="mt-3" :href="route('dosen.cpmk', $kelas->mataKuliah)">{{ __('capaian.pemetaan.kelola_cpmk') }}</x-button>
        @endif
    @else
        @if ($cakupan['versi_lama'])
            <x-alert tone="warning" class="mb-3">{{ __('capaian.pemetaan.versi_lama', ['versi' => $cakupan['versi_lama']->versi]) }}</x-alert>
        @endif

        @if ($cakupan['tak_terukur']->isNotEmpty())
            <x-alert tone="warning" class="mb-3">
                {{ __('capaian.pemetaan.tak_terukur', [
                    'jumlah' => $cakupan['tak_terukur']->count(),
                    'daftar' => $cakupan['tak_terukur']->pluck('kode')->implode(', '),
                ]) }}
            </x-alert>
        @elseif ($cakupan['komponen_tanpa']->isEmpty())
            <x-alert tone="success" class="mb-3">{{ __('capaian.pemetaan.lengkap') }}</x-alert>
        @endif

        @if ($cakupan['komponen_tanpa']->isNotEmpty())
            <p class="mb-3 text-[12px] text-ink-faint">
                {{ __('capaian.pemetaan.komponen_tanpa', ['daftar' => $cakupan['komponen_tanpa']->pluck('nama')->implode(', ')]) }}
            </p>
        @endif

        <p class="mb-3 text-[12.5px] leading-relaxed text-ink-muted">
            {{ $terkunci ? __('capaian.pemetaan.terkunci') : __('capaian.pemetaan.pengantar') }}
        </p>

        <form method="POST" action="{{ route('dosen.nilai.pemetaan', $kelas) }}"
            x-data="{
                porsi: @js((object) $cakupan['matriks']),
                jumlah(id) { return Object.values(this.porsi[id] || {}).reduce((t, n) => t + (parseInt(n) || 0), 0); },
            }">
            @csrf

            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]" style="min-width: {{ 260 + 84 * $sasaran->count() }}px">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                            <th class="py-2 pr-3 font-semibold">{{ __('capaian.pemetaan.komponen') }}</th>
                            @foreach ($sasaran as $kunci => $s)
                                <th class="px-1 py-2 text-center font-semibold normal-case" title="{{ $s['rumusan'] }}">
                                    <span @class(['text-warning' => $cakupan['tak_terukur']->has($kunci)])>{{ $s['kode'] }}</span>
                                </th>
                            @endforeach
                            <th class="px-2 py-2 text-center font-semibold">{{ __('capaian.pemetaan.jumlah') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($komponen as $k)
                            <tr class="border-b border-line/50 last:border-b-0">
                                <td class="py-2 pr-3">
                                    <div class="font-medium">{{ $k->nama }}</div>
                                    <div class="tabular text-[11px] text-ink-faint">{{ $k->bobot }}%</div>
                                </td>
                                @foreach ($sasaran as $kunci => $s)
                                    <td class="px-1 py-2 text-center">
                                        <input type="number" min="0" max="100" step="1"
                                            name="porsi[{{ $k->id }}][{{ $kunci }}]"
                                            value="{{ $cakupan['matriks'][$k->id][$kunci] ?? '' }}"
                                            @input="porsi[{{ $k->id }}] = Object.assign({}, porsi[{{ $k->id }}] || {}, { '{{ $kunci }}': $event.target.value })"
                                            @disabled($terkunci)
                                            aria-label="{{ $k->nama }} · {{ $s['kode'] }}"
                                            class="tabular w-16 rounded-control border border-line-input bg-surface px-1.5 py-1 text-center outline-none focus:border-navy disabled:bg-line/40 disabled:text-ink-faint">
                                    </td>
                                @endforeach
                                <td class="tabular px-2 py-2 text-center font-semibold"
                                    :class="[0, 100].includes(jumlah({{ $k->id }})) ? 'text-ink-muted' : 'text-danger'"
                                    x-text="jumlah({{ $k->id }}) + '%'"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @unless ($terkunci)
                <div class="mt-3 flex justify-end border-t border-line pt-3">
                    <x-button type="submit">{{ __('capaian.pemetaan.simpan') }}</x-button>
                </div>
            @endunless
        </form>
    @endif
</x-card>
