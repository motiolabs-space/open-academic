@foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
    @if (session($kunci))
        <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
    @endif
@endforeach

{{-- Apa yang ikut terjadi karena persetujuan — mis. akun pensiunan yang belum
     dapat ditutup. Dipisah dari pesan sukses supaya tidak tenggelam di dalamnya. --}}
@foreach ((array) session('catatan_validasi', []) as $catatan)
    <div class="mb-5"><x-alert tone="warning">{{ $catatan }}</x-alert></div>
@endforeach

@if ($errors->any())
    <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
@endif
