@props(['title' => null])
@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->count();
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
        ['route' => 'goals.index', 'match' => 'goals.*', 'icon' => 'target', 'label' => 'Goals'],
        ['route' => 'transactions.index', 'match' => ['transactions.*', 'transfers.*'], 'icon' => 'arrows', 'label' => 'Transactions'],
        ['route' => 'expenses.index', 'match' => 'expenses.*', 'icon' => 'receipt', 'label' => 'Expenses'],
        ['route' => 'budget.index', 'match' => 'budget.*', 'icon' => 'calculator', 'label' => 'Budget & plan'],
        ['route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'chart', 'label' => 'Reports'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}SmartSave</title>
    <x-pwa-head />
    <script src="{{ asset('js/theme-init.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="shell" @keydown.escape.window="mobileNav = false">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 btn btn-primary">Skip to content</a>

<div class="flex min-h-full">
    {{-- Mobile overlay --}}
    <div x-cloak x-show="mobileNav" x-transition.opacity class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm lg:hidden" @click="mobileNav = false"></div>

    {{-- Sidebar --}}
    <aside class="no-print fixed inset-y-0 left-0 z-40 flex flex-col border-r border-slate-200 bg-white transition-all duration-300 dark:border-slate-800 dark:bg-slate-900 lg:translate-x-0"
           :class="[mobileNav ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-20' : 'lg:w-64']"
           class="w-72 -translate-x-full" aria-label="Main navigation">
        <div class="flex h-16 shrink-0 items-center gap-3 px-5 mt-[env(safe-area-inset-top)]">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-bold text-slate-900 dark:text-white">
                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-teal-600 text-white shadow-md shadow-brand-600/30">
                    <x-icon name="piggy-bank" class="size-5" />
                </span>
                <span class="text-lg tracking-tight" :class="collapsed && 'lg:hidden'">SmartSave</span>
            </a>
            <button type="button" class="btn-ghost btn btn-sm ml-auto lg:hidden" @click="mobileNav = false" aria-label="Close menu"><x-icon name="x" /></button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($nav as $item)
                @php $active = request()->routeIs(...(array) $item['match']); @endphp
                <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $active]) @if($active) aria-current="page" @endif
                   :class="collapsed && 'lg:justify-center'" title="{{ $item['label'] }}">
                    <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                    <span :class="collapsed && 'lg:sr-only'">{{ $item['label'] }}</span>
                </a>
            @endforeach

            @if ($user->isAdmin())
                <div class="mt-6 mb-2 px-3 text-xs font-semibold tracking-wider text-slate-400 uppercase" :class="collapsed && 'lg:sr-only'">Admin</div>
                <a href="{{ route('admin.users.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('admin.*')]) :class="collapsed && 'lg:justify-center'" title="Users">
                    <x-icon name="users" class="size-5 shrink-0" />
                    <span :class="collapsed && 'lg:sr-only'">Users</span>
                </a>
            @endif
        </nav>

        <div class="space-y-1 border-t border-slate-200 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] dark:border-slate-800">
            <x-install-button />
            <button type="button" @click="toggleCollapse()" class="nav-link hidden w-full lg:flex" :class="collapsed && 'lg:justify-center'"
                    :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <x-icon name="chevron-left" class="size-5 shrink-0 transition-transform" ::class="collapsed && 'rotate-180'" />
                <span :class="collapsed && 'lg:sr-only'">Collapse</span>
            </button>
        </div>
    </aside>

    {{-- Main column --}}
    <div class="flex min-w-0 flex-1 flex-col transition-all duration-300" :class="collapsed ? 'lg:pl-20' : 'lg:pl-64'">
        <header class="no-print sticky top-0 z-20 flex h-[calc(4rem+env(safe-area-inset-top))] items-center gap-2 pt-[env(safe-area-inset-top)] border-b border-slate-200/70 bg-white/80 px-4 backdrop-blur-md dark:border-slate-800 dark:bg-slate-950/80 sm:px-6">
            <button type="button" class="btn btn-ghost -ml-2 px-2 lg:hidden" @click="mobileNav = true" aria-label="Open menu">
                <x-icon name="menu" class="size-6" />
            </button>
            <h1 class="truncate text-base font-semibold text-slate-900 dark:text-white sm:text-lg">{{ $title ?? 'SmartSave' }}</h1>

            <div class="ml-auto flex items-center gap-1">
                <div x-data="{ open: false }" class="relative hidden sm:block">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="btn btn-primary btn-sm" :aria-expanded="open">
                        <x-icon name="plus" class="size-4" /> Quick add
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        <a href="{{ route('transactions.create', ['type' => 'deposit']) }}" class="nav-link"><x-icon name="arrow-up" class="size-4 text-emerald-500" /> Add savings</a>
                        <a href="{{ route('transactions.create', ['type' => 'withdrawal']) }}" class="nav-link"><x-icon name="arrow-down" class="size-4 text-rose-500" /> Record withdrawal</a>
                        <a href="{{ route('transfers.create') }}" class="nav-link"><x-icon name="arrows" class="size-4 text-indigo-500" /> Move between goals</a>
                        <a href="{{ route('expenses.create') }}" class="nav-link"><x-icon name="receipt" class="size-4 text-amber-500" /> Record expense</a>
                        <a href="{{ route('goals.create') }}" class="nav-link"><x-icon name="target" class="size-4 text-sky-500" /> New goal</a>
                    </div>
                </div>

                <button type="button" class="btn btn-ghost px-2.5" @click="$store.theme.toggle()" :aria-label="$store.theme.dark ? 'Switch to light mode' : 'Switch to dark mode'">
                    <x-icon name="moon" class="size-5" x-show="!$store.theme.dark" />
                    <x-icon name="sun" class="size-5" x-cloak x-show="$store.theme.dark" />
                </button>

                <a href="{{ route('notifications.index') }}" class="btn btn-ghost relative px-2.5" aria-label="Notifications{{ $unread ? " ($unread unread)" : '' }}">
                    <x-icon name="bell" class="size-5" />
                    @if ($unread)
                        <span class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] leading-4 font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-xl p-1 pr-2 transition hover:bg-slate-100 dark:hover:bg-slate-800" :aria-expanded="open" aria-label="Account menu">
                        <span class="grid size-8 place-items-center rounded-full bg-gradient-to-br from-slate-700 to-slate-900 text-xs font-bold text-white dark:from-slate-600 dark:to-slate-800">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <x-icon name="chevron-down" class="hidden size-4 text-slate-400 sm:block" />
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                            <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                        <div class="p-1">
                            <a href="{{ route('profile.edit') }}" class="nav-link"><x-icon name="cog" class="size-4" /> Profile & settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="nav-link w-full text-rose-600 dark:text-rose-400"><x-icon name="logout" class="size-4" /> Sign out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1 px-4 pt-6 pb-[calc(6rem+env(safe-area-inset-bottom))] sm:px-6 sm:pb-6 lg:px-8">
            <div class="mx-auto max-w-7xl animate-fade-up">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

<x-flash />
<x-confirm-dialog />

{{-- Mobile quick-add button --}}
<a href="{{ route('transactions.create', ['type' => 'deposit']) }}" class="no-print fixed right-5 bottom-[calc(1.25rem+env(safe-area-inset-bottom))] z-20 grid size-14 place-items-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/40 transition hover:bg-brand-700 active:scale-95 sm:hidden" aria-label="Add savings">
    <x-icon name="plus" class="size-6" />
</a>
</body>
</html>
