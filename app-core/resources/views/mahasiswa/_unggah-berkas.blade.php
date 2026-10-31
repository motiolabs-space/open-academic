{{-- One file input on a self-service form. Files go to the private disk
     through BerkasService; the limits come from config/berkas.php. --}}
@php $idBerkas = 'berkas-'.Str::slug($nama).'-'.Str::random(4); @endphp
<div>
    <label for="{{ $idBerkas }}" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
        {{ $label }}
        @if ($wajib)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="sr-only">(wajib)</span>
        @endif
    </label>
    <input type="file" id="{{ $idBerkas }}" name="{{ $nama }}" accept=".pdf,.jpg,.jpeg,.png"
        class="w-full text-[12.5px]" @required($wajib)>
    <p class="mt-1 text-[11.5px] text-ink-faint">
        {{ __('swalayan.berkas_petunjuk', ['mb' => number_format((int) config('berkas.maks_kb') / 1024, 0)]) }}
    </p>
</div>
