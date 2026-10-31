{{-- Riwayat registrasi: the registration this one came from, and every
     re-NIM / readmission / reactivation that started here (ADR-042 §3).
     Expects peristiwaRegistrasi.mahasiswaBaru and registrasiSebelumnya.mahasiswaLama loaded. --}}
<x-card :title="__('registrasi_ulang.riwayat.judul')">
    @php($sebelumnya = $mahasiswa->registrasiSebelumnya)

    @if ($sebelumnya === null && $mahasiswa->peristiwaRegistrasi->isEmpty())
        <p class="text-[12px] text-ink-faint" data-riwayat-registrasi="kosong">{{ __('registrasi_ulang.riwayat.kosong') }}</p>
    @endif

    <ul class="space-y-3 text-[12.5px]" data-riwayat-registrasi>
        @if ($sebelumnya)
            <li>
                <div class="font-medium">{{ $sebelumnya->jenis->label() }}</div>
                <div class="text-ink-muted">
                    {{ __('registrasi_ulang.riwayat.dari') }}
                    <a href="{{ route('admin.mahasiswa.show', $sebelumnya->mahasiswaLama) }}" class="tabular font-semibold text-navy hover:underline">{{ $sebelumnya->nim_lama }}</a>
                </div>
                <div class="tabular text-[11px] text-ink-faint">{{ \App\Support\Format::tanggal($sebelumnya->tanggal) }} · {{ $sebelumnya->nomor_sk ?? '—' }}</div>
            </li>
        @endif

        @foreach ($mahasiswa->peristiwaRegistrasi as $r)
            <li>
                <div class="font-medium">{{ $r->jenis->label() }}</div>
                <div class="text-ink-muted">
                    @if ($r->mahasiswaBaru)
                        {{ __('registrasi_ulang.riwayat.ke') }}
                        <a href="{{ route('admin.mahasiswa.show', $r->mahasiswaBaru) }}" class="tabular font-semibold text-navy hover:underline">{{ $r->nim_baru }}</a>
                    @else
                        {{ __('registrasi_ulang.riwayat.status', ['lama' => $r->status_sebelum->label(), 'baru' => $r->status_sesudah->label()]) }}
                    @endif
                </div>
                <div class="tabular text-[11px] text-ink-faint">{{ \App\Support\Format::tanggal($r->tanggal) }} · {{ $r->nomor_sk ?? '—' }}</div>
            </li>
        @endforeach
    </ul>
</x-card>
