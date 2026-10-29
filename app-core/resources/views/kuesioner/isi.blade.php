@php use App\Enums\TipePertanyaan; @endphp
@extends('layouts.app')

@section('title', $kuesioner->nama)

@section('content')
    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mx-auto max-w-3xl space-y-5">
        <x-card>
            @if ($kuesioner->deskripsi)
                <p class="whitespace-pre-line text-[13.5px] leading-relaxed">{{ $kuesioner->deskripsi }}</p>
            @endif

            {{-- Said before the first question, not after the last: it is what
                 decides how candidly somebody answers. --}}
            <p @class(['text-[12.5px] leading-relaxed text-ink-muted', 'mt-3' => $kuesioner->deskripsi])>
                {{ $kuesioner->anonim ? __('kuesioner.isi.anonim') : __('kuesioner.isi.beridentitas') }}
            </p>
        </x-card>

        @if ($sudah)
            <x-alert tone="success">{{ __('kuesioner.isi.sudah_isi') }}</x-alert>
        @elseif (! $terbuka)
            <x-alert tone="warning">{{ __('kuesioner.isi.tidak_dibuka') }}</x-alert>
        @else
            <form method="POST" action="{{ route($rute.'.kirim', $kuesioner) }}" class="space-y-4"
                data-confirm="{{ __('kuesioner.isi.kirim_konfirmasi') }}">
                @csrf

                @foreach ($kuesioner->pertanyaan as $p)
                    <x-card>
                        <fieldset>
                            <legend class="mb-3 text-[13.5px] font-medium">
                                <span class="tabular text-ink-faint">{{ $p->urutan }}.</span> {{ $p->teks }}
                                @if ($p->wajib)
                                    <span class="text-danger" aria-hidden="true">*</span>
                                    <span class="sr-only">(wajib)</span>
                                @endif
                            </legend>

                            @if ($p->tipe === TipePertanyaan::Skala)
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[11px] text-ink-faint">{{ __('kuesioner.isi.skala_rendah') }}</span>
                                    @foreach (range(1, 5) as $nilai)
                                        <label class="flex cursor-pointer items-center gap-1.5 rounded-control border border-line-input px-3 py-1.5 text-[12px] has-[:checked]:border-navy has-[:checked]:bg-navy/5">
                                            <input type="radio" class="accent-navy" name="jawaban[{{ $p->id }}][nilai]" value="{{ $nilai }}"
                                                @required($p->wajib) @checked((string) old('jawaban.'.$p->id.'.nilai') === (string) $nilai)>
                                            <span class="tabular font-medium">{{ $nilai }}</span>
                                        </label>
                                    @endforeach
                                    <span class="text-[11px] text-ink-faint">{{ __('kuesioner.isi.skala_tinggi') }}</span>
                                </div>
                            @elseif ($p->tipe === TipePertanyaan::Pilihan)
                                <div class="space-y-1.5">
                                    @foreach ((array) $p->opsi as $opsi)
                                        <label class="flex cursor-pointer items-center gap-2 text-[13px]">
                                            <input type="radio" class="accent-navy" name="jawaban[{{ $p->id }}][teks]" value="{{ $opsi }}"
                                                @required($p->wajib) @checked(old('jawaban.'.$p->id.'.teks') === $opsi)>
                                            {{ $opsi }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <textarea name="jawaban[{{ $p->id }}][teks]" rows="3" maxlength="5000" @required($p->wajib)
                                    placeholder="{{ __('kuesioner.isi.teks_placeholder') }}"
                                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10">{{ old('jawaban.'.$p->id.'.teks') }}</textarea>
                            @endif
                        </fieldset>
                    </x-card>
                @endforeach

                <x-button type="submit" class="w-full">{{ __('kuesioner.isi.kirim') }}</x-button>
            </form>
        @endif
    </div>
@endsection
