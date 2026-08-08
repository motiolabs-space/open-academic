@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Portofolio & Kepegawaian')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5">
        {{-- Alasannya disebut sekali di atas, supaya empat borang di bawah tidak
             terasa seperti administrasi tanpa tujuan. --}}
        <x-alert tone="info">
            Isian di halaman ini adalah bahan yang diminta SISTER dan BKD. Selama
            sambungan ke sistem kementerian belum tersedia, datanya tetap dapat diekspor
            oleh bagian kepegawaian — jadi mengisinya sekarang tidak menunggu apa pun.
        </x-alert>
    </div>

    <div class="space-y-5">
        <x-card flush title="Kegiatan Semester Ini" :meta="$term->nama">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Kegiatan</th>
                            <th class="px-5 py-3 font-semibold">Unsur</th>
                            <th class="px-5 py-3 font-semibold">Luaran</th>
                            <th class="px-5 py-3 font-semibold">Bukti</th>
                            <th class="px-5 py-3 font-semibold">Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kegiatan as $k)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $k->judul }}</div>
                                    <div class="text-[11.5px] text-ink-faint">
                                        {{ $k->jenis->label() }}
                                        @if ($k->mitra_nama) · {{ $k->mitra_nama }} @endif
                                        · {{ Format::tanggal($k->tanggal_mulai) }}
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    {{ $k->unsur?->label() ?? '—' }}
                                    @if ($k->peran || $k->tingkat)
                                        <div class="text-[11.5px] text-ink-faint">
                                            {{ collect([$k->peran?->label(), $k->tingkat?->label()])->filter()->implode(' · ') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($k->luaran_jenis)
                                        {{ $k->luaran_jenis->label() }}
                                        <div class="text-[11.5px] text-ink-faint">{{ $k->luaran_identitas ?? '—' }}</div>
                                    @else
                                        <span class="text-ink-faint">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($k->dokumen_path)
                                        <x-chip tone="success">Ada</x-chip>
                                    @else
                                        <x-chip tone="warning">Belum</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$k->is_verified ? 'success' : 'neutral'">
                                        {{ $k->is_verified ? 'Terverifikasi' : 'Menunggu' }}
                                    </x-chip>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state
                                        title="Belum ada kegiatan tercatat"
                                        description="Penelitian, pengabdian, dan penunjang tidak pernah melewati sistem akademik, jadi hanya muncul di BKD bila Anda mencatatnya di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Catat Kegiatan">
            <form method="POST" action="{{ route('dosen.portofolio.kegiatan') }}"
                enctype="multipart/form-data" class="grid gap-3 lg:grid-cols-3">
                @csrf
                <x-field label="Judul kegiatan" name="judul" required class="lg:col-span-3" />
                <x-field label="Jenis" name="jenis" :options="$jenisPilihan" required />
                <x-field label="Unsur BKD" name="unsur" :options="$unsurPilihan" required
                    hint="Pendidikan tidak ada di sini karena ditarik otomatis dari daftar kelas." />
                <x-field label="Peran" name="peran" :options="$peranPilihan" />
                <x-field label="Tingkat" name="tingkat" :options="$tingkatPilihan" />
                <x-field label="Mitra" name="mitra_nama" />
                <x-field label="Lokasi" name="lokasi" />
                <x-field label="Mulai" name="tanggal_mulai" type="date" required />
                <x-field label="Selesai" name="tanggal_selesai" type="date" />
                <x-field label="SKS ekuivalen" name="sks_ekuivalen" type="number"
                    hint="Kosongkan bila mengikuti rubrik kampus." />
                <x-field label="Jenis luaran" name="luaran_jenis" :options="$luaranPilihan" />
                <x-field label="Identitas luaran" name="luaran_identitas"
                    hint="DOI, ISBN, ISSN, atau nomor pendaftaran." />
                <x-field label="Tahun luaran" name="luaran_tahun" type="number" />
                <x-field label="Bukti" name="dokumen" type="file" class="lg:col-span-2"
                    hint="Surat tugas, sertifikat, atau bukti terbit." />
                <div class="flex items-end lg:col-span-1">
                    <x-button type="submit" size="sm">Simpan kegiatan</x-button>
                </div>
            </form>
        </x-card>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-card title="Riwayat Pendidikan">
                <ul class="space-y-2">
                    @forelse ($pendidikan as $p)
                        <li class="border-b border-line/50 pb-2 text-[13px] last:border-b-0">
                            <div class="font-medium">{{ $p->jenjang->value }} — {{ $p->perguruan_tinggi }}</div>
                            <div class="text-[11.5px] text-ink-faint">
                                {{ collect([$p->program_studi, $p->bidang_ilmu, $p->tahun_lulus, $p->negara])
                                    ->filter()->implode(' · ') }}
                                @if ($p->luarNegeri())
                                    · <span class="text-warning-ink">perlu penyetaraan ijazah</span>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-[13px] text-ink-muted">Belum ada riwayat pendidikan.</li>
                    @endforelse
                </ul>

                <form method="POST" action="{{ route('dosen.portofolio.pendidikan') }}"
                    enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                    @csrf
                    <x-field label="Jenjang" name="jenjang" :options="$jenjangPilihan" required />
                    <x-field label="Perguruan tinggi" name="perguruan_tinggi" required />
                    <x-field label="Program studi" name="program_studi" />
                    <x-field label="Bidang ilmu" name="bidang_ilmu" />
                    <x-field label="Negara" name="negara" value="Indonesia" required />
                    <x-field label="Gelar" name="gelar" />
                    <x-field label="Tahun masuk" name="tahun_masuk" type="number" />
                    <x-field label="Tahun lulus" name="tahun_lulus" type="number" />
                    <x-field label="Nomor ijazah" name="nomor_ijazah" />
                    <x-field label="Salinan ijazah" name="dokumen" type="file" />
                    <div class="sm:col-span-2">
                        <x-button type="submit" size="sm" variant="outline">Tambah riwayat</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Jabatan Fungsional">
                <ul class="space-y-2">
                    @forelse ($jabatan as $j)
                        <li class="border-b border-line/50 pb-2 text-[13px] last:border-b-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium">{{ $j->jabatan->label() }}</span>
                                @if ($j->berlaku())
                                    <x-chip tone="success">Berlaku</x-chip>
                                @endif
                            </div>
                            <div class="tabular text-[11.5px] text-ink-faint">
                                AK {{ Format::angka($j->angkaKredit(), 2) }}
                                · TMT {{ Format::tanggal($j->tmt) }}
                                @if ($j->nomor_sk) · SK {{ $j->nomor_sk }} @endif
                            </div>
                            @unless ($j->angkaKreditMencukupi())
                                {{-- Dilaporkan, tidak ditolak: kampus yang memasukkan
                                     riwayat dua puluh tahun punya SK dengan skema angka
                                     kredit yang berbeda. --}}
                                <div class="text-[11.5px] text-warning-ink">
                                    Angka kredit di bawah syarat minimum
                                    {{ $j->jabatan->angkaKreditMinimum() }} untuk jabatan ini.
                                </div>
                            @endunless
                        </li>
                    @empty
                        <li class="text-[13px] text-ink-muted">Belum ada riwayat jabatan.</li>
                    @endforelse
                </ul>

                <form method="POST" action="{{ route('dosen.portofolio.jabatan') }}"
                    enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                    @csrf
                    <x-field label="Jabatan" name="jabatan" :options="$jabatanPilihan" required />
                    <x-field label="Angka kredit" name="angka_kredit" type="number" />
                    <x-field label="TMT" name="tmt" type="date" required />
                    <x-field label="Tanggal SK" name="tanggal_sk" type="date" />
                    <x-field label="Nomor SK" name="nomor_sk" />
                    <x-field label="Salinan SK" name="dokumen" type="file" />
                    <x-field label="Jadikan jabatan berlaku" name="berlaku" type="checkbox" :value="1"
                        hint="Berlaku sekarang" />
                    <div class="flex items-end">
                        <x-button type="submit" size="sm" variant="outline">Tambah jabatan</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <x-card title="Sertifikasi">
            <ul class="space-y-2">
                @forelse ($sertifikasi as $s)
                    <li class="flex items-start justify-between gap-3 border-b border-line/50 pb-2 text-[13px] last:border-b-0">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $s->nama }}</div>
                            <div class="text-[11.5px] text-ink-faint">
                                {{ collect([$s->jenis->label(), $s->nomor, $s->penyelenggara,
                                    Format::tanggal($s->tanggal)])->filter()->implode(' · ') }}
                            </div>
                        </div>
                        <x-chip :tone="$s->berlaku() ? 'success' : 'danger'">
                            {{ $s->berlaku() ? 'Berlaku' : 'Kedaluwarsa' }}
                        </x-chip>
                    </li>
                @empty
                    <li class="text-[13px] text-ink-muted">
                        Belum ada sertifikasi. Sertifikat Pendidik (Serdos) adalah yang
                        menentukan apakah Anda wajib melaporkan BKD.
                    </li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('dosen.portofolio.sertifikasi') }}"
                enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-line pt-4 lg:grid-cols-3">
                @csrf
                <x-field label="Jenis" name="jenis" :options="$sertifikasiPilihan" required />
                <x-field label="Nama sertifikat" name="nama" required class="lg:col-span-2" />
                <x-field label="Nomor" name="nomor" />
                <x-field label="Penyelenggara" name="penyelenggara" />
                <x-field label="Bidang" name="bidang" />
                <x-field label="Tanggal" name="tanggal" type="date" required />
                <x-field label="Berlaku sampai" name="berlaku_sampai" type="date"
                    hint="Kosongkan bila tidak kedaluwarsa, seperti Serdos." />
                <x-field label="Salinan sertifikat" name="dokumen" type="file" />
                <div class="flex items-end lg:col-span-3">
                    <x-button type="submit" size="sm" variant="outline">Tambah sertifikasi</x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
