{{--
    "Install app" button. Chrome/Edge/Android: shows the native install prompt.
    iPhone/iPad Safari (no prompt API): shows Add-to-Home-Screen instructions.
    Hidden once installed or when the browser can't install.
--}}
<div x-data x-cloak x-show="$store.pwa.canInstall || $store.pwa.isIos" {{ $attributes }}>
    <button type="button" @click="$store.pwa.install()" class="nav-link w-full" :class="typeof collapsed !== 'undefined' && collapsed && 'lg:justify-center'">
        <x-icon name="download" class="size-5 shrink-0" />
        <span :class="typeof collapsed !== 'undefined' && collapsed && 'lg:sr-only'">Install app</span>
    </button>

    <template x-teleport="body">
        <div x-show="$store.pwa.iosHelp" x-cloak class="fixed inset-0 z-50 grid place-items-end p-4 sm:place-items-center" role="dialog" aria-modal="true" aria-labelledby="ios-install-title" @keydown.escape.window="$store.pwa.iosHelp = false">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$store.pwa.iosHelp = false"></div>
            <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900" x-trap.noscroll="$store.pwa.iosHelp">
                <div class="flex items-center gap-3">
                    <img src="/icons/icon-192.png" alt="" class="size-12 rounded-xl">
                    <h2 id="ios-install-title" class="text-lg font-semibold text-slate-900 dark:text-white">Install SmartSave</h2>
                </div>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
                    <li>Open this page in <strong>Safari</strong>.</li>
                    <li>Tap the <strong>Share</strong> button (square with an arrow).</li>
                    <li>Choose <strong>Add to Home Screen</strong>, then <strong>Add</strong>.</li>
                </ol>
                <button type="button" class="btn btn-primary mt-6 w-full" @click="$store.pwa.iosHelp = false">Got it</button>
            </div>
        </div>
    </template>
</div>
