@extends('layouts.app')

@section('title', 'Capaian Pembelajaran')

@section('content')
    @include('admin.master.partials.tabs')

    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($prodi === null)
        <x-empty-state title="Belum ada program studi"
            description="Tambahkan program studi lebih dulu pada tab Program Studi." />
    @else
        <x-card class="mb-5">
            <form method="GET" class="grid gap-3 sm:grid-cols-[minmax(0,320px)_auto]">
                <x-field label="Program Studi" name="prodi" required
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->jenjang->label().' '.$p->nama.' ('.$p->cpl_count.')'])"
                    :value="$prodi->id" />
                <div class="flex items-end"><x-button type="submit">Tampilkan</x-button></div>
            </form>
        </x-card>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                @if ($daftar->isEmpty())
                    <x-card>
                        {{-- Dinyatakan akibatnya, bukan sekadar "belum ada data".
                             Ini bagian yang paling dibaca pembaca asing pada SKPI. --}}
                        <x-alert tone="warning">
                            Program studi ini belum memiliki capaian pembelajaran. Setiap SKPI yang
                            terbit untuk lulusannya akan mencetak bagian capaian sebagai
                            <em>belum dicatatkan</em> — bagian yang justru paling dibaca oleh
                            pihak luar negeri.
                        </x-alert>
                    </x-card>
                @endif

                @foreach ($daftar as $kategori => $baris)
                    <x-card :title="$kategoriPilihan[$kategori] ?? $kategori" flush>
                        @foreach ($baris as $c)
                            <div class="border-b border-line/50 px-5 py-4 last:border-b-0">
                                <form method="POST" action="{{ route('admin.master.cpl.update', $c) }}"
                                    class="space-y-2">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="prodi_id" value="{{ $prodi->id }}">

                                    <div class="grid gap-2 sm:grid-cols-[110px_minmax(0,1fr)_100px]">
                                        <x-field label="Kode" name="kode" :value="$c->kode" required />
                                        <x-field label="Kategori" name="kategori"
                                            :options="$kategoriPilihan" :value="$c->kategori" required />
                                        <x-field label="Urutan" name="urutan" type="number" :value="$c->urutan" />
                                    </div>

                                    <x-field label="Deskripsi (Indonesia)" name="deskripsi" type="textarea"
                                        :value="$c->deskripsi" required />

                                    <x-field label="Description (English)" name="deskripsi_en" type="textarea"
                                        :value="$c->deskripsi_en"
                                        hint="Wajib menurut regulasi SKPI. Dikosongkan berarti separuh dokumen tidak terbaca pihak luar negeri." />

                                    <div class="flex gap-2">
                                        <x-button type="submit" size="sm">Simpan</x-button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('admin.master.cpl.destroy', $c) }}"
                                    class="mt-2">
                                    @csrf @method('DELETE')
                                    <x-button type="submit" variant="outline" size="sm">Hapus</x-button>
                                </form>
                            </div>
                        @endforeach
                    </x-card>
                @endforeach
            </div>

            <div class="space-y-5">
                <x-card title="Tambah Capaian">
                    <form method="POST" action="{{ route('admin.master.cpl.store') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="prodi_id" value="{{ $prodi->id }}">

                        <x-field label="Kode" name="kode" required placeholder="CPL-01" />
                        <x-field label="Kategori" name="kategori" :options="$kategoriPilihan" required />
                        <x-field label="Deskripsi (Indonesia)" name="deskripsi" type="textarea" required />
                        <x-field label="Description (English)" name="deskripsi_en" type="textarea" />
                        <x-field label="Urutan" name="urutan" type="number" value="0" />

                        <x-button type="submit" class="w-full">Tambah</x-button>
                    </form>
                </x-card>

                <x-card title="Catatan">
                    <p class="text-[13px] leading-relaxed text-ink-muted">
                        Capaian ditulis <strong>sekali per program studi</strong>, bukan per lulusan.
                        Sebagai isian per lulusan, versi Inggrisnya akan menjadi pekerjaan
                        penerjemahan pada pagi hari wisuda — dan berhenti dikerjakan.
                    </p>
                    <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
                        Mengubah daftar ini <strong>tidak mengubah SKPI yang sudah terbit</strong>.
                        Isi setiap surat dibekukan pada saat penerbitan.
                    </p>
                </x-card>
            </div>
        </div>
    @endif
@endsection
