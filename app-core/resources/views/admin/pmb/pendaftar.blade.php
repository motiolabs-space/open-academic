@extends('layouts.app')

@php
    use App\Enums\ApplicantStatus;
    use App\Support\Format;

    $berkasPerJenis = $pendaftar->berkas->keyBy('jenis');
    $bolehKelola = auth('staff')->user()?->can('manage', $pendaftar) ?? false;
    $bolehBayar = auth('staff')->user()?->can('recordPayment', $pendaftar) ?? false;
@endphp

@section('title', $pendaftar->nama)

@section('content')
    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <x-chip :tone="$pendaftar->status->tone()">{{ $pendaftar->status->label() }}</x-chip>
        @if ($pendaftar->daring())
            <x-chip tone="info">{{ __('pmb.admin.daring') }}</x-chip>
        @endif
        @if ($pendaftar->is_calon_kip)
            <x-chip tone="gold">{{ __('pmb.admin.kip') }}@if ($pendaftar->nomor_kip) · {{ $pendaftar->nomor_kip }}@endif</x-chip>
        @endif
        @if ($biodataKurang !== [])
            <x-chip tone="warning" :title="implode(', ', $biodataKurang)">{{ __('pmb.validasi.biodata_kurang', ['kolom' => count($biodataKurang)]) }}</x-chip>
        @endif
        <x-button :href="route('admin.pmb.gelombang', $pendaftar->gelombang)" variant="outline" size="sm">{{ $pendaftar->gelombang->nama }}</x-button>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex flex-col gap-5">
            <x-card :title="__('pmb.formulir.biodata')">
                <dl class="grid gap-x-6 gap-y-2.5 text-[13px] sm:grid-cols-2">
                    @foreach (['nik', 'nisn', 'tempat_lahir', 'jenis_kelamin', 'agama_kode', 'nama_ibu', 'nama_ayah', 'telepon', 'alamat', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos', 'asal_sekolah', 'tahun_lulus_sekolah', 'pt_asal', 'prodi_asal'] as $k)
                        @continue(blank($pendaftar->{$k}))
                        <div>
                            <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.kolom.'.$k) }}</dt>
                            <dd @class(['tabular' => in_array($k, ['nik', 'nisn', 'telepon', 'kode_pos'], true)])>
                                {{ $pendaftar->{$k} instanceof \BackedEnum ? $pendaftar->{$k}->label() : $pendaftar->{$k} }}
                            </dd>
                        </div>
                    @endforeach
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.kolom.tanggal_lahir') }}</dt>
                        <dd class="tabular">{{ Format::tanggal($pendaftar->tanggal_lahir) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.akun.email') }}</dt>
                        <dd>{{ $pendaftar->email }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card :title="__('pmb.formulir.berkas')" flush>
                <ul>
                    @foreach (array_unique([...$dokumenWajib, ...$pendaftar->berkas->pluck('jenis')->all()]) as $jenis)
                        @php $b = $berkasPerJenis->get($jenis); @endphp
                        <li class="flex flex-wrap items-start justify-between gap-3 border-b border-line/60 px-5 py-3 last:border-b-0">
                            <div class="min-w-0">
                                <div class="text-[13px] font-semibold">
                                    {{ $jenisBerkas[$jenis] ?? $jenis }}
                                    @if (in_array($jenis, $dokumenWajib, true))
                                        <span class="text-[11px] font-normal text-ink-faint">· {{ __('pmb.formulir.wajib') }}</span>
                                    @endif
                                </div>
                                <div class="text-[12px]">
                                    @if (! $b)
                                        <span class="text-ink-faint">{{ __('pmb.formulir.belum_diunggah') }}</span>
                                    @else
                                        <a href="{{ route('berkas.pmb', $b) }}" class="text-navy hover:underline">{{ $b->nama_file }}</a>
                                        @if ($b->is_verified)
                                            <span class="text-success">· {{ __('pmb.formulir.terverifikasi') }}</span>
                                        @elseif ($b->dikembalikan())
                                            <span class="text-danger">· {{ __('pmb.formulir.dikembalikan', ['catatan' => $b->catatan]) }}</span>
                                        @else
                                            <span class="text-ink-faint">· {{ __('pmb.formulir.menunggu_periksa') }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            @if ($b && $bolehKelola)
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <form method="POST" action="{{ route('admin.pmb.berkas.verifikasi', $b) }}">
                                        @csrf
                                        <x-button type="submit" size="sm" :variant="$b->is_verified ? 'ghost' : 'primary'">
                                            {{ $b->is_verified ? __('pmb.admin.cabut_verifikasi') : __('pmb.admin.verifikasi') }}
                                        </x-button>
                                    </form>
                                    @unless ($b->is_verified)
                                        <form method="POST" action="{{ route('admin.pmb.berkas.kembalikan', $b) }}" class="flex items-center gap-1.5">
                                            @csrf
                                            <input type="text" name="catatan" required maxlength="255" placeholder="{{ __('pmb.admin.kembalikan_alasan') }}"
                                                class="w-44 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                            <x-button type="submit" variant="ghost" size="sm">{{ __('pmb.admin.kembalikan') }}</x-button>
                                        </form>
                                    @endunless
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-5">
            <x-card :title="__('pmb.formulir.pilihan')">
                <ol class="list-decimal pl-5 text-[13px]">
                    <li>{{ $pendaftar->prodiPilihan1->nama }}</li>
                    @if ($pendaftar->prodiPilihan2)<li>{{ $pendaftar->prodiPilihan2->nama }}</li>@endif
                </ol>
                @if ($pendaftar->nilai_seleksi !== null)
                    <div class="mt-3 text-[12px] text-ink-muted">{{ __('pmb.admin.nilai') }}</div>
                    <div class="tabular font-serif text-[24px] font-semibold">{{ Format::angka($pendaftar->nilai_seleksi) }}</div>
                @endif
                @if ($pendaftar->prodiDiterima)
                    <p class="mt-2 text-[13px] font-semibold text-navy">→ {{ $pendaftar->prodiDiterima->nama }}</p>
                @endif
                @if ($pendaftar->mahasiswa)
                    <p class="tabular mt-1 text-[13px]">NIM {{ $pendaftar->mahasiswa->nim }}</p>
                @endif
                <p class="mt-3 text-[11.5px] text-ink-faint">
                    {{ $kartu['layak'] ? __('pmb.kartu.cetak') : __('pmb.kartu.belum').' '.implode(' ', $kartu['alasan']) }}
                </p>
            </x-card>

            <x-card :title="__('pmb.admin.pembayaran')">
                <dl class="grid grid-cols-3 gap-2 text-[13px]">
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.bayar.tagihan') }}</dt>
                        <dd class="tabular font-semibold">{{ Format::rupiah($pendaftar->biaya_formulir) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.bayar.terbayar') }}</dt>
                        <dd class="tabular">{{ Format::rupiah($pendaftar->terbayarFormulir()) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.bayar.sisa') }}</dt>
                        <dd class="tabular">{{ Format::rupiah($pendaftar->sisaFormulir()) }}</dd>
                    </div>
                </dl>

                @if ($pendaftar->pembayaran->isNotEmpty())
                    <ul class="mt-3 border-t border-line pt-2 text-[12px]">
                        @foreach ($pendaftar->pembayaran as $bayar)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-1">
                                <span class="tabular">{{ Format::tanggal($bayar->paid_at) }} · {{ Format::rupiah($bayar->nominal) }} · {{ __('pmb.channel.'.$bayar->channel) }}</span>
                                <x-chip :tone="$bayar->status->tone()">{{ $bayar->status->label() }}</x-chip>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($bolehBayar && $pendaftar->sisaFormulir() > 0 && ! in_array($pendaftar->status, [ApplicantStatus::Draf, ApplicantStatus::Batal], true))
                    <form method="POST" action="{{ route('admin.pmb.pembayaran.store', $pendaftar) }}" class="mt-3 flex flex-col gap-3 border-t border-line pt-3">
                        @csrf
                        <x-field :label="__('pmb.admin.nominal')" name="nominal" type="number" required :value="$pendaftar->sisaFormulir()" />
                        <x-field :label="__('pmb.admin.channel')" name="channel" required
                            :options="collect($channel)->mapWithKeys(fn ($c) => [$c => __('pmb.channel.'.$c)])" />
                        <x-field :label="__('pmb.admin.referensi')" name="referensi" />
                        <x-button type="submit" size="sm">{{ __('pmb.admin.catat_bayar') }}</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection
