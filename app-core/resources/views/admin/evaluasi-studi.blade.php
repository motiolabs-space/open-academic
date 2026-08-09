@extends('layouts.app')

@section('title', 'Evaluasi Studi')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_320px]">

        {{-- ============ ANTREAN ============ --}}
        <x-card title="Perlu Ditindaklanjuti" :meta="$antrean->count().' temuan'" flush>
            <div class="divide-y divide-line/60">
                @forelse ($antrean as $baris)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start gap-2">
                            <x-chip :tone="$baris->temuan->tone()">{{ $baris->temuan->label() }}</x-chip>
                            <span class="text-[13.5px] font-semibold">{{ $baris->mahasiswa->nama }}</span>
                            <span class="tabular text-[11.5px] text-ink-faint">{{ $baris->mahasiswa->nim }}</span>
                        </div>

                        <div class="tabular mt-1.5 text-xs text-ink-muted">
                            {{ $baris->tahap ?? 'Evaluasi semester' }} ·
                            {{ $baris->tahunAkademik->nama }} ·
                            semester ke-{{ $baris->semester_ke }} ·
                            {{ $baris->mahasiswa->prodi?->nama }}
                        </div>

                        {{-- Ambang selalu disebut di sebelah angkanya, supaya
                             pembaca dapat berselisih dengan aturannya, bukan
                             dengan mahasiswanya. --}}
                        <div class="mt-2 text-[12.5px] text-ink">{{ $baris->alasan() }}</div>

                        <form method="POST" action="{{ route('admin.evaluasi-studi.putuskan', $baris) }}"
                            class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf

                            <label class="flex flex-col gap-1">
                                <span class="text-[11px] font-semibold text-ink-muted">Keputusan</span>
                                <select name="keputusan" required
                                    class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                                    @foreach ($keputusan as $pilihan)
                                        <option value="{{ $pilihan->value }}">{{ $pilihan->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="flex min-w-[220px] flex-1 flex-col gap-1">
                                <span class="text-[11px] font-semibold text-ink-muted">Alasan (wajib)</span>
                                <input type="text" name="catatan" required maxlength="1000"
                                    placeholder="Mis. cuti sakit satu semester, diberi kesempatan perbaikan"
                                    class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                            </label>

                            <x-button type="submit" class="px-4 py-2 text-xs">Catat</x-button>
                        </form>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-muted">
                        Tidak ada temuan yang menunggu keputusan.
                    </div>
                @endforelse
            </div>
        </x-card>

        {{-- ============ SISI KANAN ============ --}}
        <div class="flex flex-col gap-5">

            <x-card title="Jalankan Evaluasi">
                <p class="mb-3 text-[12.5px] leading-relaxed text-ink-muted">
                    Membaca angka yang <strong>sudah dibekukan</strong> saat penutupan semester,
                    bukan menghitung ulang — supaya hasilnya sama dengan KHS yang diterima
                    mahasiswa. Menghasilkan temuan saja; tidak ada status yang berubah.
                </p>

                <form method="POST" action="{{ route('admin.evaluasi-studi.jalankan') }}"
                    class="flex flex-col gap-3">
                    @csrf

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-ink-muted">Semester</span>
                        <select name="term" required
                            class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                            @foreach ($daftarTerm as $t)
                                <option value="{{ $t->kode }}">{{ $t->nama }}</option>
                            @endforeach
                        </select>
                    </label>

                    <x-button type="submit" class="self-start px-4 py-2 text-xs">Jalankan</x-button>
                </form>
            </x-card>

            <x-card title="Aturan yang Berlaku" meta="config/academic.php">
                <p class="mb-3 text-[12px] leading-relaxed text-ink-muted">
                    Kebijakan, bukan fakta. Angkanya dibekukan pada tiap baris evaluasi saat
                    dijalankan, jadi perubahan aturan tidak menulis ulang keputusan lama.
                </p>

                <dl class="flex flex-col gap-2">
                    @foreach ($aturan['tahap'] as $tahap)
                        <div class="flex items-baseline justify-between gap-2 border-b border-line pb-2">
                            <dt class="text-[12.5px] font-semibold">{{ $tahap['nama'] }}</dt>
                            <dd class="tabular text-[12px] text-ink-muted">
                                akhir smt {{ $tahap['semester_ke'] }} ·
                                {{ $tahap['min_sks'] }} SKS ·
                                IPK {{ number_format((float) $tahap['min_ipk'], 2) }}
                            </dd>
                        </div>
                    @endforeach

                    <div class="flex items-baseline justify-between gap-2 border-b border-line pb-2">
                        <dt class="text-[12.5px] font-semibold">Batas masa studi</dt>
                        <dd class="tabular text-[12px] text-ink-muted">{{ $aturan['masa_studi'] }} semester tempuh</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-[12.5px] font-semibold">Peringatan IPS</dt>
                        <dd class="tabular text-[12px] text-ink-muted">
                            di bawah {{ number_format((float) $aturan['peringatan_ips'], 2) }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-3 border-t border-line pt-3 text-[11.5px] leading-relaxed text-ink-faint">
                    Semester cuti tidak dihitung sebagai semester tempuh — itu justru gunanya cuti.
                </p>
            </x-card>
        </div>
    </div>
@endsection
