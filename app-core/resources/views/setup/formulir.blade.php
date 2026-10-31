@extends('layouts.base')

@section('title', __('instalasi.judul'))

@php
    use App\Services\Branding\BrandingService;
    $brand = app(BrandingService::class);

    // Which step owns which field, so a failed submit reopens the step that
    // holds the first error instead of leaving the operator on step five.
    $petaLangkah = [
        1 => ['nama_pt', 'singkatan', 'kode_pt', 'alamat', 'kota', 'provinsi', 'surel', 'telepon', 'situs'],
        2 => ['logo', 'warna_utama', 'warna_aksen'],
        3 => ['admin_nama', 'admin_email', 'admin_nip', 'admin_password'],
        4 => ['fakultas_kode', 'fakultas_nama', 'prodi_kode', 'prodi_nama', 'prodi_jenjang',
            'tahun_mulai', 'semester', 'tanggal_mulai', 'tanggal_selesai'],
        5 => ['telemetri_setuju', 'telemetri_sertakan_url'],
    ];

    $langkahAwal = 1;
    foreach ($petaLangkah as $nomor => $medan) {
        if ($errors->hasAny($medan)) {
            $langkahAwal = $nomor;
            break;
        }
    }

    $judulLangkah = ['identitas', 'branding', 'admin', 'struktur', 'persetujuan'];
@endphp

@section('body')
<div class="mx-auto flex min-h-screen max-w-3xl flex-col px-4 py-10 sm:px-6">
    <header class="mb-8 flex items-center gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-lg border-[1.5px] border-gold font-serif text-lg font-bold text-gold">
            OA
        </div>
        <div>
            <h1 class="font-serif text-[26px] font-semibold leading-tight">{{ __('instalasi.judul') }}</h1>
            <p class="mt-1 text-[13px] text-ink-muted">{{ __('instalasi.pengantar') }}</p>
        </div>
    </header>

    @if ($errors->any())
        <div class="mb-5" role="alert"><x-alert tone="danger">{{ __('instalasi.galat_ringkas') }}</x-alert></div>
    @endif

    <form method="POST" action="{{ route('setup.simpan') }}" enctype="multipart/form-data" novalidate
        x-data="{
            langkah: {{ $langkahAwal }},
            jumlah: {{ count($judulLangkah) }},
            ringkasan: {},
            sah() {
                const bagian = this.$refs['langkah' + this.langkah];
                for (const el of bagian.querySelectorAll('input, select, textarea')) {
                    if (!el.checkValidity()) { el.reportValidity(); return false; }
                }
                return true;
            },
            ke(nomor) {
                if (nomor > this.langkah && !this.sah()) return;
                this.langkah = nomor;
                if (nomor === this.jumlah) {
                    const f = this.$root.elements;
                    this.ringkasan = {
                        institusi: [f.nama_pt.value, f.kode_pt.value].filter(Boolean).join(' · '),
                        admin: f.admin_email.value,
                        struktur: [f.fakultas_nama.value, f.prodi_nama.value, f.tahun_mulai.value].filter(Boolean).join(' · '),
                    };
                }
                window.scrollTo({ top: 0 });
            },
        }"
        class="flex flex-col gap-5">
        @csrf

        <ol class="grid grid-cols-5 gap-2" aria-label="{{ __('instalasi.judul') }}">
            @foreach ($judulLangkah as $i => $kunci)
                <li>
                    <button type="button" @click="ke({{ $i + 1 }})"
                        class="w-full border-t-2 pt-2 text-left text-[11px] font-semibold uppercase tracking-[0.06em]"
                        :class="langkah === {{ $i + 1 }} ? 'border-navy text-navy' : (langkah > {{ $i + 1 }} ? 'border-gold text-ink-muted' : 'border-line text-ink-faint')"
                        :aria-current="langkah === {{ $i + 1 }} ? 'step' : null">
                        <span class="tabular-nums">{{ $i + 1 }}</span>
                        <span class="hidden sm:inline">· {{ __('instalasi.langkah.'.$kunci) }}</span>
                    </button>
                </li>
            @endforeach
        </ol>

        {{-- 1 · Identitas --}}
        <section x-ref="langkah1" x-show="langkah === 1">
            <x-card :title="__('instalasi.langkah.identitas')" :meta="__('instalasi.langkah_ke', ['nomor' => 1, 'jumlah' => 5])">
                <p class="mb-4 text-[12.5px] text-ink-muted">{{ __('instalasi.keterangan.identitas') }}</p>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <x-field class="sm:col-span-2" :label="__('instalasi.field.nama_pt')" name="nama_pt" required />
                    <x-field :label="__('instalasi.field.singkatan')" name="singkatan" required :hint="__('instalasi.hint.singkatan')" />
                    <x-field :label="__('instalasi.field.kode_pt')" name="kode_pt" required placeholder="001001" :hint="__('instalasi.hint.kode_pt')" />
                    <x-field class="sm:col-span-2" :label="__('instalasi.field.alamat')" name="alamat" type="textarea" required />
                    <x-field :label="__('instalasi.field.kota')" name="kota" required />
                    <x-field :label="__('instalasi.field.provinsi')" name="provinsi" required />
                    <x-field :label="__('instalasi.field.surel')" name="surel" type="email" required />
                    <x-field :label="__('instalasi.field.telepon')" name="telepon" type="tel" />
                    <x-field class="sm:col-span-2" :label="__('instalasi.field.situs')" name="situs" type="url" placeholder="https://" />
                </div>
            </x-card>
        </section>

        {{-- 2 · Branding --}}
        <section x-ref="langkah2" x-show="langkah === 2" x-cloak>
            <x-card :title="__('instalasi.langkah.branding')" :meta="__('instalasi.langkah_ke', ['nomor' => 2, 'jumlah' => 5])">
                <p class="mb-4 text-[12.5px] text-ink-muted">{{ __('instalasi.keterangan.branding') }}</p>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <x-field :label="__('instalasi.field.warna_utama')" name="warna_utama" type="color" required
                        :value="$brand->primaryColor()" :hint="__('instalasi.hint.warna')" />
                    <x-field :label="__('instalasi.field.warna_aksen')" name="warna_aksen" type="color" required
                        :value="$brand->accentColor()" :hint="__('instalasi.hint.warna')" />
                    <x-field class="sm:col-span-2" :label="__('instalasi.field.logo')" name="logo" type="file"
                        :hint="__('instalasi.hint.logo').' '.($errors->any() ? __('instalasi.logo_ulang') : '')" />
                </div>
            </x-card>
        </section>

        {{-- 3 · Super admin --}}
        <section x-ref="langkah3" x-show="langkah === 3" x-cloak>
            <x-card :title="__('instalasi.langkah.admin')" :meta="__('instalasi.langkah_ke', ['nomor' => 3, 'jumlah' => 5])">
                <p class="mb-4 text-[12.5px] text-ink-muted">{{ __('instalasi.keterangan.admin') }}</p>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <x-field class="sm:col-span-2" :label="__('instalasi.field.admin_nama')" name="admin_nama" required />
                    <x-field :label="__('instalasi.field.admin_email')" name="admin_email" type="email" required :hint="__('instalasi.hint.admin_email')" />
                    <x-field :label="__('instalasi.field.admin_nip')" name="admin_nip" />
                    <x-field :label="__('instalasi.field.admin_password')" name="admin_password" type="password" required :hint="__('instalasi.hint.admin_password')" />
                    <x-field :label="__('instalasi.field.admin_password_confirmation')" name="admin_password_confirmation" type="password" required />
                </div>
            </x-card>
        </section>

        {{-- 4 · Struktur awal (opsional) --}}
        <section x-ref="langkah4" x-show="langkah === 4" x-cloak>
            <x-card :title="__('instalasi.langkah.struktur')" :meta="__('instalasi.langkah_ke', ['nomor' => 4, 'jumlah' => 5])">
                <p class="mb-4 text-[12.5px] text-ink-muted">{{ __('instalasi.keterangan.struktur') }}</p>

                <h3 class="mb-2.5 text-[13px] font-semibold">{{ __('instalasi.hint.fakultas') }}</h3>
                <div class="grid gap-3.5 sm:grid-cols-3">
                    <x-field :label="__('instalasi.field.fakultas_kode')" name="fakultas_kode" />
                    <x-field class="sm:col-span-2" :label="__('instalasi.field.fakultas_nama')" name="fakultas_nama" />
                </div>

                <h3 class="mb-2.5 mt-5 border-t border-line pt-4 text-[13px] font-semibold">{{ __('instalasi.hint.prodi') }}</h3>
                <div class="grid gap-3.5 sm:grid-cols-3">
                    <x-field :label="__('instalasi.field.prodi_kode')" name="prodi_kode" />
                    <x-field :label="__('instalasi.field.prodi_nama')" name="prodi_nama" />
                    <x-field :label="__('instalasi.field.prodi_jenjang')" name="prodi_jenjang" :options="$jenjangPilihan" />
                </div>

                <h3 class="mb-2.5 mt-5 border-t border-line pt-4 text-[13px] font-semibold">{{ __('instalasi.hint.semester') }}</h3>
                <div class="grid gap-3.5 sm:grid-cols-4">
                    <x-field :label="__('instalasi.field.tahun_mulai')" name="tahun_mulai" type="number" :placeholder="now()->year" />
                    <x-field :label="__('instalasi.field.semester')" name="semester" :options="$semesterPilihan" />
                    <x-field :label="__('instalasi.field.tanggal_mulai')" name="tanggal_mulai" type="date" />
                    <x-field :label="__('instalasi.field.tanggal_selesai')" name="tanggal_selesai" type="date" />
                </div>
            </x-card>
        </section>

        {{-- 5 · Tinjau & persetujuan --}}
        <section x-ref="langkah5" x-show="langkah === 5" x-cloak class="flex flex-col gap-5">
            <x-card :title="__('instalasi.ringkasan.judul')" :meta="__('instalasi.langkah_ke', ['nomor' => 5, 'jumlah' => 5])">
                <p class="mb-4 text-[12.5px] text-ink-muted">{{ __('instalasi.keterangan.persetujuan') }}</p>
                <dl class="flex flex-col gap-2.5 text-[12.5px]">
                    @foreach (['institusi', 'admin', 'struktur'] as $kunci)
                        <div class="flex flex-wrap justify-between gap-2 border-b border-line pb-2.5 last:border-b-0">
                            <dt class="font-semibold">{{ __('instalasi.ringkasan.'.$kunci) }}</dt>
                            <dd class="text-ink-muted" x-text="ringkasan.{{ $kunci }} || @js(__('instalasi.ringkasan.kosong'))"></dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card :title="__('instalasi.telemetri.judul')" :meta="__('instalasi.telemetri.meta')">
                @include('setup.partials.telemetri', [
                    'diizinkan' => $telemetriDiizinkan,
                    'tujuan' => $tujuanTelemetri,
                    'urlAplikasi' => $urlAplikasi,
                    'setuju' => (bool) old('telemetri_setuju'),
                    'sertakanUrl' => (bool) old('telemetri_sertakan_url'),
                ])
            </x-card>
        </section>

        <div class="flex items-center justify-between gap-3">
            <x-button variant="outline" x-show="langkah > 1" x-cloak @click="ke(langkah - 1)">
                {{ __('instalasi.sebelumnya') }}
            </x-button>
            <span class="text-[11.5px] text-ink-faint" x-show="langkah === 1">{{ __('instalasi.wajib_catatan') }}</span>

            <x-button x-show="langkah < jumlah" @click="ke(langkah + 1)">{{ __('instalasi.berikutnya') }}</x-button>
            <x-button type="submit" x-show="langkah === jumlah" x-cloak>{{ __('instalasi.selesaikan') }}</x-button>
        </div>
    </form>
</div>
@endsection
