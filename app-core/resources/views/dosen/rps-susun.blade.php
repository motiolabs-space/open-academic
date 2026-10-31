@extends('layouts.app')

@section('title', 'Susun RPS')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @unless ($rps->status->dapatDisunting())
        <div class="mb-5">
            {{-- Alasannya disebut, bukan sekadar larangannya. --}}
            <x-alert tone="info">
                RPS versi {{ $rps->versi }} sudah berlaku dan tidak dapat disunting. Nilai yang
                sudah diukur terhadap rumusan ini tidak boleh berubah artinya — revisi berarti
                versi baru.
            </x-alert>
        </div>
    @endunless

    <form method="POST" action="{{ route('dosen.rps.simpan', $rps) }}">
        @csrf @method('PUT')

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-5">
                <x-card flush title="Rencana Mingguan"
                    :meta="'total bobot '.$rps->totalBobot().'%'">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-4 py-3 font-semibold">Pekan</th>
                                    @if ($versiCpmk)
                                        <th class="px-4 py-3 font-semibold">{{ __('capaian.istilah.sub') }}</th>
                                    @endif
                                    <th class="px-4 py-3 font-semibold">Kemampuan Akhir (Sub-CPMK)</th>
                                    <th class="px-4 py-3 font-semibold">Bahan Kajian</th>
                                    <th class="px-4 py-3 font-semibold">Metode</th>
                                    <th class="px-4 py-3 font-semibold">Bobot</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 1; $i <= $jumlahPertemuan; $i++)
                                    @php $p = $rps->pertemuan->firstWhere('pertemuan_ke', $i); @endphp
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="tabular px-4 py-2 font-medium">
                                            {{ $i }}
                                            <input type="hidden" name="pertemuan[{{ $i }}][pertemuan_ke]" value="{{ $i }}">
                                        </td>
                                        @if ($versiCpmk)
                                            {{-- Sub-CPMK yang diajarkan pekan ini, dari versi CPMK yang sama
                                                 dengan yang diukur komponen nilai — satu sumber. --}}
                                            <td class="px-4 py-2">
                                                <select name="pertemuan[{{ $i }}][sub_cpmk_id]"
                                                    @disabled(! $rps->status->dapatDisunting())
                                                    class="w-32 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px] outline-none focus:border-navy">
                                                    <option value="">—</option>
                                                    @foreach ($versiCpmk->cpmk as $c)
                                                        @foreach ($c->sub as $sub)
                                                            <option value="{{ $sub->id }}" @selected($p?->sub_cpmk_id === $sub->id)
                                                                title="{{ $sub->rumusan }}">{{ $sub->kode }}</option>
                                                        @endforeach
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif
                                        <td class="px-4 py-2">
                                            <input type="text" name="pertemuan[{{ $i }}][kemampuan_akhir]"
                                                value="{{ $p?->kemampuan_akhir }}"
                                                @disabled(! $rps->status->dapatDisunting())
                                                class="w-full rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-navy">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="text" name="pertemuan[{{ $i }}][bahan_kajian]"
                                                value="{{ $p?->bahan_kajian }}"
                                                @disabled(! $rps->status->dapatDisunting())
                                                class="w-full rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-navy">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="text" name="pertemuan[{{ $i }}][metode]"
                                                value="{{ $p?->metode }}"
                                                @disabled(! $rps->status->dapatDisunting())
                                                class="w-full rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-navy">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" min="0" max="100"
                                                name="pertemuan[{{ $i }}][bobot]" value="{{ $p?->bobot ?? 0 }}"
                                                @disabled(! $rps->status->dapatDisunting())
                                                class="tabular w-16 rounded-control border border-line-input bg-surface px-2 py-1.5 text-right text-[12.5px] outline-none focus:border-navy">
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </x-card>

                <x-card title="Deskripsi & Pustaka">
                    <div class="space-y-3">
                        <x-field label="Deskripsi mata kuliah" name="deskripsi" type="textarea"
                            :value="$rps->deskripsi" />
                        <x-field label="Pustaka" name="pustaka" type="textarea" :value="$rps->pustaka" />
                    </div>
                </x-card>
            </div>

            <div class="space-y-5">
                @if ($versiCpmk)
                    <x-card :title="__('capaian.istilah.cpmk').' · '.__('capaian.istilah.versi', ['versi' => $versiCpmk->versi])"
                        :meta="$rps->cpl->count().' CPL'">
                        <p class="mb-3 text-[12.5px] leading-relaxed text-ink-muted">{{ __('capaian.rps.pengantar') }}</p>

                        <ul class="space-y-3">
                            @foreach ($versiCpmk->cpmk as $c)
                                <li class="text-[12.5px]">
                                    <div><span class="font-semibold">{{ $c->kode }}</span> <span class="text-ink-muted">{{ $c->rumusan }}</span></div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($c->cpl as $cpl)
                                            <x-chip>{{ $cpl->kode }} · <span class="tabular">{{ $cpl->pivot->bobot }}%</span></x-chip>
                                        @endforeach
                                    </div>
                                    @if ($c->sub->isNotEmpty())
                                        <ul class="mt-1.5 space-y-0.5 border-l border-line pl-3 text-[12px] text-ink-muted">
                                            @foreach ($c->sub as $sub)
                                                <li><span class="font-medium text-ink">{{ $sub->kode }}</span> {{ $sub->rumusan }} <span class="tabular text-ink-faint">({{ $sub->bobot }}%)</span></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-4">
                            <x-button size="sm" variant="outline" :href="route('dosen.cpmk', $mataKuliah)">{{ __('capaian.rps.kelola') }}</x-button>
                        </div>
                    </x-card>
                @else
                <x-card title="CPL yang Dibebankan" :meta="$rps->cpl->count().' dipilih'">
                    <p class="mb-3 text-[12.5px] leading-relaxed text-ink-muted">
                        Pilih beberapa, bukan semuanya. Mata kuliah yang mengaku menjawab seluruh
                        CPL sama dengan tidak menjawab satu pun — angka ketercapaiannya menjadi
                        rerata atas segalanya dan berhenti menunjuk ke mana-mana.
                    </p>

                    <div class="space-y-2">
                        @foreach ($cplPilihan as $cpl)
                            <label class="flex cursor-pointer items-start gap-2 text-[12.5px]">
                                <input type="checkbox" name="cpl[]" value="{{ $cpl->id }}"
                                    class="mt-0.5 accent-navy"
                                    @checked($rps->cpl->contains('id', $cpl->id))
                                    @disabled(! $rps->status->dapatDisunting())>
                                <span>
                                    <span class="font-medium">{{ $cpl->kode }}</span>
                                    <span class="text-ink-muted">{{ $cpl->deskripsi }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-4 border-t border-line pt-3">
                        <p class="text-[12px] text-ink-faint">{{ __('capaian.rps.tanpa_cpmk') }}</p>
                        <x-button size="sm" variant="outline" class="mt-2" :href="route('dosen.cpmk', $mataKuliah)">{{ __('capaian.rps.susun') }}</x-button>
                    </div>
                </x-card>

                @endif

                @if ($rps->status->dapatDisunting())
                    <x-card title="Sebelum Diterbitkan">
                        @if ($kekurangan === [])
                            <p class="text-[13px] text-success-ink">Seluruh syarat sudah terpenuhi.</p>
                        @else
                            {{-- Disebutkan pekan dan CPL mana persisnya, supaya tidak
                                 ada yang menggulir enam belas baris mencari yang kosong. --}}
                            <ul class="space-y-1.5">
                                @foreach ($kekurangan as $k)
                                    <li class="text-[12.5px] leading-relaxed text-warning-ink">{{ $k }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-button type="submit" size="sm" variant="outline">Simpan draf</x-button>
                        </div>
                    </x-card>
                @endif
            </div>
        </div>
    </form>

    @if ($rps->status->dapatDisunting() && $kekurangan === [])
        <form method="POST" action="{{ route('dosen.rps.terbitkan', $rps) }}" class="mt-5">
            @csrf
            <x-button type="submit">Terbitkan RPS</x-button>
            <span class="ml-2 text-[11.5px] text-ink-faint">
                Setelah terbit, rumusannya dibekukan.
            </span>
        </form>
    @endif
@endsection
