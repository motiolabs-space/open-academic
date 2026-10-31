{{-- Flash and validation messages shared by the self-service screens. --}}
@foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
    @if (session($kunci))
        <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
    @endif
@endforeach

@if ($errors->any())
    <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
@endif
