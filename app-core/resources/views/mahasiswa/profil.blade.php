@extends('layouts.app')

@section('title', 'Profil Akademik')

@php $belumLengkap = collect($kelengkapan)->filter(fn ($ada) => ! $ada); @endphp

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    {{-- Alasan kartu ujian tertahan mendarat di sini. Tanpa ini, penolakannya
         hilang tanpa bekas dan tombolnya tampak sekadar tidak berfungsi. --}}
    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($belumLengkap->isNotEmpty())
        <div class="mb-5">
            <x-alert tone="warning">
                Data berikut belum lengkap dan diperlukan untuk pelaporan PDDIKTI:
                <strong>{{ $belumLengkap->keys()->implode(', ') }}</strong>. Lengkapi di bawah.
            </x-alert>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex min-w-0 flex-col gap-5">
            <x-card title="Data Akademik" meta="ditetapkan kampus">
                <dl class="grid gap-x-6 gap-y-3.5 sm:grid-cols-2">
                    @foreach ([
                        'NIM' => $mahasiswa->nim,
                        'Nama' => $mahasiswa->nama,
                        'Program Studi' => $mahasiswa->prodi->namaLengkap(),
                        'Fakultas' => $mahasiswa->prodi->fakultas->nama,
                        'Angkatan' => $mahasiswa->angkatan,
                        'Kurikulum' => $mahasiswa->kurikulum?->nama ?? '—',
                        'Dosen Wali' => $mahasiswa->dosenWali?->namaLengkap() ?? '—',
                    ] as $label => $nilai)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ $label }}</dt>
                            <dd class="tabular mt-0.5 text-[13.5px]">{{ $nilai }}</dd>
                        </div>
                    @endforeach

                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Status</dt>
                        <dd class="mt-1">
                            <x-chip :tone="$mahasiswa->status->tone()" dot>{{ $mahasiswa->status->label() }}</x-chip>
                        </dd>
                    </div>
                </dl>

                <p class="mt-4 border-t border-line pt-3 text-[11.5px] leading-relaxed text-ink-faint">
                    Data di atas adalah keterangan resmi kampus dan tidak dapat diubah sendiri.
                    Bila ada yang keliru, hubungi BAAK.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button href="{{ route('mahasiswa.ktm') }}" variant="outline" size="sm">
                        Cetak KTM
                    </x-button>

                    <x-button href="{{ route('mahasiswa.kartu-ujian') }}" variant="outline" size="sm">
                        Cetak kartu ujian
                    </x-button>
                </div>

                <p class="mt-2 text-[11.5px] text-ink-faint">
                    KTM dicetak tanpa pas foto — tempelkan foto 3×4 pada kotak yang disediakan.
                </p>
            </x-card>

            <x-card title="Data Pribadi" meta="dapat Anda perbarui">
                <form method="POST" action="{{ route('mahasiswa.profil.perbarui') }}" class="flex flex-col gap-3.5">
                    @csrf @method('PUT')

                    <div class="grid gap-3.5 sm:grid-cols-2">
                        <x-field label="Surel Pribadi" name="email_pribadi" type="email"
                            :value="$mahasiswa->email_pribadi" />
                        <x-field label="Telepon" name="telepon" :value="$mahasiswa->telepon" />
                    </div>

                    <x-field label="Alamat" name="alamat" :value="$mahasiswa->alamat" />

                    <div class="grid gap-3.5 sm:grid-cols-3">
                        @if (filled($mahasiswa->nik))
                            <div>
                                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">NIK</span>
                                <div class="tabular rounded-control border border-line bg-canvas px-3 py-2 text-[13px] text-ink-muted">
                                    {{ $mahasiswa->nik }}
                                </div>
                                <p class="mt-1 text-[11.5px] text-ink-faint">Hanya BAAK yang dapat mengubahnya.</p>
                            </div>
                        @else
                            <x-field label="NIK" name="nik" hint="16 digit sesuai KTP." />
                        @endif

                        <x-field label="Tempat Lahir" name="tempat_lahir" :value="$mahasiswa->tempat_lahir" />
                        <x-field label="Tanggal Lahir" name="tanggal_lahir" type="date"
                            :value="$mahasiswa->tanggal_lahir" />
                    </div>

                    <x-button type="submit" class="self-start">Simpan Perubahan</x-button>
                </form>
            </x-card>

            @if ($riwayatCuti->isNotEmpty())
                <x-card title="Riwayat Cuti" flush>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Semester</th>
                                    <th class="px-5 py-3 font-semibold">Jenis</th>
                                    <th class="px-5 py-3 font-semibold">Alasan</th>
                                    <th class="px-5 py-3 text-center font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($riwayatCuti as $c)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="tabular px-5 py-3">{{ $c->tahunAkademik->kode }}</td>
                                        <td class="px-5 py-3">{{ ucfirst($c->jenis) }}</td>
                                        <td class="px-5 py-3 text-ink-muted">{{ $c->alasan }}</td>
                                        <td class="px-5 py-3 text-center">
                                            <x-chip :tone="match ($c->status->value) {
                                                'disetujui' => 'success',
                                                'ditolak' => 'danger',
                                                default => 'warning',
                                            }">{{ $c->status->label() }}</x-chip>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <x-card title="Ganti Kata Sandi">
            <form method="POST" action="{{ route('mahasiswa.profil.kata-sandi') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Kata Sandi Lama" name="kata_sandi_lama" type="password" required />
                <x-field label="Kata Sandi Baru" name="kata_sandi" type="password" required />
                <x-field label="Ulangi Kata Sandi Baru" name="kata_sandi_confirmation" type="password" required />
                <x-button type="submit" class="w-full">Ganti Kata Sandi</x-button>
            </form>
        </x-card>
    </div>
@endsection
