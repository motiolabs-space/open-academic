{{--
    Penyunting CPMK bersama — layar prodi (staf) dan layar RPS (dosen).

    Satu formulir untuk keduanya, supaya janji sebuah mata kuliah ditulis
    dengan cara yang sama siapa pun penulisnya (ADR-038). Seluruh set disimpan
    sekaligus: aturan bobotnya melintasi baris, dan baris yang disimpan
    sendiri-sendiri hanya bisa diperiksa terhadap separuh aturan itu.
--}}
@php
    use App\Support\Format;

    $awal = $versi === null ? [] : $versi->cpmk->map(fn ($c): array => [
        'kode' => $c->kode,
        'rumusan' => $c->rumusan,
        'cpl' => $c->cpl->map(fn ($p): array => ['prodi_cpl_id' => $p->id, 'bobot' => (int) $p->pivot->bobot])->values()->all(),
        'sub' => $c->sub->map(fn ($s): array => ['kode' => $s->kode, 'rumusan' => $s->rumusan, 'bobot' => $s->bobot])->values()->all(),
    ])->values()->all();

    $kodeCpl = $cplPilihan->mapWithKeys(fn ($c): array => [$c->id => $c->kode])->all();
@endphp

@foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
    @if (session($kunci))
        <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
    @endif
@endforeach

@if ($errors->any())
    <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
@endif

<div class="mb-5">
    @if ($berlaku)
        <x-alert tone="info">
            {{ __('capaian.editor.berlaku_sekarang', [
                'versi' => $berlaku->versi,
                'tanggal' => Format::tanggal($berlaku->diterbitkan_at),
                'oleh' => $berlaku->diterbitkan_oleh ?? '—',
            ]) }}
            @if ($dapatSunting)
                {{ __('capaian.editor.sedang_draf', ['versi' => $versi->versi]) }}
            @endif
        </x-alert>
    @elseif ($dapatSunting)
        <x-alert tone="info">{{ __('capaian.editor.sedang_draf', ['versi' => $versi->versi]) }}</x-alert>
    @else
        <x-alert tone="warning">{{ __('capaian.editor.belum_berlaku') }}</x-alert>
    @endif
</div>

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
    <div>
        @if ($versi === null)
            <x-card>
                <x-empty-state :title="__('capaian.editor.belum_berlaku')" :description="__('capaian.kelola.pengantar')" />
                @if ($bolehMenyusun)
                    <form method="POST" action="{{ $ruteDraf }}" class="mt-4 text-center">
                        @csrf
                        <x-button type="submit">{{ __('capaian.rps.susun') }}</x-button>
                    </form>
                @endif
            </x-card>
        @else
            <form method="POST" action="{{ $ruteSimpan }}"
                x-data="{
                    cpmk: @js($awal),
                    kodeCpl: @js($kodeCpl),
                    terkunci: @js(! $dapatSunting),
                    tambahCpmk() { this.cpmk.push({ kode: 'CPMK-' + (this.cpmk.length + 1), rumusan: '', cpl: [{ prodi_cpl_id: '', bobot: 100 }], sub: [] }); },
                    tambahSub(c) { const i = this.cpmk.indexOf(c) + 1; c.sub.push({ kode: 'Sub-CPMK-' + i + '.' + (c.sub.length + 1), rumusan: '', bobot: 0 }); },
                    jumlahSub(c) { return c.sub.reduce((t, s) => t + (parseInt(s.bobot) || 0), 0); },
                    get perCpl() {
                        const hasil = {};
                        this.cpmk.forEach(c => c.cpl.forEach(p => {
                            if (!p.prodi_cpl_id) return;
                            hasil[p.prodi_cpl_id] = (hasil[p.prodi_cpl_id] || 0) + (parseInt(p.bobot) || 0);
                        }));
                        return hasil;
                    },
                }">
                @csrf @method('PUT')

                <div class="space-y-4">
                    <template x-for="(c, i) in cpmk" :key="i">
                        <x-card>
                            <div class="grid gap-3 md:grid-cols-[120px_minmax(0,1fr)_auto]">
                                <input type="text" :name="`cpmk[${i}][kode]`" x-model="c.kode" maxlength="16" required :disabled="terkunci"
                                    aria-label="{{ __('capaian.editor.kode') }}"
                                    class="rounded-control border border-line-input bg-surface px-2.5 py-1.5 text-[13px] font-semibold outline-none focus:border-navy disabled:bg-line/40">
                                <textarea :name="`cpmk[${i}][rumusan]`" x-model="c.rumusan" rows="2" maxlength="1000" required :disabled="terkunci"
                                    aria-label="{{ __('capaian.editor.rumusan') }}"
                                    class="rounded-control border border-line-input bg-surface px-2.5 py-1.5 text-[13px] outline-none focus:border-navy disabled:bg-line/40"></textarea>
                                <button type="button" x-show="!terkunci" @click="cpmk.splice(i, 1)"
                                    class="self-start text-[12px] text-danger hover:underline">{{ __('capaian.editor.hapus') }}</button>
                            </div>

                            <div class="mt-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('capaian.editor.cpl_didukung') }}</div>
                                <div class="mt-2 space-y-2">
                                    <template x-for="(p, j) in c.cpl" :key="j">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <select :name="`cpmk[${i}][cpl][${j}][prodi_cpl_id]`" x-model="p.prodi_cpl_id" :disabled="terkunci"
                                                class="min-w-[220px] flex-1 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-navy disabled:bg-line/40">
                                                <option value="">{{ __('capaian.editor.pilih_cpl') }}</option>
                                                @foreach ($cplPilihan as $cpl)
                                                    <option value="{{ $cpl->id }}" :selected="p.prodi_cpl_id == {{ $cpl->id }}">{{ $cpl->kode }} — {{ \Illuminate\Support\Str::limit($cpl->deskripsi, 70) }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" :name="`cpmk[${i}][cpl][${j}][bobot]`" x-model="p.bobot" min="0" max="100" :disabled="terkunci"
                                                aria-label="{{ __('capaian.editor.bobot') }}"
                                                class="tabular w-20 rounded-control border border-line-input bg-surface px-2 py-1.5 text-right text-[12.5px] outline-none focus:border-navy disabled:bg-line/40">
                                            <span class="text-[12px] text-ink-faint">%</span>
                                            <button type="button" x-show="!terkunci" @click="c.cpl.splice(j, 1)" class="text-[12px] text-danger hover:underline">{{ __('capaian.editor.hapus') }}</button>
                                        </div>
                                    </template>
                                    <button type="button" x-show="!terkunci" @click="c.cpl.push({ prodi_cpl_id: '', bobot: 100 })"
                                        class="text-[12px] font-medium text-navy hover:underline">+ {{ __('capaian.editor.tambah_cpl') }}</button>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-line pt-3">
                                <div class="flex items-baseline justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('capaian.istilah.sub') }}</div>
                                    <span x-show="c.sub.length" class="tabular text-[12px]" :class="jumlahSub(c) === 100 ? 'text-ink-muted' : 'font-semibold text-danger'">
                                        {{ __('capaian.editor.jumlah_sub') }} <span x-text="jumlahSub(c)"></span>%
                                    </span>
                                </div>
                                <p x-show="!c.sub.length" class="mt-1 text-[12px] text-ink-faint">{{ __('capaian.editor.tanpa_sub') }}</p>
                                <div class="mt-2 space-y-2">
                                    <template x-for="(s, k) in c.sub" :key="k">
                                        <div class="grid gap-2 md:grid-cols-[140px_minmax(0,1fr)_80px_auto]">
                                            <input type="text" :name="`cpmk[${i}][sub][${k}][kode]`" x-model="s.kode" maxlength="24" :disabled="terkunci"
                                                aria-label="{{ __('capaian.editor.kode') }}"
                                                class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-navy disabled:bg-line/40">
                                            <input type="text" :name="`cpmk[${i}][sub][${k}][rumusan]`" x-model="s.rumusan" maxlength="1000" :disabled="terkunci"
                                                aria-label="{{ __('capaian.editor.rumusan') }}"
                                                class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-navy disabled:bg-line/40">
                                            <input type="number" :name="`cpmk[${i}][sub][${k}][bobot]`" x-model="s.bobot" min="0" max="100" :disabled="terkunci"
                                                aria-label="{{ __('capaian.editor.bobot') }}"
                                                class="tabular rounded-control border border-line-input bg-surface px-2 py-1.5 text-right text-[12.5px] outline-none focus:border-navy disabled:bg-line/40">
                                            <button type="button" x-show="!terkunci" @click="c.sub.splice(k, 1)" class="text-[12px] text-danger hover:underline">{{ __('capaian.editor.hapus') }}</button>
                                        </div>
                                    </template>
                                    <button type="button" x-show="!terkunci" @click="tambahSub(c)"
                                        class="text-[12px] font-medium text-navy hover:underline">+ {{ __('capaian.editor.tambah_sub') }}</button>
                                </div>
                            </div>
                        </x-card>
                    </template>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-1.5 text-[12px]">
                        <template x-for="(jumlah, id) in perCpl" :key="id">
                            <span class="tabular rounded-control border px-2 py-0.5"
                                :class="jumlah === 100 ? 'border-line text-ink-muted' : 'border-danger-line font-semibold text-danger'"
                                x-text="(kodeCpl[id] || id) + ' ' + jumlah + '%'"></span>
                        </template>
                    </div>

                    @if ($dapatSunting)
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="outline" @click="tambahCpmk()">{{ __('capaian.editor.tambah_cpmk') }}</x-button>
                            <x-button type="submit">{{ __('capaian.editor.simpan') }}</x-button>
                        </div>
                    @endif
                </div>
            </form>
        @endif
    </div>

    <div class="space-y-5">
        @if ($dapatSunting)
            <x-card :title="__('capaian.editor.kekurangan')">
                @if ($kekurangan === [])
                    <p class="text-[13px] text-success-ink">{{ __('capaian.pemetaan.lengkap') }}</p>
                    <form method="POST" action="{{ $ruteTerbitkan }}" class="mt-3"
                        data-confirm="{{ __('capaian.editor.konfirmasi_terbit') }}">
                        @csrf
                        <x-button type="submit" variant="gold">{{ __('capaian.editor.terbitkan') }}</x-button>
                    </form>
                @else
                    <ul class="space-y-1.5">
                        @foreach ($kekurangan as $k)
                            <li class="text-[12.5px] leading-relaxed text-warning-ink">{{ $k }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-[11.5px] leading-relaxed text-ink-faint">{{ __('capaian.editor.aturan_bobot') }}</p>
            </x-card>
        @elseif ($versi !== null && $bolehMenyusun)
            <x-card>
                <p class="text-[12.5px] leading-relaxed text-ink-muted">{{ __('capaian.editor.beku') }}</p>
                <form method="POST" action="{{ $ruteDraf }}" class="mt-3">
                    @csrf
                    <x-button type="submit" variant="outline">{{ __('capaian.editor.mulai_revisi') }}</x-button>
                </form>
            </x-card>
        @endif

        @if ($riwayat->isNotEmpty())
            <x-card :title="__('capaian.editor.riwayat')">
                <ul class="space-y-2 text-[12.5px]">
                    @foreach ($riwayat as $v)
                        <li class="flex items-center justify-between gap-2">
                            <span>
                                {{ __('capaian.istilah.versi', ['versi' => $v->versi]) }}
                                <span class="tabular text-ink-faint">· {{ $v->cpmk_count }} {{ __('capaian.istilah.cpmk') }}</span>
                            </span>
                            <x-chip :tone="$v->status->tone()">{{ $v->status->label() }}</x-chip>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card>
            <p class="text-[12.5px] leading-relaxed text-ink-muted">{{ __('capaian.kelola.pengantar') }}</p>
        </x-card>
    </div>
</div>
