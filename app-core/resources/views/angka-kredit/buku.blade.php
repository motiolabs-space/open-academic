{{--
    Baris-baris buku besar satu dosen.

    $buku   Collection<AngkaKreditDosen>
    $rekap  RekapAngkaKredit — untuk menandai baris sebelum TMT
    $admin  bool — tampilkan tombol hapus untuk isian manual
--}}
@php
    use App\Support\Format;

    $tmt = $rekap->tmtJabatan;
@endphp

<x-card flush :title="__('angka_kredit.buku')" :meta="$buku->count().' baris'">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-[13px]">
            <thead>
                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                    <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.tanggal') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.unsur') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.sumber') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.keterangan') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.sks') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.nilai') }}</th>
                    @if ($admin)
                        <th class="px-5 py-3"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($buku as $b)
                    @php $sebelumTmt = !$b->sesudahTmt($tmt); @endphp
                    <tr @class(['border-b border-line/50 align-top last:border-b-0 odd:bg-zebra', 'text-ink-faint' => $sebelumTmt])>
                        <td class="tabular px-5 py-2.5">{{ Format::tanggal($b->tanggal) }}</td>
                        <td class="px-5 py-2.5">Kum {{ $b->unsur->kode() }}</td>
                        <td class="px-5 py-2.5">
                            {{ $b->sumber->label() }}
                            @if ($b->perkiraan)
                                <x-chip tone="warning">{{ __('angka_kredit.perkiraan') }}</x-chip>
                            @endif
                            @if ($sebelumTmt)
                                <x-chip tone="neutral">{{ __('angka_kredit.tidak_dihitung') }}</x-chip>
                            @endif
                        </td>
                        <td class="px-5 py-2.5">
                            {{ $b->keterangan ?? '—' }}
                            @if ($b->semester_kode)
                                <span class="tabular text-[11.5px] text-ink-faint">· {{ $b->semester_kode }}</span>
                            @endif
                        </td>
                        <td class="tabular px-5 py-2.5 text-right">
                            {{ $b->sks_ratus === null ? '—' : Format::angka($b->sks_ratus / 100, 2) }}
                        </td>
                        <td class="tabular px-5 py-2.5 text-right">{{ Format::angka($b->nilai(), 2) }}</td>
                        @if ($admin)
                            <td class="px-5 py-2.5 text-right">
                                @can('delete', $b)
                                    <form method="POST" action="{{ route('admin.angka-kredit.destroy', $b) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" size="sm" variant="danger">{{ __('angka_kredit.aksi.hapus') }}</x-button>
                                    </form>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $admin ? 7 : 6 }}" class="px-5 py-6 text-center text-ink-muted">
                            {{ __('angka_kredit.kosong.buku') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
