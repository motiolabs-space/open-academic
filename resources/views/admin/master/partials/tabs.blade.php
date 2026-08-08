{{-- Master data is one task with several parts, so the parts are tabs rather
     than six sidebar entries a registrar has to hunt between. --}}
<nav class="mb-5 overflow-x-auto border-b border-line" aria-label="Bagian master akademik">
    <ul class="flex min-w-max gap-1">
        @foreach ($tabs as $kunci => $tab)
            @php $aktif = $kunci === $tabAktif; @endphp
            <li>
                <a
                    href="{{ route($tab['route']) }}"
                    @if ($aktif) aria-current="page" @endif
                    @class([
                        'relative block whitespace-nowrap px-4 py-2.5 text-[13px] transition-colors',
                        'font-semibold text-navy' => $aktif,
                        'text-ink-muted hover:text-ink' => ! $aktif,
                    ])
                >
                    {{ $tab['label'] }}
                    @if ($aktif)
                        <span class="absolute inset-x-2 -bottom-px h-[2px] rounded-t bg-navy"></span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>

@if (session('sukses'))
    <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
@endif

@if (session('galat'))
    <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
@endif

@if ($errors->any())
    <div class="mb-5">
        <x-alert tone="danger">
            {{ $errors->count() > 1 ? $errors->count().' isian perlu diperbaiki.' : $errors->first() }}
        </x-alert>
    </div>
@endif
