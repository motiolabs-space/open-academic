@php
    use App\Support\Portal;

    $term = Portal::term();
    $role = Portal::role();
@endphp

<header class="sticky top-0 z-10 flex h-[60px] items-center gap-3 border-b border-line bg-surface px-4 sm:gap-4 sm:px-7">
    {{-- Search is the first thing to go on a narrow screen: the semester
         switcher and the profile menu are what the shell cannot work without. --}}
    <label class="hidden w-full max-w-[420px] flex-1 items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-[7px] text-[13px] text-ink-faint md:flex">
        <span aria-hidden="true">⌕</span>
        {{-- The magnifier is aria-hidden, so without this the field would have
             no accessible name at all: a placeholder is not a label. --}}
        <span class="sr-only">Cari</span>
        <input
            type="search"
            placeholder="Cari mahasiswa, mata kuliah, dokumen…"
            class="min-w-0 flex-1 bg-transparent text-ink outline-none placeholder:text-ink-faint"
        >
        <kbd class="hidden rounded border border-line bg-surface px-1.5 py-px text-[10.5px] sm:block">⌘K</kbd>
    </label>

    <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-3.5">
        {{-- Semester switcher: the signature element that contextualises the whole
             app. On mobile it collapses to the PDDIKTI term code, which is short
             and is what staff quote to each other anyway. --}}
        <div class="relative min-w-0" x-data="{ open: false }" @keydown.escape="open = false">
            <button
                type="button"
                @click="open = ! open"
                :aria-expanded="open"
                aria-haspopup="listbox"
                class="tabular flex items-center gap-1.5 rounded-full bg-navy py-1.5 pl-3 pr-1.5 text-[12.5px] font-semibold text-canvas sm:gap-2 sm:pl-3.5"
            >
                <span class="text-gold" aria-hidden="true">◆</span>
                <span class="hidden truncate sm:inline">{{ $term?->nama ?? 'Belum ada semester aktif' }}</span>
                <span class="sm:hidden">{{ $term?->kode ?? '—' }}</span>
                <span class="rounded-full bg-gold/20 px-2 py-1 text-[11px] text-gold sm:px-2.5" aria-hidden="true">▾</span>
            </button>

            <div
                x-show="open"
                x-cloak
                @click.outside="open = false"
                class="absolute right-0 z-20 mt-2 w-64 overflow-hidden rounded-xl border border-line bg-surface shadow-pop"
            >
                <div class="border-b border-line px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.12em] text-ink-faint">
                    Tahun Akademik
                </div>
                @foreach (Portal::daftarTerm() as $pilihan)
                    <div @class([
                        'tabular flex items-center justify-between px-4 py-2.5 text-[13px]',
                        'bg-highlight font-semibold' => $pilihan->is_active,
                    ])>
                        <span>{{ $pilihan->nama }}</span>
                        <span class="text-[11px] text-ink-faint">{{ $pilihan->kode }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php $belumDibaca = Portal::notifikasiBelumDibaca(); @endphp

        <a
            href="{{ route('notifikasi') }}"
            class="relative hidden h-[34px] w-[34px] place-items-center rounded-lg border border-line text-sm hover:border-navy sm:grid"
            {{-- Jumlahnya masuk ke nama aksesibel, bukan hanya ke lencana:
                 pembaca layar tidak melihat titik merah. --}}
            aria-label="Notifikasi{{ $belumDibaca > 0 ? ', '.$belumDibaca.' belum dibaca' : '' }}"
        >
            <span aria-hidden="true">🔔</span>

            @if ($belumDibaca > 0)
                <span
                    class="tabular absolute -right-1 -top-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-danger px-1 text-[10px] font-bold text-canvas"
                    aria-hidden="true"
                >{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
            @endif
        </a>

        <div class="relative flex-none" x-data="{ open: false }" @keydown.escape="open = false">
            <button
                type="button"
                @click="open = ! open"
                :aria-expanded="open"
                aria-haspopup="menu"
                aria-label="Menu akun"
                class="flex items-center gap-2.5"
            >
                <span class="grid h-[34px] w-[34px] place-items-center rounded-full bg-navy font-serif text-[13px] font-semibold text-gold">
                    {{ Portal::inisial() }}
                </span>
                <span class="hidden leading-tight sm:block">
                    <span class="block text-[12.5px] font-semibold">{{ Portal::nama() }}</span>
                    <span class="block text-[10.5px] text-ink-faint">{{ Portal::konteks() }}</span>
                </span>
                <span class="text-[10px] text-ink-faint" aria-hidden="true">▾</span>
            </button>

            <div
                x-show="open"
                x-cloak
                @click.outside="open = false"
                class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-surface shadow-pop"
            >
                <div class="border-b border-line px-4 py-3">
                    <div class="text-[13px] font-semibold">{{ Portal::nama() }}</div>
                    <div class="text-[11.5px] text-ink-faint">{{ $role?->label() }}</div>
                </div>

                <a href="{{ route('notifikasi.preferensi') }}"
                    class="block border-b border-line px-4 py-3 text-[13px] hover:bg-highlight">
                    Preferensi Notifikasi
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full px-4 py-3 text-left text-[13px] text-danger hover:bg-danger-bg">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
