{{-- Formulir telaah untuk versi yang menunggu. $aksi adalah rute tujuan. --}}
<form method="POST" action="{{ $aksi }}" class="space-y-3">
    @csrf
    <x-field :label="__('tugas_akhir_proposal.field.keputusan')" name="keputusan"
        :options="\App\Enums\KeputusanTelaah::options()" required />
    <x-field :label="__('tugas_akhir_proposal.field.catatan')" name="catatan" type="textarea"
        :hint="__('tugas_akhir_proposal.galat.catatan_wajib')" />
    <x-button type="submit" size="sm">{{ __('tugas_akhir_proposal.aksi.simpan_telaah') }}</x-button>
</form>
