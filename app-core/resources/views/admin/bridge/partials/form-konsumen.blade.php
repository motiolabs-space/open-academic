{{-- Fields shared by registering a consumer and editing one. The slug is
     asked once: the artisan command and live integrations know it by name. --}}
<x-field :label="__('bridge.daftar.nama')" name="nama" :value="$konsumen?->nama" required />

@if ($konsumen === null)
    <x-field :label="__('bridge.daftar.slug')" name="slug" required :hint="__('bridge.daftar.slug_hint')" />
@else
    <div class="text-[12.5px]">
        <span class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('bridge.daftar.slug') }}</span>
        <div class="tabular mt-1">{{ $konsumen->slug }}</div>
    </div>
@endif

<x-field :label="__('bridge.daftar.deskripsi')" name="deskripsi" :value="$konsumen?->deskripsi" />
<x-field :label="__('bridge.daftar.base_url')" name="base_url" type="url" :value="$konsumen?->base_url" />

<fieldset>
    <legend class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('bridge.daftar.scopes') }}</legend>
    <div class="space-y-1.5">
        @foreach (config('bridge.scopes') as $scope => $keterangan)
            <label class="flex items-start gap-2 text-[12.5px]">
                <input type="checkbox" name="scopes[]" value="{{ $scope }}" class="mt-0.5 accent-navy"
                    @checked(in_array($scope, old('scopes', $konsumen?->scopes ?? []), true))>
                <span><span class="tabular font-medium">{{ $scope }}</span> <span class="text-ink-muted">— {{ $keterangan }}</span></span>
            </label>
        @endforeach
    </div>
</fieldset>

<x-field :label="__('bridge.daftar.aktif')" name="is_active" type="checkbox" :value="$konsumen?->is_active ?? true"
    :hint="__('bridge.daftar.aktif_hint')" />
