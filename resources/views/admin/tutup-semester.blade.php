@extends('layouts.app')

@section('title', 'Penutupan Semester')

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field label="Semester" name="term" :value="$term?->id" :options="$daftarTerm->pluck('nama', 'id')" />
            <x-button type="submit">Tampilkan</x-button>
        </form>
    </x-card>

    <div class="mb-5">
        <x-alert tone="info">
            Membekukan catatan semester adalah yang membuat <strong>batas SKS semester
            berikutnya dihitung dari IPS</strong>. Selama catatan belum dibeku, setiap
            mahasiswa jatuh ke batas bawaan tanpa pesan galat apa pun.
        </x-alert>
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Siap Dibekukan" :value="number_format($pratinjau['siap']->count())" />
        <x-stat-card label="Terhalang" :value="number_format($pratinjau['terhalang']->count())"
            meta="menunggu nilai difinalisasi dosen" />
        <x-stat-card label="Sudah Beku" :value="number_format($pratinjau['sudah_final'])" />
        <x-stat-card label="Kelas Belum Final" :value="number_format($pratinjau['kelas_belum_final']->count())" />
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-5">
            @if ($pratinjau['terhalang']->isNotEmpty())
                <x-card title="Terhalang" :meta="$pratinjau['terhalang']->count().' mahasiswa'" flush>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                                    <th class="px-5 py-3 font-semibold">Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pratinjau['terhalang']->take(50) as $baris)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $baris['status']->mahasiswa->nama }}</div>
                                            <div class="tabular text-[11.5px] text-ink-faint">
                                                {{ $baris['status']->mahasiswa->nim }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-ink-muted">{{ $baris['alasan'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($pratinjau['terhalang']->count() > 50)
                        <div class="border-t border-line px-5 py-3 text-[12px] text-ink-faint">
                            Menampilkan 50 dari {{ $pratinjau['terhalang']->count() }}.
                        </div>
                    @endif
                </x-card>
            @endif

            @if ($beku instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $beku->total() > 0)
                <x-card title="Catatan yang Sudah Beku" :meta="$beku->total().' mahasiswa'" flush>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                                    <th class="px-5 py-3 text-center font-semibold">SKS</th>
                                    <th class="px-5 py-3 text-center font-semibold">IPS</th>
                                    <th class="px-5 py-3 text-center font-semibold">IPK</th>
                                    <th class="px-5 py-3 text-right font-semibold">Buka Kembali</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($beku as $s)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $s->mahasiswa->nama }}</div>
                                            <div class="tabular text-[11.5px] text-ink-faint">{{ $s->mahasiswa->nim }}</div>
                                        </td>
                                        <td class="tabular px-5 py-3 text-center">
                                            {{ $s->sks_semester }}
                                            <div class="text-[11px] text-ink-faint">{{ $s->sks_kumulatif }} kum.</div>
                                        </td>
                                        <td class="tabular px-5 py-3 text-center font-semibold">
                                            {{ number_format((float) $s->ips, 2, ',', '.') }}
                                        </td>
                                        <td class="tabular px-5 py-3 text-center font-semibold">
                                            {{ number_format((float) $s->ipk, 2, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <form method="POST" action="{{ route('admin.tutup-semester.buka', $s) }}"
                                                class="flex items-center justify-end gap-1.5">
                                                @csrf
                                                <input type="text" name="alasan" required minlength="10"
                                                    placeholder="Alasan (min. 10 huruf)"
                                                    class="w-44 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <x-button type="submit" variant="outline" size="sm">Buka</x-button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($beku->hasPages())
                        <div class="border-t border-line px-5 py-3">{{ $beku->links() }}</div>
                    @endif
                </x-card>
            @endif

            @if ($pratinjau['siap']->isEmpty() && $pratinjau['terhalang']->isEmpty() && $pratinjau['sudah_final'] === 0)
                <x-card>
                    <x-empty-state title="Belum ada catatan semester"
                        description="Catatan dibuat saat KRS mahasiswa disetujui. Bila kosong, belum ada KRS yang disetujui pada semester ini." />
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-5">
            <x-card title="Bekukan Catatan">
                @if ($term === null)
                    <p class="text-[13px] text-ink-muted">Pilih semester lebih dulu.</p>
                @elseif ($pratinjau['siap']->isEmpty())
                    <p class="text-[13px] text-ink-muted">
                        Tidak ada catatan yang siap dibekukan saat ini.
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.tutup-semester.tutup') }}"
                        onsubmit="return confirm('Bekukan {{ $pratinjau['siap']->count() }} catatan semester? Setelah beku, KHS-nya resmi dan hanya dapat diubah lewat pembukaan kembali yang tercatat.');">
                        @csrf
                        <input type="hidden" name="tahun_akademik_id" value="{{ $term->id }}">

                        <p class="mb-3.5 text-[13px] leading-relaxed text-ink-muted">
                            <strong class="text-ink">{{ $pratinjau['siap']->count() }} mahasiswa</strong>
                            seluruh nilainya sudah final dan siap dibekukan.
                        </p>

                        <x-button type="submit" variant="gold" class="w-full">Bekukan Sekarang</x-button>
                    </form>
                @endif

                <p class="mt-4 border-t border-line pt-3 text-[11.5px] leading-relaxed text-ink-muted">
                    Aman dijalankan berulang: catatan yang sudah beku dilewati, bukan dihitung
                    ulang. Mahasiswa yang masih terhalang tinggal dibekukan pada putaran
                    berikutnya setelah nilainya masuk.
                </p>
            </x-card>

            @if ($pratinjau['kelas_belum_final']->isNotEmpty())
                <x-card title="Kelas Belum Difinalisasi"
                    :meta="$pratinjau['kelas_belum_final']->count().' kelas'">
                    <ul class="flex flex-col gap-1.5 text-[12.5px]">
                        @foreach ($pratinjau['kelas_belum_final']->take(20) as $kelas)
                            <li class="tabular text-ink-muted">
                                {{ $kelas->mataKuliah->kode }} kelas {{ $kelas->kode }}
                            </li>
                        @endforeach
                    </ul>

                    @if ($pratinjau['kelas_belum_final']->count() > 20)
                        <p class="mt-2 text-[11.5px] text-ink-faint">
                            dan {{ $pratinjau['kelas_belum_final']->count() - 20 }} lainnya.
                        </p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
@endsection
