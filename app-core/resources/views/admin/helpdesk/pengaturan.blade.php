@extends('layouts.app')

@section('title', __('helpdesk.pengaturan.judul'))

@section('aksi')
    <x-button href="{{ route('bantuan') }}" variant="outline" size="sm">{{ __('helpdesk.pengaturan.lihat_publik') }}</x-button>
    <x-button href="{{ route('admin.helpdesk') }}" variant="outline" size="sm">← {{ __('helpdesk.antrean.judul') }}</x-button>
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="space-y-6">
        <x-card :title="__('helpdesk.pengaturan.kategori')">
            <p class="mb-4 text-[12.5px] text-ink-muted">
                {{ __('helpdesk.pengaturan.sla_hint', ['jam' => $slaBawaan]) }}
            </p>

            {{-- One form per row. A <form> cannot wrap table cells, and the
                 field component puts stray attributes on its wrapper rather
                 than the input — so rows are a grid, not a table. --}}
            <div class="space-y-4">
                @foreach ($kategori as $k)
                    <form method="POST" action="{{ route('admin.helpdesk.kategori.perbarui', $k) }}"
                        class="grid gap-3 border-b border-line/50 pb-4 last:border-b-0 lg:grid-cols-[minmax(0,1fr)_220px_90px_80px_110px_auto]">
                        @csrf @method('PUT')
                        <div class="space-y-2">
                            <x-field :label="__('helpdesk.pengaturan.nama')" name="nama" :value="$k->nama" required />
                            <x-field :label="__('helpdesk.pengaturan.deskripsi')" name="deskripsi" :value="$k->deskripsi" />
                        </div>
                        <x-field :label="__('helpdesk.pengaturan.unit_bawaan')" name="unit_kerja_id" :options="$unitPilihan"
                            :value="$k->unit_kerja_id" :hint="__('helpdesk.pengaturan.unit_tanpa')" />
                        <x-field :label="__('helpdesk.pengaturan.sla_jam')" name="sla_jam" type="number" :value="$k->sla_jam" />
                        <x-field :label="__('helpdesk.pengaturan.urutan')" name="urutan" type="number" :value="$k->urutan" />
                        <x-field :label="__('helpdesk.pengaturan.aktif')" name="is_active" type="checkbox" :value="$k->is_active" />
                        <div class="flex flex-col items-end justify-end gap-1">
                            <span class="tabular text-[11.5px] text-ink-faint">{{ $k->tiket_count }} {{ strtolower(__('helpdesk.pengaturan.jumlah_tiket')) }}</span>
                            <x-button type="submit" size="sm" variant="outline">{{ __('helpdesk.pengaturan.simpan') }}</x-button>
                        </div>
                    </form>
                @endforeach

                <form method="POST" action="{{ route('admin.helpdesk.kategori.simpan') }}"
                    class="grid gap-3 rounded-card border border-dashed border-line-input bg-navy/[0.03] p-4 lg:grid-cols-[minmax(0,1fr)_220px_90px_80px_110px_auto]">
                    @csrf
                    <div class="space-y-2">
                        <x-field :label="__('helpdesk.pengaturan.nama')" name="nama" required />
                        <x-field :label="__('helpdesk.pengaturan.deskripsi')" name="deskripsi" />
                    </div>
                    <x-field :label="__('helpdesk.pengaturan.unit_bawaan')" name="unit_kerja_id" :options="$unitPilihan"
                        :hint="__('helpdesk.pengaturan.unit_tanpa')" />
                    <x-field :label="__('helpdesk.pengaturan.sla_jam')" name="sla_jam" type="number" />
                    <x-field :label="__('helpdesk.pengaturan.urutan')" name="urutan" type="number" value="0" />
                    <x-field :label="__('helpdesk.pengaturan.aktif')" name="is_active" type="checkbox" value="1" />
                    <div class="flex items-end justify-end">
                        <x-button type="submit" size="sm">{{ __('helpdesk.pengaturan.kategori_tambah') }}</x-button>
                    </div>
                </form>
            </div>
        </x-card>

        <x-card :title="__('helpdesk.pengaturan.faq')">
            <div class="space-y-4">
                @forelse ($faq as $f)
                    <form method="POST" action="{{ route('admin.helpdesk.faq.perbarui', $f) }}"
                        class="grid gap-3 border-b border-line/50 pb-4 last:border-b-0 lg:grid-cols-[minmax(0,1fr)_200px]">
                        @csrf @method('PUT')
                        <div class="space-y-2">
                            <x-field :label="__('helpdesk.pengaturan.pertanyaan')" name="pertanyaan" :value="$f->pertanyaan" required />
                            <x-field :label="__('helpdesk.pengaturan.jawaban')" name="jawaban" type="textarea" :value="$f->jawaban" required />
                        </div>
                        <div class="space-y-2">
                            <x-field :label="__('helpdesk.pengaturan.kategori_faq')" name="helpdesk_kategori_id" :options="$kategoriPilihan"
                                :value="$f->helpdesk_kategori_id" :hint="__('helpdesk.pengaturan.tanpa_kategori')" />
                            <x-field :label="__('helpdesk.pengaturan.urutan')" name="urutan" type="number" :value="$f->urutan" />
                            <x-field :label="__('helpdesk.pengaturan.terbit')" name="is_published" type="checkbox" :value="$f->is_published"
                                :hint="__('helpdesk.pengaturan.terbit')" />
                            <div class="flex flex-wrap items-center gap-2">
                                <x-button type="submit" size="sm" variant="outline">{{ __('helpdesk.pengaturan.simpan') }}</x-button>
                                <x-chip :tone="$f->is_published ? 'success' : 'neutral'">
                                    {{ $f->is_published ? __('helpdesk.pengaturan.terbit') : __('helpdesk.pengaturan.draf') }}
                                </x-chip>
                                <x-button type="submit" size="sm" variant="danger"
                                    form="hapus-faq-{{ $f->id }}">{{ __('helpdesk.pengaturan.hapus') }}</x-button>
                            </div>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.helpdesk.faq.hapus', $f) }}" id="hapus-faq-{{ $f->id }}">
                        @csrf @method('DELETE')
                    </form>
                @empty
                    <x-empty-state :title="__('helpdesk.pengaturan.faq_kosong')"
                        :description="__('helpdesk.pengaturan.faq_kosong_deskripsi')" />
                @endforelse

                <form method="POST" action="{{ route('admin.helpdesk.faq.simpan') }}"
                    class="grid gap-3 rounded-card border border-dashed border-line-input bg-navy/[0.03] p-4 lg:grid-cols-[minmax(0,1fr)_200px]">
                    @csrf
                    <div class="space-y-2">
                        <x-field :label="__('helpdesk.pengaturan.pertanyaan')" name="pertanyaan" required />
                        <x-field :label="__('helpdesk.pengaturan.jawaban')" name="jawaban" type="textarea" required />
                    </div>
                    <div class="space-y-2">
                        <x-field :label="__('helpdesk.pengaturan.kategori_faq')" name="helpdesk_kategori_id" :options="$kategoriPilihan"
                            :hint="__('helpdesk.pengaturan.tanpa_kategori')" />
                        <x-field :label="__('helpdesk.pengaturan.urutan')" name="urutan" type="number" value="0" />
                        <x-field :label="__('helpdesk.pengaturan.terbit')" name="is_published" type="checkbox" value="1"
                            :hint="__('helpdesk.pengaturan.terbit')" />
                        <x-button type="submit" size="sm">{{ __('helpdesk.pengaturan.faq_tambah') }}</x-button>
                    </div>
                </form>
            </div>
        </x-card>
    </div>
@endsection
