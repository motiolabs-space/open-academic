{{-- Setujui / tolak satu ajuan. Alasan penolakan wajib, jadi kolomnya baru
     muncul saat "Tolak" ditekan, bukan tersedia kosong di setiap baris. --}}
@can('validasi', $baris)
    <div x-data="{ tolak: false }" class="flex flex-wrap items-center gap-2">
        <form method="POST" action="{{ route('admin.kepegawaian.setujui', [$jenis->value, $baris->uuid]) }}" x-show="!tolak">
            @csrf
            <x-button type="submit" size="sm">{{ __('kepegawaian.aksi.setujui') }}</x-button>
        </form>

        <x-button size="sm" variant="danger" x-show="!tolak" x-on:click="tolak = true">
            {{ __('kepegawaian.aksi.tolak') }}
        </x-button>

        <form method="POST" action="{{ route('admin.kepegawaian.tolak', [$jenis->value, $baris->uuid]) }}"
            x-show="tolak" x-cloak class="flex flex-wrap items-center gap-2">
            @csrf
            <input type="text" name="alasan" required minlength="5" maxlength="500"
                aria-label="{{ __('kepegawaian.field.alasan') }}"
                placeholder="{{ __('kepegawaian.field.alasan') }}"
                class="w-64 rounded-control border border-line-input bg-surface px-3 py-1.5 text-[12.5px] outline-none focus:border-navy">
            <x-button type="submit" size="sm" variant="danger">{{ __('kepegawaian.aksi.tolak') }}</x-button>
            <x-button size="sm" variant="ghost" x-on:click="tolak = false">{{ __('kepegawaian.aksi.batal') }}</x-button>
        </form>
    </div>
@endcan
