@php
    use App\Enums\TipeJawabanEdom;
    use App\Support\Format;

    // Anchors on the scale, so a 3 means the same thing to every student who
    // clicks it. Bare numbers get read differently by different people, and the
    // average then averages two different questions.
    $skala = [
        1 => 'Sangat kurang',
        2 => 'Kurang',
        3 => 'Cukup',
        4 => 'Baik',
        5 => 'Sangat baik',
    ];
@endphp
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

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
            @if ($periode === null)
                <x-card>
                    <x-empty-state
                        title="Belum ada periode EDOM"
                        description="Pengisian evaluasi dibuka menjelang akhir perkuliahan setiap semester." />
                </x-card>
            @elseif (! $terbuka)
                <x-card title="{{ $periode->nama }}">
                    <x-empty-state
                        title="Pengisian sedang ditutup"
                        description="Jadwal pengisian {{ Format::tanggal($periode->mulai) }} s.d. {{ Format::tanggal($periode->selesai) }}." />
                </x-card>
            @elseif ($tertunda->isEmpty())
                <x-card title="{{ $periode->nama }}">
                    <x-empty-state
                        title="Semua evaluasi sudah terisi"
                        description="Terima kasih. Tidak ada lagi yang perlu Anda nilai pada periode ini." />
                </x-card>
            @else
                @foreach ($tertunda as $baris)
                    @php
                        $kelas = $baris['kelas'];
                        $dosen = $baris['dosen'];
                        $kunciForm = $kelas->id.'-'.$dosen->id;
                    @endphp

                    <x-card flush x-data="{ buka: false }">
                        <button
                            type="button" @click="buka = !buka"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
                            :aria-expanded="buka ? 'true' : 'false'" aria-controls="edom-{{ $kunciForm }}"
                        >
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold">{{ $dosen->nama }}</span>
                                <span class="block text-[12px] text-ink-muted">
                                    {{ $kelas->mataKuliah->nama }} · Kelas {{ $kelas->nama }}
                                </span>
                            </span>
                            <span class="shrink-0 text-[11.5px] text-ink-faint" x-text="buka ? 'Tutup' : 'Isi'"></span>
                        </button>

                        <div id="edom-{{ $kunciForm }}" x-show="buka" x-cloak class="border-t border-line px-5 py-4">
                            <form method="POST" action="{{ route('mahasiswa.edom.kirim') }}" class="space-y-5">
                                @csrf
                                <input type="hidden" name="kelas_kuliah_id" value="{{ $kelas->id }}">
                                <input type="hidden" name="dosen_id" value="{{ $dosen->id }}">

                                @foreach ($pertanyaan as $i => $p)
                                    <input type="hidden" name="jawaban[{{ $i }}][pertanyaan_id]" value="{{ $p->id }}">

                                    @if ($p->tipe === TipeJawabanEdom::Skala)
                                        <fieldset>
                                            <legend class="text-[13px] leading-relaxed">
                                                {{ $p->teks }}
                                                <span class="text-danger" aria-hidden="true">*</span>
                                            </legend>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($skala as $nilai => $arti)
                                                    <label class="flex cursor-pointer items-center gap-1.5 rounded-control border border-line-input px-3 py-1.5 text-[12px] has-[:checked]:border-navy has-[:checked]:bg-navy/5">
                                                        <input
                                                            type="radio" class="accent-navy" required
                                                            name="jawaban[{{ $i }}][nilai]" value="{{ $nilai }}"
                                                        >
                                                        <span class="tabular font-medium">{{ $nilai }}</span>
                                                        <span class="text-ink-muted">{{ $arti }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @else
                                        <div>
                                            <label
                                                for="teks-{{ $kunciForm }}-{{ $p->id }}"
                                                class="block text-[13px] leading-relaxed"
                                            >{{ $p->teks }}</label>
                                            <textarea
                                                id="teks-{{ $kunciForm }}-{{ $p->id }}"
                                                name="jawaban[{{ $i }}][teks]" rows="3" maxlength="2000"
                                                class="mt-2 w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10"
                                            ></textarea>
                                            <p class="mt-1 text-[11.5px] text-ink-faint">
                                                Boleh dikosongkan.
                                                @if ($kebijakanKomentar === 'dosen')
                                                    Komentar dibaca oleh dosen yang bersangkutan.
                                                @elseif ($kebijakanKomentar === 'prodi')
                                                    Komentar hanya dibaca oleh program studi, bukan oleh dosen.
                                                @else
                                                    Komentar hanya dipakai sebagai catatan internal.
                                                @endif
                                            </p>
                                        </div>
                                    @endif
                                @endforeach

                                <div class="flex items-center gap-3">
                                    <x-button type="submit" size="sm">Kirim penilaian</x-button>
                                    {{-- Dikatakan tepat di sebelah tombolnya, bukan hanya di
                                         panel samping: ini titik ketika seseorang ragu. --}}
                                    <span class="text-[11.5px] text-ink-faint">
                                        Terkirim tanpa nama, dan tidak dapat diubah setelahnya.
                                    </span>
                                </div>
                            </form>
                        </div>
                    </x-card>
                @endforeach
            @endif
        </div>

        <div class="space-y-5">
            <x-card title="Kerahasiaan Jawaban">
                {{-- Klaim yang persis sekuat mekanismenya, tidak lebih. Jaminannya
                     bukan janji kebijakan: kolom yang menghubungkan jawaban ke
                     mahasiswa memang tidak ada di basis data. --}}
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    Jawaban Anda disimpan terpisah dari catatan siapa yang sudah mengisi.
                    Keduanya tidak memiliki penghubung apa pun di basis data, sehingga tidak
                    ada cara — termasuk oleh pengelola sistem — untuk mengetahui siapa
                    menjawab apa.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-muted">
                    Yang tercatat atas nama Anda hanyalah <em>sudah mengisi</em> atau
                    <em>belum</em>.
                </p>
            </x-card>

            @if ($periode !== null)
                <x-card title="Periode">
                    <dl class="space-y-2 text-[13px]">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Jadwal</dt>
                            <dd class="tabular text-right">
                                {{ Format::tanggal($periode->mulai) }} – {{ Format::tanggal($periode->selesai) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Belum diisi</dt>
                            <dd class="tabular text-right">{{ $tertunda->count() }}</dd>
                        </div>
                    </dl>

                    @if ($gerbang === 'krs' && $tertunda->isNotEmpty())
                        <p class="mt-3 text-[12px] leading-relaxed text-warning-ink">
                            Pengisian KRS semester berikutnya dibuka setelah seluruh evaluasi
                            ini terisi.
                        </p>
                    @elseif ($gerbang === 'khs' && $tertunda->isNotEmpty())
                        <p class="mt-3 text-[12px] leading-relaxed text-warning-ink">
                            Kartu hasil studi dapat dilihat setelah seluruh evaluasi ini terisi.
                        </p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
@endsection
