@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'options' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => null,
])

@php
    $id = $name.'-'.Str::random(6);
    $galat = $errors->first($name);
    $isi = old($name, $value);
@endphp

{{-- One definition of a form field, so six master-data screens do not carry six
     copies of the same markup — and an accessibility fix lands in one place. --}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <label for="{{ $id }}" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="sr-only">(wajib)</span>
        @endif
    </label>

    @php
        $kelas = 'w-full rounded-control border bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10 '
            .($galat ? 'border-danger bg-danger-bg/30' : 'border-line-input');
    @endphp

    @if ($options !== null)
        <select
            id="{{ $id }}" name="{{ $name }}" class="{{ $kelas }}"
            @required($required) @if ($galat) aria-invalid="true" aria-describedby="{{ $id }}-galat" @endif
        >
            @unless ($required)
                <option value="">—</option>
            @endunless
            @foreach ($options as $nilai => $teks)
                <option value="{{ $nilai }}" @selected((string) $isi === (string) $nilai)>{{ $teks }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea
            id="{{ $id }}" name="{{ $name }}" rows="3" class="{{ $kelas }}"
            placeholder="{{ $placeholder }}" @required($required)
            @if ($galat) aria-invalid="true" aria-describedby="{{ $id }}-galat" @endif
        >{{ $isi }}</textarea>
    @elseif ($type === 'checkbox')
        <label class="flex items-center gap-2 py-2 text-[13px]">
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox" name="{{ $name }}" value="1" class="accent-navy" @checked((bool) $isi)>
            {{ $hint ?? 'Aktif' }}
        </label>
    @else
        <input
            id="{{ $id }}" type="{{ $type }}" name="{{ $name }}"
            value="{{ $type === 'date' && $isi ? \Illuminate\Support\Carbon::parse($isi)->toDateString() : $isi }}"
            placeholder="{{ $placeholder }}" class="{{ $kelas }}" @required($required)
            @if ($galat) aria-invalid="true" aria-describedby="{{ $id }}-galat" @endif
        >
    @endif

    @if ($galat)
        <p id="{{ $id }}-galat" class="mt-1 text-[11.5px] text-danger">{{ $galat }}</p>
    @elseif ($hint && $type !== 'checkbox')
        <p class="mt-1 text-[11.5px] text-ink-faint">{{ $hint }}</p>
    @endif
</div>
