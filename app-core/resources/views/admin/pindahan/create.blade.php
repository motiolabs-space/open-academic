@extends('layouts.app')

@section('title', __('pindahan.judul_baru'))

@section('content')
    @foreach (['galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($belumDipetakan !== [])
        <div class="mb-5">
            <x-alert tone="warning">
                {{ __('pindahan.keterangan.belum_dipetakan', ['jenis' => implode(', ', $belumDipetakan)]) }}
            </x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.pindahan.store') }}"
        class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]"
        x-data="{
            saran: { pt: [], prodi: [] },
            async cari(tipe, q) {
                if (q.length < 2) { this.saran[tipe] = []; return; }
                const url = '{{ route('admin.pindahan.referensi') }}?tipe=' + tipe + '&q=' + encodeURIComponent(q);
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                this.saran[tipe] = res.ok ? await res.json() : [];
            },
        }">
        @csrf

        <div class="space-y-5">
            @if ($pendaftar)
                <input type="hidden" name="pmb_pendaftar" value="{{ $pendaftar->uuid }}">

                <x-card :title="__('pindahan.kartu.pendaftar')" :meta="$pendaftar->nomor_pendaftaran">
                    <dl class="grid gap-3 text-[13px] sm:grid-cols-3">
                        <div>
                            <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pindahan.kolom.nama') }}</dt>
                            <dd class="font-medium">{{ $pendaftar->nama }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pindahan.kolom.prodi') }}</dt>
                            <dd>{{ $pendaftar->prodiDiterima?->nama ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pindahan.kolom.gelombang') }}</dt>
                            <dd>{{ $pendaftar->gelombang->nama }} · {{ ucfirst($pendaftar->gelombang->jalur) }}</dd>
                        </div>
                    </dl>
                </x-card>
            @else
                <x-card :title="__('pindahan.kartu.identitas')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field :label="__('pindahan.kolom.nama')" name="nama" required class="sm:col-span-2" />
                        <x-field :label="__('pindahan.kolom.email')" name="email" type="email" required />
                        <x-field :label="__('pindahan.kolom.nik')" name="nik" required />
                        <x-field :label="__('pindahan.kolom.tempat_lahir')" name="tempat_lahir" required />
                        <x-field :label="__('pindahan.kolom.tanggal_lahir')" name="tanggal_lahir" type="date" required />
                        <x-field :label="__('pindahan.kolom.jenis_kelamin')" name="jenis_kelamin" :options="$genderPilihan" required />
                        <x-field :label="__('pindahan.kolom.nama_ibu')" name="nama_ibu" :hint="__('pindahan.petunjuk.nama_ibu')" />
                        <x-field :label="__('pindahan.kolom.telepon')" name="telepon" />
                        <x-field :label="__('pindahan.kolom.alamat')" name="alamat" />
                    </div>
                </x-card>
            @endif

            <x-card :title="__('pindahan.kartu.asal')">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field :label="__('pindahan.kolom.jenis_daftar')" name="jenis_daftar" :options="$jenisPilihan"
                        :value="$jenisBawaan" required class="sm:col-span-2" />

                    <div x-on:input.debounce.300ms="cari('pt', $event.target.value)">
                        <x-field :label="__('pindahan.kolom.pt_asal')" name="pt_asal" list="saran-pt" required
                            :hint="__('pindahan.petunjuk.pt_asal')" />
                    </div>
                    <div x-on:input.debounce.300ms="cari('prodi', $event.target.value)">
                        <x-field :label="__('pindahan.kolom.prodi_asal')" name="prodi_asal" list="saran-prodi" required
                            :hint="__('pindahan.petunjuk.prodi_asal')" />
                    </div>

                    <datalist id="saran-pt"><template x-for="nama in saran.pt" :key="nama"><option :value="nama"></option></template></datalist>
                    <datalist id="saran-prodi"><template x-for="nama in saran.prodi" :key="nama"><option :value="nama"></option></template></datalist>

                    <x-field :label="__('pindahan.kolom.term_masuk_asal')" name="term_masuk_asal" placeholder="20231"
                        :hint="__('pindahan.petunjuk.term_masuk_asal')" />
                </div>
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card :title="__('pindahan.kartu.penempatan')">
                <div class="space-y-3">
                    @unless ($pendaftar)
                        <x-field :label="__('pindahan.kolom.prodi')" name="prodi_id" :options="$daftarProdi" required />
                    @endunless

                    <x-field :label="__('pindahan.kolom.term_masuk')" name="tahun_akademik_id" :options="$daftarTerm"
                        :value="$termBawaan" required :hint="__('pindahan.petunjuk.term_masuk')" />

                    <p class="rounded-control border border-line bg-zebra px-3 py-2.5 text-[12.5px] leading-relaxed text-ink-muted">
                        {{ __('pindahan.petunjuk.sks_tidak_diketik', ['persen' => config('academic.konversi.maks_persen')]) }}
                    </p>

                    <p class="text-[12px] text-ink-faint">{{ __('pindahan.petunjuk.sesudah_simpan') }}</p>

                    <x-button type="submit" class="w-full">{{ __('pindahan.aksi.simpan_intake') }}</x-button>
                </div>
            </x-card>
        </div>
    </form>
@endsection
