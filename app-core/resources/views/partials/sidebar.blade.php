@php
    use App\Services\Branding\BrandingService;
    use App\Support\Navigation;
    use App\Support\Portal;

    $brand = app(BrandingService::class);
    $role = Portal::role();
    $groups = $role ? Navigation::for($role) : [];
@endphp

<aside
    x-data
    :class="$store.ui.sidebarCollapsed ? 'w-[68px]' : 'w-[248px]'"
    class="sticky top-0 hidden h-screen flex-none flex-col bg-navy text-canvas transition-[width] duration-200 md:flex"
>
    {{-- Institution mark: a gold-bordered box, replaced by the logo when one is uploaded. --}}
    <div class="flex items-center gap-2.5 border-b border-canvas/10 px-4 pb-4 pt-[18px]">
        <div class="grid h-8 w-8 flex-none place-items-center rounded-lg border-[1.5px] border-gold font-serif text-base font-bold text-gold">
            @if ($brand->logoUrl())
                <img src="{{ $brand->logoUrl() }}" alt="" class="h-full w-full rounded-lg object-cover">
            @else
                {{ $brand->logoInitial() }}
            @endif
        </div>
        <div class="leading-tight" x-show="! $store.ui.sidebarCollapsed" x-cloak>
            <div class="text-[13.5px] font-bold">{{ config('app.name') }}</div>
            <div class="text-[10.5px] text-canvas/55">{{ $brand->institutionShortName() }}</div>
        </div>
    </div>

    <nav class="flex flex-1 flex-col gap-4 overflow-y-auto px-2.5 py-3">
        @foreach ($groups as $group)
            <div>
                @if ($group['title'])
                    <div
                        class="px-2.5 pb-[7px] text-[10px] font-bold tracking-[0.14em] text-gold/90"
                        x-show="! $store.ui.sidebarCollapsed"
                        x-cloak
                    >{{ $group['title'] }}</div>
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
                                'relative flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px]',
                                'bg-gold/15 font-semibold text-canvas' => $active,
                                'text-canvas/75 hover:bg-canvas/[0.07]' => ! $active && $href,
                                'cursor-not-allowed text-canvas/30' => ! $href,
                            ])
                            @if (! $href) title="Modul belum tersedia" @endif
                        >
                            @if ($active)
                                <span class="absolute -left-2.5 bottom-1.5 top-1.5 w-[3px] rounded-r-[3px] bg-gold"></span>
                            @endif

                            <span class="w-[18px] flex-none text-center text-[13px] opacity-85">{{ $item['icon'] }}</span>

                            <span class="flex-1 whitespace-nowrap" x-show="! $store.ui.sidebarCollapsed" x-cloak>
                                {{ $item['label'] }}
                            </span>

                            @if ($item['badge'])
                                <span
                                    class="tabular rounded-full bg-gold px-[7px] py-0.5 text-[10px] font-bold text-navy"
                                    x-show="! $store.ui.sidebarCollapsed"
                                    x-cloak
                                >{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <button
        type="button"
        @click="$store.ui.toggleSidebar()"
        class="flex items-center gap-2.5 border-t border-canvas/10 px-4 py-3 text-left text-xs text-canvas/55 hover:text-gold"
    >
        <span class="w-[18px] text-center" x-text="$store.ui.sidebarCollapsed ? '»' : '«'"></span>
        <span x-show="! $store.ui.sidebarCollapsed" x-cloak>Ciutkan menu</span>
    </button>
</aside>
