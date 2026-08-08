@php
    use App\Support\Navigation;
    use App\Support\Portal;

    $role = Portal::role();
    $groups = $role ? Navigation::for($role) : [];

    // Four primary destinations plus a "Lainnya" sheet. Four rather than five
    // because the admin portal has fourteen items — a fixed five-tab bar would
    // simply hide ten of them with no way back.
    $utama = collect($groups)->flatMap(fn (array $group): array => $group['items'])->take(4);
@endphp

<nav
    x-data
    class="fixed inset-x-0 bottom-0 z-20 flex border-t border-canvas/10 bg-navy pb-[env(safe-area-inset-bottom)] text-canvas md:hidden"
    aria-label="Navigasi utama"
>
    @foreach ($utama as $item)
        @php
            $href = $item['route'] ? route($item['route']) : null;
            $active = $item['route'] && request()->routeIs($item['route'].'*');
        @endphp

        <a
            @if ($href) href="{{ $href }}" @endif
            @class([
                'relative flex min-h-[56px] flex-1 flex-col items-center justify-center gap-1 px-1 py-2 text-[10.5px] leading-tight',
                'text-gold font-semibold' => $active,
                'text-canvas/70' => ! $active && $href,
                'text-canvas/30' => ! $href,
            ])
        >
            @if ($active)
                <span class="absolute inset-x-3 top-0 h-[3px] rounded-b-[3px] bg-gold"></span>
            @endif

            <span class="text-base" aria-hidden="true">{{ $item['icon'] }}</span>
            <span class="w-full truncate text-center">{{ Str::before($item['label'], ' (') }}</span>
        </a>
    @endforeach

    <button
        type="button"
        @click="$store.ui.sidebarMobileOpen = true"
        :aria-expanded="$store.ui.sidebarMobileOpen"
        aria-controls="menu-navigasi"
        class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-1 px-1 py-2 text-[10.5px] leading-tight text-canvas/70"
    >
        <span class="text-base" aria-hidden="true">☰</span>
        <span>Lainnya</span>
    </button>
</nav>

{{-- Full navigation tree, reachable from the "Lainnya" tab. --}}
<div
    x-data
    x-show="$store.ui.sidebarMobileOpen"
    x-cloak
    id="menu-navigasi"
    role="dialog"
    aria-modal="true"
    aria-label="Menu navigasi"
    class="fixed inset-0 z-30 md:hidden"
    @keydown.escape.window="$store.ui.sidebarMobileOpen = false"
>
    {{-- Only the wrapper toggles visibility. Nesting a second x-show inside it
         let the two transitions cancel each other, and the sheet stayed hidden
         on the very first open — so the slide-up is plain CSS instead. --}}
    <div
        class="absolute inset-0 bg-[rgba(16,20,46,0.55)]"
        @click="$store.ui.sidebarMobileOpen = false"
    ></div>

    <div
        class="animate-sheet absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-card-lg bg-navy pb-[env(safe-area-inset-bottom)] text-canvas"
    >
        <div class="flex items-center justify-between border-b border-canvas/10 px-5 py-4">
            <span class="text-[13px] font-bold">Menu</span>
            <button
                type="button"
                @click="$store.ui.sidebarMobileOpen = false"
                class="grid h-11 w-11 place-items-center rounded-lg text-lg text-canvas/70 hover:text-gold"
                aria-label="Tutup menu"
            >×</button>
        </div>

        <div class="flex flex-col gap-4 px-3 py-4">
            @foreach ($groups as $group)
                <div>
                    @if ($group['title'])
                        <div class="px-3 pb-2 text-[10px] font-bold tracking-[0.14em] text-gold/75">
                            {{ $group['title'] }}
                        </div>
                    @endif

                    <div class="flex flex-col gap-0.5">
                        @foreach ($group['items'] as $item)
                            @php
                                $href = $item['route'] ? route($item['route']) : null;
                                $active = $item['route'] && request()->routeIs($item['route'].'*');
                            @endphp

                            <a
                                @if ($href) href="{{ $href }}" @endif
                                @class([
                                    'flex min-h-[44px] items-center gap-3 rounded-lg px-3 py-2.5 text-[13.5px]',
                                    'bg-gold/15 font-semibold text-canvas' => $active,
                                    'text-canvas/75' => ! $active && $href,
                                    'text-canvas/30' => ! $href,
                                ])
                                @if (! $href) title="Modul belum tersedia" @endif
                            >
                                <span class="w-5 flex-none text-center opacity-85" aria-hidden="true">{{ $item['icon'] }}</span>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if (! $href)
                                    <span class="text-[10px] uppercase tracking-wider">segera</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <form method="POST" action="{{ route('logout') }}" class="border-t border-canvas/10 pt-3">
                @csrf
                <button type="submit" class="flex min-h-[44px] w-full items-center gap-3 rounded-lg px-3 text-left text-[13.5px] text-danger-line">
                    <span class="w-5 flex-none text-center" aria-hidden="true">⏻</span>
                    Keluar
                </button>
            </form>
        </div>
    </div>
</div>
