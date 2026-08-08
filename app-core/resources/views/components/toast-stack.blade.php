{{--
    Flash messages are handed to the Alpine store on load so server-side
    redirects and client-side actions produce the same toast.
--}}
<div
    x-data
    x-init="
        @if (session('sukses')) $store.ui.toast(@js(session('sukses')), 'success'); @endif
        @if (session('galat')) $store.ui.toast(@js(session('galat')), 'error'); @endif
        @if (session('peringatan')) $store.ui.toast(@js(session('peringatan')), 'warning'); @endif
    "
    {{-- Sits above the mobile bottom nav, and never wider than the viewport. --}}
    class="pointer-events-none fixed inset-x-4 bottom-24 z-50 flex flex-col gap-2 sm:inset-x-auto sm:bottom-6 sm:right-6 sm:w-full sm:max-w-sm"
>
    <template x-for="toast in $store.ui.toasts" :key="toast.id">
        <div
            x-transition
            class="pointer-events-auto flex items-start gap-3 rounded-card px-4 py-3 text-[13px] shadow-pop"
            :class="toast.type === 'success'
                ? 'bg-navy text-canvas'
                : toast.type === 'error'
                    ? 'border-l-4 border-danger bg-surface text-ink'
                    : 'border-l-4 border-warning bg-surface text-ink'"
        >
            <span
                class="font-bold"
                :class="toast.type === 'success' ? 'text-gold' : ''"
                x-text="toast.type === 'success' ? '✓' : '!'"
            ></span>
            <span class="flex-1" x-text="toast.message"></span>
            <button type="button" class="opacity-60 hover:opacity-100" @click="$store.ui.dismiss(toast.id)">×</button>
        </div>
    </template>
</div>
