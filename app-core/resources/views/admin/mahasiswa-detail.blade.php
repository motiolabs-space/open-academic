@extends('layouts.app')

@section('title', $mahasiswa->nama)

@php
    use App\Support\Format;

    $l = fn (string $k) => __('kemahasiswaan.detail.'.$k);

    // Shown masked: the full NIK is for PDDIKTI, not for every holder of
    // mahasiswa.view who opens this page.
    $nik = filled($mahasiswa->nik)
        ? Str::substr($mahasiswa->nik, 0, 4).str_repeat('•', max(0, Str::length($mahasiswa->nik) - 8)).Str::substr($mahasiswa->nik, -4)
        : null;
@endphp

@section('content')
    @if (session('kata_sandi_baru'))
        <x-kredensial-baru :data="session('kata_sandi_baru')" />
    @endif

    <div class="mb-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card feature :label="$l('stat.ipk')" :value="Format::angka($terakhir?->ipk ?? 0)"
            :meta="$terakhir?->tahunAkademik?->nama ?? '—'" />
        <x-stat-card :label="$l('stat.sks')" :value="Format::bulat($terakhir?->sks_kumulatif ?? 0)"
            :meta="'Syarat lulus '.$mahasiswa->prodi->sks_lulus.' SKS'" />
        <x-stat-card :label="$l('stat.semester')" :value="Format::bulat($riwayat->count())" />
        <x-stat-card :label="$l('stat.status')" :meta="'Angkatan '.$mahasiswa->angkatan">
            <x-chip :tone="$mahasiswa->status->tone()" dot>{{ $mahasiswa->status->label() }}</x-chip>
        </x-stat-card>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
        <div class="min-w-0 space-y-5">
            {{-- Status & leave history --}}
            <x-card :title="$l('kartu.status')" flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ $l('kolom.semester') }}</th>
                                <th class="px-5 py-3 text-center font-semibold">{{ $l('kolom.ke') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ $l('kolom.status') }}</th>
                                <th class="px-5 py-3 text-center font-semibold">{{ $l('kolom.sks') }}</th>
                                <th class="px-5 py-3 text-center font-semibold">{{ $l('kolom.ips') }}</th>
                                <th class="px-5 py-3 text-center font-semibold">{{ $l('kolom.ipk') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($riwayat->reverse() as $s)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="px-5 py-2.5">
                                        {{ $s->tahunAkademik->nama }}
                                        @unless ($s->is_final)
                                            <span class="text-[11px] text-ink-faint">· belum ditutup</span>
                                        @endunless
                                    </td>
                                    <td class="tabular px-5 py-2.5 text-center">{{ $s->semester_ke }}</td>
                                    <td class="px-5 py-2.5"><x-chip :tone="$s->status->tone()">{{ $s->status->label() }}</x-chip></td>
                                    <td class="tabular px-5 py-2.5 text-center">{{ $s->sks_semester }}</td>
                                    <td class="tabular px-5 py-2.5 text-center">{{ $s->ips === null ? '—' : Format::angka($s->ips) }}</td>
                                    <td class="tabular px-5 py-2.5 text-center">{{ $s->ipk === null ? '—' : Format::angka($s->ipk) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-ink-faint">{{ $l('kosong.status') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-line px-5 py-3">
                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ $l('kartu.cuti') }}</div>
                    @forelse ($mahasiswa->cuti as $c)
                        <div class="flex flex-wrap items-center gap-2 py-1 text-[12.5px]">
                            <span>{{ $c->tahunAkademik->nama }}</span>
                            <x-chip :tone="$c->status->tone()">{{ $c->status->label() }}</x-chip>
                            <span class="text-ink-faint">{{ Str::limit($c->alasan, 80) }}</span>
                        </div>
                    @empty
                        <p class="text-[12.5px] text-ink-faint">{{ $l('kosong.cuti') }}</p>
                    @endforelse
                </div>
            </x-card>

            {{-- KRS per term --}}
            <x-card :title="$l('kartu.krs')" flush>
                @forelse ($krs as $k)
                    <details class="border-b border-line/60 last:border-b-0" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 px-5 py-3 text-[13px]">
                            <span class="font-medium">{{ $k->tahunAkademik->nama }}</span>
                            <span class="flex items-center gap-2">
                                <span class="tabular text-ink-faint">{{ __('kemahasiswaan.detail.krs_sks', ['sks' => $k->total_sks]) }}</span>
                                <x-chip :tone="$k->status->tone()">{{ $k->status->label() }}</x-chip>
                            </span>
                        </summary>
                        <table class="w-full text-[12.5px]">
                            <tbody>
                                @foreach ($k->detail as $d)
                                    <tr class="border-t border-line/50">
                                        <td class="tabular px-5 py-2 text-ink-muted">{{ $d->kelasKuliah->mataKuliah->kode }}</td>
                                        <td class="px-5 py-2">{{ $d->kelasKuliah->mataKuliah->nama }}</td>
                                        <td class="px-5 py-2 text-ink-muted">{{ $l('kolom.kelas') }} {{ $d->kelasKuliah->kode }}</td>
                                        <td class="tabular px-5 py-2 text-right">{{ $d->sks }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-faint">{{ $l('kosong.krs') }}</div>
                @endforelse
            </x-card>

            {{-- KHS per term + transcript --}}
            @if ($bolehNilai)
                <x-card :title="$l('kartu.khs')" flush>
                    @forelse ($riwayat->where('is_final', true)->reverse() as $s)
                        @php $nilai = $nilaiPerTerm[$s->tahun_akademik_id] ?? collect(); @endphp
                        <div class="border-b border-line/60 last:border-b-0" data-khs="{{ $s->tahunAkademik->kode }}">
                            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <div class="text-[13px] font-medium">
                                    {{ $s->tahunAkademik->nama }}
                                    <span class="tabular ml-2 text-[12px] text-ink-faint">
                                        IPS {{ Format::angka($s->ips) }} · IPK {{ Format::angka($s->ipk) }}
                                    </span>
                                </div>
                                <x-button variant="outline" size="sm"
                                    :href="route('admin.mahasiswa.khs', [$mahasiswa, $s->tahunAkademik])">
                                    {{ __('kemahasiswaan.khs.cetak') }}
                                </x-button>
                            </div>
                            <table class="w-full text-[12.5px]">
                                <tbody>
                                    @foreach ($nilai as $n)
                                        <tr class="border-t border-line/50">
                                            <td class="tabular px-5 py-2 text-ink-muted">{{ $n->kelasKuliah->mataKuliah->kode }}</td>
                                            <td class="px-5 py-2">{{ $n->kelasKuliah->mataKuliah->nama }}</td>
                                            <td class="tabular px-5 py-2 text-center">{{ $n->krsDetail->sks }}</td>
                                            <td class="tabular px-5 py-2 text-center font-semibold">{{ $n->nilai_huruf?->value ?? '—' }}</td>
                                            <td class="tabular px-5 py-2 text-right">{{ Format::angka($n->mutu()) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-[13px] text-ink-faint">{{ $l('kosong.khs') }}</div>
                    @endforelse

                    <div class="border-t border-line px-5 py-3 text-right">
                        <x-button size="sm" :href="route('admin.mahasiswa.transkrip', $mahasiswa)">{{ $l('transkrip') }}</x-button>
                    </div>
                </x-card>
            @endif

            {{-- Invoices --}}
            @if ($bolehTagihan)
                <x-card :title="$l('kartu.tagihan')" flush>
                    <table class="w-full text-[12.5px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-2.5 font-semibold">{{ $l('kolom.nomor') }}</th>
                                <th class="px-5 py-2.5 text-right font-semibold">{{ $l('kolom.total') }}</th>
                                <th class="px-5 py-2.5 text-right font-semibold">{{ $l('kolom.sisa') }}</th>
                                <th class="px-5 py-2.5 text-center font-semibold">{{ $l('kolom.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tagihan as $t)
                                <tr class="border-b border-line/50 last:border-b-0">
                                    <td class="tabular px-5 py-2.5">{{ $t->nomor }} <span class="text-ink-faint">· {{ $t->tahunAkademik->kode }}</span></td>
                                    <td class="tabular px-5 py-2.5 text-right">{{ Format::rupiah($t->total) }}</td>
                                    <td class="tabular px-5 py-2.5 text-right">{{ Format::rupiah($t->sisa()) }}</td>
                                    <td class="px-5 py-2.5 text-center">
                                        <x-chip :tone="$t->status->tone()">{{ $t->status->label() }}</x-chip>
                                        @if ($t->dispensasiAktif())
                                            <div class="mt-1"><x-chip tone="info">{{ __('keuangan.dispensasi.chip_berlaku', ['tanggal' => Format::tanggal($t->dispensasi_sampai)]) }}</x-chip></div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-6 text-center text-ink-faint">{{ $l('kosong.tagihan') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="border-t border-line px-5 py-2.5 text-right">
                        <a href="{{ route('admin.keuangan', ['cari' => $mahasiswa->nim]) }}" class="text-[12px] font-semibold text-navy hover:underline">{{ $l('tagihan_semua') }}</a>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            <x-card :title="$l('kartu.identitas')">
                <dl class="space-y-2.5 text-[12.5px]">
                    @foreach ([
                        'nim' => $mahasiswa->nim,
                        'nik' => $nik,
                        'ttl' => ($mahasiswa->tempat_lahir ?? '—').', '.Format::tanggal($mahasiswa->tanggal_lahir),
                        'jenis_kelamin' => $mahasiswa->jenis_kelamin?->label(),
                        'email' => $mahasiswa->email,
                        'telepon' => $mahasiswa->telepon,
                    ] as $kunci => $nilai)
                        <div>
                            <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ $l('label.'.$kunci) }}</dt>
                            <dd class="tabular mt-0.5">{{ filled($nilai) ? $nilai : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card :title="$l('kartu.akademik')">
                <dl class="space-y-2.5 text-[12.5px]">
                    @foreach ([
                        'prodi' => $mahasiswa->prodi->namaLengkap(),
                        'kurikulum' => $mahasiswa->kurikulum?->nama,
                        'konsentrasi' => $mahasiswa->konsentrasi?->nama,
                        'semester_masuk' => $mahasiswa->term_masuk,
                        'jenis_daftar' => $mahasiswa->jenis_daftar?->label(),
                        'asal' => $mahasiswa->pt_asal,
                        'id_feeder' => filled($mahasiswa->feeder_id) ? $l('ya') : $l('belum'),
                    ] as $kunci => $nilai)
                        <div>
                            <dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ $l('label.'.$kunci) }}</dt>
                            <dd class="tabular mt-0.5">{{ filled($nilai) ? $nilai : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card :title="$l('kartu.dosen_wali')">
                <p class="text-[13px] font-medium" data-dosen-wali>{{ $mahasiswa->dosenWali?->namaLengkap() ?? __('dosen_wali.belum_ada') }}</p>

                @if ($bolehWali && $calonWali->isNotEmpty())
                    <form method="POST" action="{{ route('admin.dosen-wali.tetapkan', $mahasiswa) }}" class="mt-3 space-y-2"
                        data-confirm="{{ __('dosen_wali.massal.konfirmasi') }}">
                        @csrf
                        <select name="dosen" required aria-label="{{ $l('wali_ganti') }}"
                            class="w-full rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px]">
                            @foreach ($calonWali as $d)
                                <option value="{{ $d->uuid }}" @selected($d->id === $mahasiswa->dosen_wali_id)>{{ $d->namaLengkap() }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="alasan" maxlength="500" placeholder="{{ __('dosen_wali.alasan') }}"
                            class="w-full rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12.5px]">
                        <x-button type="submit" size="sm" class="w-full">{{ $l('wali_tetapkan') }}</x-button>
                    </form>
                    <a href="{{ route('admin.dosen-wali', ['prodi' => $mahasiswa->prodi_id]) }}" class="mt-2 inline-block text-[12px] font-semibold text-navy hover:underline">{{ $l('wali_kelola') }}</a>
                @endif

                <div class="mt-4 border-t border-line pt-3">
                    <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('dosen_wali.riwayat.judul') }}</div>
                    @forelse ($mahasiswa->riwayatDosenWali as $r)
                        <div class="py-1 text-[12px]">
                            {{ __('dosen_wali.riwayat.baris', ['lama' => $r->dosenLama?->namaLengkap() ?? __('dosen_wali.belum_ada'), 'baru' => $r->dosenBaru->namaLengkap()]) }}
                            <div class="tabular text-[11px] text-ink-faint">
                                {{ Format::tanggal($r->created_at) }} · {{ $r->cara->label() }} · {{ $r->penetap?->nama ?? '—' }}
                                @if ($r->krs_dialihkan > 0) · {{ __('dosen_wali.riwayat.krs', ['jumlah' => $r->krs_dialihkan]) }} @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-[12px] text-ink-faint">{{ $l('kosong.riwayat_wali') }}</p>
                    @endforelse
                </div>
            </x-card>

            @include('admin.partials.riwayat-registrasi', ['mahasiswa' => $mahasiswa])
            @if ($bolehRegistrasiUlang)
                <x-button variant="outline" size="sm" class="w-full" href="{{ route('admin.mahasiswa.registrasi-ulang', $mahasiswa) }}">{{ __('registrasi_ulang.tombol') }}</x-button>
            @endif

            <x-card :title="$l('kartu.pddikti')">
                @if ($isuFeeder->isEmpty())
                    <p class="text-[13px] text-success" data-pddikti="siap">✓ {{ $l('pddikti_siap') }}</p>
                @else
                    <ul class="space-y-2 text-[12.5px]" data-pddikti="isu">
                        @foreach ($isuFeeder as $isu)
                            <li class="flex gap-2">
                                <x-chip :tone="$isu['severity'] === 'error' ? 'danger' : 'warning'">{{ $isu['severity'] === 'error' ? '✕' : '!' }}</x-chip>
                                <span class="text-ink-muted">{{ $isu['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($mahasiswa->perubahanFeeder->isNotEmpty())
                    <div class="mt-4 border-t border-line pt-3">
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ $l('kartu.perubahan') }}</div>
                        @foreach ($mahasiswa->perubahanFeeder as $p)
                            <div class="py-1 text-[12px]">
                                {{ $p->labelKolom() }}: <span class="text-ink-faint">{{ $p->nilai_lama ?? '—' }}</span> → {{ $p->nilai_baru ?? '—' }}
                                <x-chip tone="warning" class="ml-1">{{ $p->status->label() }}</x-chip>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="mt-4 text-[11.5px] leading-relaxed text-ink-faint">{{ $l('pddikti_kunci') }}</p>
                @if ($bolehFeeder)
                    <a href="{{ route('admin.feeder') }}" class="mt-1 inline-block text-[12px] font-semibold text-navy hover:underline">{{ $l('pddikti_buka') }}</a>
                @endif
            </x-card>
        </div>
    </div>
@endsection
