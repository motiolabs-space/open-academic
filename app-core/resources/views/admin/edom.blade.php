@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Evaluasi Dosen (EDOM)')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($semua->isNotEmpty())
        <div class="mb-5 flex flex-wrap gap-2">
            @foreach ($semua as $p)
                <x-button href="{{ route('admin.edom.index', ['periode' => $p->uuid]) }}"
                    :variant="$periode?->is($p) ? 'primary' : 'outline'" size="sm">
                    {{ $p->tahunAkademik->nama }}
                    @if ($p->terbuka())
                        · dibuka
                    @endif
                </x-button>
            @endforeach
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            @if ($periode === null)
                <x-card>
                    <x-empty-state
                        title="Belum ada periode EDOM"
                        description="Buat periode untuk semester berjalan lewat panel di samping." />
                </x-card>
            @else
                <x-card :title="$periode->nama" :meta="$periode->tahunAkademik->nama">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-stat-card label="Jawaban masuk" :value="$partisipasi" meta="pengisian" />
                        <x-stat-card label="Ambang" :value="$periode->min_responden" meta="responden" />
                        <x-stat-card
                            label="Status"
                            :value="$periode->terbuka() ? 'Dibuka' : 'Ditutup'"
                            :meta="Format::tanggal($periode->mulai).' – '.Format::tanggal($periode->selesai)"
                            :feature="$periode->terbuka()" />
                    </div>

                    @if ($bolehKelola)
                        <form method="POST" action="{{ route('admin.edom.status', $periode) }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="aktif" value="{{ $periode->is_active ? 0 : 1 }}">
                            <x-button type="submit" size="sm" :variant="$periode->is_active ? 'outline' : 'primary'">
                                {{ $periode->is_active ? 'Tutup periode' : 'Buka periode' }}
                            </x-button>
                        </form>
                    @endif
                </x-card>

                <x-card flush title="Rekap per Dosen" meta="hanya kelas yang memenuhi ambang">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Dosen</th>
                                    <th class="px-5 py-3 text-right font-semibold">Kelas</th>
                                    <th class="px-5 py-3 text-right font-semibold">Responden</th>
                                    <th class="px-5 py-3 text-right font-semibold">Rerata</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($ringkasan as $baris)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $baris['dosen']->nama }}</div>
                                            <div class="tabular text-[11.5px] text-ink-faint">{{ $baris['dosen']->nidn }}</div>
                                        </td>
                                        <td class="tabular px-5 py-3 text-right">{{ $baris['kelas_dinilai'] }}</td>
                                        <td class="tabular px-5 py-3 text-right">{{ $baris['responden'] }}</td>
                                        <td class="tabular px-5 py-3 text-right font-medium">
                                            {{ $baris['rerata'] === null ? '—' : Format::angka($baris['rerata'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <x-empty-state
                                                title="Belum ada hasil"
                                                description="Belum ada kelas yang mencapai {{ $periode->min_responden }} responden." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>

                <x-card flush title="Pengisian per Kelas">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Kelas</th>
                                    <th class="px-5 py-3 font-semibold">Dosen</th>
                                    <th class="px-5 py-3 text-right font-semibold">Pengisi</th>
                                    <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($perKelas as $baris)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $baris['kelas']->mataKuliah->nama }}</div>
                                            <div class="text-[11.5px] text-ink-faint">Kelas {{ $baris['kelas']->nama }}</div>
                                        </td>
                                        <td class="px-5 py-3">{{ $baris['dosen']->nama }}</td>
                                        <td class="tabular px-5 py-3 text-right">
                                            {{ $baris['responden'] }}
                                            @unless ($baris['cukup'])
                                                {{-- Jumlah pengisi tetap ditampilkan karena inilah
                                                     angka yang bisa ditindaklanjuti saat periode masih
                                                     berjalan; yang disembunyikan adalah nilainya. --}}
                                                <div class="text-[11px] text-warning-ink">di bawah ambang</div>
                                            @endunless
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            @if ($baris['cukup'])
                                                <x-button
                                                    href="{{ route('admin.edom.kelas', [$periode, $baris['kelas'], 'dosen' => $baris['dosen']->id]) }}"
                                                    variant="outline" size="sm">
                                                    Lihat
                                                </x-button>
                                            @else
                                                <span class="text-[12px] text-ink-faint">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <x-empty-state
                                                title="Belum ada pengisian"
                                                description="Belum ada mahasiswa yang mengisi EDOM pada periode ini." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            @if ($periode !== null)
                <x-card title="Pertanyaan" :meta="$periode->pertanyaan->count().' butir'">
                    @php $terkunci = $partisipasi > 0; @endphp

                    @if ($terkunci)
                        {{-- Dikatakan sebagai fakta, bukan sebagai error yang muncul
                             belakangan setelah seseorang mengetik pertanyaan baru. --}}
                        <p class="mb-3 text-[12px] leading-relaxed text-warning-ink">
                            Instrumen terkunci karena sudah ada jawaban yang masuk. Revisi
                            pertanyaan dilakukan dengan membuat periode baru.
                        </p>
                    @endif

                    <ol class="space-y-2">
                        @forelse ($periode->pertanyaan as $p)
                            <li class="flex items-start justify-between gap-3 border-b border-line/50 pb-2 last:border-b-0">
                                <div class="min-w-0">
                                    <p class="text-[13px] leading-relaxed">{{ $p->teks }}</p>
                                    <p class="mt-0.5 text-[11.5px] text-ink-faint">
                                        {{ $p->kategori->label() }} · {{ $p->tipe->label() }}
                                    </p>
                                </div>
                                @if ($bolehKelola && ! $terkunci)
                                    <form method="POST" action="{{ route('admin.edom.pertanyaan.hapus', $p) }}">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="ghost" size="sm">Hapus</x-button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li class="text-[13px] text-ink-muted">Belum ada pertanyaan.</li>
                        @endforelse
                    </ol>

                    @if ($bolehKelola && ! $terkunci)
                        <form method="POST" action="{{ route('admin.edom.pertanyaan', $periode) }}"
                            class="mt-4 space-y-3 border-t border-line pt-4">
                            @csrf
                            <x-field label="Pernyataan" name="teks" type="textarea" required
                                placeholder="Dosen menyampaikan materi dengan jelas." />
                            <x-field label="Kategori" name="kategori" :options="$kategoriPilihan" required />
                            <x-field label="Tipe jawaban" name="tipe" :options="$tipePilihan" required />
                            <x-button type="submit" size="sm" variant="outline">Tambah pertanyaan</x-button>
                        </form>

                        @if ($periode->pertanyaan->isEmpty() && $semua->count() > 1)
                            <form method="POST" action="{{ route('admin.edom.salin', $periode) }}"
                                class="mt-4 space-y-3 border-t border-line pt-4">
                                @csrf
                                <x-field label="Salin dari periode" name="dari" required
                                    :options="$semua->reject(fn ($p) => $p->is($periode))->pluck('tahunAkademik.nama', 'id')->all()"
                                    hint="Disalin, bukan dipakai bersama — hasil periode lama tetap terikat pada rumusan lamanya." />
                                <x-button type="submit" size="sm" variant="outline">Salin</x-button>
                            </form>
                        @endif
                    @endif
                </x-card>
            @endif

            @if ($bolehKelola)
                <x-card title="Periode Baru">
                    <form method="POST" action="{{ route('admin.edom.periode') }}" class="space-y-3">
                        @csrf
                        <x-field label="Semester" name="tahun_akademik_id" :options="$tahunPilihan" required />
                        <x-field label="Nama periode" name="nama" required placeholder="EDOM Ganjil 2026/2027" />
                        <x-field label="Mulai" name="mulai" type="date" required />
                        <x-field label="Selesai" name="selesai" type="date" required
                            hint="Tutup sebelum nilai dirilis: evaluasi yang ditulis setelah mahasiswa melihat nilainya mengukur nilainya." />
                        <x-field label="Ambang responden" name="min_responden" type="number" :value="5" required
                            hint="Hasil kelas di bawah angka ini tidak ditampilkan kepada siapa pun." />
                        <x-button type="submit" size="sm">Buat periode</x-button>
                    </form>
                </x-card>
            @endif

            <x-card title="Kerahasiaan">
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    Catatan siapa yang sudah mengisi dan isi jawabannya disimpan di dua tabel
                    tanpa penghubung apa pun. Tidak ada kueri — termasuk langsung ke basis
                    data — yang dapat memasangkan keduanya.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-muted">
                    Komentar bebas saat ini
                    {{ ['prodi' => 'diteruskan ke program studi', 'dosen' => 'dapat dibaca dosen yang bersangkutan', 'tutup' => 'tidak ditampilkan di mana pun'][config('edom.komentar')] ?? '—' }}.
                </p>
            </x-card>
        </div>
    </div>
@endsection
