@extends('layouts.pendaftar')

@php
    use App\Enums\ApplicantStatus;
    use App\Support\Format;

    $terlihat = $pendaftar->statusTerlihat();
    $draf = $pendaftar->masihDraf();
    $berkasPerJenis = $pendaftar->berkas->keyBy('jenis');
    $sudahAjukan = $pendaftar->diajukan_at !== null;
    $lunas = $pendaftar->formulirLunas();
    $bisaBatal = ! $pendaftar->status->sudahDiputuskan() && $pendaftar->status !== ApplicantStatus::Batal;

    // Langkah: label => selesai?
    $langkah = [
        __('pmb.langkah.biodata') => $biodataKurang === [],
        __('pmb.langkah.berkas') => $pendaftar->dokumenKurang() === [],
        __('pmb.langkah.ajukan') => $sudahAjukan,
        __('pmb.langkah.bayar') => $sudahAjukan && $lunas,
        __('pmb.langkah.verifikasi') => in_array($terlihat, [ApplicantStatus::Seleksi, ApplicantStatus::Lulus, ApplicantStatus::TidakLulus, ApplicantStatus::DaftarUlang, ApplicantStatus::Mahasiswa], true),
        __('pmb.langkah.pengumuman') => $pendaftar->hasilTerlihat(),
    ];

    $pesan = match (true) {
        $terlihat === ApplicantStatus::Draf => __('pmb.status_pesan.draf'),
        $terlihat === ApplicantStatus::Mendaftar => __('pmb.status_pesan.mendaftar', ['nominal' => Format::rupiah($pendaftar->sisaFormulir())]),
        $terlihat === ApplicantStatus::Verifikasi => __('pmb.status_pesan.verifikasi'),
        $terlihat === ApplicantStatus::Seleksi && $pendaftar->status->sudahDiputuskan() => __('pmb.status_pesan.menunggu_pengumuman'),
        $terlihat === ApplicantStatus::Seleksi => __('pmb.status_pesan.seleksi'),
        $terlihat === ApplicantStatus::Lulus => __('pmb.status_pesan.lulus', ['prodi' => $pendaftar->prodiDiterima?->nama]),
        $terlihat === ApplicantStatus::TidakLulus => __('pmb.status_pesan.tidak_lulus'),
        $terlihat === ApplicantStatus::DaftarUlang => __('pmb.status_pesan.daftar_ulang'),
        $terlihat === ApplicantStatus::Mahasiswa => __('pmb.status_pesan.mahasiswa', ['nim' => $pendaftar->mahasiswa?->nim]),
        default => __('pmb.status_pesan.batal'),
    };
@endphp

@section('title', $pendaftar->nomor_pendaftaran)

@section('content')
    <nav class="mb-2.5 text-xs text-ink-faint">
        <a href="{{ route('pmb.portal.index') }}" class="hover:text-gold">{{ __('pmb.dasbor.judul') }}</a>
        <span class="px-1" aria-hidden="true">/</span>
        <span class="tabular font-semibold text-ink">{{ $pendaftar->nomor_pendaftaran }}</span>
    </nav>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-serif text-[26px] font-semibold">{{ $pendaftar->gelombang->nama }}</h1>
            <p class="text-[13px] text-ink-muted">
                1. {{ $pendaftar->prodiPilihan1->nama }}
                @if ($pendaftar->prodiPilihan2) · 2. {{ $pendaftar->prodiPilihan2->nama }} @endif
            </p>
        </div>
        <x-chip :tone="$terlihat->tone()">{{ $terlihat->label() }}</x-chip>
    </div>

    <div class="mb-5">
        <x-alert :tone="match ($terlihat) {
            ApplicantStatus::Lulus, ApplicantStatus::Mahasiswa => 'success',
            ApplicantStatus::TidakLulus, ApplicantStatus::Batal => 'warning',
            default => 'info',
        }">{{ $pesan }}</x-alert>
    </div>

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    {{-- Langkah --}}
    <ol class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($langkah as $label => $selesai)
            <li @class([
                'rounded-card border px-3 py-2 text-[12px]',
                'border-success-line bg-success-bg text-success' => $selesai,
                'border-line bg-surface text-ink-muted' => ! $selesai,
            ])>
                <span class="tabular font-semibold">{{ $loop->iteration }}.</span> {{ $label }}
            </li>
        @endforeach
    </ol>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex flex-col gap-5">
            {{-- Biodata --}}
            <x-card :title="__('pmb.formulir.biodata')">
                @if ($draf)
                    <p class="mb-4 text-[12.5px] text-ink-muted">{{ __('pmb.formulir.biodata_petunjuk') }}</p>

                    <form method="POST" action="{{ route('pmb.portal.biodata', $pendaftar) }}" class="flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        <x-field :label="__('pmb.kolom.nama')" name="nama" required :value="$pendaftar->nama" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field :label="__('pmb.kolom.nik')" name="nik" required :value="$pendaftar->nik" />
                            <x-field :label="__('pmb.kolom.nisn')" name="nisn" :value="$pendaftar->nisn" />
                            <x-field :label="__('pmb.kolom.tempat_lahir')" name="tempat_lahir" required :value="$pendaftar->tempat_lahir" />
                            <x-field :label="__('pmb.kolom.tanggal_lahir')" name="tanggal_lahir" type="date" required :value="$pendaftar->tanggal_lahir" />
                            <x-field :label="__('pmb.kolom.jenis_kelamin')" name="jenis_kelamin" required :options="$opsiGender" :value="$pendaftar->jenis_kelamin?->value" />
                            <x-field :label="__('pmb.kolom.agama_kode')" name="agama_kode" required :options="$opsiAgama" :value="$pendaftar->agama_kode?->value" />
                            <x-field :label="__('pmb.kolom.nama_ibu')" name="nama_ibu" required :value="$pendaftar->nama_ibu" />
                            <x-field :label="__('pmb.kolom.nama_ayah')" name="nama_ayah" :value="$pendaftar->nama_ayah" />
                            <x-field :label="__('pmb.kolom.telepon')" name="telepon" type="tel" required :value="$pendaftar->telepon" />
                        </div>
                        <x-field :label="__('pmb.kolom.alamat')" name="alamat" required :value="$pendaftar->alamat" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field :label="__('pmb.kolom.kelurahan')" name="kelurahan" required :value="$pendaftar->kelurahan" />
                            <x-field :label="__('pmb.kolom.kecamatan')" name="kecamatan" required :value="$pendaftar->kecamatan" />
                            <x-field :label="__('pmb.kolom.kabupaten')" name="kabupaten" required :value="$pendaftar->kabupaten" />
                            <x-field :label="__('pmb.kolom.provinsi')" name="provinsi" required :value="$pendaftar->provinsi" />
                            <x-field :label="__('pmb.kolom.kode_pos')" name="kode_pos" :value="$pendaftar->kode_pos" />
                            <x-field :label="__('pmb.kolom.asal_sekolah')" name="asal_sekolah" :value="$pendaftar->asal_sekolah" />
                            <x-field :label="__('pmb.kolom.tahun_lulus_sekolah')" name="tahun_lulus_sekolah" type="number" :value="$pendaftar->tahun_lulus_sekolah" />
                        </div>

                        @if ($pendaftar->gelombang->jalurPindahan())
                            <fieldset class="rounded-card border border-line px-4 pb-4 pt-2">
                                <legend class="px-1 text-[12px] font-semibold">{{ __('pmb.formulir.asal') }}</legend>
                                <p class="mb-3 text-[12px] text-ink-muted">{{ __('pmb.formulir.asal_petunjuk') }}</p>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field :label="__('pmb.kolom.pt_asal')" name="pt_asal" required :value="$pendaftar->pt_asal" />
                                    <x-field :label="__('pmb.kolom.prodi_asal')" name="prodi_asal" required :value="$pendaftar->prodi_asal" />
                                </div>
                            </fieldset>
                        @endif

                        <div class="rounded-card border border-line px-4 py-3">
                            <x-field :label="__('pmb.admin.kip')" name="is_calon_kip" type="checkbox" :value="$pendaftar->is_calon_kip" :hint="__('pmb.formulir.kip')" />
                            <p class="mb-3 text-[11.5px] text-ink-faint">{{ __('pmb.formulir.kip_petunjuk') }}</p>
                            <x-field :label="__('pmb.formulir.nomor_kip')" name="nomor_kip" :value="$pendaftar->nomor_kip" />
                        </div>

                        <div>
                            <x-button type="submit">{{ __('pmb.formulir.simpan_biodata') }}</x-button>
                        </div>
                    </form>
                @else
                    <p class="mb-3 text-[12.5px] text-ink-muted">{{ __('pmb.formulir.terkunci') }}</p>
                    <dl class="grid gap-x-6 gap-y-2 text-[13px] sm:grid-cols-2">
                        @foreach (['nama', 'tempat_lahir', 'jenis_kelamin', 'nama_ibu', 'telepon', 'kabupaten'] as $k)
                            <div>
                                <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.kolom.'.$k) }}</dt>
                                <dd>{{ $pendaftar->{$k} instanceof \BackedEnum ? $pendaftar->{$k}->label() : $pendaftar->{$k} }}</dd>
                            </div>
                        @endforeach
                        <div>
                            <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.kolom.tanggal_lahir') }}</dt>
                            <dd class="tabular">{{ Format::tanggal($pendaftar->tanggal_lahir) }}</dd>
                        </div>
                    </dl>
                @endif
            </x-card>

            {{-- Berkas --}}
            <x-card :title="__('pmb.formulir.berkas')" flush>
                <ul>
                    @foreach (array_unique([...$dokumenWajib, ...$pendaftar->berkas->pluck('jenis')->all()]) as $jenis)
                        @php $b = $berkasPerJenis->get($jenis); @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line/60 px-5 py-3 last:border-b-0">
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
                                    @elseif ($b->is_verified)
                                        <span class="text-success">{{ __('pmb.formulir.terverifikasi') }}</span>
                                    @elseif ($b->dikembalikan())
                                        <span class="text-danger">{{ __('pmb.formulir.dikembalikan', ['catatan' => $b->catatan]) }}</span>
                                    @else
                                        <a href="{{ route('pmb.portal.berkas.show', [$pendaftar, $b]) }}" class="text-navy hover:underline">{{ $b->nama_file }}</a>
                                        <span class="text-ink-faint">· {{ __('pmb.formulir.menunggu_periksa') }}</span>
                                    @endif
                                </div>
                            </div>
                            @if ($b && $draf && ! $b->is_verified)
                                <form method="POST" action="{{ route('pmb.portal.berkas.destroy', [$pendaftar, $b]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="ghost" size="sm">{{ __('pmb.formulir.hapus') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @php
                    $bisaUnggah = $draf
                        || $pendaftar->berkas->contains(fn ($b) => $b->dikembalikan())
                        || (! $lunas && $pendaftar->status !== ApplicantStatus::Batal);
                @endphp
                @if ($bisaUnggah)
                    <form method="POST" action="{{ route('pmb.portal.berkas.store', $pendaftar) }}" enctype="multipart/form-data"
                        class="flex flex-wrap items-end gap-3 border-t border-line px-5 py-4">
                        @csrf
                        <x-field :label="__('pmb.formulir.jenis')" name="jenis" required
                            :options="collect($jenisBerkas)->only([...$dokumenWajib, 'bukti_bayar', 'lainnya'])->all()" />
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('pmb.kolom.berkas') }}</span>
                            <input type="file" name="berkas" required accept=".pdf,.jpg,.jpeg,.png" class="text-[12px]">
                        </label>
                        <x-button type="submit" size="sm">{{ __('pmb.formulir.unggah') }}</x-button>
                        <p class="w-full text-[11.5px] text-ink-faint">{{ __('pmb.formulir.berkas_petunjuk', ['kb' => config('berkas.maks_kb')]) }}</p>
                    </form>
                @endif
            </x-card>

            @if ($draf)
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('pmb.portal.ajukan', $pendaftar) }}"
                        onsubmit="return confirm(@js(__('pmb.formulir.ajukan_konfirmasi')));">
                        @csrf
                        <x-button type="submit" variant="gold">{{ __('pmb.formulir.ajukan') }}</x-button>
                    </form>
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-5">
            {{-- Biaya formulir --}}
            <x-card :title="__('pmb.bayar.judul')">
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
                        <dd class="tabular">
                            @if ($lunas)
                                <x-chip tone="success">{{ __('pmb.bayar.lunas') }}</x-chip>
                            @else
                                {{ Format::rupiah($pendaftar->sisaFormulir()) }}
                            @endif
                        </dd>
                    </div>
                </dl>
                @unless ($lunas)
                    <p class="mt-3 text-[12px] text-ink-muted">{{ __('pmb.bayar.petunjuk') }}</p>
                @endunless
            </x-card>

            {{-- Kartu & jadwal --}}
            <x-card :title="__('pmb.jadwal.judul')">
                @forelse ($pendaftar->gelombang->jadwalSeleksi as $j)
                    <div class="border-b border-line/60 py-2 first:pt-0 last:border-b-0">
                        <div class="text-[13px] font-semibold">{{ $j->nama }}</div>
                        <div class="tabular text-[12px] text-ink-muted">
                            {{ Format::tanggalHari($j->mulai) }}, {{ Format::rentangJam($j->mulai, $j->selesai) }}
                        </div>
                        @if ($j->lokasi)<div class="text-[12px] text-ink-muted">{{ $j->lokasi }}</div>@endif
                    </div>
                @empty
                    <p class="text-[13px] text-ink-muted">{{ __('pmb.jadwal.kosong') }}</p>
                @endforelse

                <div class="mt-4">
                    @if ($kartu['layak'])
                        <x-button :href="route('pmb.portal.kartu', $pendaftar)" class="w-full">{{ __('pmb.kartu.cetak') }}</x-button>
                    @else
                        <p class="text-[12px] text-ink-faint">{{ __('pmb.kartu.belum') }} {{ implode(' ', $kartu['alasan']) }}</p>
                    @endif
                </div>
            </x-card>

            @if ($pendaftar->hasilTerlihat() && $pendaftar->nilai_seleksi !== null)
                <x-card :title="__('pmb.pengumuman.judul')">
                    <div class="text-[12px] text-ink-muted">{{ __('pmb.admin.nilai') }}</div>
                    <div class="tabular font-serif text-[28px] font-semibold">{{ Format::angka($pendaftar->nilai_seleksi) }}</div>
                </x-card>
            @endif

            @if ($bisaBatal)
                <form method="POST" action="{{ route('pmb.portal.batal', $pendaftar) }}"
                    onsubmit="return confirm(@js(__('pmb.formulir.batal_konfirmasi')));">
                    @csrf
                    <x-button type="submit" variant="ghost" size="sm">{{ __('pmb.formulir.batal') }}</x-button>
                </form>
            @endif
        </div>
    </div>
@endsection
